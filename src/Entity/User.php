<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Repository\UserRepository;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use LogicException;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

use function in_array;
use function sprintf;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'uniq_users_nickname', fields: ['nickname'])]
#[ORM\UniqueConstraint(name: 'uniq_users_country_cellphone', fields: ['countryCode', 'cellphone'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['nickname'], message: 'Este nickname ya está registrado.')]
#[UniqueEntity(
    fields: ['email'],
    message: 'Este email ya está registrado.',
    repositoryMethod: 'findOneByEmail',
    ignoreNull: true,
)]
#[UniqueEntity(
    fields: ['countryCode', 'cellphone'],
    message: 'Este número de celular ya está registrado.',
    ignoreNull: true,
)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const int MAX_FAILED_LOGIN_ATTEMPTS = 4;

    public const int LOGIN_LOCK_MINUTES = 60;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    private string $name = '';

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    private string $lastname = '';

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email]
    private ?string $email = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    private ?int $countryCode = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Length(min: 8)]
    private ?string $cellphone = null;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    #[Assert\Count(min: 1)]
    private array $roles = [];

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 5)]
    private string $nickname = '';

    #[ORM\Column]
    private string $password = '';

    #[ORM\Column(enumType: UserStatus::class)]
    private UserStatus $status = UserStatus::UnconfirmedAccount;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private DateTimeInterface $lastUpdatedAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private DateTimeInterface $passwordUpdatedAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $noLoginUntil = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $failedLoginAttempts = 0;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['default' => false])]
    private ?bool $isHidden = false;

    public function __construct()
    {
        $now = new DateTime();
        $this->createdAt = $now;
        $this->lastUpdatedAt = $now;
        $this->passwordUpdatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getLastname(): string
    {
        return $this->lastname;
    }

    public function setLastname(string $lastname): static
    {
        $this->lastname = $lastname;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        if (null === $email || '' === trim($email)) {
            $this->email = null;

            return $this;
        }

        $this->email = mb_strtolower(trim($email));

        return $this;
    }

    public function getCountryCode(): ?int
    {
        return $this->countryCode;
    }

    public function setCountryCode(?int $countryCode): static
    {
        $this->countryCode = $countryCode;

        return $this;
    }

    public function getCellphone(): ?string
    {
        return $this->cellphone;
    }

    public function setCellphone(?string $cellphone): static
    {
        $this->cellphone = $cellphone;

        return $this;
    }

    /**
     * @return list<UserRole>
     */
    public function getApplicationRoles(): array
    {
        return UserRole::fromStoredValues($this->roles);
    }

    /**
     * @param list<UserRole> $roles
     */
    public function setApplicationRoles(array $roles): static
    {
        $this->roles = array_values(array_unique(array_map(
            static fn (UserRole $role): string => $role->value,
            $roles,
        )));

        return $this;
    }

    public function addApplicationRole(UserRole $role): static
    {
        if (!in_array($role->value, $this->roles, true)) {
            $this->roles[] = $role->value;
        }

        return $this;
    }

    public function getNickname(): string
    {
        return $this->nickname;
    }

    public static function normalizeNickname(string $nickname): string
    {
        return mb_strtolower(trim($nickname));
    }

    public function setNickname(string $nickname): static
    {
        $this->nickname = self::normalizeNickname($nickname);

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getStatus(): UserStatus
    {
        return $this->status;
    }

    public function setStatus(UserStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getLastUpdatedAt(): DateTimeInterface
    {
        return $this->lastUpdatedAt;
    }

    public function setLastUpdatedAt(DateTimeInterface $lastUpdatedAt): static
    {
        $this->lastUpdatedAt = $lastUpdatedAt;

        return $this;
    }

    public function getPasswordUpdatedAt(): DateTimeInterface
    {
        return $this->passwordUpdatedAt;
    }

    public function setPasswordUpdatedAt(DateTimeInterface $passwordUpdatedAt): static
    {
        $this->passwordUpdatedAt = $passwordUpdatedAt;

        return $this;
    }

    public function getNoLoginUntil(): ?DateTimeInterface
    {
        return $this->noLoginUntil;
    }

    public function setNoLoginUntil(?DateTimeInterface $noLoginUntil): static
    {
        $this->noLoginUntil = $noLoginUntil;

        return $this;
    }

    public function getFailedLoginAttempts(): int
    {
        return $this->failedLoginAttempts;
    }

    public function isHidden(): ?bool
    {
        return $this->isHidden;
    }

    public function setIsHidden(?bool $isHidden): static
    {
        $this->isHidden = $isHidden;

        return $this;
    }

    public function resetFailedLoginAttempts(): static
    {
        $this->failedLoginAttempts = 0;
        $this->noLoginUntil = null;

        return $this;
    }

    public function recordFailedLoginAttempt(): static
    {
        ++$this->failedLoginAttempts;

        if ($this->failedLoginAttempts >= self::MAX_FAILED_LOGIN_ATTEMPTS) {
            $this->noLoginUntil = new DateTime(sprintf('+%d minutes', self::LOGIN_LOCK_MINUTES));
            $this->failedLoginAttempts = 0;
        }

        return $this;
    }

    public function isLoginTemporarilyBlocked(): bool
    {
        if (null === $this->noLoginUntil) {
            return false;
        }

        return $this->noLoginUntil > new DateTime();
    }

    public function getRemainingLoginLockMinutes(): int
    {
        $noLoginUntil = $this->noLoginUntil;

        if (!$this->isLoginTemporarilyBlocked() || null === $noLoginUntil) {
            return 0;
        }

        $now = new DateTimeImmutable();
        $lockUntil = DateTimeImmutable::createFromInterface($noLoginUntil);
        $seconds = max(0, $lockUntil->getTimestamp() - $now->getTimestamp());

        return (int) ceil($seconds / 60);
    }

    public function loginTemporaryLockMessage(): string
    {
        $minutes = max($this->getRemainingLoginLockMinutes(), 1);
        $unit = 1 === $minutes ? 'minuto' : 'minutos';

        return sprintf(
            'Has superado el número de intentos permitidos. Debes esperar %d %s para volver a intentarlo.',
            $minutes,
            $unit,
        );
    }

    public function hasBackendAccess(): bool
    {
        foreach ($this->getApplicationRoles() as $role) {
            if ($role->grantsBackendAccess()) {
                return true;
            }
        }

        return false;
    }

    public function getPrimaryApplicationRole(): ?UserRole
    {
        return UserRole::primaryFromRoles($this->getApplicationRoles());
    }

    public function hasCompleteProfileContactInfo(): bool
    {
        if (null === $this->email || '' === trim($this->email)) {
            return false;
        }

        if (null === $this->countryCode) {
            return false;
        }

        if (null === $this->cellphone || '' === trim($this->cellphone)) {
            return false;
        }

        return true;
    }

    public function getFormattedPhone(): ?string
    {
        if (null === $this->countryCode || null === $this->cellphone || '' === trim($this->cellphone)) {
            return null;
        }

        return sprintf('+%d %s', $this->countryCode, $this->cellphone);
    }

    /**
     * @return non-empty-string
     */
    public function getUserIdentifier(): string
    {
        if ('' === $this->nickname) {
            throw new LogicException('El nickname del usuario no puede estar vacío.');
        }

        return $this->nickname;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $symfonyRoles = [];

        foreach ($this->getApplicationRoles() as $role) {
            $symfonyRoles[] = $role->toSymfonyRole();
        }

        return array_values(array_unique($symfonyRoles));
    }

    public function eraseCredentials(): void
    {
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function touchTimestamps(): void
    {
        $this->lastUpdatedAt = new DateTime();
    }
}
