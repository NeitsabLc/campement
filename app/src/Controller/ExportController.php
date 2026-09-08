<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\DocumentParticipant;
use App\Entity\Participant;
use App\Entity\Utilisateur;
use App\Repository\GroupeRepository;
use App\Repository\ParticipantRepository;
use App\Service\ContexteSejour;
use App\Service\DocumentsParticipantsPdf;
use App\Service\ListeParticipantsPdf;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(Utilisateur::ROLE_GESTIONNAIRE)]
final class ExportController extends AbstractController
{
    #[Route('/administratif/export', name: 'app_export', methods: ['GET'])]
    public function index(ContexteSejour $contexteSejour, ParticipantRepository $participants): Response
    {
        $sejour = $contexteSejour->actif();
        $compteurs = ['adultes' => 0, 'jeunes' => 0];
        if ($sejour) {
            foreach ($participants->findPourSejour($sejour) as $participant) {
                ++$compteurs[Participant::TYPE_ADULTE === $participant->getType() ? 'adultes' : 'jeunes'];
            }
        }

        return $this->render('export/index.html.twig', compact('sejour', 'compteurs'));
    }

    #[Route('/administratif/export/documents/{public}', name: 'app_export_documents', requirements: ['public' => 'adultes|jeunes'], methods: ['GET'])]
    public function documents(string $public, ContexteSejour $contexteSejour, ParticipantRepository $participants, DocumentsParticipantsPdf $pdf): Response
    {
        $sejour = $contexteSejour->actif();
        if (!$sejour) {
            throw $this->createNotFoundException('Aucun séjour actif.');
        }
        $type = 'adultes' === $public ? Participant::TYPE_ADULTE : Participant::TYPE_JEUNE;
        $typesDocuments = 'adultes' === $public
            ? [DocumentParticipant::FICHE_SANITAIRE, DocumentParticipant::VACCINS, DocumentParticipant::QUALIFICATION]
            : [DocumentParticipant::FICHE_SANITAIRE, DocumentParticipant::VACCINS, DocumentParticipant::AUTORISATION_DEPART_CAMP];
        $personnes = array_values(array_filter($participants->findPourSejour($sejour), static fn (Participant $participant): bool => $participant->getType() === $type));

        return $this->reponsePdf($pdf->generer($personnes, $typesDocuments), sprintf('documents-%s-%s.pdf', $public, $this->slug($sejour->getNom())));
    }

    #[Route('/administratif/export/participants', name: 'app_export_participants', methods: ['GET'])]
    public function participants(ContexteSejour $contexteSejour, GroupeRepository $groupes, ParticipantRepository $participants, ListeParticipantsPdf $pdf): Response
    {
        $sejour = $contexteSejour->actif();
        if (!$sejour) {
            throw $this->createNotFoundException('Aucun séjour actif.');
        }

        return $this->reponsePdf(
            $pdf->generer($sejour, $groupes->findActifsPourSejour($sejour), $participants->findPourSejour($sejour)),
            sprintf('participants-%s.pdf', $this->slug($sejour->getNom())),
        );
    }

    private function reponsePdf(string $contenu, string $nom): Response
    {
        return new Response($contenu, Response::HTTP_OK, ['Content-Type' => 'application/pdf', 'Content-Disposition' => sprintf('attachment; filename="%s"', $nom)]);
    }

    private function slug(string $nom): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($nom)) ?: 'sejour', '-');
    }
}
