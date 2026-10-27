<?php

declare(strict_types=1);

namespace App\Web\Service;

use App\Entity\Enum\UserStatus;
use App\Entity\Pokemon;
use App\Entity\User;
use App\Legal\LegalAcceptanceService;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use function is_string;
use function preg_match;

final class PendingFavoriteReturn
{
    public const string COOKIE_NAME = 'public_favorite_pokemon';

    public function __construct(
        private readonly PublicPokemonCatalog $catalog,
        private readonly LegalAcceptanceService $acceptance,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function redirectInsteadOf(User $user, Request $request, string $locale, Response $fallback): Response
    {
        if (!$this->canNavigate($user)) {
            return $fallback;
        }

        $raw = $request->cookies->get(self::COOKIE_NAME);

        if (!is_string($raw) || '' === $raw) {
            return $fallback;
        }

        $pokemonId = $this->pokemonId($raw);

        if (null === $pokemonId || !$this->catalog->findVisible($pokemonId) instanceof Pokemon) {
            $this->clearCookie($fallback, $request);

            return $fallback;
        }

        $response = new RedirectResponse($this->urlGenerator->generate('app_public_pokemon', [
            '_locale' => $locale,
            'id' => $pokemonId,
        ]));
        $this->clearCookie($response, $request);

        return $response;
    }

    private function canNavigate(User $user): bool
    {
        return UserStatus::Active === $user->getStatus() && !$this->acceptance->hasPending($user);
    }

    private function pokemonId(string $value): ?int
    {
        if (1 !== preg_match('/^[1-9][0-9]{0,9}$/', $value)) {
            return null;
        }

        return (int) $value;
    }

    private function clearCookie(Response $response, Request $request): void
    {
        $response->headers->clearCookie(
            self::COOKIE_NAME,
            '/',
            null,
            $request->isSecure(),
            false,
            Cookie::SAMESITE_LAX,
        );
    }
}
