<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum LegalDocumentVersionStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Published => 'Vigente',
            self::Archived => 'Archivada',
        };
    }
}
