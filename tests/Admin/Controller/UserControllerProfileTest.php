<?php

declare(strict_types=1);

namespace App\Tests\Admin\Controller;

use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\Admin\Support\AdminAuthenticatedClientTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserControllerProfileTest extends WebTestCase
{
    use AdminAuthenticatedClientTrait;

    public function testProfilePageDisplaysLoggedInUserData(): void
    {
        $client = static::createClient();
        $user = $this->loginAsAdmin($client);

        $client->request('GET', '/admin/profile');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h4', $user->getName());
        self::assertSelectorTextContains('body', $user->getNickname());
        self::assertSelectorTextContains('body', 'Información personal');
        self::assertSelectorTextContains('body', 'Seguridad');
    }

    public function testProfileInfoUpdateRedirectsToHomeWithFlash(): void
    {
        $client = static::createClient();
        $user = $this->loginAsAdmin($client);

        $crawler = $client->request('GET', '/admin/profile');
        $updatedEmail = 'profile-upd-' . bin2hex(random_bytes(4)) . '@example.com';
        $form = $crawler->filter('form[name="user_profile_info"]')->form();
        $form['user_profile_info[name]'] = 'Updated';
        $form['user_profile_info[lastname]'] = 'Name';
        $form['user_profile_info[email]'] = $updatedEmail;
        $form['user_profile_info[nickname]'] = $user->getNickname();
        $form['user_profile_info[countryCode]'] = '57';
        $form['user_profile_info[cellphone]'] = $user->getCellphone() ?? '3018001001';

        $client->submit($form);

        self::assertResponseRedirects('/admin/profile');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Tu información personal se actualizó correctamente.');
    }

    public function testIncompleteProfileBecomesActiveAfterContactInfoIsSaved(): void
    {
        $client = static::createClient();
        $nickname = 'pincomp01';
        $this->createProfileUser($nickname, UserStatus::UncompleteProfileInfo, null, null, null);

        $container = static::getContainer();
        /** @var UserRepository $userRepository */
        $userRepository = $container->get(UserRepository::class);
        $user = $userRepository->findOneByNickname($nickname);
        self::assertInstanceOf(User::class, $user);

        $client->loginUser($user, 'main');

        $crawler = $client->request('GET', '/admin/profile');
        $form = $crawler->filter('form[name="user_profile_info"]')->form();
        $form['user_profile_info[name]'] = 'Incomplete';
        $form['user_profile_info[lastname]'] = 'User';
        $form['user_profile_info[email]'] = 'incomplete-' . bin2hex(random_bytes(4)) . '@example.com';
        $form['user_profile_info[nickname]'] = $nickname;
        $form['user_profile_info[countryCode]'] = '57';
        $form['user_profile_info[cellphone]'] = '3018041001';

        $client->submit($form);

        self::assertResponseRedirects('/admin/profile');

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $entityManager->clear();
        $updatedUser = $userRepository->findOneByNickname($nickname);

        self::assertInstanceOf(User::class, $updatedUser);
        self::assertSame(UserStatus::Active, $updatedUser->getStatus());
    }

    public function testPasswordChangeRequiresCurrentPassword(): void
    {
        $client = static::createClient();
        $nickname = 'profpwd01';
        $this->createProfileUser(
            $nickname,
            UserStatus::Active,
            'profile-pwd-' . bin2hex(random_bytes(4)) . '@example.com',
            57,
            '3018041002',
        );

        $container = static::getContainer();
        /** @var UserRepository $userRepository */
        $userRepository = $container->get(UserRepository::class);
        $user = $userRepository->findOneByNickname($nickname);
        self::assertInstanceOf(User::class, $user);

        $client->loginUser($user, 'main');

        $client->request('POST', '/admin/profile', [
            'user_profile_password' => [
                'currentPassword' => 'WrongPassword',
                'plainPassword' => 'NewPass1',
                'confirmPassword' => 'NewPass1',
                '_token' => $this->getPasswordFormToken($client),
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Revisa los campos marcados: La contraseña actual no es correcta.');
    }

    public function testOperatorCanAccessProfilePage(): void
    {
        $client = static::createClient();
        $nickname = 'profopr01';
        $this->createProfileUser(
            $nickname,
            UserStatus::Active,
            'profile-opr-' . bin2hex(random_bytes(4)) . '@example.com',
            57,
            '3018041003',
            UserRole::Operator,
        );

        $container = static::getContainer();
        /** @var UserRepository $userRepository */
        $userRepository = $container->get(UserRepository::class);
        $user = $userRepository->findOneByNickname($nickname);
        self::assertInstanceOf(User::class, $user);

        $client->loginUser($user, 'main');
        $client->request('GET', '/admin/profile');

        self::assertResponseIsSuccessful();
    }

    private function createProfileUser(
        string $nickname,
        UserStatus $status,
        ?string $email,
        ?int $countryCode,
        ?string $cellphone,
        UserRole $role = UserRole::Admin,
    ): void {
        $container = static::getContainer();

        /** @var UserRepository $userRepository */
        $userRepository = $container->get(UserRepository::class);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);

        $existingUser = $userRepository->findOneByNickname($nickname);
        if ($existingUser instanceof User) {
            $entityManager->remove($existingUser);
            $entityManager->flush();
        }

        if (null !== $countryCode && null !== $cellphone) {
            $existingPhoneUser = $userRepository->findOneBy([
                'countryCode' => $countryCode,
                'cellphone' => $cellphone,
            ]);
            if ($existingPhoneUser instanceof User && $existingPhoneUser->getNickname() !== $nickname) {
                $entityManager->remove($existingPhoneUser);
                $entityManager->flush();
            }
        }

        $user = new User()
            ->setName('Profile')
            ->setLastname('User')
            ->setNickname($nickname)
            ->setApplicationRoles([$role])
            ->setStatus($status)
            ->setIsHidden(true);

        if (null !== $email) {
            $user->setEmail($email);
        }

        if (null !== $countryCode) {
            $user->setCountryCode($countryCode);
        }

        if (null !== $cellphone) {
            $user->setCellphone($cellphone);
        }

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);
        $user->setPassword($hasher->hashPassword($user, 'Secret123'));

        $entityManager->persist($user);
        $entityManager->flush();
        $entityManager->clear();
    }

    private function getPasswordFormToken(KernelBrowser $client): string
    {
        $crawler = $client->request('GET', '/admin/profile');
        $token = $crawler->filter('input[name="user_profile_password[_token]"]')->attr('value');
        self::assertNotNull($token);

        return $token;
    }
}
