<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class InformationsLegalesController extends AbstractController
{
    #[Route('/conditions-utilisation', name: 'app_conditions_utilisation', methods: ['GET'])]
    public function conditionsUtilisation(): Response
    {
        return $this->render('informations_legales/conditions_utilisation.html.twig');
    }

    #[Route('/politique-confidentialite', name: 'app_politique_confidentialite', methods: ['GET'])]
    public function politiqueConfidentialite(): Response
    {
        return $this->render('informations_legales/politique_confidentialite.html.twig');
    }
}
