<?php

namespace TdsProviderBundle\EventSubscriber;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SourceUrlCookieSetterSubscriber implements EventSubscriberInterface
{
    public function onKernelResponse(ResponseEvent $event): void
    {
        if ($event->getRequest()->cookies->get('source_url')) {
            return;
        }

        $referer = $event->getRequest()->headers->get('referer');

        if ($referer) {
            $cookie = new Cookie(
                'source_url',
                $referer,
                (new \DateTimeImmutable())->modify('+2 years')
            );

            $event->getResponse()->headers->setCookie($cookie);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => [['onKernelResponse']],
        ];
    }
}
