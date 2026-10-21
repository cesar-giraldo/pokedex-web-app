<?php

declare(strict_types=1);

namespace App\Web\EventSubscriber;

use App\Admin\Service\GeneralSettingsProvider;
use App\Entity\Enum\SupportedLanguage;
use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\User;
use App\Legal\LegalLanguageResolver;
use App\Repository\UserRepository;
use App\Security\PublicUserImpersonator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Http\RememberMe\RememberMeHandlerInterface;
use Throwable;

use function is_string;
use function preg_match;
use function sprintf;
use function str_starts_with;

final class PublicSecuritySubscriber implements EventSubscriberInterface
{
    private const string FIREWALL = 'public';

    private const string USERNAME_FIELD = '_username';

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly PublicUserImpersonator $publicUserImpersonator,
        private readonly RememberMeHandlerInterface $rememberMeHandler,
        private readonly GeneralSettingsProvider $generalSettingsProvider,
        private readonly LegalLanguageResolver $legalLanguageResolver,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginFailureEvent::class => 'onLoginFailure',
            LoginSuccessEvent::class => [
                ['onLoginSuccess', 0],
                // RememberMeListener writes the cookie at -64.
                ['clearRememberMeForRejectedPublicLogin', -65],
            ],
            LogoutEvent::class => ['onLogout', 100],
            KernelEvents::REQUEST => [
                ['normalizeLoginNickname', 12],
                ['guardUnconfirmedAccount', 7],
                ['guardIncompleteProfile', 7],
            ],
            KernelEvents::EXCEPTION => ['redirectUnconfirmedAccessDenied', 0],
        ];
    }

    public function normalizeLoginNickname(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!$request->isMethod('POST') || 'app_public_login' !== $request->attributes->get('_route')) {
            return;
        }

        $nickname = $request->request->get(self::USERNAME_FIELD, '');

        if (!is_string($nickname)) {
            return;
        }

        $request->request->set(self::USERNAME_FIELD, User::normalizeNickname($nickname));
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        if (self::FIREWALL !== $event->getFirewallName()) {
            return;
        }

        $request = $event->getRequest();

        if ('app_public_login' !== $request->attributes->get('_route')) {
            return;
        }

        $nickname = User::normalizeNickname((string) $request->request->get(self::USERNAME_FIELD, ''));
        $user = $this->userRepository->findOneByNickname($nickname);

        if (!$user instanceof User) {
            return;
        }

        if ($user->isLoginTemporarilyBlocked()) {
            return;
        }

        if (!$event->getException() instanceof BadCredentialsException) {
            return;
        }

        $user->recordFailedLoginAttempt();
        $this->entityManager->flush();
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        if (self::FIREWALL !== $event->getFirewallName()) {
            return;
        }

        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        $user->resetFailedLoginAttempts();

        if ($user->hasAssignedRole(UserRole::User)) {
            $user->recordSuccessfulInteractiveLogin($event->getRequest()->getClientIp());
        }

        $this->entityManager->flush();

        $requestLocale = $this->locale($event->getRequest());

        if (!$user->hasAssignedRole(UserRole::User)) {
            $session = $event->getRequest()->getSession();

            if ($session instanceof FlashBagAwareSessionInterface) {
                $session->getFlashBag()->add('error', 'auth.no_public_access');
            }

            $this->tokenStorage->setToken(null);

            $event->setResponse(new RedirectResponse(
                $this->urlGenerator->generate('app_public_login', ['_locale' => $requestLocale]),
            ));

            return;
        }

        $locale = $this->legalLanguageResolver
            ->preferredForUser($user, $this->generalSettingsProvider->get())
            ->value;

        if (UserStatus::UnconfirmedAccount === $user->getStatus()) {
            $event->setResponse(new RedirectResponse(
                $this->urlGenerator->generate('app_public_account_confirm', ['_locale' => $locale]),
            ));

            return;
        }

        if (UserStatus::UncompleteProfileInfo === $user->getStatus()) {
            $event->setResponse(new RedirectResponse(
                $this->urlGenerator->generate('app_public_profile', ['_locale' => $locale]),
            ));

            return;
        }

        $event->setResponse(new RedirectResponse(
            $this->urlGenerator->generate('app_public_home', ['_locale' => $locale]),
        ));
    }

    public function clearRememberMeForRejectedPublicLogin(LoginSuccessEvent $event): void
    {
        if (self::FIREWALL !== $event->getFirewallName()) {
            return;
        }

        $user = $event->getUser();

        if (!$user instanceof User || $user->hasAssignedRole(UserRole::User)) {
            return;
        }

        $this->rememberMeHandler->clearRememberMeCookie();
    }

    public function onLogout(LogoutEvent $event): void
    {
        $request = $event->getRequest();

        if ('security.firewall.map.context.public' !== $request->attributes->get('_firewall_context')) {
            return;
        }

        if (!$event->getToken() instanceof SwitchUserToken) {
            return;
        }

        $this->publicUserImpersonator->stop($request);

        $event->setResponse(new RedirectResponse(
            $this->urlGenerator->generate('app_backend_users'),
        ));
    }

    public function guardUnconfirmedAccount(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || $event->hasResponse()) {
            return;
        }

        $request = $event->getRequest();

        if ('security.firewall.map.context.public' !== $request->attributes->get('_firewall_context')) {
            return;
        }

        $user = $this->tokenStorage->getToken()?->getUser();

        if (!$user instanceof User || !$user->hasAssignedRole(UserRole::User)) {
            return;
        }

        if (UserStatus::UnconfirmedAccount !== $user->getStatus()) {
            return;
        }

        if ($this->isUnconfirmedAccountAllowedPath($request->getPathInfo())) {
            return;
        }

        $event->setResponse(new RedirectResponse(
            $this->urlGenerator->generate('app_public_account_confirm', [
                '_locale' => $this->legalLanguageResolver
                    ->preferredForUser($user, $this->generalSettingsProvider->get())
                    ->value,
            ]),
        ));
    }

    public function redirectUnconfirmedAccessDenied(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if ('security.firewall.map.context.public' !== $request->attributes->get('_firewall_context')) {
            return;
        }

        if (!$this->isAccessDenied($event->getThrowable())) {
            return;
        }

        $user = $this->tokenStorage->getToken()?->getUser();

        if (!$user instanceof User || !$user->hasAssignedRole(UserRole::User)) {
            return;
        }

        if (UserStatus::UnconfirmedAccount !== $user->getStatus()) {
            return;
        }

        $event->setResponse(new RedirectResponse(
            $this->urlGenerator->generate('app_public_account_confirm', [
                '_locale' => $this->legalLanguageResolver
                    ->preferredForUser($user, $this->generalSettingsProvider->get())
                    ->value,
            ]),
        ));
    }

    public function guardIncompleteProfile(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if ('security.firewall.map.context.public' !== $request->attributes->get('_firewall_context')) {
            return;
        }

        $user = $this->tokenStorage->getToken()?->getUser();

        if (!$user instanceof User || !$user->hasAssignedRole(UserRole::User)) {
            return;
        }

        if (UserStatus::UncompleteProfileInfo !== $user->getStatus()) {
            return;
        }

        $path = $request->getPathInfo();

        if ($this->isIncompleteProfileAllowedPath($path)) {
            return;
        }

        $event->setResponse(new RedirectResponse(
            $this->urlGenerator->generate('app_public_profile', [
                '_locale' => $this->legalLanguageResolver
                    ->preferredForUser($user, $this->generalSettingsProvider->get())
                    ->value,
            ]),
        ));
    }

    private function isUnconfirmedAccountAllowedPath(string $path): bool
    {
        $locale = SupportedLanguage::ROUTE_REQUIREMENT;

        if (1 === preg_match(sprintf('#^/(%s)/account/confirm(?:/resend)?$#', $locale), $path)) {
            return true;
        }

        if (1 === preg_match(sprintf('#^/(%s)/logout$#', $locale), $path)) {
            return true;
        }

        if (1 === preg_match(sprintf('#^/(%s)/impersonation/exit$#', $locale), $path)) {
            return true;
        }

        return str_starts_with($path, '/assets/')
            || str_starts_with($path, '/_profiler')
            || str_starts_with($path, '/_wdt');
    }

    private function isAccessDenied(Throwable $throwable): bool
    {
        do {
            if ($throwable instanceof AccessDeniedException || $throwable instanceof AccessDeniedHttpException) {
                return true;
            }
        } while (null !== $throwable = $throwable->getPrevious());

        return false;
    }

    private function isIncompleteProfileAllowedPath(string $path): bool
    {
        $locale = SupportedLanguage::ROUTE_REQUIREMENT;

        if (1 === preg_match(sprintf('#^/(%s)/profile(?:/avatar|/image/(?:avatar|display|original))?$#', $locale), $path)) {
            return true;
        }

        if (1 === preg_match(sprintf('#^/(%s)/logout$#', $locale), $path)) {
            return true;
        }

        if (1 === preg_match(sprintf('#^/(%s)/impersonation/exit$#', $locale), $path)) {
            return true;
        }

        return str_starts_with($path, '/assets/')
            || str_starts_with($path, '/_profiler')
            || str_starts_with($path, '/_wdt');
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
