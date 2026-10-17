<?php

declare(strict_types=1);

namespace App\Tests\Legal;

use App\Entity\Enum\SupportedLocale;
use App\Entity\Enum\UserRole;
use App\Entity\GeneralSettings;
use App\Entity\User;
use App\Legal\LegalAcceptanceLink;
use App\Legal\LegalLanguageResolver;
use App\Repository\GeneralSettingsRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[CoversClass(LegalAcceptanceLink::class)]
#[Group('unit')]
final class LegalAcceptanceLinkTest extends TestCase
{
    public function testUserRoleReceivesThePublicUrlInThePreferredLanguage(): void
    {
        $user = new User()
            ->setApplicationRoles([UserRole::User])
            ->setLocale(SupportedLocale::EnglishUnitedStates);

        self::assertSame('/en/legal/accept', $this->link(['es', 'en'], 'es')->urlFor($user));
    }

    public function testUserRoleFallsBackToTheDefaultLanguageWhenTheirsIsDisabled(): void
    {
        $user = new User()
            ->setApplicationRoles([UserRole::User, UserRole::Operator])
            ->setLocale(SupportedLocale::FrenchFrance);

        self::assertSame('/es/legal/accept', $this->link(['es'], 'es')->urlFor($user));
    }

    public function testStaffWithoutTheUserRoleReceivesTheAdminUrl(): void
    {
        $user = new User()->setApplicationRoles([UserRole::Operator]);

        self::assertSame('/admin/legal/accept', $this->link(['es', 'en'], 'es')->urlFor($user));
    }

    /**
     * @param list<string> $enabled
     */
    private function link(array $enabled, string $default): LegalAcceptanceLink
    {
        $settings = GeneralSettings::createWithDefaults()
            ->setEnabledLanguages($enabled)
            ->setWebsiteDefaultLanguage($default);
        $repository = $this->createStub(GeneralSettingsRepository::class);
        $repository->method('getOrCreateSingleton')->willReturn($settings);

        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(static function (string $route, array $parameters = []): string {
            if ('app_public_legal_accept' === $route) {
                return '/' . ($parameters['_locale'] ?? 'es') . '/legal/accept';
            }

            return '/admin/legal/accept';
        });

        return new LegalAcceptanceLink($urls, new LegalLanguageResolver(), $repository);
    }
}
