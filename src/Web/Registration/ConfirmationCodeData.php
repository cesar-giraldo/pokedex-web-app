<?php

declare(strict_types=1);

namespace App\Web\Registration;

final class ConfirmationCodeData
{
    public string $digit1 = '';

    public string $digit2 = '';

    public string $digit3 = '';

    public string $digit4 = '';

    public string $digit5 = '';

    public string $digit6 = '';

    public function code(): string
    {
        return $this->digit1 . $this->digit2 . $this->digit3 . $this->digit4 . $this->digit5 . $this->digit6;
    }
}
