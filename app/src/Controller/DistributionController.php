<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Service\ArchiveListesCourses;
use App\Service\ContexteSejour;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(Utilisateur::ROLE_GESTIONNAIRE)]
final class DistributionController extends AbstractController
{
    #[Route('/intendance/distribution', name: 'app_distribution', methods: ['GET', 'POST'])]
    public function index(Request $request, ContexteSejour $contexte, EntityManagerInterface $em): Response
    {
        $sejour = $contexte->actif();
        if (null === $sejour) { return $this->redirectToRoute('app_tableau_de_bord'); }
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('configurer_distribution_'.$sejour->getId(), $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }
            if ('renouveler' === $request->request->getString('action')) {
                $sejour->renouvelerJetonDistributionPublique();
                $message = 'Un nouveau lien public a été généré. L’ancien lien ne fonctionne plus.';
            } else {
                $sejour->setDistributionPubliqueActive($request->request->has('distribution_publique_active'));
                $sejour->setDistribuerGouterDejeuner($request->request->has('distribuer_gouter_dejeuner'));
                $message = 'La configuration de la distribution a bien été enregistrée.';
            }
            $em->flush();
            $this->addFlash('success', $message);
            return $this->redirectToRoute('app_distribution');
        }

        return $this->render('distribution/index.html.twig', [
            'sejour' => $sejour,
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
        $reponse->headers->addCacheControlDirective('no-store');
        return $reponse;
    }

    #[Route('/intendance/distribution/listes-courses', name: 'app_distribution_listes_courses', methods: ['GET'])]
    public function listesCourses(ContexteSejour $contexte, ArchiveListesCourses $archive): Response
    {
        $sejour = $contexte->actif();
        if (null === $sejour) {
            throw $this->createNotFoundException();
        }

        $reponse = new BinaryFileResponse($archive->generer($sejour));
        $reponse->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'listes-courses-'.$sejour->getDateDebut()->format('Y-m-d').'.zip',
        );
        $reponse->headers->set('Content-Type', 'application/zip');
        $reponse->headers->addCacheControlDirective('no-store');
        $reponse->deleteFileAfterSend();

        return $reponse;
    }
}
