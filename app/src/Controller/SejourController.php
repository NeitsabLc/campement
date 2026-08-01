<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\PublicCible;
use App\Entity\Sejour;
use App\Entity\SejourTypeRepas;
use App\Entity\Utilisateur;
use App\Repository\PublicCibleRepository;
use App\Repository\SejourRepository;
use App\Repository\TypeRepasRepository;
use App\Repository\UtilisateurRepository;
use App\Service\ContexteSejour;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[IsGranted(Utilisateur::ROLE_GESTIONNAIRE)]
final class SejourController extends AbstractController
{
    #[Route('/sejours', name: 'app_sejours', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        SejourRepository $repository,
        PublicCibleRepository $publics,
        TypeRepasRepository $typesRepas,
        UtilisateurRepository $utilisateurs,
        ContexteSejour $contexte,
        EntityManagerInterface $entityManager,
    ): Response {
        $admin = $this->isGranted(Utilisateur::ROLE_ADMIN);
        $connecte = $this->getUser();
        if (!$connecte instanceof Utilisateur) { throw $this->createAccessDeniedException(); }
        $liste = $admin ? $repository->findBy([], ['dateDebut' => 'DESC']) : $contexte->accessibles();
        $erreurs = [];
        $id = $request->request->getString('sejour_id');
        $sejour = '' !== $id && Uuid::isValid($id) ? $repository->find($id) : null;

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('enregistrer_sejour', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }
            $creation = !$sejour instanceof Sejour;
            if (($creation && !$admin) || (!$creation && !$admin && !$connecte->getSejoursGeres()->contains($sejour))) {
                throw $this->createAccessDeniedException('Ce séjour ne peut pas être modifié.');
            }
            $nom = trim($request->request->getString('nom'));
            try {
                $debut = new \DateTimeImmutable($request->request->getString('date_debut'));
                $fin = new \DateTimeImmutable($request->request->getString('date_fin'));
            } catch (\Throwable) {
                $debut = $fin = null;
            }
            $publicIds = array_filter($request->request->all('publics'), 'is_string');
            if ('' === $nom || mb_strlen($nom) > 150) { $erreurs[] = 'Le nom est obligatoire et limité à 150 caractères.'; }
            if (!$debut instanceof \DateTimeImmutable || !$fin instanceof \DateTimeImmutable || $fin < $debut) { $erreurs[] = 'Les dates du séjour sont invalides.'; }
            if ([] === $publicIds) { $erreurs[] = 'Sélectionnez au moins un type de public.'; }
            $gestionnaire = null;
            if ($creation) {
                $gestionnaireId = $request->request->getString('gestionnaire');
                $gestionnaire = Uuid::isValid($gestionnaireId) ? $utilisateurs->find($gestionnaireId) : null;
                if (!$gestionnaire instanceof Utilisateur || Utilisateur::ROLE_GESTIONNAIRE !== $gestionnaire->getRole() || !$gestionnaire->isActif()) {
                    $erreurs[] = 'Sélectionnez un gestionnaire actif.';
                }
            }
            if ([] === $erreurs) {
                $sejour ??= new Sejour($nom, $debut, $fin);
                $sejour->setNom($nom)
                    ->setModuleIntendanceActif($request->request->has('module_intendance'))
                    ->setModuleAdministratifActif($request->request->has('module_administratif'))
                    ->setModuleSituationsParticulieresActif($request->request->has('module_situations_particulieres'));
                if (!$creation) { $sejour->setDates($debut, $fin); }
                foreach ($publics->findActifs() as $public) {
                    in_array((string) $public->getId(), $publicIds, true) ? $sejour->addPublicCible($public) : $sejour->removePublicCible($public);
                }
                if ($creation && $gestionnaire instanceof Utilisateur) {
                    $sejour->addGestionnaire($gestionnaire); $entityManager->persist($sejour);
                    foreach ($typesRepas->findActifs() as $typeRepas) $entityManager->persist(new SejourTypeRepas($sejour, $typeRepas, $typeRepas->getOrdre()));
                }
                $entityManager->flush();
                $this->addFlash('success', $creation ? 'Le séjour a été créé.' : 'Le séjour a été mis à jour.');
                return $this->redirectToRoute('app_sejours');
            }
        }

        return $this->render('sejour/index.html.twig', [
            'sejours' => $liste, 'publics' => $publics->findActifs(), 'erreurs' => $erreurs,
            'sejour_selectionne' => $contexte->actif(),
            'est_administrateur' => $admin,
            'gestionnaires' => array_filter($utilisateurs->findBy(['actif' => true], ['nom' => 'ASC']), static fn (Utilisateur $u): bool => Utilisateur::ROLE_GESTIONNAIRE === $u->getRole()),
        ]);
    }

    #[Route('/sejours/{id}/selection', name: 'app_sejour_selectionner', methods: ['POST'])]
    public function selectionner(Sejour $sejour, Request $request, ContexteSejour $contexte): Response
    {
        if (!$this->isCsrfTokenValid('selectionner_sejour_'.$sejour->getId(), $request->request->getString('_token'))) { throw $this->createAccessDeniedException(); }
        try { $contexte->selectionner($sejour); } catch (\InvalidArgumentException) { throw $this->createAccessDeniedException(); }
        return $this->redirectToRoute('sejours' === $request->request->getString('retour') ? 'app_sejours' : 'app_tableau_de_bord');
    }

    #[Route('/sejours/{id}/statut', name: 'app_sejour_statut', methods: ['POST'])]
    public function statut(Sejour $sejour, Request $request, EntityManagerInterface $entityManager, ContexteSejour $contexte): Response
    {
        $connecte = $this->getUser();
        if (!$connecte instanceof Utilisateur || (!$this->isGranted(Utilisateur::ROLE_ADMIN) && !$connecte->getSejoursGeres()->contains($sejour))) { throw $this->createAccessDeniedException(); }
        if (!$this->isCsrfTokenValid('statut_sejour_'.$sejour->getId(), $request->request->getString('_token'))) { throw $this->createAccessDeniedException(); }
        if (!$sejour->isActif() && !$this->isGranted(Utilisateur::ROLE_ADMIN)) { throw $this->createAccessDeniedException(); }
        $sejour->setActif(!$sejour->isActif());
        if (!$sejour->isActif() && $contexte->actif() === $sejour) { $contexte->selectionner(null); }
        $entityManager->flush();
        $this->addFlash('success', $sejour->isActif() ? 'Le séjour a été réactivé.' : 'Le séjour a été désactivé.');
        return $this->redirectToRoute('app_sejours');
    }
}
