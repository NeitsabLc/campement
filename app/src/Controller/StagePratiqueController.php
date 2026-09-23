<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\CommentaireStagePratique;
use App\Entity\Participant;
use App\Entity\Sejour;
use App\Entity\Utilisateur;
use App\Repository\ParticipantRepository;
use App\Service\ContexteSejour;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[IsGranted(Utilisateur::ROLE_GESTIONNAIRE)]
final class StagePratiqueController extends AbstractController
{
    #[Route('/stages-pratiques', name: 'app_stages_pratiques', methods: ['GET'])]
    public function index(ContexteSejour $contexte, ParticipantRepository $participants): Response
    {
        $sejour = $contexte->actif();
        if (!$sejour) {
            throw $this->createNotFoundException('Aucun séjour actif.');
        }

        $adultesDisponibles = [];
        $stagiaires = [];
        $joursParParticipant = [];
        $commentairesParParticipant = [];
        foreach ($participants->findPourSejour($sejour) as $participant) {
            if (Participant::TYPE_ADULTE !== $participant->getType()) {
                continue;
            }
            if (null === $participant->getTypeStagePratique()) {
                $adultesDisponibles[] = $participant;
                continue;
            }
            $stagiaires[] = $participant;
            [$debut, $fin] = $this->periodeDeSuivi($sejour, $participant);
            $jours = [];
            for ($jour = $debut; $jour <= $fin; $jour = $jour->modify('+1 day')) {
                $jours[] = $jour;
            }
            $joursParParticipant[(string) $participant->getId()] = $jours;
            foreach ($participant->getCommentairesStagePratique() as $commentaire) {
                $commentairesParParticipant[(string) $participant->getId()][$commentaire->getDateCommentaire()->format('Y-m-d')] = $commentaire->getCommentaire();
            }
        }

        return $this->render('stage_pratique/index.html.twig', compact('sejour', 'stagiaires', 'adultesDisponibles', 'joursParParticipant', 'commentairesParParticipant'));
    }

