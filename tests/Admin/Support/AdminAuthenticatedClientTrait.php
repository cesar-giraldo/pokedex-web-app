<?php

declare(strict_types=1);

namespace App\Tests\Admin\Support;

use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

trait AdminAuthenticatedClientTrait
{
    private const string FUNCTIONAL_ADMIN_NICKNAME = 'functional-admin';

    private const string FUNCTIONAL_DEVELOPER_NICKNAME = 'functional-developer';

    private function loginAsAdmin(KernelBrowser $client): User
    {
        $user = $this->ensureFunctionalAdminUser();
        $client->loginUser($user, 'main');

        return $user;
    }

    private function loginAsDeveloper(KernelBrowser $client): User
    {
        $user = $this->ensureFunctionalDeveloperUser();
        $client->loginUser($user, 'main');

        return $user;
    }

    private function ensureFunctionalAdminUser(): User
    {
        $container = static::getContainer();

        /** @var UserRepository $userRepository */
        $userRepository = $container->get(UserRepository::class);

        $existingUser = $userRepository->findOneByNickname(self::FUNCTIONAL_ADMIN_NICKNAME);
        if ($existingUser instanceof User) {
            return $existingUser;
        }

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $user = new User()
            ->setName('Functional')
            ->setLastname('Admin')
            ->setEmail('functional-admin@example.com')
            ->setNickname(self::FUNCTIONAL_ADMIN_NICKNAME)
            ->setApplicationRoles([UserRole::Admin])
            ->setStatus(UserStatus::Active)
            ->setIsHidden(true);
        $user->setPassword($hasher->hashPassword($user, 'Secret123'));

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function ensureFunctionalDeveloperUser(): User
    {
        $container = static::getContainer();

        /** @var UserRepository $userRepository */
        $userRepository = $container->get(UserRepository::class);

        $existingUser = $userRepository->findOneByNickname(self::FUNCTIONAL_DEVELOPER_NICKNAME);
        if ($existingUser instanceof User) {
            return $existingUser;
        }

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $user = new User()
            ->setName('Functional')
            ->setLastname('Developer')
            ->setEmail('functional-developer@example.com')
            ->setNickname(self::FUNCTIONAL_DEVELOPER_NICKNAME)
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
