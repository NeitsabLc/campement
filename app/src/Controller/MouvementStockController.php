<?php

declare(strict_types=1);

namespace App\Controller;

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
use App\Service\AuditMouvementStock;
use App\Service\ContexteSejour;
use App\Service\ConversionConditionnement;
use App\Service\EnregistrementMouvementStockMultiple;
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
    public function liste(ContexteSejour $sejours, MouvementStockLigneRepository $lignes, AuditMouvementStock $audit): Response
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
                if (null !== $fournisseur) {
                    $fournisseurs[$fournisseur] = true;
                }
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
            'audits' => null === $sejour ? [] : $audit->historique($sejour),
        ]);
    }

    #[Route('/stocks/mouvement/{id}/annuler', name: 'app_mouvement_stock_annuler', methods: ['GET', 'POST'])]
    public function annuler(
        string $id,
        Request $request,
        ContexteSejour $sejours,
        MouvementStockRepository $mouvements,
        EntityManagerInterface $em,
        AuditMouvementStock $audit,
    ): Response {
        $sejour = $sejours->actif();
        $mouvement = Uuid::isValid($id) ? $mouvements->findPourFormulaire($id) : null;
        if (null === $sejour || null === $mouvement || $mouvement->getSejour() !== $sejour || $mouvement->isAnnule()) {
            throw $this->createNotFoundException('Mouvement de stock introuvable ou déjà annulé.');
        }
        if (!$request->isMethod('POST')) {
            return $this->render('mouvement_stock/confirmer_action.html.twig', [
                'mouvement' => $mouvement,
                'erreur' => null,
            ]);
        }
        if (!$this->isCsrfTokenValid('annuler_mouvement_stock_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        $motif = trim($request->request->getString('motif'));
        if ('' === $motif || mb_strlen($motif) > 1000) {
            return $this->render('mouvement_stock/confirmer_action.html.twig', [
                'mouvement' => $mouvement,
                'erreur' => 'Le motif est obligatoire et limité à 1 000 caractères.',
            ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
        }
        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof Utilisateur) {
            throw new \LogicException('Utilisateur connecté invalide.');
        }
        $avant = $audit->instantane($mouvement);

        $em->wrapInTransaction(function () use ($em, $audit, $mouvement, $sejour, $utilisateur, $motif, $avant): void {
            $mouvement->annuler($utilisateur, $motif);
            $em->flush();
            $audit->enregistrer(
                $mouvement,
                $sejour,
                $utilisateur,
                AuditMouvementStock::ANNULATION,
                $motif,
                $avant,
                $audit->instantane($mouvement),
            );
        });
        $this->addFlash('success', 'Le mouvement a été annulé et ne compte plus dans le stock.');

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
        ReferenceFournisseurRepository $references,
        ReferenceFournisseurConditionnementRepository $conditionnements,
        MouvementStockRepository $mouvements,
        MouvementStockLigneRepository $lignes,
        MouvementStockLigneConditionnementRepository $lignesConditionnements,
        ConversionConditionnement $conversion,
        EnregistrementMouvementStockMultiple $enregistrementMultiple,
        ?string $id = null,
    ): Response {
        $sejour = $sejours->actif();
        $mouvementExistant = null !== $id && Uuid::isValid($id) ? $mouvements->findPourFormulaire($id) : null;
        if (null !== $id && (null === $sejour || null === $mouvementExistant || $mouvementExistant->getSejour() !== $sejour || $mouvementExistant->isAnnule())) {
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
        $motifAudit = trim($request->request->getString('motif_audit'));
        $lignesValeurs = [];
        if ($request->isMethod('POST') && $request->request->has('lignes')) {
            $lignesValeurs = $request->request->all('lignes');
        } elseif (null !== $mouvementExistant) {
            foreach ($lignesMouvement as $ligneMouvement) {
                $conditionnementLigne = $ligneMouvement->getConditionnementSortie() ?? $ligneMouvement->getDenree()->getUniteReference();
                $lignesValeurs[] = [
                    'denree' => (string) $ligneMouvement->getDenree()->getId(),
                    'reference' => (string) ($ligneMouvement->getReferenceFournisseur()?->getId() ?? ''),
                    'conditionnement_sortie' => (string) $conditionnementLigne->getId(),
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
        if ([] === $lignesValeurs) {
            $lignesValeurs = [[]];
        }

        if ($request->isMethod('POST') && null !== $sejour && $request->request->has('lignes')) {
            if (!$this->isCsrfTokenValid('mouvement_stock', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }

            $utilisateur = $this->getUser();
            if (!$utilisateur instanceof Utilisateur) {
                throw new \LogicException('Utilisateur connecté invalide.');
            }
            $resultat = $enregistrementMultiple->enregistrer(
                $request, $sejour, $utilisateur, $denreesActives, $originesActives, $groupesActifs,
                $fournisseursActifs, $referencesParDenree, $conditionnementsParReference,
                $conditionnementsSortieParDenree, $mouvementExistant, $motifAudit,
            );
            $erreurs = $resultat['erreurs'];
            if ([] === $erreurs) {
                $this->addFlash('success', sprintf(
                    'Mouvement de stock %s avec %d denrée%s.',
                    null === $mouvementExistant ? 'enregistré' : 'modifié',
                    $resultat['nombre'],
                    $resultat['nombre'] > 1 ? 's' : '',
                ));

                return $this->redirectToRoute('app_mouvements_stock');
            }
        }

        if ($request->isMethod('POST') && null !== $sejour && !$request->request->has('lignes')) {
            if (!$this->isCsrfTokenValid('mouvement_stock', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }
            $utilisateur = $this->getUser();
            if (!$utilisateur instanceof Utilisateur) {
                throw new \LogicException('Utilisateur connecté invalide.');
            }
            $resultat = $enregistrementMultiple->enregistrerSimple(
                $request, $sejour, $utilisateur, $valeurs, $denreesActives, $originesActives, $groupesActifs,
                $referencesParDenree, $conditionnementsParReference, $conditionnementsSortieParDenree,
                $mouvementExistant, $ligneExistante, $motifAudit,
            );
            $erreurs = $resultat['erreurs'];
            if ([] === $erreurs && null !== $resultat['denree']) {
                $this->addFlash('success', sprintf('Mouvement de stock %s pour « %s ».', null === $mouvementExistant ? 'enregistré' : 'modifié', $resultat['denree']->getNom()));

                return $this->redirectToRoute('app_mouvements_stock');
            }
        }

        return $this->render('mouvement_stock/index.html.twig', compact(
            'sejour', 'denreesActives', 'originesActives', 'groupesActifs', 'fournisseursActifs',
            'referencesParDenree', 'conditionnementsParReference', 'conditionnementsSortieParDenree', 'catalogueMouvement',
            'valeurs', 'lignesValeurs', 'erreurs', 'mouvementExistant', 'motifAudit',
        ));
    }
}
