<?php

declare(strict_types=1);

namespace App\Security;

use App\Service\ContexteSejour;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsEventListener(event: KernelEvents::CONTROLLER, priority: 5)]
final class ModuleSejourSubscriber
{
    private const INTENDANCE = ['app_fournisseur', 'app_denree', 'app_menu', 'app_mouvement_stock', 'app_mouvements_stock', 'app_distribution'];
    private const SEJOUR_REQUIS = ['app_groupe'];

    public function __construct(private readonly ContexteSejour $contexte, private readonly UrlGeneratorInterface $urls) {}

    public function __invoke(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        $route = (string) $request->attributes->get('_route');
        $module = $this->correspondA($route, self::INTENDANCE) ? 'intendance' : null;
        if (null === $module && !$this->correspondA($route, self::SEJOUR_REQUIS)) { return; }
        $sejour = $this->contexte->actif();
        if (null === $sejour || ('intendance' === $module && !$sejour->isModuleIntendanceActif())) {
            $request->getSession()->getFlashBag()->add('error', null === $sejour ? 'Sélectionnez d’abord un séjour.' : 'Ce module n’est pas actif pour le séjour sélectionné.');
            $url = $this->urls->generate('app_tableau_de_bord');
            $event->setController(static fn () => new RedirectResponse($url));
        }
    }

    /** @param list<string> $prefixes */
    private function correspondA(string $route, array $prefixes): bool
    {
        return array_any($prefixes, static fn (string $prefixe): bool => str_starts_with($route, $prefixe));
    }
}
