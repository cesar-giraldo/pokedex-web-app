<?php

declare(strict_types=1);

namespace App\Web\Validator;

interface DnsRecordLookup
{
    /**
     * @return list<array<string, mixed>>|false
     */
    public function lookup(string $hostname, int $type): array|false;
}
