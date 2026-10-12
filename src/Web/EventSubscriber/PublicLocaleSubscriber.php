<?php

declare(strict_types=1);

namespace App\Web\EventSubscriber;

use App\Admin\Service\GeneralSettingsProvider;
use App\Web\Service\PublicLanguageResolver;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use function is_array;
use function is_string;

final class PublicLocaleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly GeneralSettingsProvider $settings,
        private readonly PublicLanguageResolver $languages,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 20],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $locale = $request->attributes->get('_locale');

        if (!is_string($locale) || '' === $locale) {
            return;
        }

        $settings = $this->settings->get();

        if ($this->languages->isEnabled($locale, $settings)) {
            return;
        }

        $default = $this->languages->defaultLanguage($settings);

        if (!$this->languages->isEnabled($default->value, $settings) || $default->value === $locale) {
            return;
        }

        $route = $request->attributes->get('_route');

        if (!is_string($route) || '' === $route) {
            return;
        }

        $params = $request->attributes->get('_route_params', []);

        if (!is_array($params)) {
            $params = [];
        }

        $params['_locale'] = $default->value;
        $url = $this->urlGenerator->generate($route, $params);
        $query = $request->getQueryString();

        if (is_string($query) && '' !== $query) {
            $url .= '?' . $query;
        }

        $event->setResponse(new RedirectResponse($url));
    }
}
