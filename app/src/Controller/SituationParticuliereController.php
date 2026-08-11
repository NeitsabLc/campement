<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Participant;
use App\Entity\SituationParticuliere;
use App\Entity\TacheSituationParticuliere;
use App\Entity\Utilisateur;
use App\Repository\ParticipantRepository;
use App\Repository\SituationParticuliereRepository;
use App\Service\ContexteSejour;
use App\Service\GestionTachesSituationParticuliere;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[IsGranted(Utilisateur::ROLE_GESTIONNAIRE)]
#[Route('/situations-particulieres')]
final class SituationParticuliereController extends AbstractController
{
    #[Route('', name: 'app_situations_particulieres', methods: ['GET'])]
    public function liste(ContexteSejour $contexte, SituationParticuliereRepository $repository): Response
    {
        $sejour = $contexte->actif();
        if (!$sejour) throw $this->createNotFoundException('Aucun séjour actif.');
        return $this->render('situation_particuliere/liste.html.twig', [
            'sejour' => $sejour,
            'situations' => $repository->findPourSejour($sejour),
            'types_taches' => TacheSituationParticuliere::TYPES,
        ]);
    }

    #[Route('/nouvelle', name: 'app_situation_particuliere_creer', methods: ['GET', 'POST'])]
    public function creer(
        Request $request,
        ContexteSejour $contexte,
        ParticipantRepository $participants,
        GestionTachesSituationParticuliere $gestionTaches,
        EntityManagerInterface $entityManager,
    ): Response {
        $sejour = $contexte->actif();
        if (!$sejour) throw $this->createNotFoundException('Aucun séjour actif.');
        $donnees = $this->lireSituation($request);
        $erreurs = [];
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('creer_situation_particuliere', $request->request->getString('_token'))) throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            $date = $this->validerSituation($donnees, $sejour->getDateDebut(), $sejour->getDateFin(), $erreurs);
            if ([] === $erreurs && $date) {
                $situation = new SituationParticuliere($sejour, $donnees['libelle'], $date);
                $situation->setInformationsComplementaires($donnees['informations']);
                if ($sejour->isModuleAdministratifActif()) $this->synchroniserParticipants($situation, $donnees['participants'], $participants);
                $gestionTaches->synchroniser($situation);
                $entityManager->persist($situation);
                $entityManager->flush();
                $this->addFlash('success', 'La situation particulière a été créée.');
                return $this->redirectToRoute('app_situation_particuliere_modifier', ['id' => $situation->getId()]);
            }
        }
        return $this->formulaire($sejour, null, $donnees, $erreurs, $participants);
    }

    #[Route('/{id}', name: 'app_situation_particuliere_detail', methods: ['GET'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function detail(string $id, ContexteSejour $contexte, SituationParticuliereRepository $repository): Response
    {
        $situation = $this->trouver($id, $contexte, $repository);
        return $this->redirectToRoute('app_situation_particuliere_modifier', ['id' => $situation->getId()]);
    }

    #[Route('/{id}/modifier', name: 'app_situation_particuliere_modifier', methods: ['GET', 'POST'])]
    public function modifier(
        string $id,
        Request $request,
        ContexteSejour $contexte,
        SituationParticuliereRepository $repository,
        ParticipantRepository $participants,
        GestionTachesSituationParticuliere $gestionTaches,
        EntityManagerInterface $entityManager,
    ): Response {
        $situation = $this->trouver($id, $contexte, $repository);
        $sejour = $situation->getSejour();
        $donnees = $request->isMethod('POST') ? $this->lireSituation($request) : [
            'libelle' => $situation->getLibelle(),
            'date' => $situation->getDateSituation()->format('Y-m-d'),
            'informations' => $situation->getInformationsComplementaires(),
            'participants' => array_map(static fn (Participant $participant): string => (string) $participant->getId(), $situation->getParticipants()->toArray()),
        ];
        $erreurs = [];
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('modifier_situation_particuliere_'.$id, $request->request->getString('_token'))) throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            $date = $this->validerSituation($donnees, $sejour->getDateDebut(), $sejour->getDateFin(), $erreurs);
            if ([] === $erreurs && $date) {
                $situation->setLibelle($donnees['libelle'])->setDateSituation($date)->setInformationsComplementaires($donnees['informations']);
                if ($sejour->isModuleAdministratifActif()) $this->synchroniserParticipants($situation, $donnees['participants'], $participants);
                $gestionTaches->synchroniser($situation);
                $entityManager->flush();
                $this->addFlash('success', 'La situation particulière a été mise à jour.');
                return $this->redirectToRoute('app_situation_particuliere_modifier', ['id' => $id]);
            }
        }
        return $this->formulaire($sejour, $situation, $donnees, $erreurs, $participants);
    }

    #[Route('/{id}/taches', name: 'app_situation_particuliere_tache_ajouter', methods: ['POST'])]
    public function ajouterTache(string $id, Request $request, ContexteSejour $contexte, SituationParticuliereRepository $repository, EntityManagerInterface $entityManager): Response
    {
        $situation = $this->trouver($id, $contexte, $repository);
        if (!$this->isCsrfTokenValid('ajouter_tache_situation_'.$id, $request->request->getString('_token'))) throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        $type = $request->request->getString('type_predefini');
        $libelle = trim($request->request->getString('libelle_libre'));
        try {
            $echeance = $this->dateOptionnelle($request->request->getString('date_echeance'));
            if ('' !== $type) {
                foreach ($situation->getTaches() as $existante) if ($existante->getTypePredefini() === $type) throw new \DomainException('Une tâche de ce type existe déjà.');
                $tache = TacheSituationParticuliere::manuellePredefinie($situation, $type, $echeance);
            } else {
                $tache = TacheSituationParticuliere::libre($situation, $libelle, $echeance);
            }
            $entityManager->persist($tache);
            $entityManager->flush();
            $this->addFlash('success', 'La tâche a été ajoutée.');
        } catch (\InvalidArgumentException|\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }
        return $this->redirectToRoute('app_situation_particuliere_modifier', ['id' => $id]);
    }

    #[Route('/{id}/taches/{tacheId}', name: 'app_situation_particuliere_tache_modifier', methods: ['POST'])]
    public function modifierTache(string $id, string $tacheId, Request $request, ContexteSejour $contexte, SituationParticuliereRepository $repository, EntityManagerInterface $entityManager): Response
    {
        $situation = $this->trouver($id, $contexte, $repository);
        $tache = $situation->getTaches()->findFirst(static fn (int $index, TacheSituationParticuliere $candidate): bool => (string) $candidate->getId() === $tacheId);
        if (!$tache) throw $this->createNotFoundException('Tâche introuvable.');
        if (!$this->isCsrfTokenValid('modifier_tache_'.$tacheId, $request->request->getString('_token'))) throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        try {
            $statut = $request->request->getString('statut');
            $realisation = TacheSituationParticuliere::STATUT_REALISE === $statut ? ($this->dateOptionnelle($request->request->getString('date_realisation')) ?? new \DateTimeImmutable('today')) : null;
            $tache->setStatut($statut, $realisation)->setCommentaire($request->request->getString('commentaire'));
            if (TacheSituationParticuliere::ORIGINE_AUTOMATIQUE !== $tache->getOrigine()) {
                $tache->setDateEcheance($this->dateOptionnelle($request->request->getString('date_echeance')));
            }
            $entityManager->flush();
            $this->addFlash('success', 'La tâche a été mise à jour.');
        } catch (\InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }
        return $this->redirectToRoute('app_situation_particuliere_modifier', ['id' => $id]);
    }

    #[Route('/{id}/taches/{tacheId}/supprimer', name: 'app_situation_particuliere_tache_supprimer', methods: ['POST'])]
    public function supprimerTache(string $id, string $tacheId, Request $request, ContexteSejour $contexte, SituationParticuliereRepository $repository, EntityManagerInterface $entityManager): Response
    {
        $situation = $this->trouver($id, $contexte, $repository);
        $tache = $situation->getTaches()->findFirst(static fn (int $index, TacheSituationParticuliere $candidate): bool => (string) $candidate->getId() === $tacheId);
        if (!$tache) throw $this->createNotFoundException('Tâche introuvable.');
        if (!$this->isCsrfTokenValid('supprimer_tache_'.$tacheId, $request->request->getString('_token'))) throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        try {
            $situation->removeTache($tache);
            $entityManager->remove($tache);
            $entityManager->flush();
            $this->addFlash('success', 'La tâche a été supprimée.');
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }
        return $this->redirectToRoute('app_situation_particuliere_modifier', ['id' => $id]);
    }

    #[Route('/{id}/supprimer', name: 'app_situation_particuliere_supprimer', methods: ['GET', 'POST'])]
    public function supprimer(string $id, Request $request, ContexteSejour $contexte, SituationParticuliereRepository $repository, EntityManagerInterface $entityManager): Response
    {
        $situation = $this->trouver($id, $contexte, $repository);
        if ($request->isMethod('GET')) return $this->render('situation_particuliere/suppression.html.twig', ['situation' => $situation]);
        if (!$this->isCsrfTokenValid('supprimer_situation_particuliere_'.$id, $request->request->getString('_token'))) throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        if (!$situation->peutEtreSupprimee()) {
            $this->addFlash('error', 'Une situation contenant une tâche réalisée ne peut pas être supprimée.');
            return $this->redirectToRoute('app_situation_particuliere_modifier', ['id' => $id]);
        }
        $entityManager->remove($situation);
        $entityManager->flush();
        $this->addFlash('success', 'La situation particulière a été supprimée.');
        return $this->redirectToRoute('app_situations_particulieres');
    }

    /** @return array{libelle: string, date: string, informations: list<string>, participants: list<string>} */
    private function lireSituation(Request $request): array
    {
        return [
            'libelle' => trim($request->request->getString('libelle')),
            'date' => $request->request->getString('date_situation'),
            'informations' => array_values(array_intersect(array_keys(SituationParticuliere::INFORMATIONS), array_filter($request->request->all('informations'), 'is_string'))),
            'participants' => array_values(array_filter($request->request->all('participants'), 'is_string')),
        ];
    }
    /**
     * @param array{libelle: string, date: string, informations: list<string>, participants: list<string>} $donnees
     * @param list<string> $erreurs
     */
    private function validerSituation(array $donnees, \DateTimeImmutable $debut, \DateTimeImmutable $fin, array &$erreurs): ?\DateTimeImmutable
    {
        if ('' === $donnees['libelle'] || mb_strlen($donnees['libelle']) > 200) $erreurs[] = 'Le libellé est obligatoire et limité à 200 caractères.';
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $donnees['date']);
        if (!$date || $date->format('Y-m-d') !== $donnees['date']) $erreurs[] = 'La date de la situation est invalide.';
        elseif ($date < $debut || $date > $fin) $erreurs[] = 'La date de la situation doit être comprise dans les dates du séjour.';
        return $date ?: null;
    }
    private function dateOptionnelle(string $valeur): ?\DateTimeImmutable
    {
        if ('' === $valeur) return null;
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $valeur);
        if (!$date || $date->format('Y-m-d') !== $valeur) throw new \InvalidArgumentException('La date saisie est invalide.');
        return $date;
    }
    /** @param list<string> $ids */
    private function synchroniserParticipants(SituationParticuliere $situation, array $ids, ParticipantRepository $repository): void
    {
        foreach ($situation->getParticipants()->toArray() as $participant) $situation->removeParticipant($participant);
        foreach (array_unique($ids) as $id) {
            $participant = Uuid::isValid($id) ? $repository->find($id) : null;
            if ($participant instanceof Participant && $participant->getGroupe()->getSejour() === $situation->getSejour()) $situation->addParticipant($participant);
        }
    }
    private function trouver(string $id, ContexteSejour $contexte, SituationParticuliereRepository $repository): SituationParticuliere
    {
        $sejour = $contexte->actif();
        $situation = Uuid::isValid($id) ? $repository->find($id) : null;
        if (!$sejour || !$situation instanceof SituationParticuliere || $situation->getSejour() !== $sejour) throw $this->createNotFoundException('Situation particulière introuvable pour le séjour actif.');
        return $situation;
    }
    /**
     * @param array{libelle: string, date: string, informations: list<string>, participants: list<string>} $donnees
     * @param list<string> $erreurs
     */
    private function formulaire(mixed $sejour, ?SituationParticuliere $situation, array $donnees, array $erreurs, ParticipantRepository $participants): Response
    {
        return $this->render('situation_particuliere/formulaire.html.twig', [
            'sejour' => $sejour, 'situation' => $situation, 'donnees' => $donnees, 'erreurs' => $erreurs,
            'informations' => SituationParticuliere::INFORMATIONS,
            'participants_disponibles' => $sejour->isModuleAdministratifActif() ? $participants->findPourSejour($sejour) : [],
            'types_taches' => TacheSituationParticuliere::TYPES,
            'statuts' => TacheSituationParticuliere::STATUTS,
        ]);
    }
}
