<?php

namespace App\Services;

use App\Models\DynamicForm;
use App\Support\DynamicProcurementFormCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class DynamicProcurementSubmissionValidation
{
    /** @return array<string, mixed> */
    public function rules(DynamicForm $form, ?Collection $existingValues = null): array
    {
        $rules = [];
        $seenKeys = [];
        $uploadFieldCount = 0;
        $totalOptionCount = 0;

        foreach ($form->fields as $field) {
            $key = trim((string) $field->field_key);
            if ($key === ''
                || mb_strlen($key) > 80
                || ! preg_match('/^[a-z][a-z0-9_]*$/', $key)
                || in_array($key, $seenKeys, true)) {
                throw new ConflictHttpException(
                    'This application form contains duplicate or invalid field keys. Applications are paused until the form is corrected.',
                );
            }
            $seenKeys[] = $key;
            if (! in_array($field->field_type, DynamicProcurementFormCatalog::FIELD_TYPES, true)) {
                throw new ConflictHttpException(
                    'This application form contains an unsupported answer type. Applications are paused until the form is corrected.',
                );
            }
            if (in_array($key, DynamicProcurementFormCatalog::RESERVED_FIELD_KEYS, true)
                && ! in_array($key, DynamicForm::globalFieldKeys(), true)) {
                throw new ConflictHttpException(
                    'This application form contains a field reserved by the procurement workflow. Applications are paused until the form is corrected.',
                );
            }
            $required = $field->is_required ? 'required' : 'nullable';
            $configuration = (array) $field->validation_rules;
            $options = $field->optionValues();
            $totalOptionCount += count($options);
            if (in_array($field->field_type, DynamicProcurementFormCatalog::UPLOAD_TYPES, true)) {
                $uploadFieldCount++;
            }
            $uniqueOptions = collect($options)
                ->unique(fn (string $option): string => mb_strtolower($option))
                ->values()
                ->all();
            if (in_array($field->field_type, DynamicProcurementFormCatalog::CHOICE_TYPES, true)
                && count($uniqueOptions) !== count($options)) {
                throw new ConflictHttpException(
                    'This application form contains duplicate answer choices. Applications are paused until the form is corrected.',
                );
            }
            if (in_array($field->field_type, DynamicProcurementFormCatalog::CHOICE_TYPES, true)
                && count($uniqueOptions) < 2) {
                throw new ConflictHttpException(
                    'This application form contains a choice field without enough unique options. Applications are paused until the form is corrected.',
                );
            }
            $options = $uniqueOptions;
            $maxLength = min(DynamicProcurementFormCatalog::MAX_TEXT_LENGTH, max(1, (int) (
                $configuration['max_length'] ?? ($field->field_type === 'textarea'
                    ? DynamicProcurementFormCatalog::MAX_TEXT_LENGTH
                    : 255)
            )));

            if (in_array($field->field_type, DynamicProcurementFormCatalog::UPLOAD_TYPES, true)
                && $field->is_required
                && $existingValues?->has($key)) {
                $existingValue = $existingValues->get($key);
                $existingPath = is_object($existingValue)
                    ? ($existingValue->value ?? null)
                    : (is_array($existingValue) ? ($existingValue['value'] ?? null) : $existingValue);
                if ((new DynamicProcurementSubmissionFileService)->exists($existingPath)) {
                    $required = 'nullable';
                }
            }

            switch ($field->field_type) {
                case 'email':
                    $rules[$key] = [$required, 'email:rfc', 'max:'.$maxLength];
                    break;

                case 'file':
                case 'image':
                    $defaultExtensions = DynamicProcurementFormCatalog::extensionsFor($field->field_type);
                    $extensions = array_values(array_intersect(
                        array_map(
                            fn (mixed $extension): string => strtolower(trim((string) $extension)),
                            (array) ($configuration['allowed_extensions'] ?? $defaultExtensions),
                        ),
                        $defaultExtensions,
                    ));
                    $defaultSize = $field->field_type === 'image'
                        ? DynamicProcurementFormCatalog::DEFAULT_IMAGE_SIZE_MB
                        : DynamicProcurementFormCatalog::DEFAULT_FILE_SIZE_MB;
                    $maxKilobytes = min(DynamicProcurementFormCatalog::MAX_FILE_SIZE_MB * 1024, max(
                        1024,
                        (int) ($configuration['max_file_size_mb'] ?? $defaultSize) * 1024,
                    ));
                    $rules[$key] = [
                        $required,
                        'file',
                        ...($field->field_type === 'image' ? ['image'] : []),
                        'mimes:'.implode(',', $extensions ?: $defaultExtensions),
                        'max:'.$maxKilobytes,
                    ];
                    break;

                case 'checkbox':
                case 'multiselect':
                    $rules[$key] = [$required, 'array', ...($field->is_required ? ['min:1'] : [])];
                    $rules[$key.'.*'] = ['string', Rule::in($options)];
                    break;

                case 'number':
                    $rules[$key] = [
                        $required,
                        'numeric',
                        ...(array_key_exists('min', $configuration) ? ['min:'.$configuration['min']] : []),
                        ...(array_key_exists('max', $configuration) ? ['max:'.$configuration['max']] : []),
                    ];
                    break;

                case 'url':
                    $rules[$key] = [$required, 'url:http,https', 'max:'.$maxLength];
                    break;

                case 'tel':
                    $rules[$key] = [$required, 'string', 'max:'.$maxLength];
                    break;

                case 'date':
                    $rules[$key] = [$required, 'date_format:Y-m-d'];
                    break;

                case 'time':
                    $rules[$key] = [$required, 'date_format:H:i'];
                    break;

                case 'datetime-local':
                    $rules[$key] = [$required, 'date_format:Y-m-d\\TH:i'];
                    break;

                case 'select':
                case 'radio':
                    $rules[$key] = [$required, 'string', Rule::in($options)];
                    break;

                case 'boolean':
                    $rules[$key] = $field->is_required
                        ? ['required', 'accepted']
                        : ['sometimes', 'accepted'];
                    break;

                case 'textarea':
                case 'text':
                default:
                    $rules[$key] = [$required, 'string', 'max:'.$maxLength];
                    break;
            }
        }

        if ($uploadFieldCount > DynamicProcurementFormCatalog::MAX_UPLOAD_FIELDS
            || $totalOptionCount > DynamicProcurementFormCatalog::MAX_TOTAL_OPTIONS) {
            throw new ConflictHttpException(
                'This application form exceeds the supported request limits. Applications are paused until the form is corrected.',
            );
        }

        return $rules;
    }

    public function assertUploadEnvelope(Request $request, DynamicForm $form, ?Collection $existingValues = null): void
    {
        $allowedKeys = $form->fields
            ->whereIn('field_type', DynamicProcurementFormCatalog::UPLOAD_TYPES)
            ->pluck('field_key')
            ->map(fn (mixed $key): string => (string) $key)
            ->all();
        $allFiles = $request->allFiles();
        $unexpected = array_values(array_diff(array_keys($allFiles), $allowedKeys));
        if ($unexpected !== []) {
            throw ValidationException::withMessages(collect($unexpected)
                ->mapWithKeys(fn (string $key): array => [$key => ['This upload field is not part of the application form.']])
                ->all());
        }

        $files = collect($allFiles)->flatMap(function (mixed $value): array {
            if ($value instanceof UploadedFile) {
                return [$value];
            }

            return is_array($value)
                ? collect($value)->flatten()->filter(fn (mixed $file): bool => $file instanceof UploadedFile)->all()
                : [];
        });

        $retainedPaths = collect();
        if ($existingValues !== null) {
            $retainedPaths = $form->fields
                ->whereIn('field_type', DynamicProcurementFormCatalog::UPLOAD_TYPES)
                ->reject(fn ($field): bool => $request->hasFile((string) $field->field_key))
                ->map(function ($field) use ($existingValues): mixed {
                    $existing = $existingValues->get((string) $field->field_key);

                    return is_object($existing)
                        ? ($existing->value ?? null)
                        : (is_array($existing) ? ($existing['value'] ?? null) : $existing);
                })
                ->filter(fn (mixed $path): bool => $this->submissionFiles()->exists($path))
                ->values();
        }

        if ($files->count() + $retainedPaths->count() > DynamicProcurementFormCatalog::MAX_SUBMISSION_FILES) {
            throw ValidationException::withMessages([
                'files' => ['Upload no more than '.DynamicProcurementFormCatalog::MAX_SUBMISSION_FILES.' files in one application.'],
            ]);
        }

        $maximumBytes = DynamicProcurementFormCatalog::MAX_SUBMISSION_UPLOAD_MB * 1024 * 1024;
        $newBytes = (int) $files->sum(fn (UploadedFile $file): int => max(0, (int) $file->getSize()));
        $retainedSizes = $retainedPaths->map(fn (mixed $path): ?int => $this->submissionFiles()->size($path));
        if ($retainedSizes->contains(fn (mixed $size): bool => $size === null)) {
            throw ValidationException::withMessages([
                'files' => ['A retained application file could not be read. Upload a replacement before resubmitting.'],
            ]);
        }
        $retainedBytes = (int) $retainedSizes->sum();
        if ($newBytes + $retainedBytes > $maximumBytes) {
            throw ValidationException::withMessages([
                'files' => ['The combined application upload must not exceed '.DynamicProcurementFormCatalog::MAX_SUBMISSION_UPLOAD_MB.' MB.'],
            ]);
        }
    }

    private function submissionFiles(): DynamicProcurementSubmissionFileService
    {
        return app(DynamicProcurementSubmissionFileService::class);
    }
}
