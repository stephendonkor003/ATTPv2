<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ThinkDataset;
use App\Models\User;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ThinkDatasetSeeder extends Seeder
{
    public function run()
    {
        // UUID migration: use a real user id instead of hardcoding "1".
        $createdBy = User::where('email', 'amodonlimited@gmail.com')->value('id')
            ?? User::query()->value('id');

        $filePath = database_path('seeders/thindata.xlsx');
        if (!file_exists($filePath)) {
            $this->command?->warn('Think tank dataset workbook not found at database/seeders/thindata.xlsx.');
            return;
        }

        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        $header = array_map('trim', array_values($rows[1]));
        unset($rows[1]);

        foreach ($rows as $row) {
            $data = array_combine($header, array_values($row));
            $identity = [
                'tt_name_en' => $data['Tt Name En'] ?? null,
                'country' => $data['Country'] ?? null,
            ];

            if (blank($identity['tt_name_en']) && blank($identity['country'])) {
                continue;
            }

            ThinkDataset::updateOrCreate($identity, [
                'ottd_id' => $data['Ottd Id'] ?? null,
                'continent' => $data['Continent'] ?? null,
                'sub_region' => $data['Sub Region'] ?? null,
                'Count' => $data['Count'] ?? null,
                'website' => $data['Website'] ?? null,
                'g_email' => $data['G Email'] ?? null,
                'operating_langs' => $data['Operating Langs'] ?? null,
                'tt_init' => $data['Tt Init'] ?? null,
                'description' => $data['Description'] ?? null,
                'main_city' => $data['Main City'] ?? null,
                'Region_group' => $data['Region Group'] ?? null,
                'other_offices' => $data['Other Offices'] ?? null,
                'address' => $data['Address'] ?? null,
                'tt_business_model' => $data['Tt Business Model'] ?? null,
                'Funding_sources' => $data['Funding.sources'] ?? null,
                'Funding_Mechanism' => $data['Funding.Mechanism'] ?? null,
                'tt_affiliations' => $data['Tt Affiliations'] ?? null,
                'topics' => $data['Topics'] ?? null,
                'geographies' => $data['Geographies'] ?? null,
                'date_founded' => $data['Date Founded'] ?? null,
                'Date_founded_groups' => $data['Date Founded Groups'] ?? null,
                'founder' => $data['Founder'] ?? null,
                'founder_gender' => $data['Founder Gender'] ?? null,
                'founder_other_type' => $data['Founder Other Type'] ?? null,
                'staff_no' => $data['Staff No'] ?? null,
                'pc_staff_female' => $data['Pc Staff Female'] ?? null,
                'pc_res_staff_female' => $data['Pc Res Staff Female'] ?? null,
                'assc_no' => $data['Assc No'] ?? null,
                'assc_female_no' => $data['Assc Female No'] ?? null,
                'pub_no' => $data['Pub No'] ?? null,
                'fin_usd' => $data['Fin Usd'] ?? null,
                'twitter_handle_link' => $data['Twitter Handle Link'] ?? null,
                'facebook_page' => $data['Facebook Page'] ?? null,
                'youtube_page' => $data['Youtube Page'] ?? null,
                'instagram_acc' => $data['Instagram Acc'] ?? null,
                'linkedIn_acc' => $data['Linkedin Acc'] ?? null,
                'created_by' => $createdBy,
                'is_validated' => 'Yes',
            ]);
        }
    }
}
