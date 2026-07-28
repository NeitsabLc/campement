<?php
declare(strict_types=1);
namespace App\Controller;

use App\Entity\Recette; use App\Entity\RecetteDenree; use App\Entity\RecetteDenreeQuantite; use App\Entity\Utilisateur;
use App\Repository\DenreeRepository; use App\Repository\RecetteRepository; use App\Repository\SejourPublicCibleRepository; use App\Repository\UniteRepository;
use App\Service\ContexteSejour; use App\Service\ConversionConditionnement;
use Doctrine\ORM\EntityManagerInterface; use Symfony\Bundle\FrameworkBundle\Controller\AbstractController; use Symfony\Component\HttpFoundation\Request; use Symfony\Component\HttpFoundation\Response; use Symfony\Component\Routing\Attribute\Route; use Symfony\Component\Security\Http\Attribute\IsGranted; use Symfony\Component\Uid\Uuid;

#[IsGranted(Utilisateur::ROLE_GESTIONNAIRE)]
final class RecetteController extends AbstractController
{
    #[Route('/recettes', name: 'app_recettes', methods: ['GET'])]
    public function index(ContexteSejour $c, RecetteRepository $r): Response { $s=$c->actif(); return $this->render('recette/index.html.twig',['sejour'=>$s,'recettes'=>$s ? $r->findActivesPourSejour($s) : []]); }
    #[Route('/recettes/ajouter', name: 'app_recette_ajouter', methods: ['GET','POST'])]
    #[Route('/recettes/{id}/modifier', name: 'app_recette_modifier', methods: ['GET','POST'])]
    public function formulaire(Request $request, ContexteSejour $c, RecetteRepository $repo, DenreeRepository $denrees, SejourPublicCibleRepository $publics, UniteRepository $unites, ConversionConditionnement $conversion, EntityManagerInterface $em, ?string $id = null): Response
    {
        $sejour=$c->actif(); $recette=$id && Uuid::isValid($id) ? $repo->find($id) : null;
        if (!$sejour || ($id && (!$recette || $recette->getSejour() !== $sejour))) throw $this->createNotFoundException();
        $recette ??= new Recette($sejour); $publicsActifs=$publics->findActifsPourSejour($sejour); $denreesActives=$denrees->findActifsPourSejour($sejour); $erreurs=[];
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('enregistrer_recette',$request->request->getString('_token'))) throw $this->createAccessDeniedException();
            $nom=trim($request->request->getString('nom')); $lignes=$request->request->all('lignes'); if ($nom==='') $erreurs[]='Le nom est obligatoire.'; $composition=[];
            foreach ($lignes as $i=>$data) {
                if (!is_array($data)) continue; $d=Uuid::isValid((string)($data['denree']??'')) ? $denrees->find($data['denree']) : null; $u=Uuid::isValid((string)($data['conditionnement']??'')) ? $unites->find($data['conditionnement']) : null;
                if (!$d || $d->getSejour()!==$sejour || !$u || !in_array($u,$conversion->conditionnementsPour($d),true)) { $erreurs[]=sprintf('Ligne %d invalide.',$i+1); continue; }
                $q=[]; foreach($publicsActifs as $p){$v=str_replace(',','.',trim((string)($data['quantites'][(string)$p->getId()]??''))); if(!is_numeric($v)||(float)$v<0){$erreurs[]=sprintf('Quantité invalide ligne %d.',$i+1);continue 2;} $q[(string)$p->getId()]=number_format((float)$v,3,'.','');}
                $composition[]=['denree'=>$d,'conditionnement'=>$u,'quantites'=>$q];
            }
            if (!$composition) $erreurs[]='Ajoutez au moins une denrée.';
            if (!$erreurs) {
                $recette->setNom($nom); foreach($recette->getDenrees()->toArray() as $ancienne) $recette->removeDenree($ancienne);
                foreach($composition as $ordre=>$data){$ligne=(new RecetteDenree())->setDenree($data['denree'])->setConditionnement($data['conditionnement'])->setOrdre($ordre); foreach($publicsActifs as $p)$ligne->addQuantite((new RecetteDenreeQuantite())->setSejourPublicCible($p)->setQuantiteIndividuelle($data['quantites'][(string)$p->getId()])); $recette->addDenree($ligne);}
                $em->persist($recette);$em->flush();$this->addFlash('success','La recette a bien été enregistrée.');return $this->redirectToRoute('app_recettes');
            }
        }
        $catalogue=[]; foreach($denreesActives as $d)$catalogue[(string)$d->getId()]=array_map(static fn($u)=>['id'=>(string)$u->getId(),'nom'=>$u->getNom()],$conversion->conditionnementsPour($d));
        return $this->render('recette/form.html.twig',['recette'=>$recette,'denrees'=>$denreesActives,'publics'=>$publicsActifs,'catalogue'=>$catalogue,'erreurs'=>$erreurs]);
    }
}
