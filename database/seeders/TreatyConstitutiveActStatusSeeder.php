<?php

namespace Database\Seeders;

use App\Models\AuMemberState;
use App\Models\Treaty;
use App\Models\TreatyMemberStateStatus;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TreatyConstitutiveActStatusSeeder extends Seeder
{
    private const SOURCE_URL = 'https://au.int/sites/default/files/treaties/7758-sl-constitutive_act_of_the_african_union_2.pdf';
    private const SOURCE_DATE = '2017-06-15';

    public function run(): void
    {
        if (AuMemberState::query()->count() < 55) {
            $this->command?->warn('AU member states are incomplete. Seeding AU member states first.');
            $this->call(AuMemberStateSeeder::class);
        }

        $treaty = $this->resolveTreaty();

        if (!$treaty) {
            $this->command?->warn('TreatyConstitutiveActStatusSeeder skipped: treaty not found. Run TreatySeeder first.');
            return;
        }

        $memberStatesByNormalized = AuMemberState::query()
            ->get(['id', 'name'])
            ->mapWithKeys(function (AuMemberState $state) {
                return [$this->normalizeCountryName($state->name) => $state];
            });

        if ($memberStatesByNormalized->isEmpty()) {
            $this->command?->warn('TreatyConstitutiveActStatusSeeder skipped: no AU member states found.');
            return;
        }

        $seedUserId = User::query()
            ->where('user_type', 'admin')
            ->value('id') ?? User::query()->oldest()->value('id');

        $aliases = $this->countryAliases();
        $rows = $this->statusRows();
        $seeded = 0;
        $missing = [];

        foreach ($rows as $row) {
            $normalizedPdfName = $this->normalizeCountryName($row['country']);
            $lookupName = $aliases[$normalizedPdfName] ?? $normalizedPdfName;

            /** @var AuMemberState|null $memberState */
            $memberState = $memberStatesByNormalized->get($lookupName);

            if (!$memberState) {
                $missing[] = $row['country'];
                continue;
            }

            $signedAt = $this->parsePdfDate($row['signature_date']);
            $ratifiedAt = $this->parsePdfDate($row['ratification_or_accession_date']);
            $depositedAt = $this->parsePdfDate($row['deposit_date']);

            $isRatified = !is_null($ratifiedAt);
            // Ratification by accession may exist without signature date (e.g. Morocco).
            $isSigned = !is_null($signedAt) || $isRatified;

            $status = TreatyMemberStateStatus::query()->firstOrNew([
                'treaty_id' => $treaty->id,
                'member_state_id' => $memberState->id,
            ]);

            $status->is_signed = $isSigned;
            $status->signed_at = $signedAt ?? ($isRatified ? $ratifiedAt : null);

            $status->is_ratified = $isRatified;
            $status->ratified_at = $ratifiedAt;

            if ($isSigned && empty($status->signed_notes)) {
                $status->signed_notes = $signedAt
                    ? 'Seeded from AU treaty status list PDF dated ' . Carbon::parse(self::SOURCE_DATE)->format('d/m/Y') . '.'
                    : 'Seeded as accession record from AU treaty status list PDF (no signature date published).';
            }

            if ($isRatified) {
                $status->ratified_notes = $this->buildRatifiedNote($row, $depositedAt, (string) $status->ratified_notes);
            }

            if ($seedUserId) {
                $status->updated_by = $seedUserId;
            }

            $status->save();
            $seeded++;
        }

        if (!empty($missing)) {
            $this->command?->warn('TreatyConstitutiveActStatusSeeder missing member-state matches: ' . implode(', ', array_unique($missing)));
        }

        $this->command?->info("TreatyConstitutiveActStatusSeeder synced {$seeded} member-state status rows for '{$treaty->title}'.");
    }

    private function resolveTreaty(): ?Treaty
    {
        $treaty = Treaty::query()
            ->where('title', 'Constitutive Act of the African Union')
            ->orWhere('title', 'like', '%Constitutive Act%African Union%')
            ->first();

        if ($treaty) {
            return $treaty;
        }

        $this->command?->warn('Treaty not found. Seeding treaty register first.');
        $this->call(TreatySeeder::class);

        return Treaty::query()
            ->where('title', 'Constitutive Act of the African Union')
            ->orWhere('title', 'like', '%Constitutive Act%African Union%')
            ->first();
    }

    private function buildRatifiedNote(array $row, ?Carbon $depositedAt, string $existing): string
    {
        $sourceText = 'Seeded from AU treaty status list PDF dated '
            . Carbon::parse(self::SOURCE_DATE)->format('d/m/Y')
            . ' (' . self::SOURCE_URL . ').';

        $parts = [];
        if ($depositedAt) {
            $parts[] = 'Deposit date: ' . $depositedAt->format('d/m/Y') . '.';
        } elseif (!empty($row['deposit_date']) && $row['deposit_date'] !== '-') {
            $parts[] = 'Deposit date: ' . $row['deposit_date'] . '.';
        }

        $appended = trim($sourceText . ' ' . implode(' ', $parts));

        if ($existing !== '' && !Str::contains($existing, self::SOURCE_URL)) {
            return trim($existing . ' ' . $appended);
        }

        return $existing !== '' ? $existing : $appended;
    }

    /**
     * Normalize names to map PDF entries and local member-state names reliably.
     */
    private function normalizeCountryName(string $name): string
    {
        $normalized = Str::ascii($name);
        $normalized = str_replace('&', ' and ', $normalized);
        $normalized = preg_replace('/[^a-zA-Z0-9]+/', ' ', strtolower($normalized));
        $normalized = preg_replace('/\s+/', ' ', (string) $normalized);

        return trim((string) $normalized);
    }

    /**
     * @return array<string, string>
     */
    private function countryAliases(): array
    {
        return [
            $this->normalizeCountryName('Central African Rep.') => $this->normalizeCountryName('Central African Republic'),
            $this->normalizeCountryName('Cape Verde') => $this->normalizeCountryName('Cabo Verde'),
            $this->normalizeCountryName('Democratic Rep. of Congo') => $this->normalizeCountryName('Democratic Republic of the Congo'),
            $this->normalizeCountryName('Sao Tome & Principe') => $this->normalizeCountryName('Sao Tome and Principe'),
            $this->normalizeCountryName('Swaziland') => $this->normalizeCountryName('Eswatini'),
        ];
    }

    private function parsePdfDate(string $value): ?Carbon
    {
        $trimmed = trim($value);
        if ($trimmed === '' || $trimmed === '-') {
            return null;
        }

        try {
            return Carbon::createFromFormat('d/m/Y', $trimmed)->startOfDay();
        } catch (\Throwable $exception) {
            return null;
        }
    }

    /**
     * @return array<int, array{
     *     country: string,
     *     signature_date: string,
     *     ratification_or_accession_date: string,
     *     deposit_date: string
     * }>
     */
    private function statusRows(): array
    {
        return [
            ['country' => 'Algeria', 'signature_date' => '12/07/2000', 'ratification_or_accession_date' => '23/05/2001', 'deposit_date' => '31/05/2001'],
            ['country' => 'Angola', 'signature_date' => '01/03/2001', 'ratification_or_accession_date' => '19/09/2001', 'deposit_date' => '20/12/2001'],
            ['country' => 'Benin', 'signature_date' => '12/07/2000', 'ratification_or_accession_date' => '03/07/2001', 'deposit_date' => '11/07/2001'],
            ['country' => 'Botswana', 'signature_date' => '26/02/2001', 'ratification_or_accession_date' => '01/03/2001', 'deposit_date' => '02/03/2001'],
            ['country' => 'Burkina Faso', 'signature_date' => '12/07/2000', 'ratification_or_accession_date' => '27/02/2001', 'deposit_date' => '02/03/2001'],
            ['country' => 'Burundi', 'signature_date' => '12/07/2000', 'ratification_or_accession_date' => '28/02/2001', 'deposit_date' => '01/03/2001'],
            ['country' => 'Cameroon', 'signature_date' => '28/02/2001', 'ratification_or_accession_date' => '09/11/2001', 'deposit_date' => '19/04/2002'],
            ['country' => 'Central African Rep.', 'signature_date' => '12/07/2000', 'ratification_or_accession_date' => '16/02/2001', 'deposit_date' => '01/03/2001'],
            ['country' => 'Cape Verde', 'signature_date' => '12/07/2000', 'ratification_or_accession_date' => '21/06/2001', 'deposit_date' => '09/07/2001'],
            ['country' => 'Chad', 'signature_date' => '12/07/2000', 'ratification_or_accession_date' => '16/01/2001', 'deposit_date' => '06/02/2001'],
            ['country' => 'Cote d\'Ivoire', 'signature_date' => '10/01/2001', 'ratification_or_accession_date' => '27/02/2001', 'deposit_date' => '01/03/2001'],
            ['country' => 'Comoros', 'signature_date' => '01/02/2001', 'ratification_or_accession_date' => '16/02/2001', 'deposit_date' => '27/02/2001'],
            ['country' => 'Congo', 'signature_date' => '01/03/2001', 'ratification_or_accession_date' => '18/02/2002', 'deposit_date' => '29/05/2002'],
            ['country' => 'Djibouti', 'signature_date' => '12/07/2000', 'ratification_or_accession_date' => '04/12/2000', 'deposit_date' => '10/01/2001'],
            ['country' => 'Democratic Rep. of Congo', 'signature_date' => '01/03/2001', 'ratification_or_accession_date' => '07/07/2002', 'deposit_date' => '09/07/2002'],
            ['country' => 'Egypt', 'signature_date' => '22/01/2001', 'ratification_or_accession_date' => '05/07/2001', 'deposit_date' => '30/07/2001'],
            ['country' => 'Equatorial Guinea', 'signature_date' => '12/07/2000', 'ratification_or_accession_date' => '26/12/2000', 'deposit_date' => '24/02/2001'],
            ['country' => 'Eritrea', 'signature_date' => '01/03/2001', 'ratification_or_accession_date' => '01/03/2001', 'deposit_date' => '01/03/2001'],
            ['country' => 'Ethiopia', 'signature_date' => '12/07/2000', 'ratification_or_accession_date' => '08/03/2001', 'deposit_date' => '09/03/2001'],
            ['country' => 'Gabon', 'signature_date' => '12/07/2000', 'ratification_or_accession_date' => '17/05/2001', 'deposit_date' => '05/06/2001'],
            ['country' => 'Gambia', 'signature_date' => '12/07/2000', 'ratification_or_accession_date' => '22/02/2001', 'deposit_date' => '18/04/2001'],
            ['country' => 'Ghana', 'signature_date' => '12/07/2000', 'ratification_or_accession_date' => '11/05/2001', 'deposit_date' => '21/05/2001'],
            ['country' => 'Guinea-Bissau', 'signature_date' => '12/07/2000', 'ratification_or_accession_date' => '14/01/2001', 'deposit_date' => '07/07/2003'],
            ['country' => 'Guinea', 'signature_date' => '02/03/2001', 'ratification_or_accession_date' => '23/04/2002', 'deposit_date' => '05/07/2002'],
            ['country' => 'Kenya', 'signature_date' => '02/03/2001', 'ratification_or_accession_date' => '04/07/2001', 'deposit_date' => '10/07/2001'],
            ['country' => 'Libya', 'signature_date' => '12/07/2000', 'ratification_or_accession_date' => '25/10/2000', 'deposit_date' => '29/10/2000'],
            ['country' => 'Lesotho', 'signature_date' => '12/07/2000', 'ratification_or_accession_date' => '16/02/2001', 'deposit_date' => '12/03/2001'],
            ['country' => 'Liberia', 'signature_date' => '12/07/2000', 'ratification_or_accession_date' => '26/02/2001', 'deposit_date' => '01/03/2001'],
            ['country' => 'Madagascar', 'signature_date' => '12/07/2000', 'ratification_or_accession_date' => '05/06/2003', 'deposit_date' => '10/06/2003'],
            ['country' => 'Mali', 'signature_date' => '12/07/2000', 'ratification_or_accession_date' => '11/08/2000', 'deposit_date' => '21/08/2000'],
            ['country' => 'Malawi', 'signature_date' => '12/07/2000', 'ratification_or_accession_date' => '03/02/2001', 'deposit_date' => '14/02/2001'],
            ['country' => 'Morocco', 'signature_date' => '-', 'ratification_or_accession_date' => '20/01/2017', 'deposit_date' => '31/01/2017'],
            ['country' => 'Mozambique', 'signature_date' => '23/11/2000', 'ratification_or_accession_date' => '17/05/2001', 'deposit_date' => '25/05/2001'],
            ['country' => 'Mauritania', 'signature_date' => '28/02/2001', 'ratification_or_accession_date' => '20/11/2001', 'deposit_date' => '04/07/2002'],
            ['country' => 'Mauritius', 'signature_date' => '09/02/2001', 'ratification_or_accession_date' => '13/04/2001', 'deposit_date' => '19/04/2001'],
            ['country' => 'Namibia', 'signature_date' => '27/10/2000', 'ratification_or_accession_date' => '28/02/2001', 'deposit_date' => '21/03/2001'],
            ['country' => 'Nigeria', 'signature_date' => '08/09/2000', 'ratification_or_accession_date' => '29/03/2001', 'deposit_date' => '26/04/2001'],
            ['country' => 'Niger', 'signature_date' => '12/07/2000', 'ratification_or_accession_date' => '26/01/2001', 'deposit_date' => '09/02/2001'],
            ['country' => 'Rwanda', 'signature_date' => '07/12/2000', 'ratification_or_accession_date' => '16/04/2001', 'deposit_date' => '18/04/2001'],
            ['country' => 'South Africa', 'signature_date' => '08/09/2000', 'ratification_or_accession_date' => '03/03/2001', 'deposit_date' => '23/04/2001'],
            ['country' => 'Sahrawi Arab Democratic Republic', 'signature_date' => '12/07/2000', 'ratification_or_accession_date' => '27/12/2000', 'deposit_date' => '02/01/2001'],
            ['country' => 'Senegal', 'signature_date' => '12/07/2000', 'ratification_or_accession_date' => '28/08/2000', 'deposit_date' => '31/08/2000'],
            ['country' => 'Seychelles', 'signature_date' => '11/09/2000', 'ratification_or_accession_date' => '20/03/2001', 'deposit_date' => '09/04/2001'],
            ['country' => 'Sierra Leone', 'signature_date' => '12/07/2000', 'ratification_or_accession_date' => '09/02/2001', 'deposit_date' => '01/03/2001'],
            ['country' => 'Somalia', 'signature_date' => '18/01/2001', 'ratification_or_accession_date' => '26/02/2001', 'deposit_date' => '01/03/2001'],
            ['country' => 'South Sudan', 'signature_date' => '15/08/2011', 'ratification_or_accession_date' => '15/08/2011', 'deposit_date' => '15/08/2011'],
            ['country' => 'Sao Tome & Principe', 'signature_date' => '16/12/2000', 'ratification_or_accession_date' => '27/02/2001', 'deposit_date' => '02/03/2001'],
            ['country' => 'Sudan', 'signature_date' => '12/07/2000', 'ratification_or_accession_date' => '22/11/2000', 'deposit_date' => '24/01/2001'],
            ['country' => 'Swaziland', 'signature_date' => '01/03/2001', 'ratification_or_accession_date' => '08/08/2001', 'deposit_date' => '18/09/2001'],
            ['country' => 'Tanzania', 'signature_date' => '15/12/2000', 'ratification_or_accession_date' => '06/04/2001', 'deposit_date' => '11/04/2001'],
            ['country' => 'Togo', 'signature_date' => '12/07/2000', 'ratification_or_accession_date' => '30/08/2000', 'deposit_date' => '14/09/2000'],
            ['country' => 'Tunisia', 'signature_date' => '14/12/2000', 'ratification_or_accession_date' => '13/03/2001', 'deposit_date' => '21/03/2001'],
            ['country' => 'Uganda', 'signature_date' => '25/02/2001', 'ratification_or_accession_date' => '03/04/2001', 'deposit_date' => '09/04/2001'],
            ['country' => 'Zambia', 'signature_date' => '12/07/2000', 'ratification_or_accession_date' => '21/02/2001', 'deposit_date' => '01/03/2001'],
            ['country' => 'Zimbabwe', 'signature_date' => '15/02/2001', 'ratification_or_accession_date' => '03/03/2001', 'deposit_date' => '03/04/2001'],
        ];
    }
}
