<?php

declare(strict_types=1);

namespace App\Web\Security;

use App\Entity\Enum\SupportedLanguage;
use App\Entity\Enum\UserStatus;
use App\Entity\User;
use App\Web\Service\GoogleAccountConnector;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

use function in_array;
use function is_string;
use function strlen;

final class GoogleAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly GoogleAuthorizationClient $client,
        private readonly GoogleIdTokenVerifier $verifier,
        private readonly GoogleAccountConnector $connector,
        private readonly GoogleOAuthCallbackUrl $callbackUrl,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function supports(Request $request): bool
    {
        return GoogleOAuthSession::CHECK_ROUTE === $request->attributes->get('_route');
    }

    public function authenticate(Request $request): Passport
    {
        try {
            $identity = $this->identity($request);
            $user = $this->connector->connect($identity, $this->language($request));
        } catch (GoogleSignInException $exception) {
            $this->logger->info('Google sign-in was rejected.', [
                'reason' => $exception->getMessage(),
            ]);

            throw new CustomUserMessageAuthenticationException('auth.google_failed', previous: $exception);
        }

        return new SelfValidatingPassport(new UserBadge(
            $user->getUserIdentifier(),
            static fn (): User => $user,
        ));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): Response
    {
        $user = $token->getUser();
        $route = $user instanceof User && UserStatus::UncompleteProfileInfo === $user->getStatus()
            ? 'app_public_profile'
            : 'app_public_home';

        return new RedirectResponse($this->urlGenerator->generate($route, [
            '_locale' => $this->language($request)->value,
        ]));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $session = $request->getSession();

        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('error', 'auth.google_failed');
        }

        return new RedirectResponse($this->urlGenerator->generate($this->returnRoute($request), [
            '_locale' => $this->language($request)->value,
        ]));
    }

    private function identity(Request $request): GoogleIdentity
    {
        $session = $request->getSession();
        $expected = $session->get(GoogleOAuthSession::STATE);
        $given = $request->query->getString('state');
        $session->remove(GoogleOAuthSession::STATE);

        if (
            !is_string($expected)
            || '' === $expected
            || strlen($expected) !== strlen($given)
            || !hash_equals($expected, $given)
            || '' !== $request->query->getString('error')
        ) {
            throw new GoogleSignInException('The Google sign-in state does not match.');
        }

        $code = $request->query->getString('code');

        if ('' === $code || strlen($code) > 2048) {
            throw new GoogleSignInException('The Google authorization code is missing.');
        }

        return $this->verifier->verify($this->client->fetchIdToken($code, $this->callbackUrl->absolute()));
    }

    private function language(Request $request): SupportedLanguage
    {
        $stored = $request->getSession()->get(GoogleOAuthSession::LOCALE);

        if (!is_string($stored)) {
            return SupportedLanguage::Spanish;
        }

        return SupportedLanguage::tryFrom($stored) ?? SupportedLanguage::Spanish;
    }

    private function returnRoute(Request $request): string
    {
        $stored = $request->getSession()->get(GoogleOAuthSession::RETURN_ROUTE);

        if (!is_string($stored) || !in_array($stored, [GoogleOAuthSession::REGISTER_ROUTE, GoogleOAuthSession::LOGIN_ROUTE], true)) {
            return GoogleOAuthSession::REGISTER_ROUTE;
        }

        return $stored;
    }
}
