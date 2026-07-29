<?php
declare(strict_types=1);
namespace App\Controller;

use App\Entity\Menu; use App\Entity\MenuDenree; use App\Entity\MenuDenreeQuantite; use App\Entity\Utilisateur;
use App\Repository\DenreeRepository; use App\Repository\MenuRepository; use App\Repository\RecetteRepository; use App\Repository\SejourPublicCibleRepository; use App\Repository\SejourTypeRepasRepository; use App\Repository\UniteRepository;
use App\Service\ContexteSejour; use App\Service\ConversionConditionnement;
use DateInterval; use DatePeriod; use DateTimeImmutable; use Doctrine\ORM\EntityManagerInterface; use Symfony\Bundle\FrameworkBundle\Controller\AbstractController; use Symfony\Component\HttpFoundation\Request; use Symfony\Component\HttpFoundation\Response; use Symfony\Component\Routing\Attribute\Route; use Symfony\Component\Security\Http\Attribute\IsGranted; use Symfony\Component\Uid\Uuid;

#[IsGranted(Utilisateur::ROLE_GESTIONNAIRE)]
final class MenuController extends AbstractController
{
 #[Route('/menus',name:'app_menus',methods:['GET','POST'])]
 public function index(Request $request, ContexteSejour $ctx, SejourTypeRepasRepository $repasRepo, MenuRepository $menus, DenreeRepository $denrees, SejourPublicCibleRepository $publics, RecetteRepository $recettes, UniteRepository $unites, ConversionConditionnement $conversion, EntityManagerInterface $em): Response
 {
  $sejour=$ctx->actif(); if(!$sejour)return $this->render('menu/index.html.twig',['sejour'=>null]); $repas=$repasRepo->findActifsPourSejour($sejour); if(!$repas)return $this->render('menu/index.html.twig',['sejour'=>$sejour,'repas'=>[]]);
  $specials=['EXPLO'=>'Explo','PIQUE_NIQUE_1'=>'Pique-nique 1','PIQUE_NIQUE_2'=>'Pique-nique 2']; $special=array_key_exists($request->query->getString('special'),$specials)?$request->query->getString('special'):null;
  $date=$this->date($request,$sejour->getDateDebut(),$sejour->getDateFin()); $repasSelectionne=$this->repas($request->query->getString('repas'),$repas); $menu=$special ? $menus->findSpecial($sejour,$special) : $menus->findPourRepas($sejour,$date,$repasSelectionne); $publicsActifs=$publics->findActifsPourSejour($sejour);
  $avecCategories=!$special&&$this->avecCategories($repasSelectionne->getTypeRepas()->getCode());
  if($request->isMethod('POST')){
   if(!$this->isCsrfTokenValid('enregistrer_menu',$request->request->getString('_token')))throw $this->createAccessDeniedException(); $composition=[];
   foreach($request->request->all('lignes') as $i=>$data){if(!is_array($data))continue;$d=Uuid::isValid((string)($data['denree']??''))?$denrees->find($data['denree']):null;$u=Uuid::isValid((string)($data['conditionnement']??''))?$unites->find($data['conditionnement']):null;if(!$d||$d->getSejour()!==$sejour||!$u||!in_array($u,$conversion->conditionnementsPour($d),true))continue;$qs=[];foreach($publicsActifs as $p){$v=str_replace(',','.',trim((string)($data['quantites'][(string)$p->getId()]??'')));if(!is_numeric($v)||(float)$v<0){$this->addFlash('error',sprintf('Quantité invalide pour %s.',$d->getNom()));return $this->redirectMenu($date,$repasSelectionne,$special);} $qs[(string)$p->getId()]=number_format((float)$v,3,'.','');}$cat=$avecCategories&&in_array(($data['categorie']??null),['ENTREE','PLAT','FROMAGE','DESSERT'],true)?$data['categorie']:null;$composition[]=[$d,$u,$cat,$qs];}
   $menu??=(new Menu())->setSejour($sejour); if($special)$menu->setSpecialCode($special);else $menu->setDateMenu($date)->setSejourTypeRepas($repasSelectionne);
   foreach($menu->getDenrees()->toArray() as $ancienne)$menu->removeDenree($ancienne);
   foreach($composition as $ordre=>[$d,$u,$cat,$qs]){$ligne=(new MenuDenree())->setDenree($d)->setConditionnement($u)->setCategorie($cat)->setOrdre($ordre);foreach($publicsActifs as $p)$ligne->addQuantite((new MenuDenreeQuantite())->setSejourPublicCible($p)->setQuantiteIndividuelle($qs[(string)$p->getId()]));$menu->addDenree($ligne);}
   $em->persist($menu);$em->flush();$this->addFlash('success','Le repas a bien été enregistré.');return $this->redirectMenu($date,$repasSelectionne,$special);
  }
  $denreesActives=$denrees->findActifsPourSejour($sejour);$conditionnements=$conversion->conditionnementsPourDenrees($denreesActives);
  $catalogue=[];foreach($denreesActives as $d)$catalogue[(string)$d->getId()]=['id'=>(string)$d->getId(),'nom'=>$d->getNom(),'conditionnements'=>array_map(static fn($u)=>['id'=>(string)$u->getId(),'nom'=>$u->getNom(),'symbole'=>$u->getSymbole()],$conditionnements[(string)$d->getId()])];
  $recettesActives=$recettes->findActivesPourSejour($sejour);$recettesJson=[];foreach($recettesActives as $r){$ls=[];foreach($r->getDenrees() as $l){$q=[];foreach($l->getQuantites() as $x)$q[(string)$x->getSejourPublicCible()->getId()]=$x->getQuantiteIndividuelle();$ls[]=['denree'=>(string)$l->getDenree()->getId(),'conditionnement'=>(string)$l->getConditionnement()->getId(),'quantites'=>$q];}$recettesJson[(string)$r->getId()]=['nom'=>$r->getNom(),'lignes'=>$ls];}
  $menusExistants=[];foreach($menus->findStatutsPourSejour($sejour) as $statut){$cle=$statut['specialCode']?'special|'.$statut['specialCode']:$statut['dateMenu']?->format('Y-m-d').'|'.$statut['repasId'];$menusExistants[$cle]=(int)$statut['nombreDenrees']>0;}
  $jours=[];foreach(new DatePeriod($sejour->getDateDebut(),new DateInterval('P1D'),$sejour->getDateFin()->modify('+1 day')) as $j)$jours[]=$j;
  return $this->render('menu/index.html.twig',['sejour'=>$sejour,'repas'=>$repas,'repas_selectionne'=>$repasSelectionne,'date_selectionnee'=>$date,'menu'=>$menu,'jours'=>$jours,'special'=>$special,'specials'=>$specials,'menus_existants'=>$menusExistants,'publicsCibles'=>$publicsActifs,'catalogue'=>$catalogue,'recettes'=>$recettesActives,'recettes_json'=>$recettesJson,'avec_categories'=>$avecCategories]);
 }
 private function date(Request $r,DateTimeImmutable $debut,DateTimeImmutable $fin):DateTimeImmutable{$v=DateTimeImmutable::createFromFormat('!Y-m-d',$r->query->getString('date'));return $v&&$v>=$debut&&$v<=$fin?$v:$debut;}
 private function repas(string $id,array $repas){foreach($repas as $r)if((string)$r->getId()===$id)return $r;return $repas[0];}
 private function avecCategories(string $code):bool{return in_array($code,['DEJEUNER','DINER'],true);}
 private function redirectMenu(DateTimeImmutable $d,$repas,?string $s):Response{return $this->redirectToRoute('app_menus',$s?['special'=>$s]:['date'=>$d->format('Y-m-d'),'repas'=>(string)$repas->getId()]);}
}
