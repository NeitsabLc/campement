<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\MouvementStock;
use App\Entity\MouvementStockLigne;
use App\Entity\MouvementStockLigneConditionnement;
use App\Entity\Sejour;
use App\Entity\Utilisateur;
use App\Repository\DenreeRepository;
use App\Repository\GroupeRepository;
use App\Repository\MouvementStockLigneConditionnementRepository;
use App\Repository\MouvementStockLigneRepository;
use App\Repository\MouvementStockRepository;
use App\Repository\OrigineMouvementRepository;
use App\Repository\ReferenceFournisseurConditionnementRepository;
use App\Repository\ReferenceFournisseurRepository;
use App\Service\ContexteSejour;
use App\Service\ConversionConditionnement;
use App\Repository\TypeMouvementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[IsGranted(Utilisateur::ROLE_GESTIONNAIRE)]
final class MouvementStockController extends AbstractController
{
    private const ORIGINES_PAR_TYPE = [
        'ENTREE' => ['INVENTAIRE', 'FOURNISSEUR', 'RETOUR_ALIMENTAIRE', 'CORRECTION'],
        'SORTIE' => ['INVENTAIRE', 'DISTRIBUTION', 'POUBELLE', 'DONATION', 'CORRECTION'],
    ];

    #[Route('/stocks', name: 'app_mouvements_stock', methods: ['GET'])]
    public function liste(ContexteSejour $sejours, MouvementStockLigneRepository $lignes): Response
    {
        $sejour = $sejours->actif();
        $lignesMouvements = null === $sejour ? [] : $lignes->findPourGestion($sejour);
        $mouvements = [];
        foreach ($lignesMouvements as $ligne) {
            $mouvementId = (string) $ligne->getMouvementStock()->getId();
            if (!isset($mouvements[$mouvementId])) {
                $mouvements[$mouvementId] = [
                    'mouvement' => $ligne->getMouvementStock(),
                    'lignes' => [],
                ];
            }
            $mouvements[$mouvementId]['lignes'][] = $ligne;
        }
        foreach ($mouvements as &$donneesMouvement) {
            $mouvement = $donneesMouvement['mouvement'];
            if (null !== $mouvement->getGroupe()) {
                $donneesMouvement['intervenant'] = $mouvement->getGroupe()->getNom();
                continue;
            }
            $fournisseurs = [];
            foreach ($donneesMouvement['lignes'] as $ligne) {
                $fournisseur = $ligne->getReferenceFournisseur()?->getFournisseur()->getNom();
                if (null !== $fournisseur) $fournisseurs[$fournisseur] = true;
            }
            $donneesMouvement['intervenant'] = match (count($fournisseurs)) {
                0 => '—',
                1 => array_key_first($fournisseurs),
                default => sprintf('%d fournisseurs', count($fournisseurs)),
            };
        }
        unset($donneesMouvement);

        return $this->render('mouvement_stock/liste.html.twig', [
            'sejour' => $sejour,
            'mouvements' => array_values($mouvements),
        ]);
    }

