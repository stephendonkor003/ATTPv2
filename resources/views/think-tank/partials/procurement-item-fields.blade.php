@php
    $fieldId = str_replace([' ', '.'], '-', $prefix);
    $fieldValue = static fn(string $name, mixed $default = null) => old('_item_form') === $prefix
        ? old($name, $default)
        : $default;
    $marketApproaches = [
        'Open - International', 'Open - National',
        'Limited - International', 'Limited - National',
        'Direct - International', 'Direct - National',
    ];
    $procurementMethodGroups = [
        'Goods, Works and Non-Consulting Services' => [
            'Request for Proposals (RFP)',
            'Request for Bids (RFB)',
            'Request for Quotations (RFQ)',
            'Direct Selection',
        ],
        'Consulting Firms' => [
            'Quality and Cost-Based Selection (QCBS)',
            'Fixed Budget-Based Selection (FBS)',
            'Least Cost-Based Selection (LCS)',
            'Quality-Based Selection (QBS)',
            "Consultant's Qualifications-Based Selection (CQS)",
            'Consultant Direct Selection (CDS)',
        ],
        'Individual Consultants' => [
            'Individual Consultant Selection (INDV)',
        ],
        'Approved Selection Arrangements' => [
            'Competitive Dialogue',
            'Public-Private Partnership (PPP)',
            'Commercial Practices',
            'UN Agencies',
            'E-Auctions',
            'Imports',
            'Commodities',
            'Community-Driven Development (CDD)',
            'Force Account',
            'Non-Profit Organizations (NGOs)',
            'Banks',
            'Procurement Agents',
        ],
    ];
    $documentTypes = [
        'Request for Quotations (Non Bank-SPD)',
        'Request for Quotations - Goods (Emergency) SPD - Competitive',
        'Request for Bids SPD (Goods) - 1 envelope process',
        'Request for Bids SPD (Goods) - 2 envelope process',
        'Request for Bids SPD (Works) - 1 envelope process',
        'Request for Bids SPD (Works) - 2 envelope process',
        'Request for Proposals SPD (Consulting Services)',
        'Request for Expressions of Interest (Consulting Services)',
        'Direct Selection',
        'Framework Agreement',
    ];
    $processStatuses = [
        'Pending Implementation', 'Under Preparation', 'Under Implementation',
        'Evaluation in Progress', 'Contract Negotiation', 'Contract Awarded',
        'Completed', 'Cancelled',
    ];
    $selectOptions = static function (array $options, mixed $current): array {
        $current = trim((string) $current);

        return $current !== '' && ! in_array($current, $options, true)
            ? [$current, ...$options]
            : $options;
    };
@endphp

