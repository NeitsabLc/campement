<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ContexteSejour;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TableauDeBordController extends AbstractController
{
    #[Route('/', name: 'app_tableau_de_bord', methods: ['GET'])]
    public function index(ContexteSejour $contexte): Response
    {
        return $this->render('tableau_de_bord/index.html.twig', [
            'sejours' => $contexte->accessibles(),
            'sejour_selectionne' => $contexte->actif(),
        ]);
    }
}
