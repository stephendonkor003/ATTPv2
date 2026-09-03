<?php

namespace App\Data\ThinkTank;

final readonly class CreateThinkTankUserData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $accessLevel,
    ) {}

    /** @param array{name: string, email: string, access_level: string} $data */
    public static function from(array $data): self
    {
        return new self(
            trim($data['name']),
            mb_strtolower(trim($data['email'])),
            $data['access_level'],
        );
    }
}
