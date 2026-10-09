<?php

declare(strict_types=1);

namespace App\Web\Service;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use function htmlspecialchars;
use function implode;
use function in_array;

use const ENT_QUOTES;
use const ENT_XML1;

final class PublicSitemapBuilder
{
    /**
     * @var list<string>
     */
    private const array PAGES = [
        'app_public_home',
        'app_public_pokedex',
        'app_public_about',
        'app_public_contact',
        'app_public_privacy',
        'app_public_terms',
    ];

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @param list<string>                       $locales
     * @param list<array{id: int, name: string}> $pokemons
     */
    public function build(array $locales, string $defaultLocale, array $pokemons): string
    {
        if (!in_array($defaultLocale, $locales, true) && [] !== $locales) {
            $defaultLocale = $locales[0];
        }

        $entries = [];

        foreach (self::PAGES as $route) {
            $entries[] = $this->entry(
                $locales,
                $defaultLocale,
                static fn (string $locale): array => ['_locale' => $locale],
                $route,
            );
        }

        foreach ($pokemons as $pokemon) {
            $entries[] = $this->entry(
                $locales,
                $defaultLocale,
                static fn (string $locale): array => ['_locale' => $locale, 'id' => $pokemon['id']],
                'app_public_pokemon',
            );
        }

        $body = implode("\n", $entries);

        return <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
            {$body}
            </urlset>
            XML;
    }

    /**
     * @param list<string>                                $locales
     * @param callable(string): array<string, int|string> $parametersFor
     */
    private function entry(array $locales, string $defaultLocale, callable $parametersFor, string $route): string
    {
        $locations = [];

        foreach ($locales as $locale) {
            $locations[$locale] = $this->urlGenerator->generate(
                $route,
                $parametersFor($locale),
                UrlGeneratorInterface::ABSOLUTE_URL,
            );
        }

        if ([] === $locations) {
            return '';
        }

        $lines = [];

        foreach ($locations as $locale => $location) {
            $alternates = [];

            foreach ($locations as $alternateLocale => $alternateLocation) {
                $alternates[] = $this->alternate($alternateLocale, $alternateLocation);
            }

            if (isset($locations[$defaultLocale])) {
                $alternates[] = $this->alternate('x-default', $locations[$defaultLocale]);
            }

            $alternateXml = implode('', $alternates);
            $lines[] = '<url><loc>' . $this->escape($location) . '</loc>' . $alternateXml . '</url>';
        }

        return implode("\n", $lines);
    }

    private function alternate(string $hreflang, string $href): string
    {
        return '<xhtml:link rel="alternate" hreflang="' . $this->escape($hreflang) . '" href="' . $this->escape($href) . '"/>';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
