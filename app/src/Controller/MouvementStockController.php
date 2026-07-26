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

        return $this->render('mouvement_stock/liste.html.twig', [
            'sejour' => $sejour,
            'lignes' => null === $sejour ? [] : $lignes->findPourGestion($sejour),
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
        EntityManagerInterface $em,
        ?string $id = null,
    ): Response {
        $sejour = $sejours->actif();
        $mouvementExistant = null !== $id && Uuid::isValid($id) ? $mouvements->find($id) : null;
        if (null !== $id && (null === $sejour || null === $mouvementExistant || $mouvementExistant->getSejour() !== $sejour)) {
            throw $this->createNotFoundException('Mouvement de stock introuvable.');
        }
        $ligneExistante = null === $mouvementExistant ? null : $lignes->findPourMouvement($mouvementExistant);
        if (null !== $mouvementExistant && null === $ligneExistante) {
            throw $this->createNotFoundException('Le mouvement ne contient aucune ligne modifiable.');
        }
        $denreesActives = null === $sejour ? [] : $denrees->findActifsPourSejour($sejour);
        $originesActives = null === $sejour ? [] : $origines->findActifsPourSejour($sejour);
        $groupesActifs = null === $sejour ? [] : $groupes->findActifsPourSejour($sejour);
        $referencesParDenree = [];
        $conditionnementsParReference = [];

        foreach ($denreesActives as $denree) {
            foreach ($references->findPourDenree($denree) as $reference) {
                if (!$reference->isActif() || !$reference->getFournisseur()->isActif()) {
                    continue;
                }
                $referencesParDenree[(string) $denree->getId()][] = $reference;
                $conditionnementsParReference[(string) $reference->getId()] = $conditionnements->findPourReference($reference);
            }
        }

        $valeurs = null !== $ligneExistante && !$request->isMethod('POST') ? [
            'type' => $mouvementExistant->getTypeMouvement()->getCode(),
            'denree' => (string) $ligneExistante->getDenree()->getId(),
            'origine' => (string) $mouvementExistant->getOrigineMouvement()->getId(),
            'groupe' => (string) ($mouvementExistant->getGroupe()?->getId() ?? ''),
            'reference' => (string) ($ligneExistante->getReferenceFournisseur()?->getId() ?? ''),
            'quantite' => $ligneExistante->getQuantiteUniteReference(),
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
            $quantiteReference = null;
            $quantitesConditionnements = [];

            if ('SORTIE' === $typeCode) {
                $quantiteReference = $this->normaliserQuantite($valeurs['quantite']);
                if (null === $quantiteReference) $erreurs[] = 'Saisissez une quantité de référence strictement positive.';
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
                if (null !== $ligneExistante) {
                    foreach ($lignesConditionnements->findPourLigne($ligneExistante) as $ancienConditionnement) $em->remove($ancienConditionnement);
                    $em->remove($ligneExistante);
                    $em->flush();
                }
                $ligne = new MouvementStockLigne($mouvement, $denree, $quantiteReference);
                if (null !== $reference) $ligne->setReferenceFournisseur($reference);
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
            'referencesParDenree', 'conditionnementsParReference', 'valeurs', 'erreurs', 'mouvementExistant',
        ));
    }

    private function normaliserQuantite(string $brut, bool $zeroAutorise = false): ?string
    {
        $brut = str_replace([' ', ','], ['', '.'], trim($brut));
        if ('' === $brut || !is_numeric($brut) || ($zeroAutorise ? (float) $brut < 0 : (float) $brut <= 0)) return null;

        return number_format((float) $brut, 3, '.', '');
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
