<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Denree;
use App\Entity\ReferenceFournisseur;
use App\Entity\ReferenceFournisseurConditionnement;
use App\Entity\Utilisateur;
use App\Repository\DenreeRepository;
use App\Repository\FournisseurRepository;
use App\Repository\ReferenceFournisseurConditionnementRepository;
use App\Repository\ReferenceFournisseurRepository;
use App\Service\ContexteSejour;
use App\Service\ConversionConditionnement;
use App\Repository\UniteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[IsGranted(Utilisateur::ROLE_GESTIONNAIRE)]
final class DenreeController extends AbstractController
{
    #[Route('/denrees', name: 'app_denrees', methods: ['GET'])]
    public function index(Request $request, ContexteSejour $sejours, DenreeRepository $denrees, ConversionConditionnement $conversion): Response
    {
        $sejour = $sejours->actif();
        $actives = !$request->query->getBoolean('desactivees');

        $lignes = null === $sejour ? [] : $denrees->findPourGestion($sejour, $actives);
        foreach ($lignes as &$ligne) {
            $ligne['stockInventaire'] = $conversion->stockDepuisQuantitesInventaire(
                (float) $ligne['stockEntree'],
                (float) $ligne['stockSortie'],
            );
        }
        unset($ligne);
        return $this->render('denree/index.html.twig', [
            'sejour' => $sejour,
            'actives' => $actives,
            'denrees' => $lignes,
        ]);
    }

    #[Route('/denrees/ajouter', name: 'app_denree_ajouter', methods: ['GET', 'POST'])]
    public function ajouter(Request $request, ContexteSejour $sejours, DenreeRepository $denrees, FournisseurRepository $fournisseurs, UniteRepository $unites, ReferenceFournisseurRepository $references, ReferenceFournisseurConditionnementRepository $conditionnements, EntityManagerInterface $em): Response
    {
        $sejour = $sejours->actif();
        if (null === $sejour) {
            throw $this->createNotFoundException('Aucun séjour actif.');
        }

        return $this->formulaire($request, new Denree($sejour), true, $denrees, $fournisseurs, $unites, $references, $conditionnements, $em);
    }

    #[Route('/denrees/{id}/modifier', name: 'app_denree_modifier', methods: ['GET', 'POST'])]
    public function modifier(string $id, Request $request, ContexteSejour $sejours, DenreeRepository $denrees, FournisseurRepository $fournisseurs, UniteRepository $unites, ReferenceFournisseurRepository $references, ReferenceFournisseurConditionnementRepository $conditionnements, EntityManagerInterface $em): Response
    {
        $sejour = $sejours->actif();
        $denree = Uuid::isValid($id) ? $denrees->find($id) : null;
        if (null === $sejour || null === $denree || $denree->getSejour() !== $sejour) {
            throw $this->createNotFoundException('Denrée introuvable pour le séjour actif.');
        }

        return $this->formulaire($request, $denree, false, $denrees, $fournisseurs, $unites, $references, $conditionnements, $em);
    }

