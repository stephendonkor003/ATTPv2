<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>{{ $procurement->title }} | ATTP</title>

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Apply for {{ $procurement->title }} through the ATTP digital procurement platform.">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f7f4f2;
            margin: 0;
        }

        .page-header {
            background: url('/assets/three.webp') center/cover no-repeat;
            height: 320px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
        }

        .page-header::before {
            content: "";
            position: absolute;
            inset: 0;
            background: rgba(82, 43, 57, .75);
        }

        .header-content {
            position: relative;
            max-width: 900px;
            text-align: center;
            padding: 0 1rem;
        }

        .header-content h1 {
            color: #fbbc05;
            font-size: 2.3rem;
        }

        .container {
            max-width: 1100px;
            margin: -80px auto 4rem;
            padding: 0 1.5rem;
        }

        .card {
            background: #fff;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 20px rgba(0, 0, 0, .1);
            margin-bottom: 2rem;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .meta-item {
            background: #f7f4f2;
            padding: 1rem;
            border-radius: 10px;
            font-size: .95rem;
        }

        .meta-item strong {
            color: #522b39;
        }

        .alert {
            padding: 1rem 1.2rem;
            border-radius: 10px;
            margin-bottom: 1.2rem;
            font-size: .95rem;
        }

        .alert-success {
            background: #e7f7ed;
            color: #157347;
        }

        .alert-danger {
            background: #fdecea;
            color: #842029;
        }

        .form-group {
            margin-bottom: 1.4rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: .4rem;
            color: #522b39;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: .7rem .8rem;
            border-radius: 8px;
            border: 1px solid #ccc;
            font-size: .95rem;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .error-text {
            color: #c0392b;
            font-size: .85rem;
            margin-top: .3rem;
        }

        .btn-submit {
            background: #a70d53;
            color: #fff;
            padding: .8rem 2rem;
            border: none;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            transition: .3s;
        }

        .btn-submit:hover {
            background: #e16435;
        }


        .form-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: 6px;
            color: #522b39;
        }

        .required {
            color: red;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            padding: 0.65rem 0.75rem;
            border-radius: 8px;
            border: 1px solid #ccc;
            font-size: 0.95rem;
        }

        .option-group {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .option-item {
            font-size: 0.9rem;
        }

        .error-text {
            margin-top: 4px;
            color: #c0392b;
            font-size: 0.85rem;
        }

        @media (max-width: 1100px) {
            .form-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 600px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        .procurement-description {
            margin-top: 1rem;
            line-height: 1.7;
        }

        .procurement-description h1,
        .procurement-description h2,
        .procurement-description h3 {
            color: #522b39;
            margin-top: 1.2rem;
        }

        .procurement-description ul {
            padding-left: 1.5rem;
            list-style: disc;
        }

        .procurement-description table {
            width: 100%;
            border-collapse: collapse;
        }

        .procurement-description table td,
        .procurement-description table th {
            border: 1px solid #ddd;
            padding: 8px;
        }
    </style>
</head>

<body>
    <header class="navbar">
        <div class="logo">

            <img src="{{ asset('assets/images/au.png') }}" alt="" class="logo logo-sm">

        </div>
        <nav class="nav-links">
            <a href="{{ route('landing.index') }}">Home</a>
            <a href="#process">System Flow</a>
            <a href="#customization">Customization</a>
            <a href="{{ route('public.grievances.create') }}">Grievance</a>
            <a href="#contact">Contact</a>
            <a href="{{ route('events') }}">Events</a>
            <a href="{{ route('careers.index') }}">Career</a>
            <a href="{{ route('public.procurement.index') }}">FaQ's</a>
        </nav>

        <div class="nav-actions">
            <a href="{{ route('login') }}" class="btn btn-login">Login</a>
            {{-- <a href="{{ route('applicants.create') }}" class="btn btn-primary">Call for Proposals</a> --}}

        </div>
    </header>

    {{-- ===== HEADER ===== --}}
    <section class="page-header">
        <div class="header-content">
            <br>
            <br>
            <br>
            <br>
            <h1>{{ $procurement->title }}</h1>
            <p>Reference: {{ $procurement->reference_no ?? 'N/A' }}</p>
        </div>
    </section>

    <div class="container">
        <br>
        <br>
        <br>
        <br>
        <br>
        {{-- ===== SUCCESS MESSAGE ===== --}}
        @if (session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        {{-- ===== ERROR SUMMARY ===== --}}
        @if ($errors->any())
            <div class="alert alert-danger">
                <strong>Please correct the errors below.</strong>
            </div>
        @endif

        {{-- ===== PROCUREMENT DETAILS ===== --}}
            <div class="card">
                <h3>Procurement Details</h3>

                <div style="margin-top:1rem; line-height:1.7;">
                    {!! nl2br(e(strip_tags($procurement->description ?? ''))) !!}
                </div>


            {{-- <div class="meta-grid">
                <div class="meta-item">
                    <strong>Fiscal Year:</strong><br>
                    {{ $procurement->fiscal_year ?? 'N/A' }}
                </div>

                <div class="meta-item">
                    <strong>Estimated Budget:</strong><br>
                    {{ number_format($procurement->estimated_budget ?? 0, 2) }}
                </div>

                <div class="meta-item">
                    <strong>Status:</strong><br>
                    {{ ucfirst($procurement->status) }}
                </div>
            </div> --}}
        </div>

        {{-- ===== APPLICATION FORM ===== --}}
        <div class="card">
            <h3>Application Form</h3>

            {{-- @if ($form && $form->fields->count()) --}}
            @if ($form?->fields?->isNotEmpty())

                <form method="POST" action="{{ route('public.procurement.apply', $procurement->slug) }}"
                    enctype="multipart/form-data">

                    @csrf

                    {{-- ===== GRID WRAPPER ===== --}}
                    <div class="form-grid">

                        @foreach ($form->fields as $field)
                            @php
                                $oldValue = old($field->field_key);

                                // Normalize multi-select old value (Select2 may return CSV or array)
                                if (in_array($field->field_type, ['checkbox', 'multiselect']) && is_string($oldValue)) {
                                    $oldValue = array_filter(array_map('trim', explode(',', $oldValue)));
                                }

                                // Parse comma-separated options safely
                                $options = collect(explode(',', (string) $field->options))
                                    ->map(fn($opt) => trim($opt))
                                    ->filter()
                                    ->values()
                                    ->toArray();

                                $isRequired = $field->is_required;

                                // Normalize datetime-local
                                $dateTimeValue = $oldValue;
                                if ($field->field_type === 'datetime-local' && $oldValue) {
                                    try {
                                        $dateTimeValue = \Carbon\Carbon::parse($oldValue)->format('Y-m-d\TH:i');
                                    } catch (\Exception $e) {
                                        $dateTimeValue = $oldValue;
                                    }
                                }
                            @endphp

                            <div class="form-group">
                                <label>
                                    {{ $field->label }}
                                    @if ($isRequired)
                                        <span class="required">*</span>
                                    @endif
                                </label>

                                {{-- TEXT --}}
                                @if ($field->field_type === 'text')
                                    <input type="text" name="{{ $field->field_key }}" value="{{ $oldValue }}"
                                        {{ $isRequired ? 'required' : '' }}>

                                    {{-- EMAIL --}}
                                @elseif ($field->field_type === 'email')
                                    <input type="email" name="{{ $field->field_key }}" value="{{ $oldValue }}"
                                        {{ $isRequired ? 'required' : '' }}>

                                    {{-- NUMBER --}}
                                @elseif ($field->field_type === 'number')
                                    <input type="number" name="{{ $field->field_key }}" value="{{ $oldValue }}"
                                        {{ $isRequired ? 'required' : '' }}>

                                    {{-- DATE --}}
                                @elseif ($field->field_type === 'date')
                                    <input type="date" name="{{ $field->field_key }}" value="{{ $oldValue }}"
                                        {{ $isRequired ? 'required' : '' }}>

                                    {{-- TIME --}}
                                @elseif ($field->field_type === 'time')
                                    <input type="time" name="{{ $field->field_key }}" value="{{ $oldValue }}"
                                        {{ $isRequired ? 'required' : '' }}>

                                    {{-- DATETIME LOCAL --}}
                                @elseif ($field->field_type === 'datetime-local')
                                    <input type="datetime-local" name="{{ $field->field_key }}"
                                        value="{{ $dateTimeValue }}" {{ $isRequired ? 'required' : '' }}>

                                    {{-- URL --}}
                                @elseif ($field->field_type === 'url')
                                    <input type="url" name="{{ $field->field_key }}" value="{{ $oldValue }}"
                                        {{ $isRequired ? 'required' : '' }}>

                                    {{-- PHONE --}}
                                @elseif ($field->field_type === 'tel')
                                    <input type="tel" name="{{ $field->field_key }}" value="{{ $oldValue }}"
                                        {{ $isRequired ? 'required' : '' }}>

                                    {{-- TEXTAREA --}}
                                @elseif ($field->field_type === 'textarea')
                                    <textarea name="{{ $field->field_key }}" rows="4" {{ $isRequired ? 'required' : '' }}>{{ $oldValue }}</textarea>

                                    {{-- SINGLE SELECT (SELECT2) --}}
                                @elseif ($field->field_type === 'select')
                                    <select name="{{ $field->field_key }}" class="form-select select2-single"
                                        data-placeholder="Select an option" {{ $isRequired ? 'required' : '' }}>
                                        <option></option>
                                        @foreach ($options as $option)
                                            <option value="{{ $option }}"
                                                {{ (string) $oldValue === (string) $option ? 'selected' : '' }}>
                                                {{ $option }}
                                            </option>
                                        @endforeach
                                    </select>

                                    {{-- MULTI SELECT (SELECT2) --}}
                                @elseif (in_array($field->field_type, ['checkbox', 'multiselect']))
                                    <select name="{{ $field->field_key }}[]" class="form-select select2-multiple"
                                        multiple data-placeholder="Select one or more options"
                                        {{ $isRequired ? 'required' : '' }}>
                                        @foreach ($options as $option)
                                            <option value="{{ $option }}"
                                                {{ is_array($oldValue) && in_array($option, $oldValue) ? 'selected' : '' }}>
                                                {{ $option }}
                                            </option>
                                        @endforeach
                                    </select>

                                    @if ($isRequired)
                                        <small class="text-muted">Select one or more options</small>
                                    @endif

                                    {{-- FILE --}}
                                @elseif ($field->field_type === 'file')
                                    <input type="file" name="{{ $field->field_key }}"
                                        {{ $isRequired ? 'required' : '' }}>
                                @endif

                                @error($field->field_key)
                                    <div class="error-text">{{ $message }}</div>
                                @enderror
                            </div>
                        @endforeach

                    </div>


                    <div style="text-align:center;margin-top:2rem;">
                        <button type="submit" class="btn-submit">
                            Submit Application
                        </button>
                    </div>

                </form>
            @else
                <p style="color:#999;">
                    No application form has been attached to this procurement yet.
                </p>
            @endif


        </div>

    </div>
    @include('partials.gallery-strip')

    <footer id="contact" class="footer">
        <div class="footer-content">

            <div class="footer-logo">
                <h3>ATTP<span> Administration</span></h3>
                <p>
                    African Think Tank Platform Administration ? supporting African Union
                    institutions through centralized governance, policy coordination,
                    and strategic oversight of programs and funded initiatives.
                </p>
            </div>

            <div class="footer-links">
                <h4>Quick Links</h4>
                <a href="#">Home</a>
                <a href="#process">Institutional Process Flow</a>
                <a href="#customization">Centralized Oversight</a>
                <a href="#contact">Contact</a>
            </div>

            <div class="footer-contact">
                <h4>Contact</h4>
                <p>Email: attpinfo@africanunion.org</p>
                <p>? 2026 African Think Tank Platform Administration (ATTP)</p>
            </div>

        </div>

        <p style="margin-top: 10px; font-weight: 600; text-align: center;">
            Supporting African Union policy coordination, governance reform,
            and evidence-based decision-making across the continent.
        </p>

    </footer>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.select2-single').select2({
                width: '100%',
                minimumResultsForSearch: Infinity,
                allowClear: true
            });

            $('.select2-multiple').select2({
                width: '100%',
                closeOnSelect: false,
                allowClear: true
            });
        });
    </script>



    <script src="assets/script.js"></script>

    <script type="text/javascript">
        var Tawk_API = Tawk_API || {},
            Tawk_LoadStart = new Date();
        (function() {
            var s1 = document.createElement("script"),
                s0 = document.getElementsByTagName("script")[0];
            s1.async = true;
            s1.src = 'https://embed.tawk.to/6968b44f895de4198b902486/1jf0g0m8k';
            s1.charset = 'UTF-8';
            s1.setAttribute('crossorigin', '*');
            s0.parentNode.insertBefore(s1, s0);
        })();
    </script>
</body>

</html>
