<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttpAiGuideSetting extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'attp_ai_guide_settings';

    protected $fillable = [
        'name',
        'description',
        'enabled',
        'tawk_property_id',
        'tawk_widget_id',
        'show_to_authenticated_only',
        'show_to_guests',
        'targeted_user_roles',
        'welcome_message',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'show_to_authenticated_only' => 'boolean',
        'show_to_guests' => 'boolean',
        'targeted_user_roles' => 'array',
    ];

    /**
     * Get the active settings
     */
    public static function active()
    {
        return self::where('enabled', true)->first();
    }

    /**
     * Check if widget is available for current user
     */
    public function isAvailableForUser($user = null)
    {
        if (!$this->enabled) {
            return false;
        }

        $user = $user ?? auth()->user();

        if (!$user && $this->show_to_guests) {
            return true;
        }

        if ($user && $this->show_to_authenticated_only) {
            return true;
        }

        if ($user && !empty($this->targeted_user_roles)) {
            return $user->hasAnyRole($this->targeted_user_roles);
        }

        return false;
    }
}
