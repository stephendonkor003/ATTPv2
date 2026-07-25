<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

final class ProcurementReviewAssignees
{
    public const EXCLUDED_USER_TYPES = [
        'vendor',
        'think_tank',
    ];

    public const INELIGIBLE_MESSAGE = 'Vendor and think tank users cannot be assigned to procurement reviews.';

    public static function query(): Builder
    {
        return User::query()
            ->where(function (Builder $query): void {
                $query->whereNull('user_type')
                    ->orWhereNotIn('user_type', self::EXCLUDED_USER_TYPES);
            });
    }

    public static function existsRule(): Exists
    {
        return Rule::exists('users', 'id')
            ->where(function ($query): void {
                $query->where(function ($eligible): void {
                    $eligible->whereNull('user_type')
                        ->orWhereNotIn('user_type', self::EXCLUDED_USER_TYPES);
                });
            });
    }
}
