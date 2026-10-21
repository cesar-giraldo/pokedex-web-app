<?php

declare(strict_types=1);

namespace App\Tests\Double;

use App\Web\Validator\DnsRecordLookup;

use function str_ends_with;

use const DNS_MX;

final class PredictableDnsRecordLookup implements DnsRecordLookup
{
    public function lookup(string $hostname, int $type): array|false
    {
        if (str_ends_with($hostname, 'dns-down.test')) {
            return false;
        }

        if (str_ends_with($hostname, 'invalid.test')) {
            return [];
        }

        if (DNS_MX === $type) {
            return [[
                'host' => $hostname,
                'type' => 'MX',
                'pri' => 10,
                'target' => 'mail.' . $hostname,
            ]];
        }

        return [];
    }
}
