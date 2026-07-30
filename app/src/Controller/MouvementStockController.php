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
        $mouvementExistant = null !== $id && Uuid::isValid($id) ? $mouvements->find($id) : null;
        if (null !== $id && (null === $sejour || null === $mouvementExistant || $mouvementExistant->getSejour() !== $sejour)) {
            throw $this->createNotFoundException('Mouvement de stock introuvable.');
        }
        $ligneDemandee = $request->query->getString('ligne');
        $ligneExistante = null;
        if (null !== $mouvementExistant) {
            $ligneExistante = Uuid::isValid($ligneDemandee) ? $lignes->find($ligneDemandee) : $lignes->findPourMouvement($mouvementExistant);
            if (null !== $ligneExistante && $ligneExistante->getMouvementStock() !== $mouvementExistant) {
                throw $this->createNotFoundException('La ligne ne correspond pas au mouvement demandé.');
            }
        }
        if (null !== $mouvementExistant && null === $ligneExistante) {
            throw $this->createNotFoundException('Le mouvement ne contient aucune ligne modifiable.');
        }
        $denreesActives = null === $sejour ? [] : $denrees->findActifsPourSejour($sejour);
        $originesActives = null === $sejour ? [] : $origines->findActifsPourSejour($sejour);
        $groupesActifs = null === $sejour ? [] : $groupes->findActifsPourSejour($sejour);
        $referencesParDenree = [];
        $fournisseursParId = [];
        $conditionnementsParReference = [];
        $conditionnementsSortieParDenree = [];

        foreach ($denreesActives as $denree) {
            $conditionnementsSortieParDenree[(string) $denree->getId()] = $conversion->conditionnementsPour($denree);
            foreach ($references->findPourDenree($denree) as $reference) {
                if (!$reference->isActif() || !$reference->getFournisseur()->isActif()) {
                    continue;
                }
                $referencesParDenree[(string) $denree->getId()][] = $reference;
                $fournisseursParId[(string) $reference->getFournisseur()->getId()] = $reference->getFournisseur();
                $conditionnementsParReference[(string) $reference->getId()] = $conditionnements->findPourReference($reference);
            }
        }
        $fournisseursActifs = array_values($fournisseursParId);

        $conditionnementSortieExistant = $ligneExistante?->getConditionnementSortie() ?? $ligneExistante?->getDenree()->getUniteReference();
        $valeurs = null !== $ligneExistante && !$request->isMethod('POST') ? [
            'type' => $mouvementExistant->getTypeMouvement()->getCode(),
            'denree' => (string) $ligneExistante->getDenree()->getId(),
            'origine' => (string) $mouvementExistant->getOrigineMouvement()->getId(),
            'groupe' => (string) ($mouvementExistant->getGroupe()?->getId() ?? ''),
            'fournisseur' => (string) ($ligneExistante->getReferenceFournisseur()?->getFournisseur()->getId() ?? ''),
            'reference' => (string) ($ligneExistante->getReferenceFournisseur()?->getId() ?? ''),
            'conditionnement_sortie' => (string) ($conditionnementSortieExistant?->getId() ?? ''),
            'quantite' => 'SORTIE' === $mouvementExistant->getTypeMouvement()->getCode() && null !== $conditionnementSortieExistant
                ? number_format($conversion->depuisUniteReference($ligneExistante->getDenree(), $conditionnementSortieExistant, (float) $ligneExistante->getQuantiteUniteReference()), 3, '.', '')
                : $ligneExistante->getQuantiteUniteReference(),
            'conditionnements' => array_reduce($lignesConditionnements->findPourLigne($ligneExistante), static function (array $resultat, MouvementStockLigneConditionnement $ligne): array {
                $resultat[(string) $ligne->getConditionnement()->getId()] = $ligne->getQuantite();
                return $resultat;
            }, []),
        ] : [
            'type' => $request->request->getString('type', 'SORTIE'),
            'denree' => $request->request->getString('denree'),
            'origine' => $request->request->getString('origine'),
            'groupe' => $request->request->getString('groupe'),
            'fournisseur' => $request->request->getString('fournisseur'),
            'reference' => $request->request->getString('reference'),
            'conditionnement_sortie' => $request->request->getString('conditionnement_sortie'),
            'quantite' => $request->request->getString('quantite'),
            'conditionnements' => $request->request->all('conditionnements'),
        ];
        $erreurs = [];
        $lignesValeurs = [];
        if ($request->isMethod('POST') && $request->request->has('lignes')) {
            $lignesValeurs = $request->request->all('lignes');
        } elseif (null !== $mouvementExistant) {
            foreach ($lignes->findToutesPourMouvement($mouvementExistant) as $ligneMouvement) {
                $conditionnementLigne = $ligneMouvement->getConditionnementSortie() ?? $ligneMouvement->getDenree()->getUniteReference();
                $lignesValeurs[] = [
                    'denree' => (string) $ligneMouvement->getDenree()->getId(),
                    'reference' => (string) ($ligneMouvement->getReferenceFournisseur()?->getId() ?? ''),
                    'conditionnement_sortie' => (string) ($conditionnementLigne?->getId() ?? ''),
                    'quantite' => null !== $ligneMouvement->getReferenceFournisseur()
                        ? $ligneMouvement->getQuantiteUniteReference()
                        : number_format($conversion->depuisUniteReference($ligneMouvement->getDenree(), $conditionnementLigne, (float) $ligneMouvement->getQuantiteUniteReference()), 3, '.', ''),
                    'conditionnements' => array_reduce($lignesConditionnements->findPourLigne($ligneMouvement), static function (array $resultat, MouvementStockLigneConditionnement $detail): array {
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
                    $conditionnementSortie,
                    $conditionnementsParReference,
                    $quantitesConditionnements,
                    $conversion,
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
            'referencesParDenree', 'conditionnementsParReference', 'conditionnementsSortieParDenree', 'valeurs', 'lignesValeurs', 'erreurs', 'mouvementExistant',
        ));
    }

    private function normaliserQuantite(string $brut, bool $zeroAutorise = false): ?string
    {
        $brut = str_replace([' ', ','], ['', '.'], trim($brut));
        if ('' === $brut || !is_numeric($brut) || ($zeroAutorise ? (float) $brut < 0 : (float) $brut <= 0)) return null;

        return number_format((float) $brut, 3, '.', '');
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
        if (null !== $origine && 'DISTRIBUTION' === $origine->getCode()) {
            $groupe = $this->selectionner($request->request->getString('groupe'), $groupesActifs);
            if (null === $groupe) $erreurs[] = 'Sélectionnez le groupe destinataire de la distribution.';
        }

        $lignesSaisies = $request->request->all('lignes');
        if ([] === $lignesSaisies) $erreurs[] = 'Ajoutez au moins une denrée au mouvement.';
        $lignesValides = [];
        $denreesVues = [];
        $entreeFournisseur = 'ENTREE' === $typeCode && null !== $origine && 'FOURNISSEUR' === $origine->getCode();
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

            if (in_array($typeCode, ['ENTREE', 'SORTIE'], true) && !$entreeFournisseur) {
                $conditionnementSortie = $this->selectionner((string) ($saisie['conditionnement_sortie'] ?? ''), $conditionnementsSortieParDenree[$denreeId] ?? []);
                $quantite = $this->normaliserQuantite((string) ($saisie['quantite'] ?? ''));
                if (null === $conditionnementSortie) $erreurs[] = sprintf('Ligne %d : sélectionnez un conditionnement valide.', $numero);
                if (null === $quantite) $erreurs[] = sprintf('Ligne %d : saisissez une quantité strictement positive.', $numero);
                if (null !== $conditionnementSortie && null !== $quantite) {
                    $quantiteReference = number_format($conversion->versUniteReference($denree, $conditionnementSortie, (float) $quantite), 3, '.', '');
                }
            } elseif ($entreeFournisseur) {
                foreach ($referencesParDenree[$denreeId] ?? [] as $referenceDenree) {
                    if ($referenceDenree->getFournisseur() === $fournisseur) {
                        $reference = $referenceDenree;
                        break;
                    }
                }
                if (null === $reference) {
                    $erreurs[] = sprintf('Ligne %d : « %s » n’est pas proposée par le fournisseur sélectionné.', $numero, $denree->getNom());
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
                $lignesValides[] = compact('denree', 'reference', 'conditionnementSortie', 'quantitesConditionnements', 'quantiteReference');
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
