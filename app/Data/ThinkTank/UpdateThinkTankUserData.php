<?php

namespace App\Data\ThinkTank;

final readonly class UpdateThinkTankUserData
{
    /**
     * @param  array<string, mixed>  $changes
     */
    public function __construct(public array $changes) {}

    /** @param array<string, mixed> $data */
    public static function from(array $data): self
    {
        $changes = [];

        foreach (['name', 'email', 'access_level', 'is_disabled'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $changes[$field] = match ($field) {
                'name' => trim((string) $data[$field]),
                'email' => mb_strtolower(trim((string) $data[$field])),
                'is_disabled' => (bool) $data[$field],
                default => $data[$field],
            };
        }

        return new self($changes);
    }

    public function has(string $field): bool
    {
        return array_key_exists($field, $this->changes);
    }

    public function get(string $field, mixed $default = null): mixed
    {
        return $this->changes[$field] ?? $default;
    }
}
