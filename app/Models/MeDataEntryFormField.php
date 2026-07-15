<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeDataEntryFormField extends BaseModel
{
    public const TYPE_INTEGER = 'integer';

    public const TYPE_NUMBER = 'number';

    public const TYPE_PERCENTAGE = 'percentage';

    public const TYPE_CURRENCY = 'currency';

    public const TYPE_TEXT = 'text';

    public const TYPE_TEXTAREA = 'textarea';

    public const TYPE_EMAIL = 'email';

    public const TYPE_PHONE = 'phone';

    public const TYPE_URL = 'url';

    public const TYPE_DATE = 'date';

    public const TYPE_TIME = 'time';

    public const TYPE_DATETIME = 'datetime';

    public const TYPE_MONTH = 'month';

    public const TYPE_YEAR = 'year';

    public const TYPE_SELECT = 'select';

    public const TYPE_RADIO = 'radio';

    public const TYPE_MULTISELECT = 'multiselect';

    public const TYPE_CHECKBOX = 'checkbox';

    public const TYPE_YES_NO = 'yes_no';

    public const TYPE_RATING = 'rating';

    public const TYPE_SCALE = 'scale';

    public const TYPE_FILE = 'file';

    public const TYPE_IMAGE = 'image';

    public const ALLOWED_TYPES = [
        self::TYPE_INTEGER,
        self::TYPE_NUMBER,
        self::TYPE_PERCENTAGE,
        self::TYPE_CURRENCY,
        self::TYPE_TEXT,
        self::TYPE_TEXTAREA,
        self::TYPE_EMAIL,
        self::TYPE_PHONE,
        self::TYPE_URL,
        self::TYPE_DATE,
        self::TYPE_TIME,
        self::TYPE_DATETIME,
        self::TYPE_MONTH,
        self::TYPE_YEAR,
        self::TYPE_SELECT,
        self::TYPE_RADIO,
        self::TYPE_MULTISELECT,
        self::TYPE_CHECKBOX,
        self::TYPE_YES_NO,
        self::TYPE_RATING,
        self::TYPE_SCALE,
        self::TYPE_FILE,
        self::TYPE_IMAGE,
    ];

    public const NUMERIC_TYPES = [
        self::TYPE_INTEGER,
        self::TYPE_NUMBER,
        self::TYPE_PERCENTAGE,
        self::TYPE_CURRENCY,
    ];

    public const CHOICE_TYPES = [
        self::TYPE_SELECT,
        self::TYPE_RADIO,
        self::TYPE_MULTISELECT,
        self::TYPE_CHECKBOX,
        self::TYPE_YES_NO,
    ];

    public const TEMPORAL_TYPES = [
        self::TYPE_DATE,
        self::TYPE_TIME,
        self::TYPE_DATETIME,
        self::TYPE_MONTH,
        self::TYPE_YEAR,
    ];

    public const UPLOAD_TYPES = [
        self::TYPE_FILE,
        self::TYPE_IMAGE,
    ];

    protected $table = 'me_data_entry_form_fields';

    protected $fillable = [
        'form_id',
        'section_id',
        'indicator_id',
        'section',
        'field_key',
        'label',
        'help_text',
        'field_type',
        'options',
        'validation',
        'unit_label',
        'is_required',
        'sort_order',
    ];

    protected $casts = [
        'options' => 'array',
        'validation' => 'array',
        'is_required' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(MeDataEntryForm::class, 'form_id');
    }

    public function formSection(): BelongsTo
    {
        return $this->belongsTo(MeDataEntryFormSection::class, 'section_id');
    }

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(Indicator::class, 'indicator_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(MeDataSubmissionAnswer::class, 'field_id');
    }

    public function isNumeric(): bool
    {
        return in_array($this->field_type, self::NUMERIC_TYPES, true);
    }

    public function isChoice(): bool
    {
        return in_array($this->field_type, self::CHOICE_TYPES, true);
    }

    public function isTemporal(): bool
    {
        return in_array($this->field_type, self::TEMPORAL_TYPES, true);
    }

    public function isUpload(): bool
    {
        return in_array($this->field_type, self::UPLOAD_TYPES, true);
    }
}
