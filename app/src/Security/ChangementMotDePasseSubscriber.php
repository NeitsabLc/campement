<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Utilisateur;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsEventListener(event: 'kernel.request', priority: -10)]
final class ChangementMotDePasseSubscriber
{
    public function __construct(
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $utilisateur = $this->security->getUser();
        $route = $event->getRequest()->attributes->getString('_route');
        if ($utilisateur instanceof Utilisateur
            && $utilisateur->isChangementMotDePasseRequis()
            && !in_array($route, ['app_modifier_mot_de_passe', 'app_deconnexion'], true)) {
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_modifier_mot_de_passe')));
        }
    }
}
