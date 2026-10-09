<?php

declare(strict_types=1);

namespace App\Tests\Web\Controller;

use App\Admin\Service\GeneralSettingsProvider;
use App\Entity\Pokemon;
use App\Repository\GeneralSettingsRepository;
use App\Web\Service\PublicLanguageResolver;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Group('functional')]
final class PublicPagesTest extends WebTestCase
{
    private ?int $pokemonId = null;

    private ?int $hiddenPokemonId = null;

    protected function tearDown(): void
    {
        $ids = array_filter([$this->pokemonId, $this->hiddenPokemonId]);

        if ([] !== $ids && static::$booted) {
            /** @var EntityManagerInterface $entityManager */
            $entityManager = static::getContainer()->get(EntityManagerInterface::class);

            foreach ($ids as $id) {
                $pokemon = $entityManager->find(Pokemon::class, $id);

                if ($pokemon instanceof Pokemon) {
                    $entityManager->remove($pokemon);
                }
            }

            $entityManager->flush();
        }

        parent::tearDown();
    }

    public function testPublicPagesRender(): void
    {
        $client = static::createClient();
        $locale = $this->defaultLocale();

        foreach (['/pokemones', '/quienes-somos', '/contacto', '/iniciar-sesion', '/registro'] as $path) {
            $client->request('GET', '/' . $locale . $path);
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('h1');
        }
    }

    public function testDisabledLocaleRedirectsToTheDefaultLanguage(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        /** @var GeneralSettingsRepository $repository */
        $repository = static::getContainer()->get(GeneralSettingsRepository::class);
        $settings = $repository->getOrCreateSingleton();
        $enabled = $settings->getEnabledLanguages();
        $default = $settings->getWebsiteDefaultLanguage();
        $settings->setEnabledLanguages(['es']);
        $settings->setWebsiteDefaultLanguage('es');
        $entityManager->flush();

        try {
            $client->request('GET', '/fr/contacto');
            self::assertResponseRedirects('/es/contacto');
        } finally {
            $settings->setEnabledLanguages($enabled);
            $settings->setWebsiteDefaultLanguage($default);
            $entityManager->flush();
        }
    }

    public function testPokemonPageHidesPrivateEntries(): void
    {
        $client = static::createClient();
        $locale = $this->defaultLocale();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $pokemon = new Pokemon()
            ->setName('Public Catalog Mon')
            ->setHeight(4)
            ->setWeight(60)
            ->setListOrder(9998);
        $hidden = new Pokemon()
            ->setName('Hidden Catalog Mon')
            ->setHeight(4)
            ->setWeight(60)
            ->setIsHidden(true);
        $entityManager->persist($pokemon);
        $entityManager->persist($hidden);
        $entityManager->flush();
        $this->pokemonId = $pokemon->getId();
        $this->hiddenPokemonId = $hidden->getId();

        $client->request('GET', '/' . $locale . '/pokemones/' . $this->pokemonId);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Public Catalog Mon');

        $client->request('GET', '/' . $locale . '/pokemones/' . $this->hiddenPokemonId);
        self::assertResponseStatusCodeSame(404);
    }

    public function testLoginPostDoesNotAuthenticate(): void
    {
        $client = static::createClient();
        $locale = $this->defaultLocale();
        /** @var TranslatorInterface $translator */
        $translator = static::getContainer()->get(TranslatorInterface::class);
        /** @var LocaleSwitcher $localeSwitcher */
        $localeSwitcher = static::getContainer()->get(LocaleSwitcher::class);
        $expected = $localeSwitcher->runWithLocale(
            $locale,
            static fn (): string => $translator->trans('auth.unavailable'),
        );

        $client->request('POST', '/' . $locale . '/iniciar-sesion', [
            'email' => 'ada@example.com',
            'password' => 'secret-secret',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[role="status"]', $expected);
        self::assertNull($client->getRequest()->getSession()->get('_security_main'));
    }

    public function testSeoFilesAdvertiseThePublicSite(): void
    {
        $client = static::createClient();
        $locale = $this->defaultLocale();

        $client->request('GET', '/robots.txt');
        self::assertResponseIsSuccessful();
        $robots = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Disallow: /admin', $robots);
        self::assertStringContainsString('Disallow: /api', $robots);
        self::assertStringContainsString('Sitemap:', $robots);

        $client->request('GET', '/sitemap.xml');
        self::assertResponseIsSuccessful();
        $sitemap = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('/' . $locale . '/pokemones', $sitemap);
        self::assertStringContainsString('xhtml:link', $sitemap);
    }

    private function defaultLocale(): string
    {
        /** @var GeneralSettingsProvider $settings */
        $settings = static::getContainer()->get(GeneralSettingsProvider::class);
        /** @var PublicLanguageResolver $languages */
        $languages = static::getContainer()->get(PublicLanguageResolver::class);

        return $languages->defaultLanguage($settings->get())->value;
    }
}
