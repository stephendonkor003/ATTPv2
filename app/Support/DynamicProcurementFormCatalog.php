<?php

namespace App\Support;

final class DynamicProcurementFormCatalog
{
    public const FIELD_TYPES = [
        'text', 'textarea', 'email', 'tel', 'number', 'url', 'date', 'time',
        'datetime-local', 'select', 'radio', 'multiselect', 'checkbox',
        'boolean', 'file', 'image',
    ];

    public const CHOICE_TYPES = ['select', 'radio', 'multiselect', 'checkbox'];

    public const TEXT_TYPES = ['text', 'textarea', 'email', 'tel', 'url'];

    public const UPLOAD_TYPES = ['file', 'image'];

    public const PLACEHOLDER_TYPES = ['text', 'textarea', 'email', 'tel', 'number', 'url', 'boolean'];

    public const FILE_EXTENSIONS = [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'csv', 'txt', 'zip', 'jpg', 'jpeg', 'png', 'webp',
    ];

    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public const MAX_CUSTOM_FIELDS = 30;

    /**
     * Keep this at or below PHP's max_file_uploads setting. The supported
     * Laragon/runtime baseline is 20, so a form can never require more upload
     * controls than PHP will deliver to Laravel in one request.
     */
    public const MAX_UPLOAD_FIELDS = 20;

    public const MAX_OPTIONS_PER_FIELD = 50;

    /** Leaves ample room below PHP's max_input_vars=1000 for scalar fields and workflow controls. */
    public const MAX_TOTAL_OPTIONS = 500;

    public const MAX_OPTION_LENGTH = 255;

    public const MAX_TEXT_LENGTH = 20000;

    public const MAX_FILE_SIZE_MB = 20;

    public const DEFAULT_FILE_SIZE_MB = 10;

    public const DEFAULT_IMAGE_SIZE_MB = 5;

    public const MAX_SUBMISSION_FILES = 20;

    public const MAX_SUBMISSION_UPLOAD_MB = 60;

    public const RESERVED_FIELD_KEYS = [
        'official_name',
        'official_email',
        'vendor_response',
        'lock_token',
        'confirmation',
        'procurement_id',
        'form_id',
        'submission_id',
        'submitted_by',
        'publication_version',
        'status',
        '_token',
        '_method',
    ];

    /** @return array<int, string> */
    public static function extensionsFor(string $fieldType): array
    {
        return $fieldType === 'image' ? self::IMAGE_EXTENSIONS : self::FILE_EXTENSIONS;
    }

    /** @return array<int, string> */
    public static function validationKeysFor(string $fieldType): array
    {
        if ($fieldType === 'number') {
            return ['min', 'max'];
        }

        if (in_array($fieldType, self::TEXT_TYPES, true)) {
            return ['max_length'];
        }

        if (in_array($fieldType, self::UPLOAD_TYPES, true)) {
            return ['allowed_extensions', 'max_file_size_mb'];
        }

        return [];
    }

    /** @return array<string, mixed> */
    public static function builderMetadata(): array
    {
        return [
            'fieldTypes' => [
                self::fieldType('text', 'Short text', 'Text', true, false, ['maxLength']),
                self::fieldType('textarea', 'Long text', 'Text', true, false, ['maxLength']),
                self::fieldType('email', 'Email address', 'Text', true, false, ['maxLength']),
                self::fieldType('tel', 'Telephone number', 'Text', true, false, ['maxLength']),
                self::fieldType('number', 'Number', 'Text', true, false, ['min', 'max']),
                self::fieldType('url', 'Website URL', 'Text', true, false, ['maxLength']),
                self::fieldType('date', 'Date', 'Date and time'),
                self::fieldType('time', 'Time', 'Date and time'),
                self::fieldType('datetime-local', 'Date and time', 'Date and time'),
                self::fieldType('select', 'Dropdown', 'Choice', false, true),
                self::fieldType('radio', 'Single choice', 'Choice', false, true),
                self::fieldType('multiselect', 'Multiple choice', 'Choice', false, true),
                self::fieldType('checkbox', 'Checkbox choices', 'Choice', false, true),
                self::fieldType('boolean', 'Confirmation checkbox', 'Choice', true),
                self::fieldType('file', 'Document or file upload', 'Upload', false, false, ['allowedExtensions', 'maxFileSizeMb']),
                self::fieldType('image', 'Image upload', 'Upload', false, false, ['allowedExtensions', 'maxFileSizeMb']),
            ],
            'fileTypes' => collect(self::FILE_EXTENSIONS)
                ->map(fn (string $extension): array => [
                    'extension' => $extension,
                    'label' => self::fileTypeLabel($extension),
                    'fieldTypes' => in_array($extension, self::IMAGE_EXTENSIONS, true)
                        ? ['file', 'image']
                        : ['file'],
                ])->all(),
            'limits' => [
                'maxCustomFields' => self::MAX_CUSTOM_FIELDS,
                'maxUploadFields' => self::MAX_UPLOAD_FIELDS,
                'maxOptionsPerField' => self::MAX_OPTIONS_PER_FIELD,
                'maxTotalOptions' => self::MAX_TOTAL_OPTIONS,
                'maxOptionLength' => self::MAX_OPTION_LENGTH,
                'maxHelpTextLength' => 1000,
                'maxPlaceholderLength' => 255,
                'maxTextLength' => self::MAX_TEXT_LENGTH,
                'maxFileSizeMb' => self::MAX_FILE_SIZE_MB,
                'maxSubmissionFiles' => self::MAX_SUBMISSION_FILES,
                'maxSubmissionUploadMb' => self::MAX_SUBMISSION_UPLOAD_MB,
            ],
            'protectedFieldKeys' => ['official_name', 'official_email'],
            'reservedFieldKeys' => self::RESERVED_FIELD_KEYS,
        ];
    }

    /** @return array<string, mixed> */
    private static function fieldType(
        string $value,
        string $label,
        string $group,
        bool $supportsPlaceholder = false,
        bool $supportsOptions = false,
        array $validationKeys = [],
    ): array {
        return [
            'value' => $value,
            'label' => $label,
            'group' => $group,
            'supportsPlaceholder' => $supportsPlaceholder,
            'supportsOptions' => $supportsOptions,
            'validationKeys' => $validationKeys,
        ];
    }

    private static function fileTypeLabel(string $extension): string
    {
        return match ($extension) {
            'pdf' => 'PDF document',
            'doc' => 'Microsoft Word (.doc)',
            'docx' => 'Microsoft Word (.docx)',
            'xls' => 'Microsoft Excel (.xls)',
            'xlsx' => 'Microsoft Excel (.xlsx)',
            'ppt' => 'Microsoft PowerPoint (.ppt)',
            'pptx' => 'Microsoft PowerPoint (.pptx)',
            'csv' => 'CSV spreadsheet',
            'txt' => 'Plain text',
            'zip' => 'ZIP archive',
            'jpg' => 'JPEG image (.jpg)',
            'jpeg' => 'JPEG image (.jpeg)',
            'png' => 'PNG image',
            'webp' => 'WebP image',
            default => strtoupper($extension),
        };
    }
}
