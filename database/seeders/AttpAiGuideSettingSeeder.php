<?php

namespace Database\Seeders;

use App\Models\AttpAiGuideSetting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AttpAiGuideSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        AttpAiGuideSetting::firstOrCreate(
            ['id' => 1],
            [
                'name' => 'ATTP AI Guide',
                'description' => 'AI-powered intelligent chat assistant for ATTP system users',
                'enabled' => true,
                'tawk_property_id' => '69204852eba156195f5dae48',
                'tawk_widget_id' => '1jaj1trqo',
                'show_to_authenticated_only' => true,
                'show_to_guests' => false,
                'targeted_user_roles' => null,
                'welcome_message' => 'Welcome to ATTP AI Guide! I\'m here to help you navigate the system and answer your questions. How can I assist you today?',
            ]
        );
    }
}
