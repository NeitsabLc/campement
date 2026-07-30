<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Groupe;
use App\Entity\Participant;
use App\Entity\Utilisateur;
use App\Repository\GroupeRepository;
use App\Repository\ParticipantRepository;
use App\Service\ContexteSejour;
use App\Service\ListeParticipantsPdf;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[IsGranted(Utilisateur::ROLE_GESTIONNAIRE)]
final class ParticipantController extends AbstractController
{
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
    public function index(
        Request $request,
        ContexteSejour $contexteSejour,
        GroupeRepository $groupeRepository,
        ParticipantRepository $participantRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $sejour = $contexteSejour->actif();
        $donnees = $this->lireDonnees($request);
        $erreurs = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('ajouter_participant', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }

            $groupe = $this->trouverGroupe($donnees['groupe_id'], $sejour, $groupeRepository);
            if (!$groupe instanceof Groupe) $erreurs[] = 'Sélectionnez une unité du séjour actif.';
            if (!in_array($donnees['type'], [Participant::TYPE_JEUNE, Participant::TYPE_ADULTE], true)) $erreurs[] = 'Le type de participant est invalide.';
            foreach (['nom' => 'Le nom', 'prenom' => 'Le prénom'] as $champ => $libelle) {
                if ('' === $donnees[$champ]) $erreurs[] = $libelle.' est obligatoire.';
                elseif (mb_strlen($donnees[$champ]) > 150) $erreurs[] = $libelle.' ne peut pas dépasser 150 caractères.';
            }
            $naissance = $this->dateValide($donnees['date_naissance'], 'La date de naissance', $erreurs);
            $debut = $this->dateValide($donnees['date_debut_presence'], 'La date de début de présence', $erreurs);
            $fin = $this->dateValide($donnees['date_fin_presence'], 'La date de fin de présence', $erreurs);
            if ($debut && $fin && $fin < $debut) $erreurs[] = 'La date de fin de présence doit suivre la date de début.';
            if ($sejour && $debut && ($debut < $sejour->getDateDebut() || $debut > $sejour->getDateFin())) {
                $erreurs[] = 'La date de début de présence doit être comprise dans les dates du séjour.';
            }
            if ($sejour && $fin && ($fin < $sejour->getDateDebut() || $fin > $sejour->getDateFin())) {
                $erreurs[] = 'La date de fin de présence doit être comprise dans les dates du séjour.';
            }

            if (Participant::TYPE_JEUNE === $donnees['type']) {
                if ('' === $donnees['telephone_parent_1']) $erreurs[] = 'Le premier numéro de téléphone des parents est obligatoire.';
                elseif (!$this->telephoneValide($donnees['telephone_parent_1'])) $erreurs[] = 'Le premier numéro de téléphone des parents est invalide.';
                if ('' !== $donnees['telephone_parent_2'] && !$this->telephoneValide($donnees['telephone_parent_2'])) $erreurs[] = 'Le second numéro de téléphone des parents est invalide.';
                if (mb_strlen($donnees['email_parents']) > 254 || !filter_var($donnees['email_parents'], FILTER_VALIDATE_EMAIL)) $erreurs[] = 'L’adresse e-mail des parents est invalide.';
            } else {
                if ('' === $donnees['telephone']) $erreurs[] = 'Le numéro de téléphone de l’adulte est obligatoire.';
                elseif (!$this->telephoneValide($donnees['telephone'])) $erreurs[] = 'Le numéro de téléphone de l’adulte est invalide.';
                if (mb_strlen($donnees['email']) > 254 || !filter_var($donnees['email'], FILTER_VALIDATE_EMAIL)) $erreurs[] = 'L’adresse e-mail de l’adulte est invalide.';
                if ('' === $donnees['contact_urgence_nom_prenom']) $erreurs[] = 'Le nom et le prénom du contact d’urgence sont obligatoires.';
                elseif (mb_strlen($donnees['contact_urgence_nom_prenom']) > 300) $erreurs[] = 'Le nom et le prénom du contact d’urgence ne peuvent pas dépasser 300 caractères.';
                if ('' === $donnees['contact_urgence_telephone']) $erreurs[] = 'Le numéro de téléphone du contact d’urgence est obligatoire.';
                elseif (!$this->telephoneValide($donnees['contact_urgence_telephone'])) $erreurs[] = 'Le numéro de téléphone du contact d’urgence est invalide.';
            }

            $qualifications = array_values(array_intersect(Participant::QUALIFICATIONS, $donnees['qualifications']));
            if (in_array('Autre diplôme', $qualifications, true) && '' === $donnees['autre_diplome']) {
                $erreurs[] = 'Précisez l’autre diplôme.';
            }

            if ([] === $erreurs && $groupe && $naissance && $debut && $fin) {
                $participant = (new Participant())->setGroupe($groupe)->setType($donnees['type'])
                    ->setNom($donnees['nom'])->setPrenom($donnees['prenom'])->setDateNaissance($naissance)
                    ->setDateDebutPresence($debut)->setDateFinPresence($fin);
                if (Participant::TYPE_JEUNE === $donnees['type']) {
                    $participant->setTelephoneParent1($donnees['telephone_parent_1'])
                        ->setTelephoneParent2($this->nullable($donnees['telephone_parent_2']))
                        ->setEmailParents($donnees['email_parents']);
                } else {
                    $participant->setContactUrgenceNomPrenom($donnees['contact_urgence_nom_prenom'])
                        ->setContactUrgenceTelephone($donnees['contact_urgence_telephone'])
                        ->setTelephone($donnees['telephone'])->setEmail($donnees['email'])->setQualifications($qualifications)
                        ->setAutreDiplome($this->nullable($donnees['autre_diplome']))
                        ->setStagiaireBafa($donnees['stagiaire_bafa']);
                }
                $entityManager->persist($participant);
                $entityManager->flush();
                $this->addFlash('success', sprintf('%s %s a bien été ajouté%s.', $participant->getPrenom(), $participant->getNom(), Participant::TYPE_JEUNE === $participant->getType() ? 'e' : ''));
                return $this->redirectToRoute('app_participants');
            }
        }

