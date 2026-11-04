<?php

declare(strict_types=1);

namespace App\Tests\Admin\Service;

use App\Admin\Service\InitialModuleDefinition;
use App\Admin\Service\InitialModules;
use App\Entity\Module;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function mb_strlen;
use function sprintf;

#[CoversClass(InitialModules::class)]
#[CoversClass(InitialModuleDefinition::class)]
#[Group('unit')]
final class InitialModulesTest extends TestCase
{
    public function testDefinitionsMatchTheInitialCatalog(): void
    {
        $definitions = new InitialModules()->definitions();

        self::assertCount(10, $definitions);

        /** @var array<string, true> $seenSlugs */
        $seenSlugs = [];

        foreach ($definitions as $definition) {
            self::assertMatchesRegularExpression(Module::SLUG_PATTERN, $definition->slug);
            self::assertArrayNotHasKey($definition->slug, $seenSlugs);
            self::assertTrue($definition->isEnabled);
            self::assertLessThanOrEqual(Module::NAME_MAX_LENGTH, mb_strlen($definition->name));
            self::assertLessThanOrEqual(Module::SLUG_MAX_LENGTH, mb_strlen($definition->slug));
            self::assertNotNull(Module::tryNormalizeSortOrder($definition->sortOrder));

            if (null !== $definition->description) {
                self::assertLessThanOrEqual(Module::DESCRIPTION_MAX_LENGTH, mb_strlen($definition->description));
            }

            if (null !== $definition->parentSlug) {
                self::assertArrayHasKey($definition->parentSlug, $seenSlugs);
            }

            $seenSlugs[$definition->slug] = true;
        }

        self::assertSame(self::expectedCatalog(), $this->catalogShape($definitions));
        self::assertNull($this->definition($definitions, 'module_pokemons_favorites')->description);
        self::assertStringContainsString('página de inicio', (string) $this->definition($definitions, 'module_pokemons')->description);
        self::assertStringNotContainsString('home page', (string) $this->definition($definitions, 'module_pokemons')->description);
        self::assertStringNotContainsString('signifoca', (string) $this->definition($definitions, 'module_web_google_auth')->description);
    }

    /**
     * @param list<InitialModuleDefinition> $definitions
     *
     * @return array<string, array{name: string, sortOrder: string, parentSlug: ?string}>
     */
    private function catalogShape(array $definitions): array
    {
        $shape = [];

        foreach ($definitions as $definition) {
            $shape[$definition->slug] = [
                'name' => $definition->name,
                'sortOrder' => $definition->sortOrder,
                'parentSlug' => $definition->parentSlug,
            ];
        }

        return $shape;
    }

    /**
     * @param list<InitialModuleDefinition> $definitions
     */
    private function definition(array $definitions, string $slug): InitialModuleDefinition
    {
        foreach ($definitions as $definition) {
            if ($definition->slug === $slug) {
                return $definition;
            }
        }

        self::fail(sprintf('No está definido el módulo "%s".', $slug));
    }

    /**
     * @return array<string, array{name: string, sortOrder: string, parentSlug: ?string}>
     */
    private static function expectedCatalog(): array
    {
        return [
            'module_web_secure_area' => [
                'name' => 'Web Secure Area',
                'sortOrder' => '1.00',
                'parentSlug' => null,
            ],
            'module_web_google_auth' => [
                'name' => 'Login con Google',
                'sortOrder' => '1.10',
                'parentSlug' => 'module_web_secure_area',
            ],
            'module_web_password_recovery' => [
                'name' => 'Recuperación de Contraseña',
                'sortOrder' => '1.20',
                'parentSlug' => 'module_web_secure_area',
            ],
            'module_web_show_cookies_policy' => [
                'name' => 'Mostrar Política de Cookies',
                'sortOrder' => '1.30',
                'parentSlug' => 'module_web_secure_area',
            ],
            'module_admin_secure_area' => [
                'name' => 'Admin Secure Area',
                'sortOrder' => '2.00',
                'parentSlug' => null,
            ],
            'module_admin_password_recovery' => [
                'name' => 'Recuperación de Contraseña',
                'sortOrder' => '2.10',
                'parentSlug' => 'module_admin_secure_area',
            ],
            'module_admin_legal_documents_management' => [
                'name' => 'Administración de Documentos Legales',
                'sortOrder' => '2.20',
                'parentSlug' => 'module_admin_secure_area',
            ],
            'module_admin_ui_kit' => [
                'name' => 'UI Kit',
                'sortOrder' => '2.30',
                'parentSlug' => 'module_admin_secure_area',
            ],
            'module_pokemons' => [
                'name' => 'Pokemons',
                'sortOrder' => '9.00',
                'parentSlug' => null,
            ],
            'module_pokemons_favorites' => [
                'name' => 'Pokemones Favoritos',
                'sortOrder' => '9.10',
                'parentSlug' => 'module_pokemons',
            ],
        ];
    }
}
