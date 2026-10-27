<?php

declare(strict_types=1);

namespace App\Web\Validator;

final class NativeDnsRecordLookup implements DnsRecordLookup
{
    public function lookup(string $hostname, int $type): array|false
    {
        return dns_get_record($hostname, $type);
    }
}
