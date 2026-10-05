<?php

namespace App\Support;

final class SanctumStatefulDomainList
{
    /** @return list<string> */
    public static function parse(string $domains): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (string $domain): string => mb_strtolower(trim($domain)),
            explode(',', $domains),
        ))));
    }
}
