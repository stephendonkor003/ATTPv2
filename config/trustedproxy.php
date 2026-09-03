<?php

$proxies = array_values(array_filter(array_map(
    static fn (string $proxy): string => trim($proxy),
    explode(',', (string) env('THINK_TANK_TRUSTED_PROXIES', ''))
)));

return [
    'proxies' => $proxies === [] ? null : $proxies,
];
