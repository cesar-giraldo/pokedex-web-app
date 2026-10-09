<?php

declare(strict_types=1);

namespace App\Web\Twig;

use App\Admin\Service\GeneralSettingsProvider;
use App\Admin\Service\Storage\PlatformBrandingAsset;
use App\Entity\Enum\SupportedLanguage;
use App\Web\Service\PublicLanguageResolver;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\ExceptionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

use function array_key_exists;
use function is_array;
use function is_string;
use function str_starts_with;

final class PublicSiteExtension extends AbstractExtension
{
    public function __construct(
        private readonly GeneralSettingsProvider $settings,
        private readonly PublicLanguageResolver $languageResolver,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('public_site_name', $this->siteName(...)),
            new TwigFunction('public_site_slogan', $this->siteSlogan(...)),
            new TwigFunction('public_icon_url', $this->iconUrl(...)),
            new TwigFunction('public_languages', $this->languages(...)),
            new TwigFunction('public_default_locale', $this->defaultLocale(...)),
            new TwigFunction('public_alternate_url', $this->alternateUrl(...)),
            new TwigFunction('public_canonical_url', $this->canonicalUrl(...)),
            new TwigFunction('public_og_locale', $this->ogLocale(...)),
            new TwigFunction('public_absolute_url', $this->absoluteUrl(...)),
        ];
    }

    public function siteName(): string
    {
        $name = $this->settings->get()->getPlatformName();

        if (null !== $name && '' !== $name) {
            return $name;
        }

        return $this->translator->trans('site.name_fallback');
    }

    public function siteSlogan(): ?string
    {
        $slogan = $this->settings->get()->getPlatformSlogan();

        if (null === $slogan || '' === $slogan) {
            return null;
        }

        return $slogan;
    }

    public function iconUrl(): ?string
    {
        $settings = $this->settings->get();
        $path = $settings->getPlatformIcon();

        if (null === $path || '' === $path) {
            return null;
        }

        return $this->urlGenerator->generate('app_backend_general_settings_branding_asset', [
            'asset' => PlatformBrandingAsset::Icon->value,
        ]) . '?v=' . $settings->getLastUpdatedAt()->getTimestamp();
    }

    /**
     * @return list<SupportedLanguage>
     */
    public function languages(): array
    {
        return $this->languageResolver->enabled($this->settings->get());
    }

    public function defaultLocale(): string
    {
        return $this->languageResolver->defaultLanguage($this->settings->get())->value;
    }

    public function ogLocale(string $locale): string
    {
        return SupportedLanguage::tryFrom($locale)?->ogLocale() ?? SupportedLanguage::Spanish->ogLocale();
    }

    public function absoluteUrl(?string $path): ?string
    {
        if (null === $path || '' === $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            return $path;
        }

        return $request->getSchemeAndHttpHost() . '/' . ltrim($path, '/');
    }

    public function canonicalUrl(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            return null;
        }

        $route = $request->attributes->get('_route');
        $params = $request->attributes->get('_route_params', []);

        if (!is_string($route) || '' === $route || !is_array($params)) {
            return $request->getUri();
        }

        try {
            $url = $this->urlGenerator->generate($route, $params, UrlGeneratorInterface::ABSOLUTE_URL);
        } catch (ExceptionInterface) {
            return $request->getUri();
        }

        return $this->withQuery($url, $request->getQueryString());
    }

    public function alternateUrl(string $locale): string
    {
        $fallback = $this->urlGenerator->generate(
            'app_public_home',
            ['_locale' => $locale],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            return $fallback;
        }

        $route = $request->attributes->get('_route');
        $params = $request->attributes->get('_route_params', []);

        if (!is_string($route) || !is_array($params) || !array_key_exists('_locale', $params)) {
            return $fallback;
        }

        $params['_locale'] = $locale;

        try {
            $url = $this->urlGenerator->generate($route, $params, UrlGeneratorInterface::ABSOLUTE_URL);
        } catch (ExceptionInterface) {
            return $fallback;
        }

        return $this->withQuery($url, $request->getQueryString());
    }

    private function withQuery(string $url, ?string $query): string
    {
        if (!is_string($query) || '' === $query) {
            return $url;
        }

        return $url . '?' . $query;
    }
}
