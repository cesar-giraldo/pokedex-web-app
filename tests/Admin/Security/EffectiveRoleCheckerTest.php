<?php

declare(strict_types=1);

namespace App\Tests\Admin\Security;

use App\Admin\Security\EffectiveRoleChecker;
use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Role\RoleHierarchy;

#[Group('unit')]
final class EffectiveRoleCheckerTest extends TestCase
{
    public function testGrantsDeveloperRoleForDeveloperUser(): void
    {
        $checker = $this->createChecker($this->createDeveloperUser());

        self::assertTrue($checker->isGranted('ROLE_DEVELOPER'));
        self::assertTrue($checker->isGranted('ROLE_ADMIN'));
        self::assertFalse($checker->isImpersonating());
    }

    public function testWhenImpersonatingOperatorOnlyOperatorRolesAreEffective(): void
    {
        $developer = $this->createDeveloperUser();
        $operator = $this->createOperatorUser();

        $originalToken = new UsernamePasswordToken($developer, 'main', $developer->getRoles());
        $switchToken = new SwitchUserToken($operator, 'main', $operator->getRoles(), $originalToken);

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($switchToken);

        $checker = new EffectiveRoleChecker($tokenStorage, new RoleHierarchy([
            'ROLE_DEVELOPER' => ['ROLE_ADMIN'],
            'ROLE_ADMIN' => ['ROLE_OPERATOR'],
            'ROLE_OPERATOR' => ['ROLE_USER'],
        ]));

        self::assertTrue($checker->isImpersonating());
        self::assertTrue($checker->isGranted('ROLE_OPERATOR'));
        self::assertFalse($checker->isGranted('ROLE_DEVELOPER'));
        self::assertFalse($checker->isGranted('ROLE_ADMIN'));
    }

    private function createChecker(User $user): EffectiveRoleChecker
    {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));

        return new EffectiveRoleChecker($tokenStorage, new RoleHierarchy([
            'ROLE_DEVELOPER' => ['ROLE_ADMIN'],
            'ROLE_ADMIN' => ['ROLE_OPERATOR'],
            'ROLE_OPERATOR' => ['ROLE_USER'],
        ]));
    }

    private function createDeveloperUser(): User
    {
        $user = new User();
        $user->setName('Dev');
        $user->setLastname('User');
        $user->setNickname('developer');
        $user->setApplicationRoles([UserRole::Developer]);
        $user->setStatus(UserStatus::Active);

        return $user;
    }

    private function createOperatorUser(): User
    {
        $user = new User();
        $user->setName('Op');
        $user->setLastname('User');
        $user->setNickname('operator');
        $user->setApplicationRoles([UserRole::Operator]);
        $user->setStatus(UserStatus::Active);

        return $user;
    }
}
