<?php

declare(strict_types=1);

namespace App\Web\Registration;

final class RegistrationData
{
    public string $name = '';

    public string $lastname = '';

    public string $email = '';

    public string $password = '';

    public string $passwordConfirm = '';

    public bool $terms = false;

    public string $timezone = '';

    public string $locale = '';

    public string $timeFormat = '';

    public string $companyFax = '';
}
