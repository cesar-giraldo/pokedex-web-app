<?php

declare(strict_types=1);

namespace App\Web\EventSubscriber;

use App\Entity\Enum\SupportedLanguage;
use App\Entity\Enum\UserRole;
use App\Entity\User;
use App\Legal\LegalAcceptanceService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;

use function is_string;
use function preg_match;
use function sprintf;
use function str_starts_with;

final class PublicLegalAcceptanceSubscriber implements EventSubscriberInterface
{
    /**
     * @var list<string>
     */
    private const array ALLOWED_PATH_PREFIXES = [
        '/assets/',
        '/_profiler',
        '/_wdt',
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

        $request = $event->getRequest();

        if ('security.firewall.map.context.public' !== $request->attributes->get('_firewall_context')) {
            return;
        }

        $token = $this->tokenStorage->getToken();

        if (!$token || $token instanceof SwitchUserToken) {
            return;
        }

        $user = $token->getUser();

        if (!$user instanceof User || !$user->hasAssignedRole(UserRole::User)) {
            return;
        }

        if (!$this->acceptanceService->hasPending($user)) {
            return;
        }

        if ($this->isAllowedPath($request->getPathInfo())) {
            return;
        }

        $event->setResponse(new RedirectResponse(
            $this->urlGenerator->generate('app_public_legal_accept', [
                '_locale' => $this->locale($request),
            ]),
        ));
    }

    private function isAllowedPath(string $path): bool
    {
        foreach (self::ALLOWED_PATH_PREFIXES as $allowedPrefix) {
            if (str_starts_with($path, $allowedPrefix)) {
                return true;
            }
        }

        $locale = SupportedLanguage::ROUTE_REQUIREMENT;

        return 1 === preg_match(sprintf('#^/(%s)/legal/accept$#', $locale), $path)
            || 1 === preg_match(sprintf('#^/(%s)/logout$#', $locale), $path)
            || 1 === preg_match(sprintf('#^/(%s)/impersonation/exit$#', $locale), $path)
            || 1 === preg_match(sprintf('#^/(%s)/profile/avatar$#', $locale), $path)
            || 1 === preg_match(sprintf('#^/(%s)/notifications/\d+/open$#', $locale), $path);
    }

    private function locale(Request $request): string
    {
        $locale = $request->attributes->get('_locale');

        if (is_string($locale) && '' !== $locale) {
            return $locale;
        }

        return $request->getLocale();
    }
}
