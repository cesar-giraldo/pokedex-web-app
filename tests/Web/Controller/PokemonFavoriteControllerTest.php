<?php

declare(strict_types=1);

namespace App\Tests\Web\Controller;

use App\Admin\Service\GeneralSettingsProvider;
use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\Pokemon;
use App\Entity\User;
use App\Entity\UserPokemonFavorite;
use App\Legal\LegalAcceptanceService;
use App\Repository\UserRepository;
use App\Tests\Web\Support\AcceptsPendingLegalDocumentsTrait;
use App\Web\Service\PendingFavoriteReturn;
use App\Web\Service\PublicLanguageResolver;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Contracts\Translation\TranslatorInterface;

use function bin2hex;
use function random_bytes;
use function random_int;
use function sprintf;
use function str_ends_with;

use const JSON_THROW_ON_ERROR;

#[Group('functional')]
final class PokemonFavoriteControllerTest extends WebTestCase
{
    use AcceptsPendingLegalDocumentsTrait;

    private const string PASSWORD = 'Secret123';

    /** @var list<int> */
    private array $pokemonIds = [];

    /** @var list<string> */
    private array $nicknames = [];

    protected function tearDown(): void
    {
        if (([] !== $this->pokemonIds || [] !== $this->nicknames) && static::$booted) {
            /** @var EntityManagerInterface $entityManager */
            $entityManager = static::getContainer()->get(EntityManagerInterface::class);
            /** @var UserRepository $users */
            $users = static::getContainer()->get(UserRepository::class);

            foreach ($this->pokemonIds as $pokemonId) {
                $pokemon = $entityManager->find(Pokemon::class, $pokemonId);

                if ($pokemon instanceof Pokemon) {
                    $entityManager->remove($pokemon);
                }
            }

            foreach ($this->nicknames as $nickname) {
                $user = $users->findOneByNickname($nickname);

                if ($user instanceof User) {
                    $entityManager->remove($user);
                }
            }

            $entityManager->flush();
        }

        parent::tearDown();
    }

    public function testAnonymousAndIncompleteRequestsAreForbidden(): void
    {
        $client = static::createClient();
        $locale = $this->defaultLocale();
        $pokemonId = $this->createPokemon('Forbidden Favorite');

        $client->request('GET', sprintf('/%s/pokemon/%d', $locale, $pokemonId));
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#favorite-dialog');
        self::assertSelectorExists(sprintf(
            'button.favorite-button[data-favorite-gate-value="login"][aria-pressed="false"][aria-label="%s"]',
            $this->trans($locale, 'favorites.add'),
        ));

        $this->toggle($client, 'POST', $locale, $pokemonId, null);
        self::assertResponseStatusCodeSame(403);

        $nickname = $this->rememberNickname('fav-inc-' . bin2hex(random_bytes(3)));
        $incomplete = $this->createUser($nickname, UserStatus::UncompleteProfileInfo, false);
        $this->acceptPendingLegalDocuments($incomplete);
        $client->loginUser($this->reload($nickname), 'public');
        $this->toggle($client, 'POST', $locale, $pokemonId, null);
        self::assertResponseStatusCodeSame(403);
        self::assertSame(0, $this->favoriteCount($incomplete->getId(), $pokemonId));
    }

