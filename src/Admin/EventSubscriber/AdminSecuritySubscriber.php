<?php

declare(strict_types=1);

namespace App\Admin\EventSubscriber;

use App\Entity\Enum\UserStatus;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

use function is_string;

final class AdminSecuritySubscriber implements EventSubscriberInterface
{
    private const string USERNAME_FIELD = '_username';

    /**
     * @var list<string>
     */
    private const array INCOMPLETE_PROFILE_ALLOWED_PATH_PREFIXES = [
        '/admin/profile',
        '/admin/logout',
        '/_profiler',
        '/_wdt',
        '/assets/',
        '/admin/assets/',
    ];

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginFailureEvent::class => 'onLoginFailure',
            LoginSuccessEvent::class => 'onLoginSuccess',
            KernelEvents::REQUEST => ['onKernelRequest', 8],
        ];
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $request = $event->getRequest();

        if ('app_admin_login' !== $request->attributes->get('_route')) {
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
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        $user->resetFailedLoginAttempts();

        if ($this->shouldRecordInteractiveLogin($event, $user)) {
            $user->recordSuccessfulInteractiveLogin($event->getRequest()->getClientIp());
        }

        $this->entityManager->flush();

        if (!$user->hasBackendAccess()) {
            $request = $event->getRequest();
            $session = $request->getSession();

            if ($session instanceof FlashBagAwareSessionInterface) {
                $session->getFlashBag()->add(
                    'error',
                    'No tienes acceso al panel de administración.',
                );
            }

            $this->tokenStorage->setToken(null);

            $event->setResponse(new RedirectResponse(
                $this->urlGenerator->generate('app_admin_login'),
            ));

            return;
        }

        if (UserStatus::UncompleteProfileInfo === $user->getStatus()) {
            $event->setResponse(new RedirectResponse(
                $this->urlGenerator->generate('app_backend_user_profile'),
            ));

            return;
        }

        $event->setResponse(new RedirectResponse(
            $this->urlGenerator->generate('app_backend_home'),
        ));
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->tokenStorage->getToken()?->getUser();

        if (!$user instanceof User) {
            return;
        }

        if (!$user->hasBackendAccess() || UserStatus::UncompleteProfileInfo !== $user->getStatus()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();

        if ($this->isIncompleteProfileAllowedPath($path)) {
            return;
        }

        $event->setResponse(new RedirectResponse(
            $this->urlGenerator->generate('app_backend_user_profile'),
        ));
    }

    private function isIncompleteProfileAllowedPath(string $path): bool
    {
        foreach (self::INCOMPLETE_PROFILE_ALLOWED_PATH_PREFIXES as $allowedPrefix) {
            if (str_starts_with($path, $allowedPrefix)) {
                return true;
            }
        }

        return '/admin/login' === $path;
    }

    private function shouldRecordInteractiveLogin(LoginSuccessEvent $event, User $user): bool
    {
        if (!$user->hasBackendAccess()) {
            return false;
        }

        if ($event->getAuthenticatedToken() instanceof SwitchUserToken) {
            return false;
        }

        $switchUser = $event->getRequest()->query->get('_switch_user')
            ?? $event->getRequest()->request->get('_switch_user');

        if (is_string($switchUser) && '' !== $switchUser) {
            return false;
        }

        return 'app_admin_login' === $event->getRequest()->attributes->get('_route');
    }
}
