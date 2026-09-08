<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\SituationParticuliere;
use App\Entity\TacheSituationParticuliere;

final class GestionTachesSituationParticuliere
{
    private const ACCIDENT = [
        'SINISTRE_MATERIEL', 'SINISTRE_CORPOREL_MINEUR', 'DECES', 'HOSPITALISATION_PLUSIEURS_JOURS',
        'BLESSURE_GRAVE_RISQUE_INCAPACITE', 'PLUSIEURS_VICTIMES', 'INTERVENTION_FORCES_ORDRE',
        'DEPOT_PLAINTE', 'MISE_EN_PERIL_MINEURS', 'RISQUE_MEDIATIQUE',
    ];
    private const GRAVE_URGENCE = [
        'DECES', 'HOSPITALISATION_PLUSIEURS_JOURS', 'BLESSURE_GRAVE_RISQUE_INCAPACITE',
        'PLUSIEURS_VICTIMES', 'INTERVENTION_FORCES_ORDRE', 'DEPOT_PLAINTE',
        'MISE_EN_PERIL_MINEURS', 'RISQUE_MEDIATIQUE',
    ];

    public function synchroniser(SituationParticuliere $situation): void
    {
        $informations = $situation->getInformationsComplementaires();
        $requis = [];
        if ([] !== array_intersect($informations, self::ACCIDENT)) {
            $requis[TacheSituationParticuliere::TYPE_ACCIDENT] = $situation->getDateSituation()->modify('+5 days');
        }
        if ([] !== array_intersect($informations, self::GRAVE_URGENCE)) {
            $requis[TacheSituationParticuliere::TYPE_EVENEMENT_GRAVE] = $situation->getDateSituation()->modify('+2 days');
            $requis[TacheSituationParticuliere::TYPE_APPEL_URGENCE] = $situation->getDateSituation();
        }
        if (in_array('MALTRAITANCE', $informations, true)) {
            $requis[TacheSituationParticuliere::TYPE_IP_SIGNALEMENT] = null;
            $requis[TacheSituationParticuliere::TYPE_APPEL_URGENCE] ??= $situation->getDateSituation()->modify('+1 day');
        }

        $existantes = [];
        foreach ($situation->getTaches() as $tache) {
            if (null !== $tache->getTypePredefini()) {
                $existantes[$tache->getTypePredefini()] = $tache;
            }
        }
        foreach ($requis as $type => $echeance) {
            if (!isset($existantes[$type])) {
                TacheSituationParticuliere::automatique($situation, $type, $echeance);
            } elseif (TacheSituationParticuliere::ORIGINE_AUTOMATIQUE === $existantes[$type]->getOrigine()) {
                $existantes[$type]->setDateEcheance($echeance);
                if (TacheSituationParticuliere::STATUT_NON_REQUIS === $existantes[$type]->getStatut()) {
                    $existantes[$type]->setStatut(TacheSituationParticuliere::STATUT_A_FAIRE);
                }
            }
        }
        foreach ($existantes as $type => $tache) {
            if (!array_key_exists($type, $requis)
                && TacheSituationParticuliere::ORIGINE_AUTOMATIQUE === $tache->getOrigine()
                && TacheSituationParticuliere::STATUT_REALISE !== $tache->getStatut()) {
                $tache->setStatut(TacheSituationParticuliere::STATUT_NON_REQUIS);
            }
        }
    }
}