    public function testActiveUserCanAddAndRemoveAFavoriteWithoutDuplicatingIt(): void
    {
        $client = static::createClient();
        $locale = $this->defaultLocale();
        $pokemonId = $this->createPokemon('Toggle Favorite');
        $hiddenId = $this->createPokemon('Hidden Toggle Favorite', true);
        $nickname = $this->rememberNickname('fav-on-' . bin2hex(random_bytes(3)));
        $user = $this->createUser($nickname, UserStatus::Active, true);
        $client->loginUser($this->reload($nickname), 'public');

        $crawler = $client->request('GET', sprintf('/%s/pokemon/%d', $locale, $pokemonId));
        self::assertResponseIsSuccessful();
        $token = $crawler->filter('.favorite-button')->attr('data-favorite-csrf-value');
        self::assertIsString($token);

        $this->toggle($client, 'POST', $locale, $pokemonId, $token);
        self::assertResponseIsSuccessful();
        self::assertSame(['favorited' => true], $this->json($client));
        $markedAt = $this->favoritedAt($user->getId(), $pokemonId);

        $this->toggle($client, 'POST', $locale, $pokemonId, $token);
        self::assertResponseIsSuccessful();
        self::assertSame(['favorited' => true], $this->json($client));
        self::assertSame(1, $this->favoriteCount($user->getId(), $pokemonId));
        self::assertSame($markedAt, $this->favoritedAt($user->getId(), $pokemonId));

        $this->toggle($client, 'DELETE', $locale, $pokemonId, $token);
        self::assertResponseIsSuccessful();
        self::assertSame(['favorited' => false], $this->json($client));
        self::assertSame(0, $this->favoriteCount($user->getId(), $pokemonId));

        $this->toggle($client, 'DELETE', $locale, $pokemonId, $token);
        self::assertResponseIsSuccessful();
        self::assertSame(['favorited' => false], $this->json($client));

        $this->toggle($client, 'POST', $locale, $hiddenId, $token);
        self::assertResponseStatusCodeSame(404);
    }

