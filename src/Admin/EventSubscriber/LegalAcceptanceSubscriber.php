<?php

declare(strict_types=1);

namespace App\Admin\EventSubscriber;

use App\Entity\User;
use App\Legal\LegalAcceptanceService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;

final class LegalAcceptanceSubscriber implements EventSubscriberInterface
{
    /**
     * @var list<string>
     */
    private const array ALLOWED_PATH_PREFIXES = [
        '/admin/legal/accept',
        '/admin/logout',
        '/_profiler',
        '/_wdt',
        '/assets/',
        '/admin/assets/',
    ];

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly LegalAcceptanceService $acceptanceService,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 7],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || $event->hasResponse()) {
            return;
        }

        $token = $this->tokenStorage->getToken();

        if (!$token || $token instanceof SwitchUserToken) {
            return;
        }

        $user = $token->getUser();

        if (!$user instanceof User || !$user->hasBackendAccess()) {
            return;
        }

        if (!$this->acceptanceService->hasPending($user)) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if ($this->isAllowedPath($path)) {
            return;
        }

        if ($request->isMethod('GET')) {
            $request->getSession()->set('legal_accept_target', $request->getRequestUri());
        }

        $event->setResponse(new RedirectResponse(
            $this->urlGenerator->generate('app_backend_legal_accept'),
        ));
    }

    private function isAllowedPath(string $path): bool
    {
        foreach (self::ALLOWED_PATH_PREFIXES as $allowedPrefix) {
            if (str_starts_with($path, $allowedPrefix)) {
                return true;
            }
        }

        return '/admin/login' === $path;
    }
}
