<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        $headers = $response->headers;

        if (!$headers->has('X-Content-Type-Options')) {
            $headers->set('X-Content-Type-Options', 'nosniff');
        }
        if (!$headers->has('X-Frame-Options')) {
            $headers->set('X-Frame-Options', 'SAMEORIGIN');
        }
        if (!$headers->has('Referrer-Policy')) {
            $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        }
        if (!$headers->has('Permissions-Policy')) {
            $headers->set('Permissions-Policy', 'geolocation=(), camera=(), microphone=()');
        }
        if (!$headers->has('Content-Security-Policy')) {
            $headers->set(
                'Content-Security-Policy',
                "default-src 'self'; " .
                "base-uri 'self'; " .
                "img-src 'self' data: https:; " .
                "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com https://unpkg.com; " .
                "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://unpkg.com; " .
                "font-src 'self' https://fonts.gstatic.com; " .
                "connect-src 'self' https://cdn.jsdelivr.net https://unpkg.com; " .
                "frame-ancestors 'self'"
            );
        }
    }
}
