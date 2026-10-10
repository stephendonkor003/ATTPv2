<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AuMemberState;

class AuMemberStateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Seeds all 55 African Union member states.
     */
    public function run(): void
    {
        $memberStates = [
            ['name' => 'Algeria', 'code' => 'DZA', 'code_alpha2' => 'DZ'],
            ['name' => 'Angola', 'code' => 'AGO', 'code_alpha2' => 'AO'],
            ['name' => 'Benin', 'code' => 'BEN', 'code_alpha2' => 'BJ'],
            ['name' => 'Botswana', 'code' => 'BWA', 'code_alpha2' => 'BW'],
            ['name' => 'Burkina Faso', 'code' => 'BFA', 'code_alpha2' => 'BF'],
            ['name' => 'Burundi', 'code' => 'BDI', 'code_alpha2' => 'BI'],
            ['name' => 'Cabo Verde', 'code' => 'CPV', 'code_alpha2' => 'CV'],
            ['name' => 'Cameroon', 'code' => 'CMR', 'code_alpha2' => 'CM'],
            ['name' => 'Central African Republic', 'code' => 'CAF', 'code_alpha2' => 'CF'],
            ['name' => 'Chad', 'code' => 'TCD', 'code_alpha2' => 'TD'],
            ['name' => 'Comoros', 'code' => 'COM', 'code_alpha2' => 'KM'],
            ['name' => 'Congo', 'code' => 'COG', 'code_alpha2' => 'CG'],
            ['name' => 'Côte d\'Ivoire', 'code' => 'CIV', 'code_alpha2' => 'CI'],
            ['name' => 'Democratic Republic of the Congo', 'code' => 'COD', 'code_alpha2' => 'CD'],
            ['name' => 'Djibouti', 'code' => 'DJI', 'code_alpha2' => 'DJ'],
            ['name' => 'Egypt', 'code' => 'EGY', 'code_alpha2' => 'EG'],
            ['name' => 'Equatorial Guinea', 'code' => 'GNQ', 'code_alpha2' => 'GQ'],
            ['name' => 'Eritrea', 'code' => 'ERI', 'code_alpha2' => 'ER'],
            ['name' => 'Eswatini', 'code' => 'SWZ', 'code_alpha2' => 'SZ'],
            ['name' => 'Ethiopia', 'code' => 'ETH', 'code_alpha2' => 'ET'],
            ['name' => 'Gabon', 'code' => 'GAB', 'code_alpha2' => 'GA'],
            ['name' => 'Gambia', 'code' => 'GMB', 'code_alpha2' => 'GM'],
            ['name' => 'Ghana', 'code' => 'GHA', 'code_alpha2' => 'GH'],
            ['name' => 'Guinea', 'code' => 'GIN', 'code_alpha2' => 'GN'],
            ['name' => 'Guinea-Bissau', 'code' => 'GNB', 'code_alpha2' => 'GW'],
            ['name' => 'Kenya', 'code' => 'KEN', 'code_alpha2' => 'KE'],
            ['name' => 'Lesotho', 'code' => 'LSO', 'code_alpha2' => 'LS'],
            ['name' => 'Liberia', 'code' => 'LBR', 'code_alpha2' => 'LR'],
            ['name' => 'Libya', 'code' => 'LBY', 'code_alpha2' => 'LY'],
            ['name' => 'Madagascar', 'code' => 'MDG', 'code_alpha2' => 'MG'],
            ['name' => 'Malawi', 'code' => 'MWI', 'code_alpha2' => 'MW'],
            ['name' => 'Mali', 'code' => 'MLI', 'code_alpha2' => 'ML'],
            ['name' => 'Mauritania', 'code' => 'MRT', 'code_alpha2' => 'MR'],
            ['name' => 'Mauritius', 'code' => 'MUS', 'code_alpha2' => 'MU'],
            ['name' => 'Morocco', 'code' => 'MAR', 'code_alpha2' => 'MA'],
            ['name' => 'Mozambique', 'code' => 'MOZ', 'code_alpha2' => 'MZ'],
            ['name' => 'Namibia', 'code' => 'NAM', 'code_alpha2' => 'NA'],
            ['name' => 'Niger', 'code' => 'NER', 'code_alpha2' => 'NE'],
            ['name' => 'Nigeria', 'code' => 'NGA', 'code_alpha2' => 'NG'],
            ['name' => 'Rwanda', 'code' => 'RWA', 'code_alpha2' => 'RW'],
            ['name' => 'Sahrawi Arab Democratic Republic', 'code' => 'ESH', 'code_alpha2' => 'EH'],
            ['name' => 'São Tomé and Príncipe', 'code' => 'STP', 'code_alpha2' => 'ST'],
            ['name' => 'Senegal', 'code' => 'SEN', 'code_alpha2' => 'SN'],
            ['name' => 'Seychelles', 'code' => 'SYC', 'code_alpha2' => 'SC'],
            ['name' => 'Sierra Leone', 'code' => 'SLE', 'code_alpha2' => 'SL'],
            ['name' => 'Somalia', 'code' => 'SOM', 'code_alpha2' => 'SO'],
            ['name' => 'South Africa', 'code' => 'ZAF', 'code_alpha2' => 'ZA'],
            ['name' => 'South Sudan', 'code' => 'SSD', 'code_alpha2' => 'SS'],
            ['name' => 'Sudan', 'code' => 'SDN', 'code_alpha2' => 'SD'],
            ['name' => 'Tanzania', 'code' => 'TZA', 'code_alpha2' => 'TZ'],
            ['name' => 'Togo', 'code' => 'TGO', 'code_alpha2' => 'TG'],
            ['name' => 'Tunisia', 'code' => 'TUN', 'code_alpha2' => 'TN'],
            ['name' => 'Uganda', 'code' => 'UGA', 'code_alpha2' => 'UG'],
            ['name' => 'Zambia', 'code' => 'ZMB', 'code_alpha2' => 'ZM'],
            ['name' => 'Zimbabwe', 'code' => 'ZWE', 'code_alpha2' => 'ZW'],
        ];

        foreach ($memberStates as &$memberState) {
            if ($memberState['code'] === 'CIV') {
                $memberState['name'] = "Côte d'Ivoire";
            } elseif ($memberState['code'] === 'STP') {
                $memberState['name'] = 'São Tomé and Príncipe';
            }
        }
        unset($memberState);

        foreach ($memberStates as $index => $state) {
            AuMemberState::updateOrCreate(
                ['code' => $state['code']],
                [
                    'name' => $state['name'],
                    'code' => $state['code'],
                    'code_alpha2' => $state['code_alpha2'],
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ]
            );
        }
    }
}
