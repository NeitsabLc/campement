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
    public function index(Request $request, ContexteSejour $sejours, DenreeRepository $denrees): Response
    {
        $sejour = $sejours->actif();
        $actives = !$request->query->getBoolean('desactivees');

        return $this->render('denree/index.html.twig', [
            'sejour' => $sejour,
            'actives' => $actives,
            'denrees' => null === $sejour ? [] : $denrees->findPourGestion($sejour, $actives),
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
        $donnees = $this->donneesInitiales($denree, $references, $conditionnements);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('enregistrer_denree', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }
            $donnees = ['nom' => trim($request->request->getString('nom')), 'unite' => $request->request->getString('unite'), 'fournisseurs' => $request->request->all('fournisseurs')];
            $unite = Uuid::isValid($donnees['unite']) ? $unites->find($donnees['unite']) : null;
            if ('' === $donnees['nom'] || mb_strlen($donnees['nom']) > 150) {
                $erreurs[] = 'Le nom de la denrée est obligatoire et limité à 150 caractères.';
            } elseif ($denrees->existeAvecNomPourSejour($denree->getSejour(), $donnees['nom'], $creation ? null : $denree)) {
                $erreurs[] = 'Une denrée portant ce nom existe déjà.';
            }
            if (null === $unite || !$unite->isActif()) {
                $erreurs[] = 'Sélectionnez une unité de référence active.';
            }

            $fournisseursValides = [];
            $fournisseursSelectionnes = [];
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
                if (null === $fournisseur || !$fournisseur->isActif() || $fournisseur->getSejour() !== $denree->getSejour() || '' === $reference) {
                    $erreurs[] = sprintf('Complétez le fournisseur et sa référence dans le bloc %d.', $index + 1);
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
                    $quantite = str_replace(',', '.', trim((string) ($niveau['quantite'] ?? '')));
                    $libelle = trim((string) ($niveau['libelle'] ?? ''));
                    $uniteNiveau = ($niveauIndex === array_key_last($niveaux)) ? $unite : null;
                    if ('' === $libelle || !is_numeric($quantite) || (float) $quantite <= 0 || ($niveauIndex === array_key_last($niveaux) && null === $uniteNiveau)) {
                        $erreurs[] = sprintf('Le niveau %d du fournisseur %s est incomplet.', $niveauIndex + 1, $fournisseur->getNom());
                    }
                }
                $fournisseursValides[] = [$ligne, $fournisseur, $reference, $niveaux];
            }

            if ([] === $erreurs && null !== $unite) {
                $denree->setNom($donnees['nom'])->setUniteReference($unite);
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
                    $reference = $existantes[$id] ?? new ReferenceFournisseur($fournisseur, $denree, $referenceTexte, $denree->getNom());
                    unset($existantes[$id]);
                    $reference->setFournisseur($fournisseur)->setReference($referenceTexte)->setDesignation($denree->getNom())->setActif(true);
                    $em->persist($reference);
                    $niveauxExistants = [];
                    foreach ($conditionnements->findPourReference($reference) as $niveauExistant) { $niveauxExistants[(string) $niveauExistant->getId()] = $niveauExistant; }
                    foreach (array_values($niveaux) as $ordre => $niveau) {
                        $niveauId = (string) ($niveau['id'] ?? '');
                        $dernier = $ordre === count($niveaux) - 1;
                        $uniteNiveau = $dernier ? $unite : null;
                        $libelleContenu = $dernier ? null : trim((string) $niveaux[$ordre + 1]['libelle']);
                        $conditionnement = $niveauxExistants[$niveauId] ?? new ReferenceFournisseurConditionnement($reference, $ordre + 1, trim((string) $niveau['libelle']), str_replace(',', '.', (string) $niveau['quantite']), $uniteNiveau, $libelleContenu);
                        unset($niveauxExistants[$niveauId]);
                        $conditionnement->setOrdre($ordre + 1)->setLibelle(trim((string) $niveau['libelle']))->setQuantiteContenu(str_replace(',', '.', (string) $niveau['quantite']))->setUniteContenu($uniteNiveau)->setLibelleContenu($libelleContenu);
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

        return $this->render('denree/form.html.twig', ['denree' => $denree, 'creation' => $creation, 'donnees' => $donnees, 'erreurs' => $erreurs, 'unites' => $unites->findActifs(), 'fournisseurs' => $fournisseurs->findActifsPourSejour($denree->getSejour()), 'references_archivees' => $referencesArchivees]);
    }

    private function donneesInitiales(Denree $denree, ReferenceFournisseurRepository $references, ReferenceFournisseurConditionnementRepository $conditionnements): array
    {
        $resultat = ['nom' => '', 'unite' => null, 'fournisseurs' => []];
        try { $resultat['nom'] = $denree->getNom(); $resultat['unite'] = (string) $denree->getUniteReference()->getId(); } catch (\Error) {}
        if ('' === $resultat['nom']) { return $resultat; }
        foreach ($references->findPourDenree($denree) as $reference) {
            if (!$reference->isActif() || !$reference->getFournisseur()->isActif()) { continue; }
            $ligne = ['id' => (string) $reference->getId(), 'fournisseur' => (string) $reference->getFournisseur()->getId(), 'reference' => $reference->getReference(), 'niveaux' => []];
            foreach ($conditionnements->findPourReference($reference) as $niveau) { $ligne['niveaux'][] = ['id' => (string) $niveau->getId(), 'libelle' => $niveau->getLibelle(), 'quantite' => $niveau->getQuantiteContenu()]; }
            $resultat['fournisseurs'][] = $ligne;
        }
        return $resultat;
    }
}
