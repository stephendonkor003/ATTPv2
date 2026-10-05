<?php

namespace App\Models;

class Role extends BaseModel
{
    public const AUDITOR_NAME = 'Auditor';

    protected $fillable = [
        'name',
        'description',
        'is_read_only_auditor',
    ];

    protected function casts(): array
    {
        return [
            'is_read_only_auditor' => 'boolean',
        ];
    }

    /* ===============================
     | RELATIONSHIPS
     =============================== */

    public function permissions()
    {
        return $this->belongsToMany(
            Permission::class,
            'role_permission'
        );
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    /* ===============================
     | HELPERS
     =============================== */

    public function hasPermission(string $permission): bool
    {
        return $this->permissions->contains('name', $permission);
    }

    /**
     * The persisted flag is the canonical identity and survives a role rename.
     * The name check keeps existing installations safe until the flag migration
     * has been applied.
     */
    public function isReadOnlyAuditor(): bool
    {
        return (bool) $this->is_read_only_auditor
            || trim((string) $this->name) === self::AUDITOR_NAME;
    }
}
