<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Participant;
use App\Entity\Sejour;
use Symfony\Component\HttpFoundation\Request;

final class FormulaireParticipant
{
    /** @return array<string, mixed> */
    public function lire(Request $request): array
    {
        return [
            'groupe_id' => $request->request->getString('groupe_id'),
            'type' => $request->request->getString('type'),
            'nom' => trim($request->request->getString('nom')),
            'prenom' => trim($request->request->getString('prenom')),
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

    /** @return array<string, mixed> */
    public function depuisParticipant(Participant $participant): array
    {
        return [
            'groupe_id' => (string) $participant->getGroupe()->getId(),
            'type' => $participant->getType(),
            'nom' => $participant->getNom(),
            'prenom' => $participant->getPrenom(),
            'date_naissance' => $participant->getDateNaissance()->format('Y-m-d'),
            'telephone' => $participant->getTelephone() ?? '',
            'email' => $participant->getEmail() ?? '',
            'telephone_parent_1' => $participant->getTelephoneParent1() ?? '',
            'telephone_parent_2' => $participant->getTelephoneParent2() ?? '',
            'email_parents' => $participant->getEmailParents() ?? '',
            'contact_urgence_nom_prenom' => $participant->getContactUrgenceNomPrenom() ?? '',
            'contact_urgence_telephone' => $participant->getContactUrgenceTelephone() ?? '',
            'qualifications' => $participant->getQualifications(),
            'autre_diplome' => $participant->getAutreDiplome() ?? '',
            'stagiaire_bafa' => $participant->isStagiaireBafa(),
            'date_debut_presence' => $participant->getDateDebutPresence()->format('Y-m-d'),
            'date_fin_presence' => $participant->getDateFinPresence()->format('Y-m-d'),
        ];
    }

    /**
     * @param array<string, mixed> $donnees
     *
     * @return array{erreurs: list<string>, naissance: ?\DateTimeImmutable, debut: ?\DateTimeImmutable, fin: ?\DateTimeImmutable, qualifications: list<string>}
     */
    public function valider(array $donnees, Sejour $sejour): array
    {
        $erreurs = [];
        if (!in_array($donnees['type'], [Participant::TYPE_JEUNE, Participant::TYPE_ADULTE], true)) {
            $erreurs[] = 'Le type de participant est invalide.';
        }
        foreach (['nom' => 'Le nom', 'prenom' => 'Le prénom'] as $champ => $libelle) {
            if ('' === $donnees[$champ]) {
                $erreurs[] = $libelle.' est obligatoire.';
            } elseif (mb_strlen($donnees[$champ]) > 150) {
                $erreurs[] = $libelle.' ne peut pas dépasser 150 caractères.';
            }
        }

        $naissance = $this->dateValide($donnees['date_naissance'], 'La date de naissance', $erreurs);
        $debut = $this->dateValide($donnees['date_debut_presence'], 'La date de début de présence', $erreurs);
        $fin = $this->dateValide($donnees['date_fin_presence'], 'La date de fin de présence', $erreurs);
        if ($debut && $fin && $fin < $debut) {
            $erreurs[] = 'La date de fin de présence doit suivre la date de début.';
        }
        if ($debut && ($debut < $sejour->getDateDebut() || $debut > $sejour->getDateFin())) {
            $erreurs[] = 'La date de début de présence doit être comprise dans les dates du séjour.';
        }
        if ($fin && ($fin < $sejour->getDateDebut() || $fin > $sejour->getDateFin())) {
            $erreurs[] = 'La date de fin de présence doit être comprise dans les dates du séjour.';
        }

        $qualifications = array_values(array_intersect(Participant::QUALIFICATIONS, $donnees['qualifications']));
        if (Participant::TYPE_JEUNE === $donnees['type']) {
            if ('' === $donnees['telephone_parent_1'] || !$this->telephoneValide($donnees['telephone_parent_1'])) {
                $erreurs[] = 'Le premier numéro de téléphone des parents est invalide.';
            }
            if ('' !== $donnees['telephone_parent_2'] && !$this->telephoneValide($donnees['telephone_parent_2'])) {
                $erreurs[] = 'Le second numéro de téléphone des parents est invalide.';
            }
            if (mb_strlen($donnees['email_parents']) > 254 || !filter_var($donnees['email_parents'], FILTER_VALIDATE_EMAIL)) {
                $erreurs[] = 'L’adresse e-mail des parents est invalide.';
            }
        } elseif (Participant::TYPE_ADULTE === $donnees['type']) {
            if ('' === $donnees['telephone'] || !$this->telephoneValide($donnees['telephone'])) {
                $erreurs[] = 'Le numéro de téléphone de l’adulte est invalide.';
            }
            if (mb_strlen($donnees['email']) > 254 || !filter_var($donnees['email'], FILTER_VALIDATE_EMAIL)) {
                $erreurs[] = 'L’adresse e-mail de l’adulte est invalide.';
            }
            if ('' === $donnees['contact_urgence_nom_prenom']) {
                $erreurs[] = 'Le nom et le prénom du contact d’urgence sont obligatoires.';
            } elseif (mb_strlen($donnees['contact_urgence_nom_prenom']) > 300) {
                $erreurs[] = 'Le nom et le prénom du contact d’urgence ne peuvent pas dépasser 300 caractères.';
            }
            if ('' === $donnees['contact_urgence_telephone'] || !$this->telephoneValide($donnees['contact_urgence_telephone'])) {
                $erreurs[] = 'Le numéro de téléphone du contact d’urgence est invalide.';
            }
            if (in_array('Autre diplôme', $qualifications, true) && '' === $donnees['autre_diplome']) {
                $erreurs[] = 'Précisez l’autre diplôme.';
            }
        }

        return compact('erreurs', 'naissance', 'debut', 'fin', 'qualifications');
    }

    /**
     * @param array<string, mixed>                                                                                                                             $donnees
     * @param array{erreurs: list<string>, naissance: ?\DateTimeImmutable, debut: ?\DateTimeImmutable, fin: ?\DateTimeImmutable, qualifications: list<string>} $validation
     */
    public function appliquer(Participant $participant, array $donnees, array $validation): void
    {
        if (!$validation['naissance'] instanceof \DateTimeImmutable
            || !$validation['debut'] instanceof \DateTimeImmutable
            || !$validation['fin'] instanceof \DateTimeImmutable) {
            throw new \LogicException('Les dates du participant doivent être validées avant leur application.');
        }
        $participant->setNom($donnees['nom'])->setPrenom($donnees['prenom'])
            ->setDateNaissance($validation['naissance'])->setDateDebutPresence($validation['debut'])->setDateFinPresence($validation['fin']);
        if (Participant::TYPE_JEUNE === $participant->getType()) {
            $participant->setTelephoneParent1($donnees['telephone_parent_1'])
                ->setTelephoneParent2($this->nullable($donnees['telephone_parent_2']))
                ->setEmailParents($donnees['email_parents']);

            return;
        }
        $participant->setTelephone($donnees['telephone'])->setEmail($donnees['email'])
            ->setContactUrgenceNomPrenom($donnees['contact_urgence_nom_prenom'])
            ->setContactUrgenceTelephone($donnees['contact_urgence_telephone'])
            ->setQualifications($validation['qualifications'])
            ->setAutreDiplome($this->nullable($donnees['autre_diplome']))
            ->setStagiaireBafa($donnees['stagiaire_bafa']);
    }

    /** @param list<string> $erreurs */
    private function dateValide(string $valeur, string $libelle, array &$erreurs): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $valeur);
        if (!$date || $date->format('Y-m-d') !== $valeur) {
            $erreurs[] = $libelle.' est obligatoire et doit être valide.';

            return null;
        }

        return $date;
    }

    private function telephoneValide(string $valeur): bool
    {
        return mb_strlen($valeur) <= 30
            && 1 === preg_match('/^(?:0[1-9](?:[ .-]?\d{2}){4}|\+33[ .-]?[1-9](?:[ .-]?\d{2}){4})$/', $valeur);
    }

    private function nullable(string $valeur): ?string
    {
        return '' === $valeur ? null : $valeur;
    }
}
