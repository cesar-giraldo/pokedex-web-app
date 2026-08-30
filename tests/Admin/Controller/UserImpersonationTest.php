<?php

declare(strict_types=1);

namespace App\Tests\Admin\Controller;

use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\Admin\Support\AdminAuthenticatedClientTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserImpersonationTest extends WebTestCase
{
    use AdminAuthenticatedClientTrait;

    private const string FUNCTIONAL_OPERATOR_NICKNAME = 'tst-oper';

    public function testDeveloperCanImpersonateOperatorAndExit(): void
    {
        $client = static::createClient();
        $operator = $this->ensureFunctionalOperatorUser();

        $this->loginAsDeveloper($client);

        $client->request('GET', '/admin/home?_switch_user=' . $operator->getNickname());
        self::assertResponseRedirects();

        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertStringContainsString(
            'Estás viendo la aplicación como',
            (string) $client->getResponse()->getContent(),
        );
        self::assertStringContainsString(
            '@' . $operator->getNickname(),
            (string) $client->getResponse()->getContent(),
        );

        $client->request('GET', '/admin/home?_switch_user=_exit');
        self::assertResponseRedirects();

        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString(
            'Estás viendo la aplicación como',
            (string) $client->getResponse()->getContent(),
        );
    }

    public function testDeveloperCannotImpersonateAnotherDeveloper(): void
    {
        $client = static::createClient();
        $developer = $this->ensureFunctionalDeveloperUser();

        $otherDeveloper = $this->createHiddenDeveloper('tst-devel-2');

        $this->loginAsDeveloper($client);

        $client->request('GET', '/admin/home?_switch_user=' . $otherDeveloper->getNickname());
        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminCannotImpersonateUsers(): void
    {
        $client = static::createClient();
        $operator = $this->ensureFunctionalOperatorUser();

        $this->loginAsAdmin($client);

        $client->request('GET', '/admin/home?_switch_user=' . $operator->getNickname());
        self::assertResponseStatusCodeSame(403);
    }

    private function ensureFunctionalOperatorUser(): User
    {
        $container = static::getContainer();

        /** @var UserRepository $userRepository */
        $userRepository = $container->get(UserRepository::class);

        $existingUser = $userRepository->findOneByNickname(self::FUNCTIONAL_OPERATOR_NICKNAME);
        if ($existingUser instanceof User) {
            return $existingUser;
        }

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $user = new User()
            ->setName('Functional')
            ->setLastname('Operator')
            ->setEmail('tst-oper@example.com')
            ->setNickname(self::FUNCTIONAL_OPERATOR_NICKNAME)
            ->setCountryCode(57)
            ->setCellphone('3018001003')
            ->setApplicationRoles([UserRole::Operator])
            ->setStatus(UserStatus::Active)
            ->setIsHidden(true);
        $user->setPassword($hasher->hashPassword($user, 'Secret123'));

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function createHiddenDeveloper(string $nickname): User
    {
        $container = static::getContainer();

        /** @var UserRepository $userRepository */
        $userRepository = $container->get(UserRepository::class);

        $existingUser = $userRepository->findOneByNickname($nickname);
        if ($existingUser instanceof User) {
            return $existingUser;
        }

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $user = new User()
            ->setName('Other')
            ->setLastname('Developer')
            ->setEmail($nickname . '@example.com')
            ->setNickname($nickname)
            ->setCountryCode(57)
            ->setCellphone('3018001099')
            ->setApplicationRoles([UserRole::Developer])
            ->setStatus(UserStatus::Active)
            ->setIsHidden(true);
        $user->setPassword($hasher->hashPassword($user, 'Secret123'));

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
