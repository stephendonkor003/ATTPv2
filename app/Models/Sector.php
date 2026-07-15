<?php



namespace App\Models;

use App\Models\BaseModel;
use App\Models\GovernanceNode;

class Sector extends BaseModel
{
    protected $table = 'myb_sectors';

    protected $fillable = [
        'name',
        'description',
        'status',
        'currency',
        'governance_node_id',
        'portfolio_manager_user_id',
        'portfolio_manager_name',
        'portfolio_manager_email',
        'portfolio_manager_role',
        'ttl_name',
        'ttl_email',
        'me_manager_user_id',
        'me_manager_name',
        'me_manager_email',
    ];

    public function programs()
    {
        return $this->hasMany(Program::class, 'sector_id');
    }

    public function governanceNode()
    {
        return $this->belongsTo(GovernanceNode::class, 'governance_node_id');
    }

    public function portfolioManager()
    {
        return $this->belongsTo(User::class, 'portfolio_manager_user_id');
    }

    public function meManager()
    {
        return $this->belongsTo(User::class, 'me_manager_user_id');
    }

    public function dataEntryForms()
    {
        return $this->hasMany(MeDataEntryForm::class, 'portfolio_id');
    }

    public function reportingPeriods()
    {
        return $this->hasMany(MeReportingPeriod::class, 'portfolio_id');
    }
}
