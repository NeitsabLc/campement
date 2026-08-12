<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Groupe;
use App\Entity\DocumentParticipant;
use App\Entity\Participant;
use App\Entity\Utilisateur;
use App\Repository\GroupeRepository;
use App\Repository\ParticipantRepository;
use App\Service\ContexteSejour;
use App\Service\FormulaireParticipant;
use App\Service\ListeParticipantsPdf;
use App\Service\StockageDocumentParticipant;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[IsGranted(new Expression("is_granted('ROLE_GESTIONNAIRE') or is_granted('ROLE_GROUPE')"))]
final class ParticipantController extends AbstractController
{
    #[IsGranted(Utilisateur::ROLE_GESTIONNAIRE)]
    #[Route('/administratif/participants/pdf', name: 'app_participants_pdf', methods: ['GET'])]
    public function pdf(
        ContexteSejour $contexteSejour,
        GroupeRepository $groupeRepository,
        ParticipantRepository $participantRepository,
        ListeParticipantsPdf $pdf,
    ): Response {
        $sejour = $contexteSejour->actif();
        if (!$sejour) throw $this->createNotFoundException('Aucun séjour actif.');
        $contenu = $pdf->generer($sejour, $groupeRepository->findActifsPourSejour($sejour), $participantRepository->findPourSejour($sejour));
        $nom = preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($sejour->getNom())) ?: 'sejour';

