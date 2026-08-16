<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Utilisateur;
use App\Service\ContexteSejour;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsEventListener(event: KernelEvents::CONTROLLER, priority: 5)]
final class ModuleSejourSubscriber
{
    private const INTENDANCE = ['app_fournisseur', 'app_denree', 'app_menu', 'app_mouvement_stock', 'app_mouvements_stock', 'app_distribution'];
    private const ADMINISTRATIF = ['app_participant', 'app_presence'];
    private const SEJOUR_REQUIS = ['app_groupe'];
    private const SITUATIONS_PARTICULIERES = ['app_situation_particuliere', 'app_situations_particulieres'];

    public function __construct(
        private readonly ContexteSejour $contexte,
        private readonly UrlGeneratorInterface $urls,
        private readonly Security $security,
    ) {
    }

    public function __invoke(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        $route = (string) $request->attributes->get('_route');
        $module = $this->correspondA($route, self::INTENDANCE)
            ? 'intendance'
            : ($this->correspondA($route, self::ADMINISTRATIF)
                ? 'administratif'
                : ($this->correspondA($route, self::SITUATIONS_PARTICULIERES) ? 'situations_particulieres' : null));
        if (null === $module && !$this->correspondA($route, self::SEJOUR_REQUIS)) {
            return;
        }
        $sejour = $this->contexte->actif();
        if (null === $sejour
            || ('intendance' === $module && !$sejour->isModuleIntendanceActif())
            || ('administratif' === $module && !$sejour->isModuleAdministratifActif())
            || ('situations_particulieres' === $module && !$sejour->isModuleSituationsParticulieresActif())) {
            $session = $request->getSession();
            if ($session instanceof FlashBagAwareSessionInterface) {
                $message = null === $sejour
                    ? ($this->security->isGranted(Utilisateur::ROLE_GROUPE)
                        ? 'Votre compte ne possède actuellement aucun droit sur un séjour. Contactez les responsables de votre séjour.'
                        : 'Sélectionnez d’abord un séjour.')
                    : 'Ce module n’est pas actif pour le séjour sélectionné.';
                $session->getFlashBag()->add('error', $message);
            }
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
