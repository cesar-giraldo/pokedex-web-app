<?php

declare(strict_types=1);

namespace App\Web\Service;

use App\Admin\Service\GeneralSettingsProvider;
use App\Entity\Pokemon;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function is_string;
use function json_encode;
use function mb_strlen;
use function mb_substr;
use function preg_replace;
use function strip_tags;
use function trim;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final class PublicStructuredData
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
        private readonly GeneralSettingsProvider $settings,
    ) {
    }

    public function websiteJson(string $locale): string
    {
        $home = $this->absolute('app_public_home', ['_locale' => $locale]);
        $search = $this->absolute('app_public_pokedex', ['_locale' => $locale]);

        return $this->encode([
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $this->siteName(),
            'url' => $home,
            'inLanguage' => $locale,
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => $search . '?q={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ]);
    }

    public function collectionJson(string $locale, string $name, string $description): string
    {
        return $this->encode([
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => $name,
            'description' => $description,
            'url' => $this->absolute('app_public_pokedex', ['_locale' => $locale]),
            'inLanguage' => $locale,
            'isPartOf' => $this->websiteNode($locale),
        ]);
    }

    public function webPageJson(string $locale, string $route, string $name, string $description): string
    {
        return $this->encode([
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $name,
            'description' => $description,
            'url' => $this->absolute($route, ['_locale' => $locale]),
            'inLanguage' => $locale,
            'isPartOf' => $this->websiteNode($locale),
        ]);
    }

    /**
     * @param list<string> $imageUrls
     */
    public function pokemonJson(string $locale, Pokemon $pokemon, array $imageUrls): string
    {
        $description = $this->excerpt(
            $pokemon->getDescription(),
            $this->translator->trans('pokemon.meta_fallback', ['name' => $pokemon->getName()]),
        );

        $about = [
            '@type' => 'Thing',
            'name' => $pokemon->getName(),
            'description' => $description,
        ];

        if ([] !== $imageUrls) {
            $about['image'] = $imageUrls;
        }

        $payload = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $pokemon->getName(),
            'description' => $description,
            'url' => $this->absolute('app_public_pokemon', [
                '_locale' => $locale,
                'id' => $pokemon->getId(),
            ]),
            'inLanguage' => $locale,
            'isPartOf' => $this->websiteNode($locale),
            'about' => $about,
        ];

        if ([] !== $imageUrls) {
            $payload['primaryImageOfPage'] = [
                '@type' => 'ImageObject',
                'url' => $imageUrls[0],
            ];
        }

        return $this->encode($payload);
    }

    public function excerpt(?string $description, string $fallback): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim(strip_tags((string) $description)));
        $text = trim(is_string($normalized) ? $normalized : '');

        if ('' === $text) {
            return $fallback;
        }

        if (mb_strlen($text) <= 160) {
            return $text;
        }

        return mb_substr($text, 0, 157) . '...';
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function absolute(string $route, array $parameters): string
    {
        return $this->urlGenerator->generate($route, $parameters, UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /**
     * @return array<string, string>
     */
    private function websiteNode(string $locale): array
    {
        return [
            '@type' => 'WebSite',
            'name' => $this->siteName(),
            'url' => $this->absolute('app_public_home', ['_locale' => $locale]),
        ];
    }

    private function siteName(): string
    {
        $name = $this->settings->get()->getPlatformName();

        if (null !== $name && '' !== $name) {
            return $name;
        }

        return $this->translator->trans('site.name_fallback');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function encode(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