        return new Response($contenu, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="participants-%s.pdf"', trim($nom, '-')),
        ]);
    }

    #[Route('/administratif/participants', name: 'app_participants', methods: ['GET', 'POST'])]
    #[Route('/administratif/participants/ajouter', name: 'app_participant_ajouter', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        ContexteSejour $contexteSejour,
        GroupeRepository $groupeRepository,
        ParticipantRepository $participantRepository,
        FormulaireParticipant $formulaire,
        EntityManagerInterface $entityManager,
    ): Response {
        $sejour = $contexteSejour->actif();
        $utilisateurGroupe = $this->getUser() instanceof Utilisateur && $this->isGranted(Utilisateur::ROLE_GROUPE)
            ? $this->getUser()->getGroupe()
            : null;
        $donnees = $formulaire->lire($request);
        $erreurs = [];

        if (!$request->isMethod('POST') && 'app_participant_ajouter' === $request->attributes->get('_route')) {
            $donnees['groupe_id'] = $utilisateurGroupe instanceof Groupe
                ? (string) $utilisateurGroupe->getId()
                : $request->query->getString('groupe');
            $donnees['type'] = $request->query->getString('type');
            $groupeInitial = $this->trouverGroupe($donnees['groupe_id'], $sejour, $groupeRepository);
            if ($groupeInitial) {
                $donnees['date_debut_presence'] = $groupeInitial->getDateDebutPresence()->format('Y-m-d');
                $donnees['date_fin_presence'] = $groupeInitial->getDateFinPresence()->format('Y-m-d');
            }
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('ajouter_participant', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }

            $groupe = $this->trouverGroupe($donnees['groupe_id'], $sejour, $groupeRepository);
            if ($utilisateurGroupe instanceof Groupe && $groupe !== $utilisateurGroupe) {
                $groupe = null;
            }
            if (!$groupe instanceof Groupe) $erreurs[] = 'Sélectionnez une unité du séjour actif.';
            $validation = $sejour ? $formulaire->valider($donnees, $sejour) : ['erreurs' => []];
            $erreurs = [...$erreurs, ...$validation['erreurs']];

            if ([] === $erreurs && $groupe) {
                $participant = (new Participant())->setGroupe($groupe)->setType($donnees['type']);
                $formulaire->appliquer($participant, $donnees, $validation);
                $entityManager->persist($participant);
                $entityManager->flush();
                $this->addFlash('success', sprintf('%s %s a bien été ajouté%s.', $participant->getPrenom(), $participant->getNom(), Participant::TYPE_JEUNE === $participant->getType() ? 'e' : ''));
                return $this->redirectToRoute('app_participants');
            }
        }

        $groupes = null === $sejour
            ? []
            : ($utilisateurGroupe instanceof Groupe
                ? ($utilisateurGroupe->isActif() ? [$utilisateurGroupe] : [])
                : $groupeRepository->findActifsPourSejour($sejour));
        $participantsParGroupe = [];
        if ($sejour) foreach ($participantRepository->findPourSejour($sejour) as $participant) {
            if ($utilisateurGroupe instanceof Groupe && $participant->getGroupe() !== $utilisateurGroupe) continue;
            $participantsParGroupe[(string) $participant->getGroupe()->getId()][$participant->getType()][] = $participant;
        }

        $template = 'app_participants' === $request->attributes->get('_route') && !$request->isMethod('POST')
            ? 'participant/index.html.twig'
            : 'participant/ajouter.html.twig';

        return $this->render($template, compact('sejour', 'groupes', 'participantsParGroupe', 'donnees', 'erreurs') + [
            'qualifications' => Participant::QUALIFICATIONS,
            'utilisateur_groupe' => $utilisateurGroupe instanceof Groupe,
        ]);
    }

    #[Route('/administratif/participants/{id}', name: 'app_participant_modifier', methods: ['GET', 'POST'])]
    public function modifier(
        string $id,
        Request $request,
        ContexteSejour $contexteSejour,
        ParticipantRepository $participantRepository,
        FormulaireParticipant $formulaire,
        EntityManagerInterface $entityManager,
    ): Response {
        $sejour = $contexteSejour->actif();
        $participant = Uuid::isValid($id) ? $participantRepository->find($id) : null;
        if (!$sejour || !$participant instanceof Participant || $participant->getGroupe()->getSejour() !== $sejour) {
            throw $this->createNotFoundException('Participant introuvable pour le séjour actif.');
        }
        if ($this->isGranted(Utilisateur::ROLE_GROUPE)
            && (!$this->getUser() instanceof Utilisateur || $participant->getGroupe() !== $this->getUser()->getGroupe())) {
            throw $this->createAccessDeniedException('Ce participant n’appartient pas à votre unité.');
        }

        $donnees = $request->isMethod('POST') ? $formulaire->lire($request) : $formulaire->depuisParticipant($participant);
        $donnees['type'] = $participant->getType();
        $erreurs = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('modifier_participant_'.$id, $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }
            $validation = $formulaire->valider($donnees, $sejour);
            $erreurs = $validation['erreurs'];
            if ([] === $erreurs) {
                $formulaire->appliquer($participant, $donnees, $validation);
                $entityManager->flush();
                $this->addFlash('success', sprintf('La fiche de %s %s a bien été mise à jour.', $participant->getPrenom(), $participant->getNom()));

                return $this->redirectToRoute('app_participant_modifier', ['id' => $participant->getId()]);
            }
        }

        return $this->render('participant/modifier.html.twig', [
            'sejour' => $sejour,
            'participant' => $participant,
            'donnees' => $donnees,
            'erreurs' => $erreurs,
            'qualifications' => Participant::QUALIFICATIONS,
            'types_documents' => DocumentParticipant::typesPour($participant->getType()),
            'libelles_documents' => [
                DocumentParticipant::AUTORISATION_DEPART_CAMP => 'Autorisation de départ',
                DocumentParticipant::QUALIFICATION => 'Formation',
                DocumentParticipant::FICHE_SANITAIRE => 'Fiche sanitaire',
                DocumentParticipant::VACCINS => 'Vaccins',
            ],
        ]);
    }

    #[IsGranted(Utilisateur::ROLE_GESTIONNAIRE)]
    #[Route('/administratif/participants/{id}/supprimer', name: 'app_participant_supprimer', methods: ['POST'])]
    public function supprimer(
        string $id,
        Request $request,
        ContexteSejour $contexteSejour,
        ParticipantRepository $participantRepository,
        EntityManagerInterface $entityManager,
        StockageDocumentParticipant $stockageDocuments,
    ): Response {
        $sejour = $contexteSejour->actif();
        $participant = Uuid::isValid($id) ? $participantRepository->find($id) : null;
        if (!$sejour || !$participant instanceof Participant || $participant->getGroupe()->getSejour() !== $sejour) {
            throw $this->createNotFoundException('Participant introuvable pour le séjour actif.');
        }
        if (!$this->isCsrfTokenValid('supprimer_participant_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $nomComplet = $participant->getPrenom().' '.$participant->getNom();
        foreach ($participant->getDocuments() as $document) $stockageDocuments->supprimer($document->getCheminStockage());
        $entityManager->remove($participant);
        $entityManager->flush();
        $this->addFlash('success', sprintf('La fiche de %s a bien été supprimée.', $nomComplet));

        return $this->redirectToRoute('app_participants');
    }

    private function trouverGroupe(string $id, mixed $sejour, GroupeRepository $repository): ?Groupe
    {
        if (!$sejour || !Uuid::isValid($id)) return null;
        $groupe = $repository->find($id);
        return $groupe instanceof Groupe && $groupe->isActif() && $groupe->getSejour() === $sejour ? $groupe : null;
    }

}