    #[Route('/stocks/mouvement/{id}/supprimer', name: 'app_mouvement_stock_supprimer', methods: ['POST'])]
    public function supprimer(
        string $id,
        Request $request,
        ContexteSejour $sejours,
        MouvementStockRepository $mouvements,
        EntityManagerInterface $em,
    ): Response {
        $sejour = $sejours->actif();
        $mouvement = Uuid::isValid($id) ? $mouvements->findPourFormulaire($id) : null;
        if (null === $sejour || null === $mouvement || $mouvement->getSejour() !== $sejour) {
            throw $this->createNotFoundException('Mouvement de stock introuvable.');
        }
        if (!$this->isCsrfTokenValid('supprimer_mouvement_stock_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $em->remove($mouvement);
        $em->flush();
        $this->addFlash('success', 'Le mouvement de stock a bien été supprimé.');

        return $this->redirectToRoute('app_mouvements_stock');
    }

    #[Route('/stocks/mouvement', name: 'app_mouvement_stock', methods: ['GET', 'POST'])]
    #[Route('/stocks/mouvement/{id}', name: 'app_mouvement_stock_modifier', methods: ['GET', 'POST'])]
    public function formulaire(
        Request $request,
        ContexteSejour $sejours,
        DenreeRepository $denrees,
        GroupeRepository $groupes,
        OrigineMouvementRepository $origines,
        TypeMouvementRepository $types,
        ReferenceFournisseurRepository $references,
        ReferenceFournisseurConditionnementRepository $conditionnements,
        MouvementStockRepository $mouvements,
        MouvementStockLigneRepository $lignes,
        MouvementStockLigneConditionnementRepository $lignesConditionnements,
        ConversionConditionnement $conversion,
        EntityManagerInterface $em,
        ?string $id = null,
    ): Response {
        $sejour = $sejours->actif();
        $mouvementExistant = null !== $id && Uuid::isValid($id) ? $mouvements->findPourFormulaire($id) : null;
        if (null !== $id && (null === $sejour || null === $mouvementExistant || $mouvementExistant->getSejour() !== $sejour)) {
            throw $this->createNotFoundException('Mouvement de stock introuvable.');
        }
        $ligneDemandee = $request->query->getString('ligne');
        $ligneExistante = null;
        $lignesMouvement = [];
        if (null !== $mouvementExistant) {
            $lignesMouvement = $lignes->findToutesPourMouvement($mouvementExistant);
            if (Uuid::isValid($ligneDemandee)) {
                foreach ($lignesMouvement as $ligneMouvement) {
                    if ((string) $ligneMouvement->getId() === $ligneDemandee) {
                        $ligneExistante = $ligneMouvement;
                        break;
                    }
                }
            } else {
                $ligneExistante = $lignesMouvement[0] ?? null;
            }
            if (Uuid::isValid($ligneDemandee) && null === $ligneExistante) {
                throw $this->createNotFoundException('La ligne ne correspond pas au mouvement demandé.');
            }
        }
        if (null !== $mouvementExistant && null === $ligneExistante) {
            throw $this->createNotFoundException('Le mouvement ne contient aucune ligne modifiable.');
        }
        $denreesActives = null === $sejour ? [] : $denrees->findActifsPourSejour($sejour);
        $originesActives = $origines->findActifs();
        $groupesActifs = null === $sejour ? [] : $groupes->findActifsPourSejour($sejour);
        $referencesParDenree = [];
        $fournisseursParId = [];
        $conditionnementsParReference = [];

        foreach ($references->findActifsPourDenrees($denreesActives) as $reference) {
            $referencesParDenree[(string) $reference->getDenree()->getId()][] = $reference;
            $fournisseursParId[(string) $reference->getFournisseur()->getId()] = $reference->getFournisseur();
            $conditionnementsParReference[(string) $reference->getId()] = [];
        }
        $niveauxActifs = $conditionnements->findActifsPourDenrees($denreesActives);
        foreach ($niveauxActifs as $niveau) {
            $conditionnementsParReference[(string) $niveau->getReferenceFournisseur()->getId()][] = $niveau;
        }
        $conditionnementsSortieParDenree = $conversion->conditionnementsPourDenrees($denreesActives, $niveauxActifs);
        $catalogueMouvement = ['denrees' => [], 'sorties' => [], 'references' => []];
        foreach ($denreesActives as $denree) {
            $denreeId = (string) $denree->getId();
            $fournisseurs = [];
            foreach ($referencesParDenree[$denreeId] ?? [] as $reference) {
                $fournisseur = $reference->getFournisseur();
                $fournisseurs[(string) $fournisseur->getId()] = true;
                $referenceId = (string) $reference->getId();
                $catalogueMouvement['references'][] = [
                    'id' => $referenceId,
                    'denree' => $denreeId,
                    'fournisseur' => (string) $fournisseur->getId(),
                    'nom' => $fournisseur->getNom(),
                    'conditionnements' => array_map(static function ($conditionnement): array {
                        $quantite = number_format((float) $conditionnement->getQuantiteContenu(), 3, ',', ' ');
                        $quantite = str_replace(',000', '', $quantite);

                        return [
                            'id' => (string) $conditionnement->getId(),
                            'libelle' => $conditionnement->getLibelle(),
                            'description' => sprintf(
                                '1 %s contient %s %s',
                                $conditionnement->getLibelle(),
                                $quantite,
                                $conditionnement->getLibelleContenu() ?: ($conditionnement->getUniteContenu()?->getSymbole() ?? 'unité(s)'),
                            ),
                        ];
                    }, $conditionnementsParReference[$referenceId] ?? []),
                ];
            }
            $catalogueMouvement['denrees'][] = [
                'id' => $denreeId,
                'nom' => $denree->getNom(),
                'fournisseurs' => array_keys($fournisseurs),
            ];
            $catalogueMouvement['sorties'][$denreeId] = array_map(static fn ($unite): array => [
                'id' => (string) $unite->getId(),
                'nom' => $unite->getNom(),
                'symbole' => $unite->getSymbole(),
            ], $conditionnementsSortieParDenree[$denreeId] ?? []);
        }
        $fournisseursActifs = array_values($fournisseursParId);
        $detailsParLigne = [];
        foreach ($lignesConditionnements->findPourLignes($lignesMouvement) as $detail) {
            $detailsParLigne[(string) $detail->getMouvementStockLigne()->getId()][] = $detail;
        }

        $valeurs = null !== $ligneExistante && !$request->isMethod('POST') ? [
            'type' => $mouvementExistant->getTypeMouvement()->getCode(),
            'origine' => (string) $mouvementExistant->getOrigineMouvement()->getId(),
            'groupe' => (string) ($mouvementExistant->getGroupe()?->getId() ?? ''),
            'fournisseur' => (string) ($ligneExistante->getReferenceFournisseur()?->getFournisseur()->getId() ?? ''),
        ] : [
            'type' => $request->request->getString('type', 'SORTIE'),
            'denree' => $request->request->getString('denree'),
            'origine' => $request->request->getString('origine'),
            'groupe' => $request->request->getString('groupe'),
            'fournisseur' => $request->request->getString('fournisseur'),
            'reference' => $request->request->getString('reference'),
            'conditionnement_sortie' => $request->request->getString('conditionnement_sortie'),
            'numero_lot' => $request->request->getString('numero_lot'),
            'quantite' => $request->request->getString('quantite'),
            'conditionnements' => $request->request->all('conditionnements'),
        ];
        $erreurs = [];
        $lignesValeurs = [];
        if ($request->isMethod('POST') && $request->request->has('lignes')) {
            $lignesValeurs = $request->request->all('lignes');
        } elseif (null !== $mouvementExistant) {
            foreach ($lignesMouvement as $ligneMouvement) {
                $conditionnementLigne = $ligneMouvement->getConditionnementSortie() ?? $ligneMouvement->getDenree()->getUniteReference();
                $lignesValeurs[] = [
                    'denree' => (string) $ligneMouvement->getDenree()->getId(),
                    'reference' => (string) ($ligneMouvement->getReferenceFournisseur()?->getId() ?? ''),
                    'conditionnement_sortie' => (string) ($conditionnementLigne?->getId() ?? ''),
                    'numero_lot' => $ligneMouvement->getNumeroLot() ?? '',
                    'quantite' => null !== $ligneMouvement->getReferenceFournisseur()
                        ? $ligneMouvement->getQuantiteUniteReference()
                        : number_format($conversion->depuisUniteReferenceAvecNiveaux($ligneMouvement->getDenree(), $conditionnementLigne, (float) $ligneMouvement->getQuantiteUniteReference(), $niveauxActifs), 3, '.', ''),
                    'conditionnements' => array_reduce($detailsParLigne[(string) $ligneMouvement->getId()] ?? [], static function (array $resultat, MouvementStockLigneConditionnement $detail): array {
                        $resultat[(string) $detail->getConditionnement()->getId()] = $detail->getQuantite();
                        return $resultat;
                    }, []),
                ];
            }
        }
        if ([] === $lignesValeurs) $lignesValeurs = [[]];

        if ($request->isMethod('POST') && null !== $sejour && $request->request->has('lignes')) {
            if (!$this->isCsrfTokenValid('mouvement_stock', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }

            $erreurs = $this->enregistrerMouvementMultiple(
                $request, $sejour, $denreesActives, $originesActives, $groupesActifs,
                $fournisseursActifs, $referencesParDenree, $conditionnementsParReference, $conditionnementsSortieParDenree,
                $types, $conversion, $em, $mouvementExistant, $lignes, $lignesConditionnements,
            );
            if ([] === $erreurs) {
                return $this->redirectToRoute('app_mouvements_stock');
            }
        }

        if ($request->isMethod('POST') && null !== $sejour && !$request->request->has('lignes')) {
            if (!$this->isCsrfTokenValid('mouvement_stock', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }

            $typeCode = in_array($valeurs['type'], ['ENTREE', 'SORTIE'], true) ? $valeurs['type'] : '';
            $type = '' !== $typeCode ? $types->findOneBy(['code' => $typeCode, 'actif' => true]) : null;
            $denree = $this->selectionner($valeurs['denree'], $denreesActives);
            $origine = $this->selectionner($valeurs['origine'], $originesActives);
            if (null === $type) $erreurs[] = 'Sélectionnez un type de mouvement valide.';
            if (null === $denree) $erreurs[] = 'Sélectionnez une denrée valide.';
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
                $conditionnementSortie = null === $denree ? null : $this->selectionner($valeurs['conditionnement_sortie'], $conditionnementsSortieParDenree[(string) $denree->getId()] ?? []);
                if (null === $conditionnementSortie) $erreurs[] = 'Sélectionnez un conditionnement valide.';
                $quantiteSaisie = $this->normaliserQuantite($valeurs['quantite']);
                if (null === $quantiteSaisie) {
                    $erreurs[] = 'Saisissez une quantité strictement positive.';
                } elseif (null !== $denree && null !== $conditionnementSortie) {
                    $quantiteReference = number_format($conversion->versUniteReference($denree, $conditionnementSortie, (float) $quantiteSaisie), 3, '.', '');
                }
                if (null !== $origine && 'DISTRIBUTION' === $origine->getCode()) {
                    $groupe = $this->selectionner($valeurs['groupe'], $groupesActifs);
                    if (null === $groupe) $erreurs[] = 'Sélectionnez le groupe destinataire de la distribution.';
                }
            } elseif ($entreeFournisseur && null !== $denree) {
                $referencesDenree = $referencesParDenree[(string) $denree->getId()] ?? [];
                $reference = $this->selectionner($valeurs['reference'], $referencesDenree);
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
                            continue;
                        }
                        if ((float) $quantite > 0) {
                            $quantitesConditionnements[$id] = $quantite;
                            $total += (float) $quantite * $facteurs[$id];
                        }
                    }
                    if ([] === $quantitesConditionnements) {
                        $erreurs[] = 'Saisissez au moins une quantité de conditionnement.';
                    } else {
                        $quantiteReference = number_format($total, 3, '.', '');
                    }
                }
            }

            if ([] === $erreurs && null !== $type && null !== $denree && null !== $origine && null !== $quantiteReference) {
                $utilisateur = $this->getUser();
                if (!$utilisateur instanceof Utilisateur) throw new \LogicException('Utilisateur connecté invalide.');

                $em->wrapInTransaction(function () use (
                    $em,
                    $mouvementExistant,
                    $sejour,
                    $utilisateur,
                    $type,
                    $origine,
                    $groupe,
                    $request,
                    $ligneExistante,
                    $lignesConditionnements,
                    $denree,
                    $quantiteReference,
                    $reference,
                    $typeCode,
                    $entreeFournisseur,
                    $conditionnementSortie,
                    $conditionnementsParReference,
                    $quantitesConditionnements,
                    $conversion,
                    $valeurs,
                ): void {
                    $mouvement = $mouvementExistant ?? new MouvementStock($sejour, $utilisateur, $type, $origine);
                    $mouvement->setTypeMouvement($type)->setOrigineMouvement($origine)->setGroupe($groupe);
                    if (null === $mouvementExistant) {
                        $mouvement->setDateMouvement($this->dateNavigateur($request->request->getString('date_navigateur')) ?? new \DateTimeImmutable());
                    }
                    if (null !== $ligneExistante) {
                        foreach ($lignesConditionnements->findPourLigne($ligneExistante) as $ancienConditionnement) {
                            $em->remove($ancienConditionnement);
                        }
                        $em->remove($ligneExistante);
                        // La suppression doit précéder l'insertion à cause de la contrainte
                        // unique (mouvement_stock_id, denree_id), tout en restant atomique.
                        $em->flush();
                    }

                    $ligne = new MouvementStockLigne($mouvement, $denree, $quantiteReference);
                    $quantiteInventaire = null !== $reference
                        ? $conversion->quantiteEntreeInventaire(
                            $denree,
                            (float) $quantiteReference,
                            $conditionnementsParReference[(string) $reference->getId()] ?? [],
                            $quantitesConditionnements,
                        )
                        : $conversion->quantiteInventaireExacte($denree, (float) $quantiteReference);
                    $ligne->setQuantiteUniteInventaire($conversion->formaterQuantiteInventaire($quantiteInventaire));
                    if (null !== $reference) {
                        $ligne->setReferenceFournisseur($reference);
                    }
                    $ligne->setConditionnementSortie(null === $reference ? $conditionnementSortie : null);
                    $ligne->setNumeroLot($entreeFournisseur ? $this->normaliserNumeroLot($valeurs['numero_lot']) : null);
                    $em->persist($mouvement);
                    $em->persist($ligne);
                    if (null !== $reference) {
                        foreach ($conditionnementsParReference[(string) $reference->getId()] as $conditionnement) {
                            $id = (string) $conditionnement->getId();
                            if (isset($quantitesConditionnements[$id])) {
                                $em->persist(new MouvementStockLigneConditionnement($ligne, $conditionnement, $quantitesConditionnements[$id]));
                            }
                        }
                    }
                });
                $this->addFlash('success', sprintf('Mouvement de stock %s pour « %s ».', null === $mouvementExistant ? 'enregistré' : 'modifié', $denree->getNom()));

                return $this->redirectToRoute('app_mouvements_stock');
            }
        }

        return $this->render('mouvement_stock/index.html.twig', compact(
            'sejour', 'denreesActives', 'originesActives', 'groupesActifs', 'fournisseursActifs',
            'referencesParDenree', 'conditionnementsParReference', 'conditionnementsSortieParDenree', 'catalogueMouvement',
            'valeurs', 'lignesValeurs', 'erreurs', 'mouvementExistant',
        ));
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
        if (null === $lot || '' === $lot) return null;

        return mb_substr($lot, 0, 100);
    }

