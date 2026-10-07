<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum LegalDocumentType: string
{
    case PrivacyPolicy = 'privacy_policy';
    case TermsOfUse = 'terms_of_use';

    public function label(): string
    {
        return match ($this) {
            self::PrivacyPolicy => 'Política de privacidad',
            self::TermsOfUse => 'Términos y condiciones de uso',
        };
    }

    public function acceptanceOrder(): int
    {
        return match ($this) {
            self::PrivacyPolicy => 1,
            self::TermsOfUse => 2,
        };
    }

    public function defaultNotificationTitle(int $versionNumber): string
    {
        return $this->label() . ' v' . $versionNumber;
    }

    public function defaultNotificationMessage(): string
    {
        return match ($this) {
            self::PrivacyPolicy => 'Conoce nuestra Política de privacidad. Debes leerla y aceptarla para continuar.',
            self::TermsOfUse => 'Conoce nuestros Términos y condiciones de uso. Debes leerlos y aceptarlos para continuar.',
        };
    }
}
