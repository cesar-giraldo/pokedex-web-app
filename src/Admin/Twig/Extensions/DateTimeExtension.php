<?php

declare(strict_types=1);

namespace App\Admin\Twig\Extensions;

use App\Admin\Data\IanaTimezones;
use App\Admin\Service\DateTimeFormatter;
use App\Entity\User;
use DateTimeInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class DateTimeExtension extends AbstractExtension
{
    public function __construct(
        private readonly DateTimeFormatter $dateTimeFormatter,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('app_datetime', $this->formatDateTime(...)),
            new TwigFilter('timezone_label', $this->timezoneLabel(...)),
        ];
    }

    public function formatDateTime(
        ?DateTimeInterface $value,
        string $empty = '—',
        ?User $viewer = null,
    ): string {
        return $this->dateTimeFormatter->format($value, $empty, $viewer);
    }

    public function timezoneLabel(string $identifier): string
    {
        return IanaTimezones::label($identifier);
    }
}
