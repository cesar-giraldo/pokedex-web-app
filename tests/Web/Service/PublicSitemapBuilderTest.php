<?php

declare(strict_types=1);

namespace App\Tests\Web\Service;

use App\Entity\Enum\LegalDocumentType;
use App\Entity\Module;
use App\Module\ModuleAvailability;
use App\Repository\LegalDocumentVersionRepository;
use App\Repository\ModuleRepository;
use App\Web\Service\PublicLegalAvailability;
use App\Web\Service\PublicSitemapBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[CoversClass(PublicSitemapBuilder::class)]
#[Group('unit')]
final class PublicSitemapBuilderTest extends TestCase
{
    public function testSitemapOmitsLegalPagesWithoutAPublishedVersion(): void
    {
        $xml = $this->sitemap([LegalDocumentType::TermsOfUse]);

        self::assertStringNotContainsString('app_public_privacy', $xml);
        self::assertStringContainsString('app_public_terms', $xml);
        self::assertStringContainsString('app_public_home', $xml);
        self::assertStringContainsString('app_public_contact', $xml);
        self::assertStringContainsString('app_public_cookies', $xml);
    }

    public function testSitemapOmitsTheLegalSectionWhenNothingIsPublished(): void
    {
        $xml = $this->sitemap([]);

        self::assertStringNotContainsString('app_public_privacy', $xml);
        self::assertStringNotContainsString('app_public_terms', $xml);
        self::assertStringContainsString('app_public_cookies', $xml);
    }

    public function testSitemapOmitsCookiesWhenTheModuleIsDisabled(): void
    {
        $xml = $this->sitemap([], false);

        self::assertStringNotContainsString('app_public_cookies', $xml);
        self::assertStringContainsString('app_public_home', $xml);
        self::assertStringContainsString('app_public_contact', $xml);
    }

    public function testSitemapOmitsTheCatalogWhenTheModuleIsDisabled(): void
    {
        $xml = $this->sitemap([], true, false, [['id' => 7, 'name' => 'Bulbasaur']]);

        self::assertStringNotContainsString('app_public_pokedex', $xml);
        self::assertStringNotContainsString('app_public_pokemon', $xml);
        self::assertStringContainsString('app_public_home', $xml);
    }

    public function testSitemapIncludesTheCatalogWhenTheModuleIsEnabled(): void
    {
        $xml = $this->sitemap([], true, true, [['id' => 7, 'name' => 'Bulbasaur']]);

        self::assertStringContainsString('app_public_pokedex', $xml);
        self::assertStringContainsString('app_public_pokemon', $xml);
    }

    /**
     * @param list<LegalDocumentType>            $published
     * @param list<array{id: int, name: string}> $pokemons
     */
    private function sitemap(array $published, bool $cookiesEnabled = true, bool $pokemonsEnabled = true, array $pokemons = []): string
    {
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(
            static fn (string $route, array $params, int $referenceType): string => 'https://example.test/' . $params['_locale'] . '/' . $route,
        );
        $versions = $this->createStub(LegalDocumentVersionRepository::class);
        $versions->method('findPublishedDocumentTypes')->willReturn($published);
        $parent = new Module('Web Secure Area', 'module_web_secure_area', '1.00');
        $parent->setIsEnabled(true);
        $cookies = new Module('Mostrar Política de Cookies', 'module_web_show_cookies_policy', '1.30');
        $cookies->setIsEnabled($cookiesEnabled);
        $cookies->setParent($parent);
        $catalog = new Module('Pokemons', 'module_pokemons', '9.00');
        $catalog->setIsEnabled($pokemonsEnabled);
        $modules = $this->createStub(ModuleRepository::class);
        $modules->method('findAllOrdered')->willReturn([$parent, $cookies, $catalog]);

        return new PublicSitemapBuilder($urls, new PublicLegalAvailability($versions), new ModuleAvailability($modules))->build(['es'], 'es', $pokemons);
    }
}
