<?php

declare(strict_types=1);

namespace App\Web\Controller;

use App\Entity\Enum\SupportedLanguage;
use App\Web\Security\GoogleAuthorizationClient;
use App\Web\Security\GoogleOAuthCallbackUrl;
use App\Web\Security\GoogleOAuthSession;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

use function bin2hex;
use function random_bytes;

final class GoogleConnectController extends AbstractController
{
    public function __construct(
        private readonly GoogleAuthorizationClient $client,
        private readonly GoogleOAuthCallbackUrl $callbackUrl,
    ) {
    }

    #[Route(
        '/{_locale}/connect/google',
        name: 'app_connect_google_start',
        requirements: ['_locale' => SupportedLanguage::ROUTE_REQUIREMENT],
        methods: ['GET'],
    )]
    public function start(Request $request): RedirectResponse
    {
        $language = SupportedLanguage::tryFrom($request->getLocale()) ?? SupportedLanguage::Spanish;
        $returnRoute = 'login' === $request->query->getString('from')
            ? GoogleOAuthSession::LOGIN_ROUTE
            : GoogleOAuthSession::REGISTER_ROUTE;

        if (!$this->client->isConfigured()) {
            $this->addFlash('error', 'auth.google_unavailable');

            return $this->redirectToRoute($returnRoute, ['_locale' => $language->value]);
        }

        $state = bin2hex(random_bytes(16));
        $session = $request->getSession();
        $session->set(GoogleOAuthSession::STATE, $state);
        $session->set(GoogleOAuthSession::LOCALE, $language->value);
        $session->set(GoogleOAuthSession::RETURN_ROUTE, $returnRoute);

        return new RedirectResponse($this->client->authorizationUrl($state, $this->callbackUrl->absolute()));
    }

    #[Route('/connect/google/check', name: GoogleOAuthSession::CHECK_ROUTE, methods: ['GET'])]
    public function check(): never
    {
        throw new LogicException('Google authenticator intercepts this route.');
    }
}
