<?php

namespace App\Imports;

use App\Mail\VendorAccountCreated;
use App\Models\User;
use App\Models\VendorCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class VendorImport implements ToCollection, WithHeadingRow, WithValidation, SkipsEmptyRows
{
    private int $created = 0;
    private array $duplicates = [];
    private array $createdEmails = [];
    private array $mailFailures = [];

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            $email = trim((string) ($row['email'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            $category = trim((string) ($row['vendor_category'] ?? ''));
            $categoryName = null;
            if ($category !== '') {
                $match = VendorCategory::whereRaw('LOWER(name) = ?', [Str::lower($category)])->first();
                if ($match) {
                    $categoryName = $match->name;
                }
            }

            if ($email === '') {
                continue;
            }

            $emailKey = Str::lower($email);

            if (in_array($emailKey, $this->createdEmails, true)) {
                $this->duplicates[] = $email;
                continue;
            }

            $existing = User::whereRaw('LOWER(email) = ?', [$emailKey])->first();
            if ($existing) {
                $this->duplicates[] = $email;
                continue;
            }

            $password = Str::random(12);
            $isDisabled = $this->parseBoolean($row['disabled'] ?? 'no');
            $isBlacklisted = $this->parseBoolean($row['blacklisted'] ?? 'no');

            $vendor = User::create([
                'name' => $name !== '' ? $name : $email,
                'email' => $email,
                'password' => Hash::make($password),
                'user_type' => 'vendor',
                'vendor_category' => $categoryName ?: null,
                'is_disabled' => $isDisabled,
                'disabled_at' => $isDisabled ? now() : null,
                'is_blacklisted' => $isBlacklisted,
                'blacklisted_at' => $isBlacklisted ? now() : null,
                'must_change_password' => true,
            ]);

            try {
                Mail::to($vendor->email)->queue(new VendorAccountCreated($vendor, $password));
            } catch (\Throwable $exception) {
                $this->mailFailures[] = [
                    'email' => $vendor->email,
                    'error' => $exception->getMessage(),
                ];
                \Log::error('Vendor account email failed', [
                    'vendor_email' => $vendor->email,
                    'error' => $exception->getMessage(),
                ]);
            }

            $this->created++;
            $this->createdEmails[] = $emailKey;
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'vendor_category' => [
                'nullable',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    if ($value === null || trim((string) $value) === '') {
                        return;
                    }

                    $exists = VendorCategory::whereRaw('LOWER(name) = ?', [Str::lower((string) $value)])->exists();
                    if (!$exists) {
                        $fail('Vendor category "' . $value . '" does not exist.');
                    }
                },
            ],
            'disabled' => 'nullable|string',
            'blacklisted' => 'nullable|string',
        ];
    }

    public function customValidationMessages()
    {
        return [
            'name.required' => 'Each row must include a vendor name.',
            'email.required' => 'Each row must include an email address.',
            'email.email' => 'Each vendor email must be a valid email address.',
        ];
    }

    public function summary(): array
    {
        return [
            'created' => $this->created,
            'duplicates' => array_values(array_unique($this->duplicates)),
            'mail_failures' => $this->mailFailures,
        ];
    }

    private function parseBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $value = strtolower(trim((string) $value));

        return in_array($value, ['yes', 'true', '1', 'y', 'disabled', 'blacklisted'], true);
    }
}
