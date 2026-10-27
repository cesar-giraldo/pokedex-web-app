<?php

declare(strict_types=1);

namespace App\Tests\Web\Controller;

use App\Entity\AccountConfirmationCode;
use App\Entity\Enum\SupportedLanguage;
use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\Notification;
use App\Entity\User;
use App\Entity\UserLegalAcceptance;
use App\Repository\UserRepository;
use App\Tests\Double\FakeGoogleIdTokenVerifier;
use App\Web\Security\GoogleIdentity;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

use function bin2hex;
use function parse_str;
use function parse_url;
use function random_bytes;

use const PHP_URL_PATH;
use const PHP_URL_QUERY;

#[Group('functional')]
final class GoogleSignUpTest extends WebTestCase
{
    /** @var list<string> */
    private array $emails = [];

    protected function tearDown(): void
    {
        FakeGoogleIdTokenVerifier::$identity = null;

        if ([] !== $this->emails && static::$booted) {
            /** @var EntityManagerInterface $entityManager */
            $entityManager = static::getContainer()->get(EntityManagerInterface::class);
            /** @var UserRepository $users */
            $users = static::getContainer()->get(UserRepository::class);

            foreach ($this->emails as $email) {
                $entityManager->createQueryBuilder()
                    ->delete(Notification::class, 'notification')
                    ->andWhere('notification.message LIKE :email')
                    ->setParameter('email', '%' . $email . '%')
                    ->getQuery()
                    ->execute();

                $user = $users->findOneByEmail($email);

                if ($user instanceof User) {
                    $entityManager->remove($user);
                }
            }

            $entityManager->flush();
        }

        parent::tearDown();
    }

    public function testTheGoogleButtonSitsOutsideTheManualForms(): void
    {
        $client = static::createClient();

        $register = $client->request('GET', '/es/register');
        self::assertResponseIsSuccessful();
        self::assertSame(0, $register->filter('form')->first()->filter('a[href*="/connect/google"]')->count());
        self::assertGreaterThan(0, $register->filter('a[href*="from=register"]')->count());
        self::assertSelectorTextContains('a[href*="from=register"]', 'Regístrate con Google');

        $login = $client->request('GET', '/es/login');
        self::assertResponseIsSuccessful();
        self::assertSame(0, $login->filter('form')->first()->filter('a[href*="/connect/google"]')->count());
        self::assertGreaterThan(0, $login->filter('a[href*="from=login"]')->count());
        self::assertSelectorTextContains('a[href*="from=login"]', 'Entra con Google');
    }

    public function testANewGoogleAccountLandsOnTheProfileWithoutAConfirmationCode(): void
    {
        $email = 'google-' . bin2hex(random_bytes(4)) . '@example.com';
        $this->emails[] = $email;
        FakeGoogleIdTokenVerifier::$identity = new GoogleIdentity(
            sub: 'sub-' . bin2hex(random_bytes(4)),
            email: $email,
            givenName: 'Ada',
            familyName: 'Lovelace',
        );

        $client = static::createClient();
        $client->request('GET', '/es/connect/google?from=register');
        self::assertResponseRedirects();
        $state = $this->oauthState($client);

        $client->request('GET', '/connect/google/check?code=ok&state=' . rawurlencode($state));
        self::assertResponseRedirects();
        $location = (string) $client->getResponse()->headers->get('Location');
        $path = parse_url($location, PHP_URL_PATH);
        self::assertIsString($path);
        self::assertStringEndsWith('/es/profile', $path);

        $client->followRedirect();
        self::assertResponseIsSuccessful();

        $tokenUser = $client->getContainer()->get(TokenStorageInterface::class)->getToken()?->getUser();
        self::assertInstanceOf(User::class, $tokenUser);
        self::assertSame($email, $tokenUser->getUserIdentifier());

        /** @var UserRepository $users */
        $users = static::getContainer()->get(UserRepository::class);
        $user = $users->findOneByEmail($email);
        self::assertInstanceOf(User::class, $user);
        self::assertSame(UserStatus::UncompleteProfileInfo, $user->getStatus());
        self::assertTrue($user->hasAssignedRole(UserRole::User));
        self::assertSame(SupportedLanguage::Spanish, $user->getPreferredLanguage());
        self::assertNotNull($user->getGoogleSub());

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertSame(0, $entityManager->getRepository(AccountConfirmationCode::class)->count(['user' => $user]));
        self::assertSame(0, $entityManager->getRepository(UserLegalAcceptance::class)->count(['user' => $user]));
    }

    private function oauthState(KernelBrowser $client): string
    {
        $location = $client->getResponse()->headers->get('Location');
        self::assertIsString($location);
        $queryString = parse_url($location, PHP_URL_QUERY);
        self::assertIsString($queryString);
        $query = [];
        parse_str($queryString, $query);
        $state = $query['state'] ?? null;
        self::assertIsString($state);
        self::assertNotSame('', $state);

        return $state;
    }
}
