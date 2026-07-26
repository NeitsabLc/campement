<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class ConnexionController extends AbstractController
{
    #[Route('/login', name: 'app_connexion', methods: ['GET', 'POST'])]
    public function connexion(AuthenticationUtils $authenticationUtils): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('app_tableau_de_bord');
        }

        return $this->render('securite/connexion.html.twig', [
            'derniere_adresse' => $authenticationUtils->getLastUsername(),
            'erreur' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/logout', name: 'app_deconnexion', methods: ['GET'])]
    public function deconnexion(): never
    {
        throw new \LogicException('Cette route est interceptée par le pare-feu Symfony.');
    }
}
