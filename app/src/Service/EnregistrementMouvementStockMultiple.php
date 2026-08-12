<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Denree;
use App\Entity\MouvementStock;
use App\Entity\MouvementStockLigne;
use App\Entity\MouvementStockLigneConditionnement;
use App\Entity\Sejour;
use App\Entity\Utilisateur;
use App\Repository\MouvementStockLigneConditionnementRepository;
use App\Repository\MouvementStockLigneRepository;
use App\Repository\TypeMouvementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;

final class EnregistrementMouvementStockMultiple
{
    private const ORIGINES_PAR_TYPE = [
        'ENTREE' => ['INVENTAIRE', 'FOURNISSEUR', 'RETOUR_ALIMENTAIRE', 'CORRECTION'],
        'SORTIE' => ['INVENTAIRE', 'DISTRIBUTION', 'POUBELLE', 'DONATION', 'CORRECTION'],
    ];

    public function __construct(
        private readonly TypeMouvementRepository $types,
        private readonly ConversionConditionnement $conversion,
        private readonly EntityManagerInterface $entityManager,
        private readonly MouvementStockLigneRepository $lignes,
        private readonly MouvementStockLigneConditionnementRepository $details,
        private readonly AuditMouvementStock $audit,
    ) {
    }

    /**
     * @param list<object> $denrees
     * @param list<object> $origines
     * @param list<object> $groupes
     * @param list<object> $fournisseurs
     * @param array<string, list<object>> $referencesParDenree
     * @param array<string, list<object>> $conditionnementsParReference
     * @param array<string, list<object>> $conditionnementsSortieParDenree
     * @return array{erreurs: list<string>, nombre: int}
     */
    public function enregistrer(
        Request $request,
        Sejour $sejour,
        Utilisateur $utilisateur,
        array $denrees,
        array $origines,
        array $groupes,
        array $fournisseurs,
        array $referencesParDenree,
        array $conditionnementsParReference,
        array $conditionnementsSortieParDenree,
        ?MouvementStock $mouvementExistant,
        string $motifAudit,
    ): array {
        $erreurs = [];
        if (null !== $mouvementExistant && ('' === $motifAudit || mb_strlen($motifAudit) > 1000)) {
            $erreurs[] = 'Le motif de modification est obligatoire et limité à 1 000 caractères.';
        }
        $typeCode = in_array($request->request->getString('type'), ['ENTREE', 'SORTIE'], true) ? $request->request->getString('type') : '';
        $type = '' !== $typeCode ? $this->types->findOneBy(['code' => $typeCode, 'actif' => true]) : null;
        $origine = $this->selectionner($request->request->getString('origine'), $origines);
        $groupe = null;
        $fournisseur = null;
        if (null === $type) $erreurs[] = 'Sélectionnez un type de mouvement valide.';
        if (null === $origine) $erreurs[] = 'Sélectionnez une origine valide.';
        elseif (!in_array($origine->getCode(), self::ORIGINES_PAR_TYPE[$typeCode] ?? [], true)) {
            $erreurs[] = 'Sélectionnez une origine compatible avec le type de mouvement.';
            $origine = null;
        }
        if (null !== $origine && 'DISTRIBUTION' === $origine->getCode()) {
            $groupe = $this->selectionner($request->request->getString('groupe'), $groupes);
            if (null === $groupe) $erreurs[] = 'Sélectionnez le groupe destinataire de la distribution.';
        }

        $lignesSaisies = $request->request->all('lignes');
        if ([] === $lignesSaisies) $erreurs[] = 'Ajoutez au moins une denrée au mouvement.';
        $lignesValides = [];
        $denreesVues = [];
        $entreeFournisseur = 'ENTREE' === $typeCode && null !== $origine && 'FOURNISSEUR' === $origine->getCode();
        $mouvementInventaire = null !== $origine && 'INVENTAIRE' === $origine->getCode();
        $mouvementConditionne = $entreeFournisseur || $mouvementInventaire;
        if ($entreeFournisseur) {
            $fournisseur = $this->selectionner($request->request->getString('fournisseur'), $fournisseurs);
            if (null === $fournisseur) $erreurs[] = 'Sélectionnez un fournisseur valide.';
        }
        foreach (array_values($lignesSaisies) as $index => $saisie) {
            if (!is_array($saisie)) continue;
            $numero = $index + 1;
            $denree = $this->selectionner((string) ($saisie['denree'] ?? ''), $denrees);
            if (null === $denree) {
                $erreurs[] = sprintf('Ligne %d : sélectionnez une denrée valide.', $numero);
                continue;
            }
            $denreeId = (string) $denree->getId();
            if (isset($denreesVues[$denreeId])) {
                $erreurs[] = sprintf('Ligne %d : la denrée « %s » est déjà présente.', $numero, $denree->getNom());
                continue;
            }
            $denreesVues[$denreeId] = true;
            $reference = null;
            $conditionnementSortie = null;
            $quantitesConditionnements = [];
            $quantiteReference = null;
            $numeroLot = $entreeFournisseur ? $this->normaliserNumeroLot((string) ($saisie['numero_lot'] ?? '')) : null;

            if (in_array($typeCode, ['ENTREE', 'SORTIE'], true) && !$mouvementConditionne) {
                $conditionnementSortie = $this->selectionner((string) ($saisie['conditionnement_sortie'] ?? ''), $conditionnementsSortieParDenree[$denreeId] ?? []);
                $quantite = $this->normaliserQuantite((string) ($saisie['quantite'] ?? ''));
                if (null === $conditionnementSortie) $erreurs[] = sprintf('Ligne %d : sélectionnez un conditionnement valide.', $numero);
                if (null === $quantite) $erreurs[] = sprintf('Ligne %d : saisissez une quantité strictement positive.', $numero);
                if (null !== $conditionnementSortie && null !== $quantite) {
                    $quantiteReference = number_format($this->conversion->versUniteReference($denree, $conditionnementSortie, (float) $quantite), 3, '.', '');
                }
            } elseif ($mouvementConditionne) {
                if ($mouvementInventaire) {
                    $reference = $this->selectionner((string) ($saisie['reference'] ?? ''), $referencesParDenree[$denreeId] ?? []);
                } else {
                    foreach ($referencesParDenree[$denreeId] ?? [] as $referenceDenree) {
                        if ($referenceDenree->getFournisseur() === $fournisseur) { $reference = $referenceDenree; break; }
                    }
                }
                if (null === $reference) {
                    $erreurs[] = $mouvementInventaire
                        ? sprintf('Ligne %d : sélectionnez un fournisseur pour « %s ».', $numero, $denree->getNom())
                        : sprintf('Ligne %d : « %s » n’est pas proposée par le fournisseur sélectionné.', $numero, $denree->getNom());
                } else {
                    $conditionnementsReference = $conditionnementsParReference[(string) $reference->getId()] ?? [];
                    $facteur = 1.0;
                    $facteurs = [];
                    for ($i = count($conditionnementsReference) - 1; $i >= 0; --$i) {
                        $facteur *= (float) $conditionnementsReference[$i]->getQuantiteContenu();
                        $facteurs[(string) $conditionnementsReference[$i]->getId()] = $facteur;
                    }
                    $total = 0.0;
                    foreach ($conditionnementsReference as $conditionnement) {
                        $id = (string) $conditionnement->getId();
                        $brut = trim((string) ($saisie['conditionnements'][$id] ?? ''));
                        if ('' === $brut) continue;
                        $quantite = $this->normaliserQuantite($brut, true);
                        if (null === $quantite) {
                            $erreurs[] = sprintf('Ligne %d : la quantité de « %s » doit être positive ou nulle.', $numero, $conditionnement->getLibelle());
                        } elseif ((float) $quantite > 0) {
                            $quantitesConditionnements[$id] = $quantite;
                            $total += (float) $quantite * $facteurs[$id];
                        }
                    }
                    if ([] === $quantitesConditionnements) $erreurs[] = sprintf('Ligne %d : saisissez au moins une quantité de conditionnement.', $numero);
                    else $quantiteReference = number_format($total, 3, '.', '');
                }
            }
            if (null !== $quantiteReference) {
                $lignesValides[] = compact('denree', 'reference', 'conditionnementSortie', 'quantitesConditionnements', 'quantiteReference', 'numeroLot');
            }
        }
        if ([] !== $erreurs || null === $type || null === $origine) return ['erreurs' => $erreurs, 'nombre' => 0];

        $avant = null === $mouvementExistant ? null : $this->audit->instantane($mouvementExistant);
        $this->entityManager->wrapInTransaction(function () use ($sejour, $utilisateur, $type, $origine, $groupe, $request, $lignesValides, $conditionnementsParReference, $mouvementExistant, $avant, $motifAudit): void {
            $mouvement = $mouvementExistant ?? new MouvementStock($sejour, $utilisateur, $type, $origine);
            $mouvement->setTypeMouvement($type)->setOrigineMouvement($origine)->setGroupe($groupe);
            if (null === $mouvementExistant) {
                $mouvement->setDateMouvement($this->dateNavigateur($request->request->getString('date_navigateur')) ?? new \DateTimeImmutable());
            } else {
                foreach ($this->lignes->findToutesPourMouvement($mouvementExistant) as $ancienneLigne) {
                    foreach ($this->details->findPourLigne($ancienneLigne) as $ancienDetail) $this->entityManager->remove($ancienDetail);
                    $this->entityManager->remove($ancienneLigne);
                }
                $this->entityManager->flush();
            }
            $this->entityManager->persist($mouvement);
            foreach ($lignesValides as $donnees) {
                $ligne = new MouvementStockLigne($mouvement, $donnees['denree'], $donnees['quantiteReference']);
                $quantiteInventaire = null !== $donnees['reference']
                    ? $this->conversion->quantiteEntreeInventaire($donnees['denree'], (float) $donnees['quantiteReference'], $conditionnementsParReference[(string) $donnees['reference']->getId()] ?? [], $donnees['quantitesConditionnements'])
                    : $this->conversion->quantiteInventaireExacte($donnees['denree'], (float) $donnees['quantiteReference']);
                $ligne->setQuantiteUniteInventaire($this->conversion->formaterQuantiteInventaire($quantiteInventaire));
                $ligne->setReferenceFournisseur($donnees['reference']);
                $ligne->setConditionnementSortie(null === $donnees['reference'] ? $donnees['conditionnementSortie'] : null);
                $ligne->setNumeroLot($donnees['numeroLot']);
                $this->entityManager->persist($ligne);
                if (null !== $donnees['reference']) foreach ($conditionnementsParReference[(string) $donnees['reference']->getId()] ?? [] as $conditionnement) {
                    $id = (string) $conditionnement->getId();
                    if (isset($donnees['quantitesConditionnements'][$id])) {
                        $this->entityManager->persist(new MouvementStockLigneConditionnement($ligne, $conditionnement, $donnees['quantitesConditionnements'][$id]));
                    }
                }
            }
            if (null !== $avant) {
                $this->entityManager->flush();
                $this->audit->enregistrer($mouvement, $sejour, $utilisateur, AuditMouvementStock::MODIFICATION, $motifAudit, $avant, $this->audit->instantane($mouvement));
            }
        });
        return ['erreurs' => [], 'nombre' => count($lignesValides)];
    }