    #[Route('/denrees/{id}/statut', name: 'app_denree_statut', methods: ['POST'])]
    public function statut(string $id, Request $request, ContexteSejour $sejours, DenreeRepository $denrees, EntityManagerInterface $em): Response
    {
        $sejour = $sejours->actif();
        $denree = Uuid::isValid($id) ? $denrees->find($id) : null;
        if (null === $sejour || null === $denree || $denree->getSejour() !== $sejour) {
            throw $this->createNotFoundException('Denrée introuvable.');
        }
        if (!$this->isCsrfTokenValid('statut_denree_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        $denree->setActif(!$denree->isActif());
        $em->flush();
        $this->addFlash('success', sprintf('La denrée « %s » a bien été %s.', $denree->getNom(), $denree->isActif() ? 'réactivée' : 'désactivée'));

        return $this->redirectToRoute('app_denrees', $denree->isActif() ? [] : ['desactivees' => 1]);
    }

    private function formulaire(Request $request, Denree $denree, bool $creation, DenreeRepository $denrees, FournisseurRepository $fournisseurs, UniteRepository $unites, ReferenceFournisseurRepository $references, ReferenceFournisseurConditionnementRepository $conditionnements, EntityManagerInterface $em): Response
    {
        $erreurs = [];
        $erreursUniteInventaire = [];
        $donnees = $this->donneesInitiales($denree, $references, $conditionnements);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('enregistrer_denree', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }
            $donnees = ['nom' => trim($request->request->getString('nom')), 'unite_inventaire' => $request->request->getString('unite_inventaire'), 'stock_min' => trim($request->request->getString('stock_min')), 'fournisseurs' => $request->request->all('fournisseurs')];
            $uniteInventaire = Uuid::isValid($donnees['unite_inventaire']) ? $unites->find($donnees['unite_inventaire']) : null;
            $stockMinNormalise = str_replace([' ', ','], ['', '.'], $donnees['stock_min']);
            if ('' === $donnees['nom'] || mb_strlen($donnees['nom']) > 150) {
                $erreurs[] = 'Le nom de la denrée est obligatoire et limité à 150 caractères.';
            } elseif ($denrees->existeAvecNomPourSejour($denree->getSejour(), $donnees['nom'], $creation ? null : $denree)) {
                $erreurs[] = 'Une denrée portant ce nom existe déjà.';
            }
            if ('' !== $stockMinNormalise && (!is_numeric($stockMinNormalise) || (float) $stockMinNormalise < 0 || (float) $stockMinNormalise >= 1000000000)) {
                $erreurs[] = 'Le seuil minimum doit être un nombre positif ou nul inférieur à un milliard.';
            }
            $fournisseursValides = [];
            $fournisseursSelectionnes = [];
            $uniteTerminale = null;
            $possedeReferenceArchivee = false;
            if (!$creation) {
                foreach ($references->findPourDenree($denree) as $referenceExistante) {
                    if ($referenceExistante->isActif() && !$referenceExistante->getFournisseur()->isActif()) {
                        $possedeReferenceArchivee = true;
                        break;
                    }
                }
            }
            if ([] === $donnees['fournisseurs'] && !$possedeReferenceArchivee) { $erreurs[] = 'Ajoutez au moins un fournisseur.'; }
            foreach ($donnees['fournisseurs'] as $index => $ligne) {
                if (!is_array($ligne)) { continue; }
                $fournisseur = isset($ligne['fournisseur']) && Uuid::isValid((string) $ligne['fournisseur']) ? $fournisseurs->find($ligne['fournisseur']) : null;
                $reference = trim((string) ($ligne['reference'] ?? ''));
                $niveaux = is_array($ligne['niveaux'] ?? null) ? $ligne['niveaux'] : [];
                if (null !== $uniteInventaire && !array_filter($niveaux, static fn (array $niveau): bool => (string) ($niveau['conditionnement'] ?? '') === (string) $uniteInventaire->getId())) {
                    $nomFournisseur = null !== $fournisseur ? $fournisseur->getNom() : sprintf('le bloc fournisseur %d', $index + 1);
                    $message = sprintf('L’unité référence inventaire « %s » doit être présente dans le conditionnement de %s.', $uniteInventaire->getNom(), $nomFournisseur);
                    $erreurs[] = $message;
                    $erreursUniteInventaire[] = $message;
                }
                if (null === $fournisseur || !$fournisseur->isActif() || $fournisseur->getSejour() !== $denree->getSejour()) {
                    $erreurs[] = sprintf('Sélectionnez un fournisseur valide dans le bloc %d.', $index + 1);
                    continue;
                }
                $fournisseurId = (string) $fournisseur->getId();
                if (isset($fournisseursSelectionnes[$fournisseurId])) {
                    $erreurs[] = sprintf('Le fournisseur %s est déjà associé à cette denrée.', $fournisseur->getNom());
                    continue;
                }
                $fournisseursSelectionnes[$fournisseurId] = true;
                if ([] === $niveaux) {
                    $erreurs[] = sprintf('Ajoutez au moins un niveau de conditionnement au fournisseur %s.', $fournisseur->getNom());
                    continue;
                }
                foreach ($niveaux as $niveauIndex => $niveau) {
                    $dernier = $niveauIndex === array_key_last($niveaux);
                    $quantite = $dernier ? '1' : str_replace(',', '.', trim((string) ($niveau['quantite'] ?? '')));
                    $conditionnement = isset($niveau['conditionnement']) && Uuid::isValid((string) $niveau['conditionnement']) ? $unites->find($niveau['conditionnement']) : null;
                    if (null === $conditionnement || !$conditionnement->isActif() || !is_numeric($quantite) || (float) $quantite <= 0) {
                        $erreurs[] = sprintf('Le niveau %d du fournisseur %s est incomplet.', $niveauIndex + 1, $fournisseur->getNom());
                    }
                    if ($dernier && null !== $conditionnement) {
                        if (null === $uniteTerminale) { $uniteTerminale = $conditionnement; }
                        elseif ($uniteTerminale !== $conditionnement) { $erreurs[] = 'Tous les fournisseurs doivent terminer par la même unité.'; }
                    }
                }
                $fournisseursValides[] = [$ligne, $fournisseur, $reference, $niveaux];
            }

            if (null === $uniteInventaire || !$uniteInventaire->isActif()) $erreurs[] = 'Sélectionnez une unité référence inventaire active.';
            if (null === $uniteTerminale) $erreurs[] = 'Définissez l’unité terminale commune aux conditionnements.';

            if ([] === $erreurs && null !== $uniteTerminale && null !== $uniteInventaire) {
                $denree->setNom($donnees['nom'])->setUniteReference($uniteTerminale)->setUniteInventaire($uniteInventaire)->setStockMin('' === $stockMinNormalise ? null : number_format((float) $stockMinNormalise, 3, '.', ''));
                if ($creation) { $em->persist($denree); }
                $existantes = [];
                foreach ($references->findPourDenree($denree) as $referenceExistante) {
                    // Une référence liée à un fournisseur désactivé reste intacte :
                    // elle n'est plus proposée dans le formulaire mais conserve ses conditionnements.
                    if ($referenceExistante->getFournisseur()->isActif()) {
                        $existantes[(string) $referenceExistante->getId()] = $referenceExistante;
                    }
                }
                foreach ($fournisseursValides as [$ligne, $fournisseur, $referenceTexte, $niveaux]) {
                    $id = (string) ($ligne['id'] ?? '');
                    $referenceNormalisee = '' === $referenceTexte ? null : $referenceTexte;
                    $reference = $existantes[$id] ?? new ReferenceFournisseur($fournisseur, $denree, $referenceNormalisee, $denree->getNom());
                    unset($existantes[$id]);
                    $reference->setFournisseur($fournisseur)->setReference($referenceNormalisee)->setDesignation($denree->getNom())->setActif(true);
                    $em->persist($reference);
                    $niveauxExistants = [];
                    foreach ($conditionnements->findPourReference($reference) as $niveauExistant) { $niveauxExistants[(string) $niveauExistant->getId()] = $niveauExistant; }
                    foreach (array_values($niveaux) as $ordre => $niveau) {
                        $niveauId = (string) ($niveau['id'] ?? '');
                        $dernier = $ordre === count($niveaux) - 1;
                        $typeConditionnement = $unites->find((string) $niveau['conditionnement']);
                        $typeContenu = $dernier ? null : $unites->find((string) $niveaux[$ordre + 1]['conditionnement']);
                        $libelleContenu = $typeContenu?->getNom();
                        $quantite = $dernier ? '1' : str_replace(',', '.', (string) $niveau['quantite']);
                        $uniteContenu = $dernier ? $typeConditionnement : null;
                        $conditionnement = $niveauxExistants[$niveauId] ?? new ReferenceFournisseurConditionnement($reference, $ordre + 1, $typeConditionnement->getNom(), $quantite, $uniteContenu, $libelleContenu, $typeConditionnement);
                        unset($niveauxExistants[$niveauId]);
                        $conditionnement->setOrdre($ordre + 1)->setConditionnement($typeConditionnement)->setQuantiteContenu($quantite)->setUniteContenu($uniteContenu)->setLibelleContenu($libelleContenu);
                        $em->persist($conditionnement);
                    }
                    foreach ($niveauxExistants as $niveauExistant) { $em->remove($niveauExistant); }
                }
                foreach ($existantes as $referenceExistante) { $referenceExistante->setActif(false); }
                $em->flush();
                $this->addFlash('success', sprintf('La denrée « %s » a bien été %s.', $denree->getNom(), $creation ? 'créée' : 'modifiée'));
                return $this->redirectToRoute('app_denrees');
            }
        }

        $referencesArchivees = 0;
        if (!$creation) {
            foreach ($references->findPourDenree($denree) as $reference) {
                if ($reference->isActif() && !$reference->getFournisseur()->isActif()) {
                    ++$referencesArchivees;
                }
            }
        }

        $response = [] === $erreurs ? null : new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY);

        return $this->render('denree/form.html.twig', ['denree' => $denree, 'creation' => $creation, 'donnees' => $donnees, 'erreurs' => $erreurs, 'erreurs_unite_inventaire' => $erreursUniteInventaire, 'conditionnements' => array_filter($unites->findActifs(), static fn ($u) => $u->isUtilisableConditionnement()), 'fournisseurs' => $fournisseurs->findActifsPourSejour($denree->getSejour()), 'references_archivees' => $referencesArchivees], $response);
    }

