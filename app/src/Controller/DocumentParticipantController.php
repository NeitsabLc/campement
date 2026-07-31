<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\DocumentParticipant;
use App\Entity\Participant;
use App\Entity\Utilisateur;
use App\Repository\ParticipantRepository;
use App\Service\ContexteSejour;
use App\Service\StockageDocumentParticipant;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[IsGranted(new Expression("is_granted('ROLE_GESTIONNAIRE') or is_granted('ROLE_GROUPE')"))]
final class DocumentParticipantController extends AbstractController
{
    #[Route('/administratif/participants/{id}/documents/{type}', name: 'app_participant_document_ajouter', methods: ['POST'])]
    public function ajouter(string $id, string $type, Request $request, ContexteSejour $contexteSejour, ParticipantRepository $participants, EntityManagerInterface $entityManager, StockageDocumentParticipant $stockage): Response
    {
        $participant = $this->participantAutorise($id, $contexteSejour, $participants);
        if (!in_array($type, DocumentParticipant::typesPour($participant->getType()), true)) throw $this->createNotFoundException('Catégorie de document invalide.');
        if (!$this->isCsrfTokenValid('ajouter_document_'.$participant->getId().'_'.$type, $request->request->getString('_token'))) throw $this->createAccessDeniedException('Jeton CSRF invalide.');

        $fichiers = $request->files->all('documents');
        if (!is_array($fichiers)) $fichiers = [$fichiers];
        $fichiers = array_values(array_filter($fichiers, static fn (mixed $fichier): bool => $fichier instanceof UploadedFile));
        if ([] === $fichiers) {
            $this->addFlash('error', 'Sélectionnez au moins un fichier.');
            return $this->redirectToRoute('app_participant_modifier', ['id' => $participant->getId()]);
        }
        if (DocumentParticipant::AUTORISATION_DEPART_CAMP === $type && count($fichiers) > 1) {
            $this->addFlash('error', 'Une seule autorisation de départ peut être envoyée.');
            return $this->redirectToRoute('app_participant_modifier', ['id' => $participant->getId()]);
        }
        foreach ($fichiers as $fichier) if (null !== ($erreur = $stockage->valider($fichier))) {
            $this->addFlash('error', $erreur);
            return $this->redirectToRoute('app_participant_modifier', ['id' => $participant->getId()]);
        }

        $nouveauxChemins = [];
        $anciens = [];
        $connexion = $entityManager->getConnection();
        $connexion->beginTransaction();
        try {
            foreach ($fichiers as $fichier) {
                $chemin = $stockage->stocker($fichier);
                $nouveauxChemins[] = $chemin;
                $document = (new DocumentParticipant())->setParticipant($participant)->setType($type)
                    ->setNomFichier(mb_substr($fichier->getClientOriginalName(), 0, 255))->setCheminStockage($chemin);
                if (DocumentParticipant::AUTORISATION_DEPART_CAMP === $type) {
                    foreach ($participant->getDocuments() as $existant) if ($existant->getType() === $type) { $anciens[] = $existant->getCheminStockage(); $entityManager->remove($existant); }
                    $entityManager->flush();
                }
                $entityManager->persist($document);
            }
            $entityManager->flush();
            $connexion->commit();
        } catch (\Throwable $exception) {
            if ($connexion->isTransactionActive()) $connexion->rollBack();
            foreach ($nouveauxChemins as $chemin) $stockage->supprimer($chemin);
            throw $exception;
        }
        foreach ($anciens as $chemin) $stockage->supprimer($chemin);
        $this->addFlash('success', count($fichiers) > 1 ? 'Les documents ont bien été ajoutés.' : 'Le document a bien été ajouté.');

        return $this->redirectToRoute('app_participant_modifier', ['id' => $participant->getId()]);
    }

    #[Route('/administratif/documents/{id}/telecharger', name: 'app_participant_document_telecharger', methods: ['GET'])]
    public function telecharger(string $id, ContexteSejour $contexteSejour, EntityManagerInterface $entityManager, StockageDocumentParticipant $stockage): BinaryFileResponse
    {
        $document = Uuid::isValid($id) ? $entityManager->find(DocumentParticipant::class, $id) : null;
        if (!$document instanceof DocumentParticipant) throw $this->createNotFoundException('Document introuvable.');
        $this->verifierAcces($document->getParticipant(), $contexteSejour);
        $chemin = $stockage->chemin($document->getCheminStockage());
        if (!is_file($chemin)) throw $this->createNotFoundException('Le fichier associé est introuvable.');

        return $this->file($chemin, $document->getNomFichier());
    }

    #[Route('/administratif/documents/{id}/supprimer', name: 'app_participant_document_supprimer', methods: ['POST'])]
    public function supprimer(string $id, Request $request, ContexteSejour $contexteSejour, EntityManagerInterface $entityManager, StockageDocumentParticipant $stockage): Response
    {
        $document = Uuid::isValid($id) ? $entityManager->find(DocumentParticipant::class, $id) : null;
        if (!$document instanceof DocumentParticipant) throw $this->createNotFoundException('Document introuvable.');
        $participant = $document->getParticipant();
        $this->verifierAcces($participant, $contexteSejour);
        if (!$this->isCsrfTokenValid('supprimer_document_'.$id, $request->request->getString('_token'))) throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        $chemin = $document->getCheminStockage();
        $entityManager->remove($document);
        $entityManager->flush();
        $stockage->supprimer($chemin);
        $this->addFlash('success', 'Le document a bien été supprimé.');

        return $this->redirectToRoute('app_participant_modifier', ['id' => $participant->getId()]);
    }

    private function participantAutorise(string $id, ContexteSejour $contexteSejour, ParticipantRepository $participants): Participant
    {
        $participant = Uuid::isValid($id) ? $participants->find($id) : null;
        if (!$participant instanceof Participant) throw $this->createNotFoundException('Participant introuvable.');
        $this->verifierAcces($participant, $contexteSejour);

        return $participant;
    }

    private function verifierAcces(Participant $participant, ContexteSejour $contexteSejour): void
    {
        $sejour = $contexteSejour->actif();
        if (!$sejour || $participant->getGroupe()->getSejour() !== $sejour) throw $this->createNotFoundException('Participant introuvable pour le séjour actif.');
        if ($this->isGranted(Utilisateur::ROLE_GROUPE) && (!$this->getUser() instanceof Utilisateur || $participant->getGroupe() !== $this->getUser()->getGroupe())) {
            throw $this->createAccessDeniedException('Ce participant n’appartient pas à votre unité.');
        }
    }
}
