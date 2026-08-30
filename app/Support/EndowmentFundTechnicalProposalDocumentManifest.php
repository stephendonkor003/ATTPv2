<?php

namespace App\Support;

/**
 * Immutable register of the historical Endowment Fund proposal scans that are
 * bundled with the application. Keeping this outside a database seeder makes
 * the same fingerprints available to production recovery tooling.
 */
final class EndowmentFundTechnicalProposalDocumentManifest
{
    public const PROCUREMENT_REFERENCE = 'ET-AUC- 494958-CS-QCBS';

    public const ROUND_TITLE = 'Technical Proposal Submission — AU Physical Delivery';

    public const BUNDLED_ASSET_DIRECTORY = 'seeders/assets/endowment-fund-technical-proposals';

    /**
     * @var array<int, array{
     *     applicant_name: string,
     *     filename: string,
     *     sha256: string,
     *     file_size: int,
     *     document_label: string
     * }>
     */
    private const DOCUMENTS = [
        [
            'applicant_name' => 'KPMG',
            'filename' => 'Auc (KPMG)_compressed.pdf',
            'sha256' => '23f91a5c03a4fec91a0fe4f643b0da49bf5b6ed2efdf7a1f8f38f2613fd06aec',
            'file_size' => 35209956,
            'document_label' => 'Physical proposal scan',
        ],
        [
            'applicant_name' => 'Impact Africa Consulting',
            'filename' => 'Impact Africa August 2026_compressed.pdf',
            'sha256' => '485876f28273730375b3809964a5a5714560049a92b15f6f66bae202d38f1b21',
            'file_size' => 16433111,
            'document_label' => 'Physical proposal scan',
        ],
        [
            'applicant_name' => 'BwB',
            'filename' => 'Power of Attorney_compressed.pdf',
            'sha256' => '6e9ffbc85841f12c9d138fb4c2db697183615456ee17f7ec8150c99ab87ddbb7',
            'file_size' => 10726985,
            'document_label' => 'Supporting document — Power of Attorney',
        ],
        [
            'applicant_name' => 'LNO',
            'filename' => 'LNO.pdf',
            'sha256' => 'dafe11cbf0e757f73739248bfe62336105b8e7bc09c7357e2077df2da4a1a182',
            'file_size' => 14871615,
            'document_label' => 'Physical proposal scan',
        ],
    ];

    private function __construct() {}

    /**
     * @return array<int, array{
     *     applicant_name: string,
     *     filename: string,
     *     sha256: string,
     *     file_size: int,
     *     document_label: string
     * }>
     */
    public static function all(): array
    {
        return self::DOCUMENTS;
    }

    /** @return array<int, string> */
    public static function filenames(): array
    {
        return array_column(self::DOCUMENTS, 'filename');
    }

    /**
     * @return array<int, array{
     *     applicant_name: string,
     *     filename: string,
     *     sha256: string,
     *     file_size: int,
     *     document_label: string
     * }>
     */
    public static function forApplicant(string $applicantName): array
    {
        return array_values(array_filter(
            self::DOCUMENTS,
            fn (array $document): bool => $document['applicant_name'] === $applicantName
        ));
    }

    /**
     * @return null|array{
     *     applicant_name: string,
     *     filename: string,
     *     sha256: string,
     *     file_size: int,
     *     document_label: string
     * }
     */
    public static function find(string $applicantName, string $filename): ?array
    {
        foreach (self::DOCUMENTS as $document) {
            if ($document['applicant_name'] === $applicantName && $document['filename'] === $filename) {
                return $document;
            }
        }

        return null;
    }
}
