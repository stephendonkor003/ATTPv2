<?php

namespace Database\Seeders;

use App\Models\GeoRegion;
use Illuminate\Database\Seeder;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

class GeoRegionSeeder extends Seeder
{
    public function run(): void
    {
        $filePath = base_path('database/seeders/data.xlsx');

        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        $header = array_map('strtolower', $rows[0]);

        $continentIndex = array_search('continent', $header);
        $subRegionIndex = array_search('sub_region', $header);
        $countryIndex = array_search('country', $header);
        $regionGroupIndex = array_search('region_group', $header);

        $unique = [];

        foreach (array_slice($rows, 1) as $row) {
            $key = implode('|', [
                $row[$continentIndex] ?? '',
                $row[$subRegionIndex] ?? '',
                $row[$countryIndex] ?? '',
                $row[$regionGroupIndex] ?? '',
            ]);

            if (!isset($unique[$key])) {
                GeoRegion::updateOrCreate(
                    [
                        'continent'     => $row[$continentIndex] ?? null,
                        'sub_region'    => $row[$subRegionIndex] ?? null,
                        'country'       => $row[$countryIndex] ?? null,
                        'region_group'  => $row[$regionGroupIndex] ?? null,
                    ],
                    [
                    'continent'     => $row[$continentIndex] ?? null,
                    'sub_region'    => $row[$subRegionIndex] ?? null,
                    'country'       => $row[$countryIndex] ?? null,
                    'region_group'  => $row[$regionGroupIndex] ?? null,
                    ]
                );
                $unique[$key] = true;
            }
        }
    }
}
