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
        'governance_node_id',
    ];

    public function programs()
    {
        return $this->hasMany(Program::class, 'sector_id');
    }

    public function governanceNode()
    {
        return $this->belongsTo(GovernanceNode::class, 'governance_node_id');
    }
}
