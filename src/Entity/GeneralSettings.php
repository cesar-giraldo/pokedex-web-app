<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\SupportedLanguage;
use App\Repository\GeneralSettingsRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

use function in_array;

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

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private DateTimeInterface $lastUpdatedAt;

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

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function touchLastUpdatedAt(): void
    {
        $this->lastUpdatedAt = new DateTime();
    }
}
