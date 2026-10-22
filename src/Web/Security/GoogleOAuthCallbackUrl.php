<?php

declare(strict_types=1);

namespace App\Web\Security;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class GoogleOAuthCallbackUrl
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function absolute(): string
    {
        return $this->urlGenerator->generate(
            GoogleOAuthSession::CHECK_ROUTE,
            [],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }
}
