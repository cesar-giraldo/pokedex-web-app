<?php

declare(strict_types=1);

namespace App\Tests\Web\Support;

use App\Entity\User;
use App\Legal\LegalAcceptanceService;

trait AcceptsPendingLegalDocumentsTrait
{
    private function acceptPendingLegalDocuments(User $user): void
    {
        /** @var LegalAcceptanceService $acceptance */
        $acceptance = static::getContainer()->get(LegalAcceptanceService::class);

        while ($acceptance->hasPending($user)) {
            $acceptance->acceptCurrent($user, '127.0.0.1', 'functional-test');
        }
    }
}
