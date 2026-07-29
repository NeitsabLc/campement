<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ContentSecurityPolicySubscriber
{
    private const REQUEST_ATTRIBUTE = 'csp_nonce';

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 256)]
    public function initializeNonce(RequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            $event->getRequest()->attributes->set(self::REQUEST_ATTRIBUTE, base64_encode(random_bytes(18)));
        }
    }

    #[AsEventListener(event: KernelEvents::RESPONSE, priority: -256)]
    public function addPolicy(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $nonce = $event->getRequest()->attributes->getString(self::REQUEST_ATTRIBUTE);
        if ('' === $nonce) {
            return;
        }

        $event->getResponse()->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "connect-src 'self'",
            "font-src 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "img-src 'self' data:",
            "object-src 'none'",
            "script-src 'self' 'nonce-{$nonce}'",
            "style-src 'self'",
        ]));
    }
}
