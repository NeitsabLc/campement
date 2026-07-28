<?php
declare(strict_types=1);
namespace App\Controller;

use App\Entity\Menu; use App\Entity\MouvementStock; use App\Entity\MouvementStockLigne;
use App\Repository\GroupeRepository; use App\Repository\MenuRepository; use App\Repository\OrigineMouvementRepository; use App\Repository\SejourRepository; use App\Repository\TypeMouvementRepository; use App\Repository\UtilisateurRepository;
use App\Service\ConversionConditionnement; use DateTimeImmutable; use Doctrine\ORM\EntityManagerInterface; use Symfony\Bundle\FrameworkBundle\Controller\AbstractController; use Symfony\Component\HttpFoundation\Request; use Symfony\Component\HttpFoundation\Response; use Symfony\Component\HttpKernel\Attribute\RateLimit; use Symfony\Component\Routing\Attribute\Route; use Symfony\Component\Uid\Uuid;

final class SortieConsommationController extends AbstractController
{
 #[Route('/distribution/{jeton}',name:'app_sortie_consommation',requirements:['jeton'=>'[0-9a-fA-F-]{36}'],methods:['GET','POST'])] #[RateLimit('public_distribution',methods:['POST'])]
 public function index(string $jeton,Request $request,SejourRepository $sejours,GroupeRepository $groupes,MenuRepository $menus,TypeMouvementRepository $types,OrigineMouvementRepository $origines,UtilisateurRepository $utilisateurs,ConversionConditionnement $conversion,EntityManagerInterface $em):Response
 {
  $sejour=Uuid::isValid($jeton)?$sejours->findPourDistributionPublique($jeton):null;if(!$sejour)return $this->render('sortie_consommation/index.html.twig',['sejour'=>null]);
  $groupesActifs=$groupes->findActifsPourSejour($sejour);$tousLesMenus=$menus->findActifsPourSejour($sejour);$menusActifs=$tousLesMenus;
  if($sejour->isDistribuerGouterDejeuner())$menusActifs=array_values(array_filter($menusActifs,fn(Menu $m)=>$m->isSpecial()||!$this->repasEst($m,'GOUTER')));
  $vues=[];foreach($menusActifs as $m)$vues[(string)$m->getId()]=$this->vueMenu($m,$tousLesMenus,$sejour->isDistribuerGouterDejeuner(),$conversion);$selection=null;$erreurs=[];
  if($request->isMethod('POST')){
   if(!$this->isCsrfTokenValid('sortie_consommation',$request->request->getString('_token')))throw $this->createAccessDeniedException();$groupe=$this->selection($request->request->getString('groupe'),$groupesActifs);$menu=$this->selection($request->request->getString('menu'),$menusActifs);if(!$groupe)$erreurs[]='Sélectionnez un groupe valide.';if(!$menu)$erreurs[]='Sélectionnez un repas valide.';$quantites=[];
   if($menu){foreach($vues[(string)$menu->getId()]['lignes'] as $ligne){$id=(string)$ligne['denree']->getId();$brut=str_replace([' ', ','],['','.'],trim((string)($request->request->all('quantites')[$id]??'')));if($brut===''||!is_numeric($brut)||(float)$brut<0){$erreurs[]=sprintf('Renseignez une quantité positive ou nulle pour %s.',$ligne['denree']->getNom());continue;}$quantites[$id]=number_format((float)$brut,3,'.','');}if(!array_filter($quantites,fn($q)=>(float)$q>0))$erreurs[]='Au moins une denrée doit avoir une quantité supérieure à zéro.';}
   if(!$erreurs&&$groupe&&$menu){$selection=['groupe'=>$groupe,'menu'=>$menu,'vue'=>$vues[(string)$menu->getId()],'quantites'=>$quantites];if($request->request->getString('action')==='confirmer'){$type=$types->findOneBy(['code'=>'SORTIE','actif'=>true]);$origine=$origines->findOneBy(['sejour'=>$sejour,'code'=>'DISTRIBUTION','actif'=>true]);$utilisateur=$utilisateurs->loadUserByIdentifier('saisie-consommation@campement.local');if(!$type||!$origine||!$utilisateur)throw new \RuntimeException('Référentiel incomplet.');$mouvement=(new MouvementStock($sejour,$utilisateur,$type,$origine))->setGroupe($groupe)->setMenu($menu)->setDateMouvement($this->dateMouvementNavigateur($request,$menu));$em->persist($mouvement);foreach($selection['vue']['lignes'] as $ligne){$id=(string)$ligne['denree']->getId();$q=(float)$quantites[$id];if($q>0){$mouvementLigne=new MouvementStockLigne($mouvement,$ligne['denree'],number_format($conversion->versUniteReference($ligne['denree'],$ligne['unite'],$q),3,'.',''));$mouvementLigne->setConditionnementSortie($ligne['unite']);$em->persist($mouvementLigne);}}$em->flush();return $this->render('sortie_consommation/succes.html.twig',compact('groupe','menu'));}}
  }
  $menuSoumis=$this->selection($request->request->getString('menu'),$menusActifs);
  return $this->render('sortie_consommation/index.html.twig',['sejour'=>$sejour,'groupes'=>$groupesActifs,'menus'=>$menusActifs,'vues'=>$vues,'selection'=>$selection,'erreurs'=>$erreurs,'valeurs'=>$request->request->all('quantites'),'groupe_soumis'=>$request->request->getString('groupe'),'menu_soumis'=>$request->request->getString('menu'),'date_soumise'=>$menuSoumis?($menuSoumis->getDateMenu()?->format('Y-m-d')??'special'):'']);
 }
 #[Route('/sortie-consommation',name:'app_sortie_consommation_sans_sejour',methods:['GET'])] public function sansSejour():Response{return $this->render('sortie_consommation/index.html.twig',['sejour'=>null]);}
 private function repasEst(Menu $m,string $code):bool{return !$m->isSpecial()&&$m->getSejourTypeRepas()?->getTypeRepas()->getCode()===$code;}
 private function vueMenu(Menu $menu,array $menus,bool $fusion,ConversionConditionnement $conversion):array
 {
  $sources=[$menu];if($fusion&&$this->repasEst($menu,'DEJEUNER'))foreach($menus as $c)if($c->getDateMenu()?->format('Y-m-d')===$menu->getDateMenu()?->format('Y-m-d')&&$this->repasEst($c,'GOUTER'))$sources[]=$c;$groupes=[];
  foreach($sources as $source)foreach($source->getDenrees() as $ligne){$id=(string)$ligne->getDenree()->getId();$groupes[$id]['denree']=$ligne->getDenree();$groupes[$id]['sources'][]=$ligne;}
  $result=[];foreach($groupes as $g){$premiere=$g['sources'][0]->getConditionnement();$meme=true;foreach($g['sources'] as $l)if($l->getConditionnement()!==$premiere)$meme=false;$unite=$meme?$premiere:$g['denree']->getUniteReference();$facteurSortie=$conversion->facteurMinimal($g['denree'],$unite)??1;$qs=[];foreach($g['sources'] as $l){$facteur=$conversion->facteurMinimal($g['denree'],$l->getConditionnement())??1;foreach($l->getQuantites() as $q){$pid=(string)$q->getSejourPublicCible()->getId();$qs[$pid]??=['libelle'=>$q->getSejourPublicCible()->getPublicCible()->getLibelle(),'quantite'=>0.0];$qs[$pid]['quantite']+=(float)$q->getQuantiteIndividuelle()*$facteur/$facteurSortie;}}$result[]=['denree'=>$g['denree'],'unite'=>$unite,'quantites'=>$qs];}
  return ['menu'=>$menu,'lignes'=>$result];
 }
 private function selection(string $id,array $items):?object{if(!Uuid::isValid($id))return null;foreach($items as $x)if((string)$x->getId()===$id)return $x;return null;}
 private function dateMouvementNavigateur(Request $request,Menu $menu):DateTimeImmutable
 {
  $iso=$request->request->getString('date_navigateur');$heure=$request->request->getString('heure_navigateur');$decalage=$request->request->getInt('decalage_utc');
  try{$instant=''!==$iso?new DateTimeImmutable($iso):new DateTimeImmutable();}catch(\Exception){$instant=new DateTimeImmutable();}
  if(null===$menu->getDateMenu()||!preg_match('/^([01]\d|2[0-3]):[0-5]\d:[0-5]\d$/',$heure))return $instant;
  $decalage=max(-840,min(840,$decalage));$minutes=-$decalage;$signe=$minutes>=0?'+':'-';$minutes=abs($minutes);$offset=sprintf('%s%02d:%02d',$signe,intdiv($minutes,60),$minutes%60);
  return new DateTimeImmutable($menu->getDateMenu()->format('Y-m-d').'T'.$heure.$offset);
 }
}
