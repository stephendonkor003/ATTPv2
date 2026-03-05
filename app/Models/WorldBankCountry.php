<?php

namespace App\Models;

class WorldBankCountry extends BaseModel
{
    protected $table = 'world_bank_countries';

    protected $fillable = [
        'wb_country_id',
        'iso2_code',
        'name',
        'region',
        'admin_region',
        'income_level',
        'lending_type',
        'capital_city',
        'longitude',
        'latitude',
        'continent',
        'is_aggregate',
    ];

    protected $casts = [
        'is_aggregate' => 'boolean',
    ];
}

