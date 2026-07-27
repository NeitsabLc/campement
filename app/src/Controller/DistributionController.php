<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\SejourTypeRepas;
use App\Entity\Utilisateur;
use App\Repository\SejourTypeRepasRepository;
use App\Repository\TypeRepasRepository;
use App\Service\ContexteSejour;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(Utilisateur::ROLE_GESTIONNAIRE)]
final class DistributionController extends AbstractController
{
    #[Route('/intendance/distribution', name: 'app_distribution', methods: ['GET', 'POST'])]
    public function index(Request $request, ContexteSejour $contexte, SejourTypeRepasRepository $repas, TypeRepasRepository $typesRepasRepository, EntityManagerInterface $em): Response
    {
        $sejour = $contexte->actif();
        if (null === $sejour) { return $this->redirectToRoute('app_tableau_de_bord'); }
        $typesRepas = $typesRepasRepository->findActifs();
        $associations = [];
        foreach ($repas->findPourSejour($sejour) as $association) {
            $associations[(string) $association->getTypeRepas()->getId()] = $association;
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('configurer_distribution_'.$sejour->getId(), $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }
            if ('renouveler' === $request->request->getString('action')) {
                $sejour->renouvelerJetonDistributionPublique();
                $message = 'Un nouveau lien public a été généré. L’ancien lien ne fonctionne plus.';
            } else {
                $sejour->setDistributionPubliqueActive($request->request->has('distribution_publique_active'));
                $actifs = $request->request->all('repas');
                foreach ($typesRepas as $typeRepas) {
                    $id = (string) $typeRepas->getId();
                    $active = isset($actifs[$id]);
                    $association = $associations[$id] ?? null;
                    if ($active && null === $association) {
                        $association = new SejourTypeRepas($sejour, $typeRepas, $typeRepas->getOrdre());
                        $em->persist($association);
                    }
                    if (null !== $association) {
                        if ($active) { $association->setActif(true); }
                        $association->setDistributionActive($active);
                    }
                }
                $message = 'La configuration de la distribution a bien été enregistrée.';
            }
            $em->flush();
            $this->addFlash('success', $message);
            return $this->redirectToRoute('app_distribution');
        }

        return $this->render('distribution/index.html.twig', [
            'sejour' => $sejour,
            'repas' => array_map(static function ($typeRepas) use ($associations): array {
                $association = $associations[(string) $typeRepas->getId()] ?? null;
                return [
                    'type' => $typeRepas,
                    'selectionne' => null !== $association && $association->isActif() && $association->isDistributionActive(),
                ];
            }, $typesRepas),
            'lien_public' => $this->generateUrl('app_sortie_consommation', ['jeton' => $sejour->getJetonDistributionPublique()], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
    }

    #[Route('/intendance/distribution/qr-code', name: 'app_distribution_qr_code', methods: ['GET'])]
    public function qrCode(Request $request, ContexteSejour $contexte): Response
    {
        $sejour = $contexte->actif();
        if (null === $sejour) { throw $this->createNotFoundException(); }
        $url = $this->generateUrl('app_sortie_consommation', ['jeton' => $sejour->getJetonDistributionPublique()], UrlGeneratorInterface::ABSOLUTE_URL);
        $resultat = (new SvgWriter())->write(new QrCode(
            data: $url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 420,
            margin: 16,
            foregroundColor: new Color(0, 58, 93),
            backgroundColor: new Color(255, 255, 255),
        ));
        $reponse = new Response($resultat->getString(), Response::HTTP_OK, ['Content-Type' => 'image/svg+xml']);
        if ($request->query->getBoolean('telecharger')) {
            $reponse->headers->set('Content-Disposition', 'attachment; filename="qr-distribution-'.$sejour->getId().'.svg"');
        }
        return $reponse;
    }
}