<div class="ttpp-item-builder">
    <fieldset class="ttpp-form-section">
        <legend>
            <span class="ttpp-section-icon"><i class="feather-file-text" aria-hidden="true"></i></span>
            <span><strong>Item details</strong><small>Identify what the Think Tank intends to procure.</small></span>
        </legend>
        <div class="ttpp-form-grid">
            <div class="ttpp-field wide">
                <label for="{{ $fieldId }}-title">Activity / description <em>Required</em></label>
                <input id="{{ $fieldId }}-title" @if(!$item) data-first-item-title @endif name="title" value="{{ $fieldValue('title', $item?->title) }}" placeholder="e.g. Consultancy to conduct a policy impact study" required>
                <p class="ttpp-help">Enter the official activity description used in the procurement plan.</p>
            </div>
            <div class="ttpp-field">
                <label for="{{ $fieldId }}-reference">Activity reference no.</label>
                <input id="{{ $fieldId }}-reference" name="source_reference" value="{{ $fieldValue('source_reference', $item?->source_reference) }}" placeholder="e.g. KE-TT-001">
            </div>
            <div class="ttpp-field">
                <label for="{{ $fieldId }}-loan">Loan / credit no.</label>
                <input id="{{ $fieldId }}-loan" name="loan_credit_no" value="{{ $fieldValue('loan_credit_no', $item?->loan_credit_no) }}" placeholder="Enter financing reference">
            </div>
            <div class="ttpp-field wide">
                <label for="{{ $fieldId }}-component">Component</label>
                <input id="{{ $fieldId }}-component" name="component" value="{{ $fieldValue('component', $item?->component) }}" placeholder="Project component or sub-component">
            </div>
            <div class="ttpp-field wide">
                <label for="{{ $fieldId }}-category">Category <em>Required</em></label>
                <select id="{{ $fieldId }}-category" name="procurement_category" required>
                    @foreach(['goods'=>'Goods','works'=>'Works','consulting_services'=>'Consulting services','non_consulting_services'=>'Non-consulting services','training'=>'Training','other'=>'Other'] as $value=>$label)
                        <option value="{{ $value }}" @selected($fieldValue('procurement_category', $item?->procurement_category) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ttpp-field full">
                <label for="{{ $fieldId }}-description">Detailed scope or notes</label>
                <textarea id="{{ $fieldId }}-description" name="description" rows="4" placeholder="Explain what is being procured, the intended result and the main scope">{{ $fieldValue('description', $item?->description) }}</textarea>
            </div>
        </div>
    </fieldset>

    <fieldset class="ttpp-form-section">
        <legend>
            <span class="ttpp-section-icon"><i class="feather-compass" aria-hidden="true"></i></span>
            <span><strong>Procurement approach and status</strong><small>Use consistent STEP-aligned classifications for the activity.</small></span>
        </legend>
        <div class="ttpp-form-grid">
            <div class="ttpp-field">
                @php
                    $selectedMethod = $fieldValue('procurement_method', $item?->procurement_method);
                    $knownMethods = collect($procurementMethodGroups)->flatten()->all();
                @endphp
                <label for="{{ $fieldId }}-method">Procurement method <em>Required</em></label>
                <select id="{{ $fieldId }}-method" name="procurement_method" required>
                    <option value="">Select World Bank procurement method</option>
                    @if($selectedMethod && ! in_array($selectedMethod, $knownMethods, true))
                        <optgroup label="Existing imported value">
                            <option value="{{ $selectedMethod }}" selected>{{ $selectedMethod }}</option>
                        </optgroup>
                    @endif
                    @foreach($procurementMethodGroups as $group => $methods)
                        <optgroup label="{{ $group }}">
                            @foreach($methods as $method)
                                <option value="{{ $method }}" @selected($selectedMethod === $method)>{{ $method }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                <p class="ttpp-help">Methods and approved arrangements follow the World Bank Procurement Regulations for IPF Borrowers.</p>
            </div>
            <div class="ttpp-field">
                @php($selectedReview = $fieldValue('review_type', $item?->review_type))
                <label for="{{ $fieldId }}-review">Review type <em>Required</em></label>
                <select id="{{ $fieldId }}-review" name="review_type" required>
                    <option value="">Select review type</option>
                    @foreach($selectOptions(['Prior', 'Post'], $selectedReview) as $option)
                        <option value="{{ $option }}" @selected($selectedReview === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ttpp-field">
                @php($selectedMarket = $fieldValue('market_approach', $item?->market_approach))
                <label for="{{ $fieldId }}-market">Market approach <em>Required</em></label>
                <select id="{{ $fieldId }}-market" name="market_approach" required>
                    <option value="">Select market approach</option>
                    @foreach($selectOptions($marketApproaches, $selectedMarket) as $option)
                        <option value="{{ $option }}" @selected($selectedMarket === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ttpp-field">
                @php($selectedRisk = $fieldValue('source_sea_sh_risk', $item?->source_sea_sh_risk))
                <label for="{{ $fieldId }}-sea-risk">High SEA/SH risk</label>
                <select id="{{ $fieldId }}-sea-risk" name="source_sea_sh_risk">
                    <option value="">Select risk classification</option>
                    @foreach($selectOptions(['Yes', 'No', 'Not Applicable'], $selectedRisk) as $option)
                        <option value="{{ $option }}" @selected($selectedRisk === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ttpp-field wide">
                @php($selectedDocumentType = $fieldValue('source_document_type', $item?->source_document_type))
                <label for="{{ $fieldId }}-document-type">Procurement document type <em>Required</em></label>
                <select id="{{ $fieldId }}-document-type" name="source_document_type" required>
                    <option value="">Select procurement document type</option>
                    @foreach($selectOptions($documentTypes, $selectedDocumentType) as $option)
                        <option value="{{ $option }}" @selected($selectedDocumentType === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ttpp-field">
                @php($selectedProcessStatus = $fieldValue('source_process_status', $item?->source_process_status ?: 'Pending Implementation'))
                <label for="{{ $fieldId }}-process-status">Process status <em>Required</em></label>
                <select id="{{ $fieldId }}-process-status" name="source_process_status" required>
                    @foreach($selectOptions($processStatuses, $selectedProcessStatus) as $option)
                        <option value="{{ $option }}" @selected($selectedProcessStatus === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ttpp-field">
                <label>Activity status</label>
                <div class="ttpp-system-status">
                    <i class="feather-shield" aria-hidden="true"></i>
                    <span><strong>{{ $item?->workflowActivityStatus() ?: \App\Models\ThinkTankProcurementItem::ACTIVITY_STATUS_DRAFT }}</strong><small>Updated automatically by the approval workflow</small></span>
                </div>
            </div>
        </div>
    </fieldset>

    <fieldset class="ttpp-form-section">
        <legend>
            <span class="ttpp-section-icon"><i class="feather-dollar-sign" aria-hidden="true"></i></span>
            <span><strong>Quantity and budget</strong><small>Enter the working estimate that will feed the annual plan total.</small></span>
        </legend>
        <div class="ttpp-form-grid is-budget">
            <div class="ttpp-field">
                <label for="{{ $fieldId }}-quantity">Quantity <em>Required</em></label>
                <input id="{{ $fieldId }}-quantity" type="number" name="quantity" value="{{ $fieldValue('quantity', $item?->quantity) }}" min=".0001" step=".0001" placeholder="0" required data-ttpp-quantity oninput="var builder=this.closest('.ttpp-item-builder');var quantity=builder.querySelector('[data-ttpp-quantity]');var cost=builder.querySelector('[data-ttpp-unit-cost]');var total=builder.querySelector('[data-ttpp-total]');total.value=quantity.value!==''&&cost.value!==''?(Number(quantity.value)*Number(cost.value)).toFixed(2):''">
            </div>
            <div class="ttpp-field">
                <label for="{{ $fieldId }}-unit">Unit</label>
                <input id="{{ $fieldId }}-unit" name="unit" value="{{ $fieldValue('unit', $item?->unit) }}" placeholder="lot, unit, person-days">
            </div>
            <div class="ttpp-field">
                <label for="{{ $fieldId }}-unit-cost">Estimated unit cost <em>Required</em></label>
                <input id="{{ $fieldId }}-unit-cost" type="number" name="estimated_unit_cost" value="{{ $fieldValue('estimated_unit_cost', $item?->estimated_unit_cost) }}" min=".01" step=".01" placeholder="0.00" required data-ttpp-unit-cost oninput="var builder=this.closest('.ttpp-item-builder');var quantity=builder.querySelector('[data-ttpp-quantity]');var cost=builder.querySelector('[data-ttpp-unit-cost]');var total=builder.querySelector('[data-ttpp-total]');total.value=quantity.value!==''&&cost.value!==''?(Number(quantity.value)*Number(cost.value)).toFixed(2):''">
            </div>
            <div class="ttpp-field is-emphasis">
                <label for="{{ $fieldId }}-amount">Estimated amount (US$) <em>Required</em></label>
                <input id="{{ $fieldId }}-amount" type="number" name="estimated_amount" value="{{ $fieldValue('estimated_amount', $item?->estimated_amount) }}" min="0" step=".01" placeholder="Calculated automatically" required readonly aria-readonly="true" data-ttpp-total>
            </div>
            <div class="ttpp-field">
                <label for="{{ $fieldId }}-currency">Currency <em>Required</em></label>
                <input id="{{ $fieldId }}-currency" name="currency" value="{{ $fieldValue('currency', $item?->currency ?: $plan->currency) }}" maxlength="10" placeholder="USD" required>
            </div>
        </div>
        <p class="ttpp-section-note"><i class="feather-info" aria-hidden="true"></i> Estimated amount is calculated automatically as Quantity &times; Estimated unit cost and verified again when saved.</p>
    </fieldset>

    <fieldset class="ttpp-form-section">
        <legend>
            <span class="ttpp-section-icon"><i class="feather-calendar" aria-hidden="true"></i></span>
            <span><strong>Implementation schedule</strong><small>Show when this activity is expected to start and finish.</small></span>
        </legend>
        <div class="ttpp-form-grid is-three">
            <div class="ttpp-field">
                <label for="{{ $fieldId }}-quarter">Planned quarter</label>
                <select id="{{ $fieldId }}-quarter" name="planned_quarter">
                    <option value="">Select quarter</option>
                    @foreach(['Q1','Q2','Q3','Q4'] as $quarter)
                        <option value="{{ $quarter }}" @selected($fieldValue('planned_quarter', $item?->planned_quarter) === $quarter)>{{ $quarter }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ttpp-field">
                <label for="{{ $fieldId }}-start">Planned start</label>
                <input id="{{ $fieldId }}-start" type="date" name="planned_start_date" value="{{ $fieldValue('planned_start_date', $item?->planned_start_date?->toDateString()) }}">
            </div>
            <div class="ttpp-field">
                <label for="{{ $fieldId }}-end">Planned end</label>
                <input id="{{ $fieldId }}-end" type="date" name="planned_end_date" value="{{ $fieldValue('planned_end_date', $item?->planned_end_date?->toDateString()) }}">
            </div>
        </div>
    </fieldset>

    <fieldset class="ttpp-form-section is-documents">
        <legend>
            <span class="ttpp-section-icon"><i class="feather-paperclip" aria-hidden="true"></i></span>
            <span><strong>Required documents</strong><small>Attach the TOR and any evidence needed for review.</small></span>
        </legend>
        <div class="ttpp-document-grid">
            <label class="ttpp-upload-box is-required" for="{{ $fieldId }}-tor">
                <span class="ttpp-upload-icon"><i class="feather-file-text" aria-hidden="true"></i></span>
                <span class="ttpp-upload-copy">
                    <strong>{{ $item ? 'Replacement or additional TOR' : 'Terms of Reference' }}</strong>
                    <small>PDF or Word document, maximum 20 MB</small>
                </span>
                <span class="ttpp-upload-action">Choose file</span>
                <input id="{{ $fieldId }}-tor" type="file" name="tor" accept=".pdf,.doc,.docx" @required(!$item) data-ttpp-file>
                @if(!$item)<span class="ttpp-required-chip">Required</span>@endif
            </label>
            <label class="ttpp-upload-box" for="{{ $fieldId }}-supporting">
                <span class="ttpp-upload-icon"><i class="feather-layers" aria-hidden="true"></i></span>
                <span class="ttpp-upload-copy">
                    <strong>Supporting documents</strong>
                    <small>Multiple files are allowed, maximum 20 MB each</small>
                </span>
                <span class="ttpp-upload-action">Choose files</span>
                <input id="{{ $fieldId }}-supporting" type="file" name="supporting_documents[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.csv,.txt,.jpg,.jpeg,.png,.zip" data-ttpp-file>
            </label>
        </div>
    </fieldset>
</div>
