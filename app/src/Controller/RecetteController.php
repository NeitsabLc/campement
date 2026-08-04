<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Recette;
use App\Entity\RecetteDenree;
use App\Entity\RecetteDenreeQuantite;
use App\Entity\Utilisateur;
use App\Repository\DenreeRepository;
use App\Repository\RecetteRepository;
use App\Repository\SejourPublicCibleRepository;
use App\Repository\UniteRepository;
use App\Service\ContexteSejour;
use App\Service\ConversionConditionnement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[IsGranted(Utilisateur::ROLE_GESTIONNAIRE)]
final class RecetteController extends AbstractController
{
    #[Route('/recettes', name: 'app_recettes', methods: ['GET'])]
    public function index(Request $request, ContexteSejour $contexte, RecetteRepository $recettes): Response
    {
        $sejour = $contexte->actif();
        $actives = !$request->query->getBoolean('desactivees');

        return $this->render('recette/index.html.twig', [
            'sejour' => $sejour,
            'actives' => $actives,
            'recettes' => null === $sejour ? [] : $recettes->findPourGestion($sejour, $actives),
        ]);
    }

    #[Route('/recettes/{id}/supprimer', name: 'app_recette_supprimer', methods: ['POST'])]
    public function supprimer(string $id, Request $request, ContexteSejour $contexte, RecetteRepository $recettes, EntityManagerInterface $entityManager): Response
    {
        $sejour = $contexte->actif();
        $recette = Uuid::isValid($id) ? $recettes->find($id) : null;
        if (null === $sejour || null === $recette || $recette->getSejour() !== $sejour) throw $this->createNotFoundException();
        if (!$this->isCsrfTokenValid('supprimer_recette_'.$id, $request->request->getString('_token'))) throw $this->createAccessDeniedException();
        // Les menus existants conservent ainsi leur référence vers la recette.
        $recette->setActif(!$recette->isActif());
        $entityManager->flush();
        $this->addFlash('success', sprintf('La recette « %s » a été %s.', $recette->getNom(), $recette->isActif() ? 'réactivée' : 'désactivée'));
        return $this->redirectToRoute('app_recettes', $recette->isActif() ? [] : ['desactivees' => 1]);
    }

    #[Route('/recettes/ajouter', name: 'app_recette_ajouter', methods: ['GET', 'POST'])]
    #[Route('/recettes/{id}/modifier', name: 'app_recette_modifier', methods: ['GET', 'POST'])]
    public function formulaire(
        Request $request,
        ContexteSejour $contexte,
        RecetteRepository $recettes,
        DenreeRepository $denrees,
        SejourPublicCibleRepository $publics,
        UniteRepository $unites,
        ConversionConditionnement $conversion,
        EntityManagerInterface $entityManager,
        ?string $id = null,
    ): Response {
        $sejour = $contexte->actif();
        $recette = null !== $id && Uuid::isValid($id) ? $recettes->find($id) : null;
        if (null === $sejour || (null !== $id && (null === $recette || $recette->getSejour() !== $sejour))) {
            throw $this->createNotFoundException();
        }

        $recette ??= new Recette($sejour);
        $publicsActifs = $publics->findActifsPourSejour($sejour);
        $denreesActives = $denrees->findActifsPourSejour($sejour);
        $erreurs = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('enregistrer_recette', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $nom = trim($request->request->getString('nom'));
            if ('' === $nom) {
                $erreurs[] = 'Le nom est obligatoire.';
            }
            $categorie = $request->request->getString('categorie');
            if (!in_array($categorie, Recette::CATEGORIES, true)) {
                $erreurs[] = 'La catégorie est obligatoire.';
            }

            $composition = [];
            foreach (array_values($request->request->all('lignes')) as $index => $donnees) {
                if (!is_array($donnees)) {
                    continue;
                }

                $denreeId = (string) ($donnees['denree'] ?? '');
                $uniteId = (string) ($donnees['conditionnement'] ?? '');
                $denree = Uuid::isValid($denreeId) ? $denrees->find($denreeId) : null;
                $unite = Uuid::isValid($uniteId) ? $unites->find($uniteId) : null;
                if (null === $denree
                    || $denree->getSejour() !== $sejour
                    || null === $unite
                    || !in_array($unite, $conversion->conditionnementsPour($denree), true)) {
                    $erreurs[] = sprintf('Ligne %d invalide.', $index + 1);
                    continue;
                }

                $quantites = [];
                foreach ($publicsActifs as $public) {
                    $valeur = str_replace(',', '.', trim((string) ($donnees['quantites'][(string) $public->getId()] ?? '')));
                    if (!is_numeric($valeur) || (float) $valeur < 0) {
                        $erreurs[] = sprintf('Quantité invalide ligne %d.', $index + 1);
                        continue 2;
                    }
                    $quantites[(string) $public->getId()] = number_format((float) $valeur, 3, '.', '');
                }
                $composition[] = ['denree' => $denree, 'conditionnement' => $unite, 'quantites' => $quantites];
            }

            if ([] === $composition) {
                $erreurs[] = 'Ajoutez au moins une denrée.';
            }
            if ([] === $erreurs) {
                $recette->setNom($nom)->setCategorie($categorie);
                foreach ($recette->getDenrees()->toArray() as $ancienne) {
                    $recette->removeDenree($ancienne);
                }
                foreach ($composition as $ordre => $donnees) {
                    $ligne = (new RecetteDenree())
                        ->setDenree($donnees['denree'])
                        ->setConditionnement($donnees['conditionnement'])
                        ->setOrdre($ordre);
                    foreach ($publicsActifs as $public) {
                        $ligne->addQuantite((new RecetteDenreeQuantite())
                            ->setSejourPublicCible($public)
                            ->setQuantiteIndividuelle($donnees['quantites'][(string) $public->getId()]));
                    }
                    $recette->addDenree($ligne);
                }

                $entityManager->persist($recette);
                $entityManager->flush();
                $this->addFlash('success', 'La recette a bien été enregistrée.');

                return $this->redirectToRoute('app_recettes');
            }
        }

        $catalogue = [];
        foreach ($denreesActives as $denree) {
            $catalogue[(string) $denree->getId()] = array_map(
                static fn ($unite): array => ['id' => (string) $unite->getId(), 'nom' => $unite->getNom()],
                $conversion->conditionnementsPour($denree),
            );
        }

        return $this->render('recette/form.html.twig', [
            'recette' => $recette,
            'denrees' => $denreesActives,
            'publics' => $publicsActifs,
            'catalogue' => $catalogue,
            'erreurs' => $erreurs,
        ]);
    }
}