    #[Route('/stages-pratiques/ajouter', name: 'app_stage_pratique_ajouter', methods: ['POST'])]
    public function ajouter(
        Request $request,
        ContexteSejour $contexte,
        ParticipantRepository $participants,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$this->isCsrfTokenValid('ajouter_stage_pratique', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        $sejour = $contexte->actif();
        $id = $request->request->getString('participant_id');
        $participant = Uuid::isValid($id) ? $participants->find($id) : null;
        $type = $request->request->getString('type_stage_pratique');
        if (!$sejour
            || !$participant instanceof Participant
            || $participant->getGroupe()->getSejour() !== $sejour
            || Participant::TYPE_ADULTE !== $participant->getType()) {
            throw $this->createNotFoundException('Encadrant introuvable pour le séjour actif.');
        }
        if (!in_array($type, ['BAFA', 'BAFD'], true)) {
            $this->addFlash('error', 'Sélectionnez un type de stage pratique.');

            return $this->redirectToRoute('app_stages_pratiques');
        }

        $participant->setTypeStagePratique($type);
        $entityManager->flush();
        $this->addFlash('success', sprintf('%s %s est maintenant en stage pratique %s.', $participant->getPrenom(), $participant->getNom(), $type));

        return $this->redirectToRoute('app_stages_pratiques');
    }

    #[Route('/stages-pratiques/{id}', name: 'app_stage_pratique_modifier', methods: ['POST'])]
    public function modifier(
        string $id,
        Request $request,
        ContexteSejour $contexte,
        ParticipantRepository $participants,
        EntityManagerInterface $entityManager,
    ): Response {
        $participant = $this->stagiaireDuSejour($id, $contexte, $participants);
        if (!$this->isCsrfTokenValid('modifier_stage_pratique_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $type = $request->request->getString('type_stage_pratique');
        $avisSgdf = trim($request->request->getString('avis_sgdf'));
        $avisFormation = trim($request->request->getString('avis_formation'));
        $commentaires = $request->request->all('commentaires');
        $erreurs = [];
        if (!in_array($type, ['BAFA', 'BAFD'], true)) {
            $erreurs[] = 'Le type de stage pratique est invalide.';
        }
        if (mb_strlen($avisSgdf) > 10000 || mb_strlen($avisFormation) > 10000) {
            $erreurs[] = 'Chaque avis est limité à 10 000 caractères.';
        }
        foreach ($commentaires as $commentaire) {
            if (!is_string($commentaire) || mb_strlen(trim($commentaire)) > 3000) {
                $erreurs[] = 'Chaque commentaire quotidien est limité à 3 000 caractères.';
                break;
            }
        }
        if ([] !== $erreurs) {
            foreach ($erreurs as $erreur) {
                $this->addFlash('error', $erreur);
            }

            return $this->redirectToRoute('app_stages_pratiques', ['_fragment' => 'stage-'.$id]);
        }

        $participant->setTypeStagePratique($type)
            ->setAvisStageSgdf('' === $avisSgdf ? null : $avisSgdf)
            ->setAvisStageFormation('' === $avisFormation ? null : $avisFormation);
        $existants = [];
        foreach ($participant->getCommentairesStagePratique() as $commentaire) {
            $existants[$commentaire->getDateCommentaire()->format('Y-m-d')] = $commentaire;
        }
        $sejour = $contexte->actif();
        if (!$sejour) {
            throw $this->createNotFoundException('Aucun séjour actif.');
        }
        [$debut, $fin] = $this->periodeDeSuivi($sejour, $participant);
        for ($jour = $debut; $jour <= $fin; $jour = $jour->modify('+1 day')) {
            $cle = $jour->format('Y-m-d');
            $texte = isset($commentaires[$cle]) && is_string($commentaires[$cle]) ? trim($commentaires[$cle]) : '';
            $existant = $existants[$cle] ?? null;
            if ('' === $texte && $existant instanceof CommentaireStagePratique) {
                $entityManager->remove($existant);
            } elseif ('' !== $texte && $existant instanceof CommentaireStagePratique) {
                $existant->setCommentaire($texte);
            } elseif ('' !== $texte) {
                $entityManager->persist(new CommentaireStagePratique($participant, $jour, $texte));
            }
        }
        $entityManager->flush();
        $this->addFlash('success', sprintf('Le suivi de %s %s a été enregistré.', $participant->getPrenom(), $participant->getNom()));

        return $this->redirectToRoute('app_stages_pratiques', ['_fragment' => 'stage-'.$id]);
    }

    #[Route('/stages-pratiques/{id}/retirer', name: 'app_stage_pratique_retirer', methods: ['POST'])]
    public function retirer(
        string $id,
        Request $request,
        ContexteSejour $contexte,
        ParticipantRepository $participants,
        EntityManagerInterface $entityManager,
    ): Response {
        $participant = $this->stagiaireDuSejour($id, $contexte, $participants);
        if (!$this->isCsrfTokenValid('retirer_stage_pratique_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        foreach ($participant->getCommentairesStagePratique() as $commentaire) {
            $entityManager->remove($commentaire);
        }
        $participant->setTypeStagePratique(null)->setAvisStageSgdf(null)->setAvisStageFormation(null);
        $entityManager->flush();
        $this->addFlash('success', 'Le dossier de stage pratique a été retiré.');

        return $this->redirectToRoute('app_stages_pratiques');
    }

    private function stagiaireDuSejour(string $id, ContexteSejour $contexte, ParticipantRepository $participants): Participant
    {
        $sejour = $contexte->actif();
        $participant = Uuid::isValid($id) ? $participants->find($id) : null;
        if (!$sejour
            || !$participant instanceof Participant
            || $participant->getGroupe()->getSejour() !== $sejour
            || Participant::TYPE_ADULTE !== $participant->getType()
            || null === $participant->getTypeStagePratique()) {
            throw $this->createNotFoundException('Stagiaire introuvable pour le séjour actif.');
        }

        return $participant;
    }

    /** @return array{\DateTimeImmutable, \DateTimeImmutable} */
    private function periodeDeSuivi(Sejour $sejour, Participant $participant): array
    {
        $debut = $participant->getDateDebutPresence() > $sejour->getDateDebut()
            ? $participant->getDateDebutPresence()
            : $sejour->getDateDebut();
        $fin = $participant->getDateFinPresence() < $sejour->getDateFin()
            ? $participant->getDateFinPresence()
            : $sejour->getDateFin();

        return [$debut, $fin];
    }
}
