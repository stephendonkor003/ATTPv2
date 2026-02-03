<?php

namespace Database\Seeders;

use App\Models\ThinkDataset;
use App\Models\User;
use Illuminate\Database\Seeder;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ThinkTankDatasetSeeder extends Seeder
{
    public function run(): void
    {
        // UUID migration: use a real user id instead of hardcoding "1".
        $createdBy = User::where('email', 'amodonlimited@gmail.com')->value('id')
            ?? User::query()->value('id');

        $filePath = database_path('seeders/thindata.xlsx');

        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray(null, true, true, true);

        // Use the first row as headers
        $headers = array_map('trim', array_values($rows[1]));
        unset($rows[1]);

        foreach ($rows as $row) {
            $record = array_combine($headers, array_values($row));

            ThinkDataset::create([
                'ottd_id' => $record['ottd_id'] ?? null,
                'tt_name_en' => $record['tt_name_en'] ?? null,
                'country' => $record['country'] ?? null,
                'continent' => $record['continent'] ?? null,
                'sub_region' => $record['sub_region'] ?? null,
                'Count' => $record['Count'] ?? null,
                'website' => $record['website'] ?? null,
                'g_email' => $record['g_email'] ?? null,
                'operating_langs' => $record['operating_langs'] ?? null,
                'tt_init' => $record['tt_init'] ?? null,
                'description' => $record['description'] ?? null,
                'main_city' => $record['main_city'] ?? null,
                'Region_group' => $record['Region_group'] ?? null,
                'other_offices' => $record['other_offices'] ?? null,
                'address' => $record['address'] ?? null,
                'tt_business_model' => $record['tt_business_model'] ?? null,
                'Funding_sources' => $record['Funding.sources'] ?? null,
                'Funding_Mechanism' => $record['Funding.Mechanism'] ?? null,
                'tt_affiliations' => $record['tt_affiliations'] ?? null,
                'topics' => $record['topics'] ?? null,
                'geographies' => $record['geographies'] ?? null,
                'date_founded' => $record['date_founded'] ?? null,
                'Date_founded_groups' => $record['Date founded groups'] ?? null,
                'founder' => $record['founder'] ?? null,
                'founder_gender' => $record['founder_gender'] ?? null,
                'founder_other_type' => $record['founder_other_type'] ?? null,
                'staff_no' => $record['staff_no'] ?? null,
                'pc_staff_female' => $record['pc_staff_female'] ?? null,
                'pc_res_staff_female' => $record['pc_res_staff_female'] ?? null,
                'assc_no' => $record['assc_no'] ?? null,
                'assc_female_no' => $record['assc_female_no'] ?? null,
                'pub_no' => $record['pub_no'] ?? null,
                'fin_usd' => $record['fin_usd'] ?? null,
                'twitter_handle_link' => $record['twitter_handle_link'] ?? null,
                'facebook_page' => $record['facebook_page'] ?? null,
                'youtube_page' => $record['youtube_page'] ?? null,
                'instagram_acc' => $record['instagram_acc'] ?? null,
                'linkedIn_acc' => $record['linkedIn_acc'] ?? null,
                'created_by' => $createdBy,
                'is_validated' => 'Yes',
            ]);
        }
    }
}
