<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\SupportedLanguage;
use App\Entity\Enum\SupportedLocale;
use App\Entity\Enum\TimeFormat;
use App\Repository\GeneralSettingsRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

use function in_array;
use function trim;

/**
 * Singleton de configuración general de la plataforma.
 * Solo debe existir un registro en la tabla general_settings.
 */
#[ORM\Entity(repositoryClass: GeneralSettingsRepository::class)]
#[ORM\Table(name: 'general_settings')]
#[ORM\HasLifecycleCallbacks]
class GeneralSettings
{
    public const bool DEFAULT_SHOW_HIDDEN_USERS = true;

    /**
     * @var list<string>
     */
    public const array DEFAULT_ENABLED_LANGUAGES = [SupportedLanguage::Spanish->value];

    public const string DEFAULT_WEBSITE_DEFAULT_LANGUAGE = SupportedLanguage::Spanish->value;

    public const string DEFAULT_TIMEZONE = 'America/Bogota';

    public const SupportedLocale DEFAULT_LOCALE = SupportedLocale::SpanishColombia;

    public const TimeFormat DEFAULT_TIME_FORMAT = TimeFormat::Hour12;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $showHiddenUsers = self::DEFAULT_SHOW_HIDDEN_USERS;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $enabledLanguages = self::DEFAULT_ENABLED_LANGUAGES;

    #[ORM\Column(length: 5)]
    private string $websiteDefaultLanguage = self::DEFAULT_WEBSITE_DEFAULT_LANGUAGE;

    #[ORM\Column(length: 64, options: ['default' => self::DEFAULT_TIMEZONE])]
    private string $defaultTimezone = self::DEFAULT_TIMEZONE;

    #[ORM\Column(enumType: SupportedLocale::class, length: 16, options: ['default' => 'es-CO'])]
    private SupportedLocale $defaultLocale = self::DEFAULT_LOCALE;

    #[ORM\Column(enumType: TimeFormat::class, length: 8, options: ['default' => '12h'])]
    private TimeFormat $defaultTimeFormat = self::DEFAULT_TIME_FORMAT;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private DateTimeInterface $lastUpdatedAt;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $lastBackupFilePath = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $lastBackupGeneratedAt = null;

    public function __construct()
    {
        $this->lastUpdatedAt = new DateTime();
    }

    public static function createWithDefaults(): self
    {
        return new self();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isShowHiddenUsers(): bool
    {
        return $this->showHiddenUsers;
    }

    public function setShowHiddenUsers(bool $showHiddenUsers): static
    {
        $this->showHiddenUsers = $showHiddenUsers;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getEnabledLanguages(): array
    {
        return $this->enabledLanguages;
    }

    /**
     * @param list<string> $enabledLanguages
     */
    public function setEnabledLanguages(array $enabledLanguages): static
    {
        $this->enabledLanguages = $enabledLanguages;

        return $this;
    }

    public function getWebsiteDefaultLanguage(): string
    {
        return $this->websiteDefaultLanguage;
    }

    public function setWebsiteDefaultLanguage(string $websiteDefaultLanguage): static
    {
        $this->websiteDefaultLanguage = $websiteDefaultLanguage;

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

    /**
     * @return list<string>
     */
    public function getEnabledLanguageLabels(): array
    {
        $labels = [];

        foreach ($this->enabledLanguages as $languageCode) {
            $language = SupportedLanguage::tryFrom($languageCode);

            if (null !== $language) {
                $labels[] = $language->label();
            }
        }

        return $labels;
    }

    public function getWebsiteDefaultLanguageLabel(): ?string
    {
        $language = SupportedLanguage::tryFrom($this->websiteDefaultLanguage);

        return $language?->label();
    }

    public function isWebsiteDefaultLanguageEnabled(): bool
    {
        return in_array($this->websiteDefaultLanguage, $this->enabledLanguages, true);
    }

    public function getDefaultTimezone(): string
    {
        return $this->defaultTimezone;
    }

    public function setDefaultTimezone(?string $defaultTimezone): static
    {
        $this->defaultTimezone = $defaultTimezone ?? '';

        return $this;
    }

    public function getDefaultLocale(): SupportedLocale
    {
        return $this->defaultLocale;
    }

    public function setDefaultLocale(SupportedLocale $defaultLocale): static
    {
        $this->defaultLocale = $defaultLocale;

        return $this;
    }

    public function getDefaultLocaleLabel(): string
    {
        return $this->defaultLocale->label();
    }

    public function getDefaultTimeFormat(): TimeFormat
    {
        return $this->defaultTimeFormat;
    }

    public function setDefaultTimeFormat(TimeFormat $defaultTimeFormat): static
    {
        $this->defaultTimeFormat = $defaultTimeFormat;

        return $this;
    }

    public function getDefaultTimeFormatLabel(): string
    {
        return $this->defaultTimeFormat->label();
    }

    public function getLastBackupFilePath(): ?string
    {
        return $this->lastBackupFilePath;
    }

    public function setLastBackupFilePath(?string $lastBackupFilePath): static
    {
        if (null === $lastBackupFilePath || '' === trim($lastBackupFilePath)) {
            $this->lastBackupFilePath = null;

            return $this;
        }

        $this->lastBackupFilePath = $lastBackupFilePath;

        return $this;
    }

    public function getLastBackupGeneratedAt(): ?DateTimeInterface
    {
        return $this->lastBackupGeneratedAt;
    }

    public function setLastBackupGeneratedAt(?DateTimeInterface $lastBackupGeneratedAt): static
    {
        $this->lastBackupGeneratedAt = $lastBackupGeneratedAt;

        return $this;
    }

    public function hasLastDatabaseBackup(): bool
    {
        return null !== $this->lastBackupFilePath
            && '' !== $this->lastBackupFilePath
            && $this->lastBackupGeneratedAt instanceof DateTimeInterface;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function touchLastUpdatedAt(): void
    {
        $this->lastUpdatedAt = new DateTime();
    }
}