    /**
     * @param array<string, mixed> $valeurs
     * @param list<object> $denrees
     * @param list<object> $origines
     * @param list<object> $groupes
     * @param array<string, list<object>> $referencesParDenree
     * @param array<string, list<object>> $conditionnementsParReference
     * @param array<string, list<object>> $conditionnementsSortieParDenree
     * @return array{erreurs: list<string>, denree: Denree|null}
     */
    public function enregistrerSimple(
        Request $request,
        Sejour $sejour,
        Utilisateur $utilisateur,
        array $valeurs,
        array $denrees,
        array $origines,
        array $groupes,
        array $referencesParDenree,
        array $conditionnementsParReference,
        array $conditionnementsSortieParDenree,
        ?MouvementStock $mouvementExistant,
        ?MouvementStockLigne $ligneExistante,
        string $motifAudit,
    ): array {
        $erreurs = [];
        if (null !== $mouvementExistant && ('' === $motifAudit || mb_strlen($motifAudit) > 1000)) {
            $erreurs[] = 'Le motif de modification est obligatoire et limité à 1 000 caractères.';
        }

        $typeCode = in_array($valeurs['type'], ['ENTREE', 'SORTIE'], true) ? $valeurs['type'] : '';
        $type = '' !== $typeCode ? $this->types->findOneBy(['code' => $typeCode, 'actif' => true]) : null;
        $denree = $this->selectionner((string) $valeurs['denree'], $denrees);
        $origine = $this->selectionner((string) $valeurs['origine'], $origines);
        if (null === $type) $erreurs[] = 'Sélectionnez un type de mouvement valide.';
        if (!$denree instanceof Denree) $erreurs[] = 'Sélectionnez une denrée valide.';
        if (null === $origine) $erreurs[] = 'Sélectionnez une origine valide.';
        elseif (!in_array($origine->getCode(), self::ORIGINES_PAR_TYPE[$typeCode] ?? [], true)) {
            $erreurs[] = 'Sélectionnez une origine compatible avec le type de mouvement.';
            $origine = null;
        }

        $groupe = null;
        $reference = null;
        $conditionnementSortie = null;
        $quantiteReference = null;
        $quantitesConditionnements = [];
        $entreeFournisseur = 'ENTREE' === $typeCode && null !== $origine && 'FOURNISSEUR' === $origine->getCode();

        if (in_array($typeCode, ['ENTREE', 'SORTIE'], true) && !$entreeFournisseur) {
            $conditionnementSortie = !$denree instanceof Denree ? null : $this->selectionner((string) $valeurs['conditionnement_sortie'], $conditionnementsSortieParDenree[(string) $denree->getId()] ?? []);
            if (null === $conditionnementSortie) $erreurs[] = 'Sélectionnez un conditionnement valide.';
            $quantiteSaisie = $this->normaliserQuantite((string) $valeurs['quantite']);
            if (null === $quantiteSaisie) {
                $erreurs[] = 'Saisissez une quantité strictement positive.';
            } elseif ($denree instanceof Denree && null !== $conditionnementSortie) {
                $quantiteReference = number_format($this->conversion->versUniteReference($denree, $conditionnementSortie, (float) $quantiteSaisie), 3, '.', '');
            }
            if (null !== $origine && 'DISTRIBUTION' === $origine->getCode()) {
                $groupe = $this->selectionner((string) $valeurs['groupe'], $groupes);
                if (null === $groupe) $erreurs[] = 'Sélectionnez le groupe destinataire de la distribution.';
            }
        } elseif ($entreeFournisseur && $denree instanceof Denree) {
            $reference = $this->selectionner((string) $valeurs['reference'], $referencesParDenree[(string) $denree->getId()] ?? []);
            if (null === $reference) {
                $erreurs[] = 'Sélectionnez un fournisseur associé à cette denrée.';
            } else {
                $conditionnementsReference = $conditionnementsParReference[(string) $reference->getId()] ?? [];
                $facteur = 1.0;
                $facteurs = [];
                for ($i = count($conditionnementsReference) - 1; $i >= 0; --$i) {
                    $facteur *= (float) $conditionnementsReference[$i]->getQuantiteContenu();
                    $facteurs[(string) $conditionnementsReference[$i]->getId()] = $facteur;
                }
                $total = 0.0;
                foreach ($conditionnementsReference as $conditionnement) {
                    $id = (string) $conditionnement->getId();
                    $brut = trim((string) ($valeurs['conditionnements'][$id] ?? ''));
                    if ('' === $brut) continue;
                    $quantite = $this->normaliserQuantite($brut, true);
                    if (null === $quantite) {
                        $erreurs[] = sprintf('La quantité de « %s » doit être positive ou nulle.', $conditionnement->getLibelle());
                    } elseif ((float) $quantite > 0) {
                        $quantitesConditionnements[$id] = $quantite;
                        $total += (float) $quantite * $facteurs[$id];
                    }
                }
                if ([] === $quantitesConditionnements) $erreurs[] = 'Saisissez au moins une quantité de conditionnement.';
                else $quantiteReference = number_format($total, 3, '.', '');
            }
        }

        if ([] !== $erreurs || null === $type || !$denree instanceof Denree || null === $origine || null === $quantiteReference) {
            return ['erreurs' => $erreurs, 'denree' => $denree instanceof Denree ? $denree : null];
        }

        $avant = null === $mouvementExistant ? null : $this->audit->instantane($mouvementExistant);
        $this->entityManager->wrapInTransaction(function () use ($sejour, $utilisateur, $type, $origine, $groupe, $request, $ligneExistante, $denree, $quantiteReference, $reference, $entreeFournisseur, $conditionnementSortie, $conditionnementsParReference, $quantitesConditionnements, $valeurs, $mouvementExistant, $avant, $motifAudit): void {
            $mouvement = $mouvementExistant ?? new MouvementStock($sejour, $utilisateur, $type, $origine);
            $mouvement->setTypeMouvement($type)->setOrigineMouvement($origine)->setGroupe($groupe);
            if (null === $mouvementExistant) {
                $mouvement->setDateMouvement($this->dateNavigateur($request->request->getString('date_navigateur')) ?? new \DateTimeImmutable());
            }
            if (null !== $ligneExistante) {
                foreach ($this->details->findPourLigne($ligneExistante) as $ancienConditionnement) $this->entityManager->remove($ancienConditionnement);
                $this->entityManager->remove($ligneExistante);
                $this->entityManager->flush();
            }

            $ligne = new MouvementStockLigne($mouvement, $denree, $quantiteReference);
            $quantiteInventaire = null !== $reference
                ? $this->conversion->quantiteEntreeInventaire($denree, (float) $quantiteReference, $conditionnementsParReference[(string) $reference->getId()] ?? [], $quantitesConditionnements)
                : $this->conversion->quantiteInventaireExacte($denree, (float) $quantiteReference);
            $ligne->setQuantiteUniteInventaire($this->conversion->formaterQuantiteInventaire($quantiteInventaire));
            $ligne->setReferenceFournisseur($reference);
            $ligne->setConditionnementSortie(null === $reference ? $conditionnementSortie : null);
            $ligne->setNumeroLot($entreeFournisseur ? $this->normaliserNumeroLot((string) $valeurs['numero_lot']) : null);
            $this->entityManager->persist($mouvement);
            $this->entityManager->persist($ligne);
            if (null !== $reference) foreach ($conditionnementsParReference[(string) $reference->getId()] ?? [] as $conditionnement) {
                $id = (string) $conditionnement->getId();
                if (isset($quantitesConditionnements[$id])) {
                    $this->entityManager->persist(new MouvementStockLigneConditionnement($ligne, $conditionnement, $quantitesConditionnements[$id]));
                }
            }
            if (null !== $avant) {
                $this->entityManager->flush();
                $this->audit->enregistrer($mouvement, $sejour, $utilisateur, AuditMouvementStock::MODIFICATION, $motifAudit, $avant, $this->audit->instantane($mouvement));
            }
        });

        return ['erreurs' => [], 'denree' => $denree];
    }

    private function normaliserQuantite(string $brut, bool $zeroAutorise = false): ?string
    {
        $brut = str_replace([' ', ','], ['', '.'], trim($brut));
        if ('' === $brut || !is_numeric($brut) || ($zeroAutorise ? (float) $brut < 0 : (float) $brut <= 0)) return null;
        return number_format((float) $brut, 3, '.', '');
    }

    private function normaliserNumeroLot(string $brut): ?string
    {
        $lot = preg_replace('/\s+/u', ' ', trim($brut));
        return null === $lot || '' === $lot ? null : mb_substr($lot, 0, 100);
    }

    private function dateNavigateur(string $iso): ?\DateTimeImmutable
    {
        if ('' === trim($iso)) return null;
        try { return new \DateTimeImmutable($iso); } catch (\Exception) { return null; }
    }

    /**
     * @template T of object
     * @param list<T> $entites
     * @return T|null
     */
    private function selectionner(string $id, array $entites): ?object
    {
        if (!Uuid::isValid($id)) return null;
        foreach ($entites as $entite) if ((string) $entite->getId() === $id) return $entite;
        return null;
    }
}
