@php
    $launchFormKey = 'launch-'.$item->id;
    $isLaunchRetry = old('_item_form') === $launchFormKey;
    $initialQuestions = $isLaunchRetry ? array_values((array) old('fields', [])) : [];
@endphp

<dialog class="ttpp-dialog ttpp-publication-dialog" id="launch-item-{{ $item->id }}" @if($isLaunchRetry && $errors->any()) data-auto-open @endif>
    <div class="ttpp-dialog-head ttpp-publication-head">
        <div class="ttpp-dialog-title">
            <span><i class="feather-send"></i></span>
            <div>
                <small>{{ $item->item_code }} &middot; Public opportunity</small>
                <h3>Configure application form &amp; publication</h3>
                <p>Design the vendor application and publish it under {{ $plan->member?->name ?? 'your Think Tank' }}.</p>
            </div>
        </div>
        <button class="ttpp-dialog-close" type="button" onclick="this.closest('dialog').close()" aria-label="Close publication builder">&times;</button>
    </div>

    <form class="ttpp-publication-form" method="POST" action="{{ route('think-tank.procurement-plans.items.launch',$routeParams(['plan'=>$plan,'item'=>$item])) }}" enctype="multipart/form-data" data-publication-builder>
        @csrf
        <input type="hidden" name="_item_form" value="{{ $launchFormKey }}">
        <input type="hidden" name="visibility_type" value="public">

        <div class="ttpp-dialog-body">
            @if($isLaunchRetry && $errors->any())
                <div class="ttpp-validation-summary" role="alert">
                    <i class="feather-alert-circle"></i>
                    <div><strong>Please review the publication setup</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                </div>
            @endif

            <section class="ttpp-builder-section">
                <div class="ttpp-builder-section-head">
                    <span class="ttpp-builder-step">1</span>
                    <div><h4>Publication details</h4><p>Set the application window and the image shown on the public opportunity card.</p></div>
                </div>
                <div class="ttpp-form-grid is-three">
                    <div class="ttpp-field">
                        <label>Applications open <em>Required</em></label>
                        <input type="date" name="application_start_date" value="{{ $isLaunchRetry ? old('application_start_date') : now()->toDateString() }}" required>
                    </div>
                    <div class="ttpp-field">
                        <label>Applications close <em>Required</em></label>
                        <input type="date" name="application_end_date" value="{{ $isLaunchRetry ? old('application_end_date') : now()->addDays(21)->toDateString() }}" required>
                    </div>
                    <div class="ttpp-field">
                        <label>Cover image</label>
                        <input type="file" name="cover_image" accept="image/jpeg,image/png,image/webp" data-publication-cover>
                        <p class="ttpp-help">JPG, PNG or WebP, maximum 5 MB. A branded fallback is used if omitted.</p>
                    </div>
                </div>
                <div class="ttpp-cover-preview" data-cover-preview hidden>
                    <img alt="Selected procurement cover preview" data-cover-preview-image>
                    <div><strong>Cover ready</strong><span data-cover-preview-name></span></div>
                </div>
            </section>

            <section class="ttpp-builder-section">
                <div class="ttpp-builder-section-head">
                    <span class="ttpp-builder-step">2</span>
                    <div><h4>Standard applicant information</h4><p>These protected questions are included automatically in every application.</p></div>
                </div>
                <div class="ttpp-standard-fields">
                    @foreach(['Official name','Official email','Organization profile','Technical proposal','Financial proposal','Quoted amount','Relevant experience'] as $standardField)
                        <span><i class="feather-check"></i>{{ $standardField }}</span>
                    @endforeach
                </div>
            </section>

            <section class="ttpp-builder-section">
                <div class="ttpp-builder-section-head is-actionable">
                    <div class="ttpp-builder-heading">
                        <span class="ttpp-builder-step">3</span>
                        <div><h4>Additional application questions</h4><p>Add up to 30 questions, choose the data type and decide whether each answer is required.</p></div>
                    </div>
                    <button class="ttpp-small-btn ttpp-add-question" type="button" data-add-publication-question><i class="feather-plus"></i> Add question</button>
                </div>

                <div class="ttpp-builder-empty" data-publication-empty>
                    <i class="feather-list"></i>
                    <strong>No additional questions yet</strong>
                    <span>The standard applicant information above will still appear on the public form.</span>
                </div>
                <div class="ttpp-question-list" data-publication-questions></div>

                <script type="application/json" data-publication-initial>@json($initialQuestions)</script>
                <template data-publication-question-template>
                    <article class="ttpp-question-card" data-publication-question>
                        <header class="ttpp-question-head">
                            <div><span class="ttpp-question-number" data-question-number>1</span><strong data-question-title>New question</strong></div>
                            <div class="ttpp-question-actions">
                                <button type="button" data-question-up title="Move question up" aria-label="Move question up"><i class="feather-arrow-up"></i></button>
                                <button type="button" data-question-down title="Move question down" aria-label="Move question down"><i class="feather-arrow-down"></i></button>
                                <button type="button" class="is-danger" data-remove-publication-question title="Remove question" aria-label="Remove question"><i class="feather-trash-2"></i></button>
                            </div>
                        </header>
                        <div class="ttpp-question-body">
                            <div class="ttpp-form-grid is-three">
                                <div class="ttpp-field wide">
                                    <label>Question label <em>Required</em></label>
                                    <input name="fields[__INDEX__][label]" maxlength="255" placeholder="What should the applicant answer?" required data-question-label>
                                </div>
                                <div class="ttpp-field">
                                    <label>Answer type <em>Required</em></label>
                                    <select name="fields[__INDEX__][type]" required data-question-type>
                                        <option value="text">Short text</option>
                                        <option value="textarea">Long text</option>
                                        <option value="email">Email address</option>
                                        <option value="tel">Telephone number</option>
                                        <option value="number">Number / amount</option>
                                        <option value="url">Website URL</option>
                                        <option value="date">Date</option>
                                        <option value="time">Time</option>
                                        <option value="datetime-local">Date and time</option>
                                        <option value="select">Dropdown (single choice)</option>
                                        <option value="radio">Radio buttons (single choice)</option>
                                        <option value="multiselect">Multi-select dropdown</option>
                                        <option value="checkbox">Checkbox group</option>
                                        <option value="boolean">Confirmation checkbox</option>
                                        <option value="file">File upload</option>
                                        <option value="image">Image upload</option>
                                    </select>
                                </div>
                                <div class="ttpp-field wide">
                                    <label>Help text</label>
                                    <input name="fields[__INDEX__][help_text]" maxlength="500" placeholder="Optional guidance shown below the question">
                                </div>
                                <div class="ttpp-field" data-question-placeholder-wrap>
                                    <label>Placeholder</label>
                                    <input name="fields[__INDEX__][placeholder]" maxlength="255" placeholder="Example answer or instruction">
                                </div>
                                <div class="ttpp-field ttpp-required-control">
                                    <label>Answer requirement</label>
                                    <input type="hidden" name="fields[__INDEX__][required]" value="0">
                                    <label class="ttpp-switch-line"><input type="checkbox" name="fields[__INDEX__][required]" value="1" data-question-required><span></span><b data-required-label>Optional</b></label>
                                </div>
                            </div>

                            <div class="ttpp-question-settings" data-question-options hidden>
                                <div class="ttpp-field full">
                                    <label><span data-choice-settings-title>Permitted answers</span> <em>Required &middot; at least two</em></label>
                                    <textarea name="fields[__INDEX__][options]" rows="4" maxlength="2000" placeholder="Enter one answer per line&#10;Example:&#10;National&#10;Regional&#10;International" data-question-options-input></textarea>
                                    <p class="ttpp-help" data-choice-settings-help>Applicants will only be able to choose from the answers entered here.</p>
                                    <div class="ttpp-choice-preview" data-choice-preview hidden><strong>Applicant choices</strong><div data-choice-preview-values></div></div>
                                </div>
                            </div>
                            <div class="ttpp-question-settings" data-question-number-settings hidden>
                                <div class="ttpp-form-grid">
                                    <div class="ttpp-field"><label>Minimum value</label><input type="number" step="any" name="fields[__INDEX__][min]" placeholder="No minimum"></div>
                                    <div class="ttpp-field"><label>Maximum value</label><input type="number" step="any" name="fields[__INDEX__][max]" placeholder="No maximum"></div>
                                </div>
                            </div>
                            <div class="ttpp-question-settings" data-question-length-settings>
                                <div class="ttpp-field"><label>Maximum characters</label><input type="number" min="1" max="20000" name="fields[__INDEX__][max_length]" placeholder="Use platform default"></div>
                            </div>
                            <div class="ttpp-question-settings" data-question-file-settings hidden>
                                <div class="ttpp-form-grid">
                                    <div class="ttpp-field wide"><label>Allowed extensions</label><input name="fields[__INDEX__][allowed_extensions]" maxlength="255" placeholder="pdf, doc, docx"></div>
                                    <div class="ttpp-field"><label>Maximum size (MB)</label><input type="number" min="1" max="20" name="fields[__INDEX__][max_file_size_mb]" placeholder="10"></div>
                                </div>
                            </div>
                        </div>
                    </article>
                </template>
            </section>

            <section class="ttpp-builder-section ttpp-publish-confirmation">
                <div>
                    <span class="ttpp-builder-step">4</span>
                    <div><h4>Publish to the public procurement portal</h4><p>The Think Tank name, logo, cover image, documents and application form will be visible immediately.</p></div>
                </div>
                <label class="ttpp-inline-check"><input type="checkbox" name="publish_now" value="1" @checked($isLaunchRetry ? old('publish_now') : true) required> Publish now</label>
            </section>
        </div>

        <div class="ttpp-dialog-actions">
            <p><i class="feather-shield"></i> Publication and form configuration are recorded in the audit trail.</p>
            <div><button class="ttpp-btn" type="button" onclick="this.closest('dialog').close()">Cancel</button><button class="ttpp-btn primary" type="submit"><i class="feather-send"></i> Create &amp; publish opportunity</button></div>
        </div>
    </form>
</dialog>