        $groupes = null === $sejour ? [] : $groupeRepository->findActifsPourSejour($sejour);
        $participantsParGroupe = [];
        if ($sejour) foreach ($participantRepository->findPourSejour($sejour) as $participant) {
            $participantsParGroupe[(string) $participant->getGroupe()->getId()][$participant->getType()][] = $participant;
        }

        return $this->render('participant/index.html.twig', compact('sejour', 'groupes', 'participantsParGroupe', 'donnees', 'erreurs') + ['qualifications' => Participant::QUALIFICATIONS]);
    }

    #[Route('/administratif/participants/{id}', name: 'app_participant_modifier', methods: ['GET', 'POST'])]
    public function modifier(
        string $id,
        Request $request,
        ContexteSejour $contexteSejour,
        ParticipantRepository $participantRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $sejour = $contexteSejour->actif();
        $participant = Uuid::isValid($id) ? $participantRepository->find($id) : null;
        if (!$sejour || !$participant instanceof Participant || $participant->getGroupe()->getSejour() !== $sejour) {
            throw $this->createNotFoundException('Participant introuvable pour le séjour actif.');
        }

        $donnees = $request->isMethod('POST') ? $this->lireDonnees($request) : $this->donneesParticipant($participant);
        $donnees['type'] = $participant->getType();
        $erreurs = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('modifier_participant_'.$id, $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }
            $dates = $this->validerFiche($donnees, $sejour, $erreurs);
            if ([] === $erreurs) {
                $participant->setNom($donnees['nom'])->setPrenom($donnees['prenom'])
                    ->setDateNaissance($dates['naissance'])->setDateDebutPresence($dates['debut'])->setDateFinPresence($dates['fin']);
                if (Participant::TYPE_JEUNE === $participant->getType()) {
                    $participant->setTelephoneParent1($donnees['telephone_parent_1'])
                        ->setTelephoneParent2($this->nullable($donnees['telephone_parent_2']))
                        ->setEmailParents($donnees['email_parents']);
                } else {
                    $qualifications = array_values(array_intersect(Participant::QUALIFICATIONS, $donnees['qualifications']));
                    $participant->setTelephone($donnees['telephone'])->setEmail($donnees['email'])
                        ->setContactUrgenceNomPrenom($donnees['contact_urgence_nom_prenom'])
                        ->setContactUrgenceTelephone($donnees['contact_urgence_telephone'])
                        ->setQualifications($qualifications)->setAutreDiplome($this->nullable($donnees['autre_diplome']))
                        ->setStagiaireBafa($donnees['stagiaire_bafa']);
                }
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
        ]);
    }

    #[Route('/administratif/participants/{id}/supprimer', name: 'app_participant_supprimer', methods: ['POST'])]
    public function supprimer(
        string $id,
        Request $request,
        ContexteSejour $contexteSejour,
        ParticipantRepository $participantRepository,
        EntityManagerInterface $entityManager,
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
        $entityManager->remove($participant);
        $entityManager->flush();
        $this->addFlash('success', sprintf('La fiche de %s a bien été supprimée.', $nomComplet));

        return $this->redirectToRoute('app_participants');
    }

    /** @return array<string, mixed> */
    private function lireDonnees(Request $request): array
    {
        return [
            'groupe_id' => $request->request->getString('groupe_id'), 'type' => $request->request->getString('type'),
            'nom' => trim($request->request->getString('nom')), 'prenom' => trim($request->request->getString('prenom')),
            'date_naissance' => $request->request->getString('date_naissance'),
            'telephone' => trim($request->request->getString('telephone')),
            'email' => trim($request->request->getString('email')),
            'telephone_parent_1' => trim($request->request->getString('telephone_parent_1')),
            'telephone_parent_2' => trim($request->request->getString('telephone_parent_2')),
            'email_parents' => trim($request->request->getString('email_parents')),
            'contact_urgence_nom_prenom' => trim($request->request->getString('contact_urgence_nom_prenom')),
            'contact_urgence_telephone' => trim($request->request->getString('contact_urgence_telephone')),
            'qualifications' => array_values(array_filter($request->request->all('qualifications'), 'is_string')),
            'autre_diplome' => trim($request->request->getString('autre_diplome')),
            'stagiaire_bafa' => $request->request->getBoolean('stagiaire_bafa'),
            'date_debut_presence' => $request->request->getString('date_debut_presence'),
            'date_fin_presence' => $request->request->getString('date_fin_presence'),
        ];
    }

    private function trouverGroupe(string $id, mixed $sejour, GroupeRepository $repository): ?Groupe
    {
        if (!$sejour || !Uuid::isValid($id)) return null;
        $groupe = $repository->find($id);
        return $groupe instanceof Groupe && $groupe->isActif() && $groupe->getSejour() === $sejour ? $groupe : null;
    }

    /** @param list<string> $erreurs */
    private function dateValide(string $valeur, string $libelle, array &$erreurs): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $valeur);
        if (!$date || $date->format('Y-m-d') !== $valeur) { $erreurs[] = $libelle.' est obligatoire et doit être valide.'; return null; }
        return $date;
    }

    private function nullable(string $valeur): ?string { return '' === $valeur ? null : $valeur; }

    private function telephoneValide(string $valeur): bool
    {
        return mb_strlen($valeur) <= 30
            && 1 === preg_match('/^(?:0[1-9](?:[ .-]?\d{2}){4}|\+33[ .-]?[1-9](?:[ .-]?\d{2}){4})$/', $valeur);
    }

    /** @return array<string, mixed> */
    private function donneesParticipant(Participant $participant): array
    {
        return [
            'groupe_id' => (string) $participant->getGroupe()->getId(), 'type' => $participant->getType(),
            'nom' => $participant->getNom(), 'prenom' => $participant->getPrenom(),
            'date_naissance' => $participant->getDateNaissance()->format('Y-m-d'),
            'telephone' => $participant->getTelephone() ?? '', 'email' => $participant->getEmail() ?? '',
            'telephone_parent_1' => $participant->getTelephoneParent1() ?? '',
            'telephone_parent_2' => $participant->getTelephoneParent2() ?? '',
            'email_parents' => $participant->getEmailParents() ?? '',
            'contact_urgence_nom_prenom' => $participant->getContactUrgenceNomPrenom() ?? '',
            'contact_urgence_telephone' => $participant->getContactUrgenceTelephone() ?? '',
            'qualifications' => $participant->getQualifications(), 'autre_diplome' => $participant->getAutreDiplome() ?? '',
            'stagiaire_bafa' => $participant->isStagiaireBafa(),
            'date_debut_presence' => $participant->getDateDebutPresence()->format('Y-m-d'),
            'date_fin_presence' => $participant->getDateFinPresence()->format('Y-m-d'),
        ];
    }

    /** @param array<string, mixed> $donnees @param list<string> $erreurs @return array{naissance: ?DateTimeImmutable, debut: ?DateTimeImmutable, fin: ?DateTimeImmutable} */
    private function validerFiche(array $donnees, mixed $sejour, array &$erreurs): array
    {
        foreach (['nom' => 'Le nom', 'prenom' => 'Le prénom'] as $champ => $libelle) {
            if ('' === $donnees[$champ]) $erreurs[] = $libelle.' est obligatoire.';
            elseif (mb_strlen($donnees[$champ]) > 150) $erreurs[] = $libelle.' ne peut pas dépasser 150 caractères.';
        }
        $naissance = $this->dateValide($donnees['date_naissance'], 'La date de naissance', $erreurs);
        $debut = $this->dateValide($donnees['date_debut_presence'], 'La date de début de présence', $erreurs);
        $fin = $this->dateValide($donnees['date_fin_presence'], 'La date de fin de présence', $erreurs);
        if ($debut && $fin && $fin < $debut) $erreurs[] = 'La date de fin de présence doit suivre la date de début.';
        if ($debut && ($debut < $sejour->getDateDebut() || $debut > $sejour->getDateFin())) $erreurs[] = 'La date de début de présence doit être comprise dans les dates du séjour.';
        if ($fin && ($fin < $sejour->getDateDebut() || $fin > $sejour->getDateFin())) $erreurs[] = 'La date de fin de présence doit être comprise dans les dates du séjour.';

        if (Participant::TYPE_JEUNE === $donnees['type']) {
            if ('' === $donnees['telephone_parent_1'] || !$this->telephoneValide($donnees['telephone_parent_1'])) $erreurs[] = 'Le premier numéro de téléphone des parents est invalide.';
            if ('' !== $donnees['telephone_parent_2'] && !$this->telephoneValide($donnees['telephone_parent_2'])) $erreurs[] = 'Le second numéro de téléphone des parents est invalide.';
            if (mb_strlen($donnees['email_parents']) > 254 || !filter_var($donnees['email_parents'], FILTER_VALIDATE_EMAIL)) $erreurs[] = 'L’adresse e-mail des parents est invalide.';
        } else {
            if ('' === $donnees['telephone'] || !$this->telephoneValide($donnees['telephone'])) $erreurs[] = 'Le numéro de téléphone de l’adulte est invalide.';
            if (mb_strlen($donnees['email']) > 254 || !filter_var($donnees['email'], FILTER_VALIDATE_EMAIL)) $erreurs[] = 'L’adresse e-mail de l’adulte est invalide.';
            if ('' === $donnees['contact_urgence_nom_prenom']) $erreurs[] = 'Le nom et le prénom du contact d’urgence sont obligatoires.';
            if ('' === $donnees['contact_urgence_telephone'] || !$this->telephoneValide($donnees['contact_urgence_telephone'])) $erreurs[] = 'Le numéro de téléphone du contact d’urgence est invalide.';
            $qualifications = array_values(array_intersect(Participant::QUALIFICATIONS, $donnees['qualifications']));
            if (in_array('Autre diplôme', $qualifications, true) && '' === $donnees['autre_diplome']) $erreurs[] = 'Précisez l’autre diplôme.';
        }

        return ['naissance' => $naissance, 'debut' => $debut, 'fin' => $fin];
    }
}
