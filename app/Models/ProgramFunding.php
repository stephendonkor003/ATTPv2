<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\GovernanceNode;
use App\Models\User;

class ProgramFunding extends BaseModel
{
    protected $table = 'myb_program_fundings';

    protected $fillable = [
        'department_id',
        'program_id',
        'program_name',
        'funder_id',
        'governance_node_id',
        'funding_type',
        'approved_amount',
        'currency',
        'start_year',
        'end_year',
        'status',
        'is_continental_initiative',
        'rejection_reason',
        'rejected_by',
        'rejected_at',
        'approved_by',
        'approved_at',
        'created_by',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'approved_amount' => 'decimal:2',
        'is_continental_initiative' => 'boolean',
    ];

    /* ==========================
     * RELATIONSHIPS
     * ========================== */

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function program()
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    public function funder()
    {
        return $this->belongsTo(Funder::class, 'funder_id');
    }

    public function governanceNode()
    {
        return $this->belongsTo(GovernanceNode::class, 'governance_node_id');
    }




    public function documents()
    {
        return $this->hasMany(
            ProgramFundingDocument::class,
            'program_funding_id'
        );
    }


    /* ==========================
     * STATUS HELPERS
     * ========================== */

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isExpired(): bool
    {
        return now()->year > $this->end_year;
    }

    /* ==========================
     * YEAR HELPERS
     * ========================== */

    public function years(): array
    {
        return range($this->start_year, $this->end_year);
    }

    public function commitments()
{
    return $this->hasMany(BudgetCommitment::class, 'program_funding_id');
}

public function consortia()
{
    return $this->hasMany(Consortium::class, 'program_funding_id');
}

public function consortiumFundAllocations()
{
    return $this->hasMany(ConsortiumFundAllocation::class, 'program_funding_id');
}


public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejector()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /* ==========================
     * AU STRATEGIC ALIGNMENT RELATIONSHIPS
     * ========================== */

    /**
     * Beneficiary member states for this funding.
     */
    public function memberStates(): BelongsToMany
    {
        return $this->belongsToMany(
            AuMemberState::class,
            'myb_program_funding_member_states',
            'program_funding_id',
            'member_state_id'
        )->withTimestamps();
    }

    /**
     * Regional blocks targeted by this funding.
     */
    public function regionalBlocks(): BelongsToMany
    {
        return $this->belongsToMany(
            AuRegionalBlock::class,
            'myb_program_funding_regional_blocks',
            'program_funding_id',
            'regional_block_id'
        )->withTimestamps();
    }

    /**
     * Agenda 2063 aspirations aligned with this funding.
     */
    public function aspirations(): BelongsToMany
    {
        return $this->belongsToMany(
            AuAspiration::class,
            'myb_program_funding_aspirations',
            'program_funding_id',
            'aspiration_id'
        )->withTimestamps();
    }

    /**
     * Agenda 2063 goals aligned with this funding.
     */
    public function goals(): BelongsToMany
    {
        return $this->belongsToMany(
            AuGoal::class,
            'myb_program_funding_goals',
            'program_funding_id',
            'goal_id'
        )->withTimestamps();
    }

    /**
     * AU flagship projects aligned with this funding.
     */
    public function flagshipProjects(): BelongsToMany
    {
        return $this->belongsToMany(
            AuFlagshipProject::class,
            'myb_program_funding_flagship_projects',
            'program_funding_id',
            'flagship_project_id'
        )->withTimestamps();
    }

}
