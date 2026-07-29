<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\MouvementStock;
use App\Entity\MouvementStockLigne;
use App\Entity\MouvementStockLigneConditionnement;
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
    public function liste(ContexteSejour $sejours, MouvementStockLigneRepository $lignes, ConversionConditionnement $conversion): Response
    {
        $sejour = $sejours->actif();
        $mouvements = null === $sejour ? [] : $lignes->findPourGestion($sejour);
        $quantitesAffichees = [];
        foreach ($mouvements as $ligne) {
            if ('ENTREE' === $ligne->getMouvementStock()->getTypeMouvement()->getCode()) {
                $quantitesAffichees[(string) $ligne->getId()] = [
                    'quantite' => (float) $ligne->getQuantiteUniteInventaire(),
                    'unite' => $ligne->getDenree()->getUniteInventaire(),
                ];
            } else {
                $unite = $ligne->getConditionnementSortie() ?? $ligne->getDenree()->getUniteReference();
                $quantitesAffichees[(string) $ligne->getId()] = [
                    'quantite' => $conversion->depuisUniteReference($ligne->getDenree(), $unite, (float) $ligne->getQuantiteUniteReference()),
                    'unite' => $unite,
                ];
            }
        }

        return $this->render('mouvement_stock/liste.html.twig', [
            'sejour' => $sejour,
            'lignes' => $mouvements,
            'quantites_affichees' => $quantitesAffichees,
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
        $conditionnementsParReference = [];
        $conditionnementsSortieParDenree = [];

        foreach ($denreesActives as $denree) {
            $conditionnementsSortieParDenree[(string) $denree->getId()] = $conversion->conditionnementsPour($denree);
            foreach ($references->findPourDenree($denree) as $reference) {
                if (!$reference->isActif() || !$reference->getFournisseur()->isActif()) {
                    continue;
                }
                $referencesParDenree[(string) $denree->getId()][] = $reference;
                $conditionnementsParReference[(string) $reference->getId()] = $conditionnements->findPourReference($reference);
            }
        }

        $conditionnementSortieExistant = $ligneExistante?->getConditionnementSortie() ?? $ligneExistante?->getDenree()->getUniteReference();
        $valeurs = null !== $ligneExistante && !$request->isMethod('POST') ? [
            'type' => $mouvementExistant->getTypeMouvement()->getCode(),
            'denree' => (string) $ligneExistante->getDenree()->getId(),
            'origine' => (string) $mouvementExistant->getOrigineMouvement()->getId(),
            'groupe' => (string) ($mouvementExistant->getGroupe()?->getId() ?? ''),
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
            'reference' => $request->request->getString('reference'),
            'conditionnement_sortie' => $request->request->getString('conditionnement_sortie'),
            'quantite' => $request->request->getString('quantite'),
            'conditionnements' => $request->request->all('conditionnements'),
        ];
        $erreurs = [];

        if ($request->isMethod('POST') && null !== $sejour) {
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

            if ('SORTIE' === $typeCode) {
                $conditionnementSortie = null === $denree ? null : $this->selectionner($valeurs['conditionnement_sortie'], $conditionnementsSortieParDenree[(string) $denree->getId()] ?? []);
                if (null === $conditionnementSortie) $erreurs[] = 'Sélectionnez un conditionnement de sortie valide.';
                $quantiteSaisie = $this->normaliserQuantite($valeurs['quantite']);
                if (null === $quantiteSaisie) {
                    $erreurs[] = 'Saisissez une quantité de sortie strictement positive.';
                } elseif (null !== $denree && null !== $conditionnementSortie) {
                    $quantiteReference = number_format($conversion->versUniteReference($denree, $conditionnementSortie, (float) $quantiteSaisie), 3, '.', '');
                }
                if (null !== $origine && 'DISTRIBUTION' === $origine->getCode()) {
                    $groupe = $this->selectionner($valeurs['groupe'], $groupesActifs);
                    if (null === $groupe) $erreurs[] = 'Sélectionnez le groupe destinataire de la distribution.';
                }
            } elseif ('ENTREE' === $typeCode && null !== $denree) {
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

                $mouvement = $mouvementExistant ?? new MouvementStock($sejour, $utilisateur, $type, $origine);
                $mouvement->setTypeMouvement($type)->setOrigineMouvement($origine)->setGroupe($groupe);
                if (null === $mouvementExistant) {
                    $mouvement->setDateMouvement($this->dateNavigateur($request->request->getString('date_navigateur')) ?? new \DateTimeImmutable());
                }
                if (null !== $ligneExistante) {
                    foreach ($lignesConditionnements->findPourLigne($ligneExistante) as $ancienConditionnement) $em->remove($ancienConditionnement);
                    $em->remove($ligneExistante);
                    $em->flush();
                }
                $ligne = new MouvementStockLigne($mouvement, $denree, $quantiteReference);
                $quantiteInventaire = 'ENTREE' === $typeCode
                    ? $conversion->quantiteEntreeInventaire(
                        $denree,
                        (float) $quantiteReference,
                        $conditionnementsParReference[(string) $reference->getId()] ?? [],
                        $quantitesConditionnements,
                    )
                    : $conversion->quantiteInventaireExacte($denree, (float) $quantiteReference);
                $ligne->setQuantiteUniteInventaire(number_format($quantiteInventaire, 3, '.', ''));
                if (null !== $reference) $ligne->setReferenceFournisseur($reference);
                $ligne->setConditionnementSortie('SORTIE' === $typeCode ? $conditionnementSortie : null);
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
                $em->flush();
                $this->addFlash('success', sprintf('Mouvement de stock %s pour « %s ».', null === $mouvementExistant ? 'enregistré' : 'modifié', $denree->getNom()));

                return $this->redirectToRoute('app_mouvements_stock');
            }
        }

        return $this->render('mouvement_stock/index.html.twig', compact(
            'sejour', 'denreesActives', 'originesActives', 'groupesActifs',
            'referencesParDenree', 'conditionnementsParReference', 'conditionnementsSortieParDenree', 'valeurs', 'erreurs', 'mouvementExistant',
        ));
    }

    private function normaliserQuantite(string $brut, bool $zeroAutorise = false): ?string
    {
        $brut = str_replace([' ', ','], ['', '.'], trim($brut));
        if ('' === $brut || !is_numeric($brut) || ($zeroAutorise ? (float) $brut < 0 : (float) $brut <= 0)) return null;

        return number_format((float) $brut, 3, '.', '');
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
