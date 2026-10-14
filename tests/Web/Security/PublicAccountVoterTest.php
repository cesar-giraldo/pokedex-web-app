<?php

declare(strict_types=1);

namespace App\Tests\Web\Security;

use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\User;
use App\Web\Security\PublicAccountVoter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

#[Group('unit')]
final class PublicAccountVoterTest extends TestCase
{
    private PublicAccountVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new PublicAccountVoter();
    }

    public function testGrantsActiveUserRole(): void
    {
        $user = $this->createUser([UserRole::User], UserStatus::Active);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($user));
    }

    public function testGrantsIncompleteProfileWhenUserRoleIsAssigned(): void
    {
        $user = $this->createUser([UserRole::Admin, UserRole::User], UserStatus::UncompleteProfileInfo);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($user));
    }

    public function testDeniesHierarchyWithoutStoredUserRole(): void
    {
        $user = $this->createUser([UserRole::Admin], UserStatus::Active);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($user));
    }

    public function testDeniesBannedAccount(): void
    {
        $user = $this->createUser([UserRole::User], UserStatus::Banned);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($user));
    }

    /**
     * @param list<UserRole> $roles
     */
    private function createUser(array $roles, UserStatus $status): User
    {
        return new User()
            ->setName('Ada')
            ->setLastname('Lovelace')
            ->setNickname('ada')
            ->setPassword('hashed')
            ->setApplicationRoles($roles)
            ->setStatus($status);
    }

    private function vote(User $user): int
    {
        $token = new UsernamePasswordToken($user, 'public', $user->getRoles());

        return $this->voter->vote($token, null, [PublicAccountVoter::ATTRIBUTE]);
    }
}
