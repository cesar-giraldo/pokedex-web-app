<?php

declare(strict_types=1);

namespace App\Web\Controller;

use App\Admin\Service\GeneralSettingsProvider;
use App\Entity\Enum\SupportedLanguage;
use App\Web\Service\PublicLanguageResolver;
use App\Web\Service\PublicPokemonCatalog;
use App\Web\Service\PublicSitemapBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use function array_map;

final class SeoController extends AbstractController
{
    public function __construct(
        private readonly GeneralSettingsProvider $settings,
        private readonly PublicLanguageResolver $languages,
        private readonly PublicPokemonCatalog $catalog,
        private readonly PublicSitemapBuilder $sitemapBuilder,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route('/robots.txt', name: 'app_public_robots', methods: ['GET'])]
    public function robots(): Response
    {
        $sitemap = $this->urlGenerator->generate('app_public_sitemap', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $body = "User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /api\n\nSitemap: {$sitemap}\n";

        return new Response($body, Response::HTTP_OK, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    #[Route('/sitemap.xml', name: 'app_public_sitemap', methods: ['GET'])]
    public function sitemap(): Response
    {
        $settings = $this->settings->get();
        $locales = array_map(
            static fn (SupportedLanguage $language): string => $language->value,
            $this->languages->enabled($settings),
        );
        $default = $this->languages->defaultLanguage($settings)->value;

        if ([] === $locales) {
            $locales = [$default];
        }

        return new Response(
            $this->sitemapBuilder->build($locales, $default, $this->catalog->visibleIdentities()),
            Response::HTTP_OK,
            ['Content-Type' => 'application/xml; charset=UTF-8'],
        );
    }
}
