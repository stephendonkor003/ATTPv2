<?php

namespace App\Models;

use App\Models\BaseModel;

class ThinkDataset extends BaseModel
{
    protected $fillable = [
        'ottd_id', 'tt_name_en', 'country', 'continent', 'sub_region', 'Count', 'website',
        'g_email', 'operating_langs', 'tt_init', 'description', 'main_city', 'Region_group',
        'other_offices', 'address', 'tt_business_model', 'Funding_sources', 'Funding_Mechanism',
        'tt_affiliations', 'topics', 'geographies', 'date_founded', 'Date_founded_groups',
        'founder', 'founder_gender', 'founder_other_type', 'staff_no', 'pc_staff_female',
        'pc_res_staff_female', 'assc_no', 'assc_female_no', 'pub_no', 'fin_usd',
        'twitter_handle_link', 'facebook_page', 'youtube_page', 'instagram_acc', 'linkedIn_acc',
        'is_validated', 'created_by'
    ];

    public function consortiumMemberships()
    {
        return $this->hasMany(ConsortiumThinkTank::class, 'think_dataset_id');
    }

    public function ledConsortia()
    {
        return $this->hasMany(Consortium::class, 'lead_think_dataset_id');
    }
}