    public function testFavoritesPageListsOnlyTheSignedInUsersPokemon(): void
    {
        $client = static::createClient();
        $locale = $this->defaultLocale();
        $name = 'Owned Favorite ' . bin2hex(random_bytes(3));
        $pokemonId = $this->createPokemon($name);
        $ownerNickname = $this->rememberNickname('fav-own-' . bin2hex(random_bytes(3)));
        $otherNickname = $this->rememberNickname('fav-oth-' . bin2hex(random_bytes(3)));
        $owner = $this->createUser($ownerNickname, UserStatus::Active, true);
        $this->createUser($otherNickname, UserStatus::Active, true);
        $this->persistFavorite($owner, $pokemonId);

        $client->loginUser($this->reload($otherNickname), 'public');
        $client->request('GET', sprintf('/%s/favorites', $locale));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('main', $name);

        $client->loginUser($this->reload($ownerNickname), 'public');
        $client->request('GET', sprintf('/%s/favorites?q=%s', $locale, rawurlencode($name)));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h2', $name);
        self::assertSelectorExists('button.favorite-button[aria-pressed="true"]');

        $client->request('GET', sprintf('/%s/favorites?q=%s', $locale, 'missing-' . bin2hex(random_bytes(4))));
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-catalog-empty]:not([hidden])');
    }

    public function testActiveLoginWithAVisiblePokemonCookieReturnsToThatPokemon(): void
    {
        $client = static::createClient();
        $locale = $this->defaultLocale();
        $pokemonId = $this->createPokemon('Cookie Favorite');
        $nickname = $this->rememberNickname('fav-ret-' . bin2hex(random_bytes(3)));
        $this->createUser($nickname, UserStatus::Active, true);
        $this->setFavoriteCookie($client, (string) $pokemonId);

        $this->submitLogin($client, $locale, $nickname);

        $this->assertRedirectEndsWith($client, sprintf('/%s/pokemon/%d', $locale, $pokemonId));
        self::assertNull($client->getCookieJar()->get(PendingFavoriteReturn::COOKIE_NAME));
    }

    public function testInvalidCookieIsDiscardedAndLoginContinuesHome(): void
    {
        $client = static::createClient();
        $locale = $this->defaultLocale();
        $nickname = $this->rememberNickname('fav-bad-' . bin2hex(random_bytes(3)));
        $this->createUser($nickname, UserStatus::Active, true);
        $this->setFavoriteCookie($client, 'nope');

        $this->submitLogin($client, $locale, $nickname);

        $this->assertRedirectEndsWith($client, '/' . $locale);
        self::assertNull($client->getCookieJar()->get(PendingFavoriteReturn::COOKIE_NAME));
    }

    public function testTheCookieWaitsUntilTheAccountCanOpenThePokemonPage(): void
    {
        $client = static::createClient();
        $locale = $this->defaultLocale();
        $pokemonId = $this->createPokemon('Waiting Favorite');
        $nickname = $this->rememberNickname('fav-wait-' . bin2hex(random_bytes(3)));
        $this->createUser($nickname, UserStatus::UncompleteProfileInfo, false);
        $this->setFavoriteCookie($client, (string) $pokemonId);

        $this->submitLogin($client, $locale, $nickname);

        $this->assertRedirectEndsWith($client, '/' . $locale . '/profile');
        $cookie = $client->getCookieJar()->get(PendingFavoriteReturn::COOKIE_NAME);
        self::assertNotNull($cookie);
        self::assertSame((string) $pokemonId, $cookie->getValue());

        $crawler = $client->request('GET', '/' . $locale . '/profile');
        $form = $crawler->filter('form[name="user_profile_info"]')->form();
        $form['user_profile_info[email]'] = sprintf('wait-%s@example.com', bin2hex(random_bytes(3)));
        $form['user_profile_info[countryCode]'] = '57';
        $form['user_profile_info[cellphone]'] = (string) random_int(3000000000, 3999999999);
        $client->submit($form);

        $location = (string) $client->getResponse()->headers->get('Location');

        if (str_ends_with($location, sprintf('/%s/pokemon/%d', $locale, $pokemonId))) {
            self::assertNull($client->getCookieJar()->get(PendingFavoriteReturn::COOKIE_NAME));

            return;
        }

        $this->assertRedirectEndsWith($client, '/' . $locale);
        self::assertNotNull($client->getCookieJar()->get(PendingFavoriteReturn::COOKIE_NAME));
        $this->acceptPendingLegalDocuments($this->reload($nickname));
        $client->request('GET', '/' . $locale . '/legal/accept');
        $this->assertRedirectEndsWith($client, sprintf('/%s/pokemon/%d', $locale, $pokemonId));
        self::assertNull($client->getCookieJar()->get(PendingFavoriteReturn::COOKIE_NAME));
    }

    public function testPendingLegalAcceptanceKeepsTheCookieUntilTheDocumentsAreAccepted(): void
    {
        $client = static::createClient();
        $locale = $this->defaultLocale();
        $pokemonId = $this->createPokemon('Legal Favorite');
        $nickname = $this->rememberNickname('fav-law-' . bin2hex(random_bytes(3)));
        $user = $this->createUser($nickname, UserStatus::Active, false);
        /** @var LegalAcceptanceService $acceptance */
        $acceptance = static::getContainer()->get(LegalAcceptanceService::class);

        if (!$acceptance->hasPending($user)) {
            $this->setFavoriteCookie($client, (string) $pokemonId);
            $this->submitLogin($client, $locale, $nickname);
            $this->assertRedirectEndsWith($client, sprintf('/%s/pokemon/%d', $locale, $pokemonId));
            self::assertNull($client->getCookieJar()->get(PendingFavoriteReturn::COOKIE_NAME));

            return;
        }

        $this->setFavoriteCookie($client, (string) $pokemonId);
        $this->submitLogin($client, $locale, $nickname);
        $this->assertRedirectEndsWith($client, '/' . $locale);
        self::assertNotNull($client->getCookieJar()->get(PendingFavoriteReturn::COOKIE_NAME));

        $this->acceptPendingLegalDocuments($this->reload($nickname));
        $client->request('GET', '/' . $locale . '/legal/accept');
        $this->assertRedirectEndsWith($client, sprintf('/%s/pokemon/%d', $locale, $pokemonId));
        self::assertNull($client->getCookieJar()->get(PendingFavoriteReturn::COOKIE_NAME));
    }

    private function toggle(KernelBrowser $client, string $method, string $locale, int $pokemonId, ?string $token): void
    {
        $server = ['HTTP_ACCEPT' => 'application/json'];

        if (null !== $token) {
            $server['HTTP_X_CSRF_TOKEN'] = $token;
        }

        $client->request($method, sprintf('/%s/pokemon/%d/favorite', $locale, $pokemonId), [], [], $server);
    }

    /**
     * @return array<string, mixed>
     */
    private function json(KernelBrowser $client): array
    {
        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return $payload;
    }

    private function submitLogin(KernelBrowser $client, string $locale, string $nickname): void
    {
        $crawler = $client->request('GET', '/' . $locale . '/login');
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form')->form();
        $form['_username'] = $nickname;
        $form['_password'] = self::PASSWORD;
        $client->submit($form);
    }

    private function setFavoriteCookie(KernelBrowser $client, string $value): void
    {
        $client->getCookieJar()->set(new Cookie(
            PendingFavoriteReturn::COOKIE_NAME,
            $value,
            (string) (time() + 86400),
            '/',
            'localhost',
            false,
            false,
            false,
            'lax',
        ));
    }

    private function assertRedirectEndsWith(KernelBrowser $client, string $path): void
    {
        self::assertResponseRedirects();
        $location = $client->getResponse()->headers->get('Location');
        self::assertIsString($location);
        self::assertTrue(str_ends_with($location, $path), $location);
    }

    private function createPokemon(string $name, bool $hidden = false): int
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $pokemon = new Pokemon()
            ->setName($name)
            ->setHeight(4)
            ->setWeight(40)
            ->setListOrder(9000)
            ->setIsHidden($hidden);
        $entityManager->persist($pokemon);
        $entityManager->flush();
        $id = $pokemon->getId();
        self::assertNotNull($id);
        $this->pokemonIds[] = $id;

        return $id;
    }

    private function persistFavorite(User $user, int $pokemonId): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $pokemon = $entityManager->find(Pokemon::class, $pokemonId);
        self::assertInstanceOf(Pokemon::class, $pokemon);
        $entityManager->persist(new UserPokemonFavorite($user, $pokemon));
        $entityManager->flush();
    }

    private function createUser(string $nickname, UserStatus $status, bool $acceptLegal): User
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user = new User()
            ->setName('Ada')
            ->setLastname('Tester')
            ->setNickname($nickname)
            ->setEmail(sprintf('%s@example.com', $nickname))
            ->setApplicationRoles([UserRole::User])
            ->setStatus($status);
        $user->setPassword($hasher->hashPassword($user, self::PASSWORD));
        $entityManager->persist($user);
        $entityManager->flush();

        if ($acceptLegal && UserStatus::Active === $status) {
            $this->acceptPendingLegalDocuments($user);
        }

        return $user;
    }

    private function reload(string $nickname): User
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        /** @var UserRepository $users */
        $users = static::getContainer()->get(UserRepository::class);
        $user = $users->findOneByNickname($nickname);
        self::assertInstanceOf(User::class, $user);

        return $user;
    }

    private function rememberNickname(string $nickname): string
    {
        $nickname = User::normalizeNickname($nickname);
        $this->nicknames[] = $nickname;

        return $nickname;
    }

    private function favoriteCount(?int $userId, int $pokemonId): int
    {
        self::assertNotNull($userId);

        return $this->favorite($userId, $pokemonId) instanceof UserPokemonFavorite ? 1 : 0;
    }

    private function favoritedAt(?int $userId, int $pokemonId): string
    {
        self::assertNotNull($userId);
        $favorite = $this->favorite($userId, $pokemonId);
        self::assertInstanceOf(UserPokemonFavorite::class, $favorite);

        return $favorite->getFavoritedAt()->format('Y-m-d H:i:s');
    }

    private function favorite(int $userId, int $pokemonId): ?UserPokemonFavorite
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $favorite = $entityManager->getRepository(UserPokemonFavorite::class)->findOneBy([
            'user' => $userId,
            'pokemon' => $pokemonId,
        ]);

        return $favorite instanceof UserPokemonFavorite ? $favorite : null;
    }

    private function defaultLocale(): string
    {
        /** @var GeneralSettingsProvider $settings */
        $settings = static::getContainer()->get(GeneralSettingsProvider::class);
        /** @var PublicLanguageResolver $languages */
        $languages = static::getContainer()->get(PublicLanguageResolver::class);

        return $languages->defaultLanguage($settings->get())->value;
    }

    private function trans(string $locale, string $key): string
    {
        /** @var TranslatorInterface $translator */
        $translator = static::getContainer()->get(TranslatorInterface::class);
        /** @var LocaleSwitcher $localeSwitcher */
        $localeSwitcher = static::getContainer()->get(LocaleSwitcher::class);

        return $localeSwitcher->runWithLocale(
            $locale,
            static fn (): string => $translator->trans($key),
        );
    }
}