    /**
     * Enregistre atomiquement toutes les lignes envoyées lors de la création.
     *
     * @param list<object> $denreesActives
     * @param list<object> $originesActives
     * @param list<object> $groupesActifs
     * @param array<string, list<object>> $referencesParDenree
     * @param array<string, list<object>> $conditionnementsParReference
     * @param array<string, list<object>> $conditionnementsSortieParDenree
     * @return list<string>
     */
    private function enregistrerMouvementMultiple(
        Request $request,
        Sejour $sejour,
        array $denreesActives,
        array $originesActives,
        array $groupesActifs,
        array $fournisseursActifs,
        array $referencesParDenree,
        array $conditionnementsParReference,
        array $conditionnementsSortieParDenree,
        TypeMouvementRepository $types,
        ConversionConditionnement $conversion,
        EntityManagerInterface $em,
        ?MouvementStock $mouvementExistant,
        MouvementStockLigneRepository $lignes,
        MouvementStockLigneConditionnementRepository $lignesConditionnements,
    ): array {
        $erreurs = [];
        $typeCode = in_array($request->request->getString('type'), ['ENTREE', 'SORTIE'], true) ? $request->request->getString('type') : '';
        $type = '' !== $typeCode ? $types->findOneBy(['code' => $typeCode, 'actif' => true]) : null;
        $origine = $this->selectionner($request->request->getString('origine'), $originesActives);
        $groupe = null;
        $fournisseur = null;
        if (null === $type) $erreurs[] = 'Sélectionnez un type de mouvement valide.';
        if (null === $origine) $erreurs[] = 'Sélectionnez une origine valide.';
        elseif (!in_array($origine->getCode(), self::ORIGINES_PAR_TYPE[$typeCode] ?? [], true)) {
            $erreurs[] = 'Sélectionnez une origine compatible avec le type de mouvement.';
            $origine = null;
        }
        if (null !== $origine && 'DISTRIBUTION' === $origine->getCode()) {
            $groupe = $this->selectionner($request->request->getString('groupe'), $groupesActifs);
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
            $fournisseur = $this->selectionner($request->request->getString('fournisseur'), $fournisseursActifs);
            if (null === $fournisseur) $erreurs[] = 'Sélectionnez un fournisseur valide.';
        }
        foreach (array_values($lignesSaisies) as $index => $saisie) {
            if (!is_array($saisie)) continue;
            $numero = $index + 1;
            $denree = $this->selectionner((string) ($saisie['denree'] ?? ''), $denreesActives);
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
                    $quantiteReference = number_format($conversion->versUniteReference($denree, $conditionnementSortie, (float) $quantite), 3, '.', '');
                }
            } elseif ($mouvementConditionne) {
                if ($mouvementInventaire) {
                    $reference = $this->selectionner((string) ($saisie['reference'] ?? ''), $referencesParDenree[$denreeId] ?? []);
                } else {
                    foreach ($referencesParDenree[$denreeId] ?? [] as $referenceDenree) {
                        if ($referenceDenree->getFournisseur() === $fournisseur) {
                            $reference = $referenceDenree;
                            break;
                        }
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
                        $conditionnementId = (string) $conditionnement->getId();
                        $brut = trim((string) (($saisie['conditionnements'][$conditionnementId] ?? '')));
                        if ('' === $brut) continue;
                        $quantite = $this->normaliserQuantite($brut, true);
                        if (null === $quantite) {
                            $erreurs[] = sprintf('Ligne %d : la quantité de « %s » doit être positive ou nulle.', $numero, $conditionnement->getLibelle());
                        } elseif ((float) $quantite > 0) {
                            $quantitesConditionnements[$conditionnementId] = $quantite;
                            $total += (float) $quantite * $facteurs[$conditionnementId];
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
        if ([] !== $erreurs || null === $type || null === $origine) return $erreurs;

        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof Utilisateur) throw new \LogicException('Utilisateur connecté invalide.');
        $em->wrapInTransaction(function () use ($em, $sejour, $utilisateur, $type, $origine, $groupe, $request, $lignesValides, $typeCode, $conversion, $conditionnementsParReference, $mouvementExistant, $lignes, $lignesConditionnements): void {
            $mouvement = $mouvementExistant ?? new MouvementStock($sejour, $utilisateur, $type, $origine);
            $mouvement->setTypeMouvement($type)->setOrigineMouvement($origine)->setGroupe($groupe);
            if (null === $mouvementExistant) {
                $mouvement->setDateMouvement($this->dateNavigateur($request->request->getString('date_navigateur')) ?? new \DateTimeImmutable());
            } else {
                foreach ($lignes->findToutesPourMouvement($mouvementExistant) as $ancienneLigne) {
                    foreach ($lignesConditionnements->findPourLigne($ancienneLigne) as $ancienDetail) $em->remove($ancienDetail);
                    $em->remove($ancienneLigne);
                }
                $em->flush();
            }
            $em->persist($mouvement);
            foreach ($lignesValides as $donnees) {
                $ligne = new MouvementStockLigne($mouvement, $donnees['denree'], $donnees['quantiteReference']);
                $quantiteInventaire = null !== $donnees['reference']
                    ? $conversion->quantiteEntreeInventaire($donnees['denree'], (float) $donnees['quantiteReference'], $conditionnementsParReference[(string) $donnees['reference']->getId()] ?? [], $donnees['quantitesConditionnements'])
                    : $conversion->quantiteInventaireExacte($donnees['denree'], (float) $donnees['quantiteReference']);
                $ligne->setQuantiteUniteInventaire($conversion->formaterQuantiteInventaire($quantiteInventaire));
                $ligne->setReferenceFournisseur($donnees['reference']);
                $ligne->setConditionnementSortie(null === $donnees['reference'] ? $donnees['conditionnementSortie'] : null);
                $ligne->setNumeroLot($donnees['numeroLot']);
                $em->persist($ligne);
                if (null !== $donnees['reference']) {
                    foreach ($conditionnementsParReference[(string) $donnees['reference']->getId()] ?? [] as $conditionnement) {
                        $conditionnementId = (string) $conditionnement->getId();
                        if (isset($donnees['quantitesConditionnements'][$conditionnementId])) {
                            $em->persist(new MouvementStockLigneConditionnement($ligne, $conditionnement, $donnees['quantitesConditionnements'][$conditionnementId]));
                        }
                    }
                }
            }
        });
        $this->addFlash('success', sprintf('Mouvement de stock %s avec %d denrée%s.', null === $mouvementExistant ? 'enregistré' : 'modifié', count($lignesValides), count($lignesValides) > 1 ? 's' : ''));
        return [];
    }

    private function dateNavigateur(string $iso): ?\DateTimeImmutable
    {
        if ('' === trim($iso)) return null;
        try {
            return new \DateTimeImmutable($iso);
        } catch (\Exception) {
            return null;
        }
    }

    /** @template T of object @param list<T> $entites @return T|null */
    private function selectionner(string $id, array $entites): ?object
    {
        if (!Uuid::isValid($id)) return null;
        foreach ($entites as $entite) {
            if ((string) $entite->getId() === $id) return $entite;
        }
        return null;
    }
}