    private function donneesInitiales(Denree $denree, ReferenceFournisseurRepository $references, ReferenceFournisseurConditionnementRepository $conditionnements): array
    {
        $resultat = ['nom' => '', 'unite' => null, 'unite_inventaire' => null, 'stock_min' => '', 'fournisseurs' => []];
        try { $resultat['nom'] = $denree->getNom(); $resultat['unite'] = (string) $denree->getUniteReference()->getId(); $resultat['unite_inventaire'] = (string) $denree->getUniteInventaire()->getId(); $resultat['stock_min'] = $denree->getStockMin() ?? ''; } catch (\Error) {}
        if ('' === $resultat['nom']) { return $resultat; }
        foreach ($references->findPourDenree($denree) as $reference) {
            if (!$reference->isActif() || !$reference->getFournisseur()->isActif()) { continue; }
            $ligne = ['id' => (string) $reference->getId(), 'fournisseur' => (string) $reference->getFournisseur()->getId(), 'reference' => $reference->getReference(), 'niveaux' => []];
            foreach ($conditionnements->findPourReference($reference) as $niveau) { $ligne['niveaux'][] = ['id' => (string) $niveau->getId(), 'conditionnement' => (string) $niveau->getConditionnement()->getId(), 'libelle' => $niveau->getLibelle(), 'quantite' => $niveau->getQuantiteContenu()]; }
            $resultat['fournisseurs'][] = $ligne;
        }
        return $resultat;
    }
}
