<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $surveyTitle = (string) data_get($surveyConfig, 'title', 'Public Survey');
        $normalSections = collect($sections)->filter(fn ($section) => strtolower((string) data_get($section, 'effective_flow_type', data_get($section, 'flow_type', 'normal'))) !== 'special')->values();
        $specialSections = collect($sections)->filter(fn ($section) => strtolower((string) data_get($section, 'effective_flow_type', data_get($section, 'flow_type', 'normal'))) === 'special')->values();
        $specialQuestions = collect($questions)->filter(fn ($question) => strtolower((string) data_get($question, 'effective_flow_type', data_get($question, 'flow_type', 'normal'))) === 'special')->values();
        $sectionCount = $normalSections->count();
        $estimatedMinutes = (int) data_get($surveyConfig, 'estimated_minutes', 0);
        $presentationSettings = (array) data_get($surveyConfig, 'presentation', []);
        $respondentSettings = (array) data_get($surveyConfig, 'respondent', []);
        $showHeaderMeta = (bool) data_get($presentationSettings, 'show_header_meta', true);
        $showBriefingPanel = (bool) data_get($presentationSettings, 'show_briefing_panel', true);
        $showSidebarGuide = (bool) data_get($presentationSettings, 'show_sidebar_guide', true);
        $showSideNavigation = (bool) data_get($presentationSettings, 'show_side_navigation', true);
        $showIntroGuidance = (bool) data_get($presentationSettings, 'show_intro_guidance', true);
        $showProgressTracker = (bool) data_get($presentationSettings, 'show_progress_tracker', true);
        $useCompactTitle = (bool) data_get($presentationSettings, 'compact_title', false);
        $useSimpleLayout = ! $showSideNavigation;
        $showRespondentNotes = (bool) data_get($respondentSettings, 'show_notes', true);
        $respondentNameConfig = (array) data_get($respondentSettings, 'fields.name', []);
        $respondentEmailConfig = (array) data_get($respondentSettings, 'fields.email', []);
        $respondentPhoneConfig = (array) data_get($respondentSettings, 'fields.phone', []);
        $respondentOrganizationConfig = (array) data_get($respondentSettings, 'fields.organization', []);
        $respondentNameRequired = (bool) data_get($respondentNameConfig, 'required', false);
        $respondentEmailRequired = (bool) data_get($respondentEmailConfig, 'required', false);
        $respondentPhoneRequired = (bool) data_get($respondentPhoneConfig, 'required', false);
        $respondentOrganizationRequired = (bool) data_get($respondentOrganizationConfig, 'required', false);
    @endphp
    <title>{{ $surveyTitle }}</title>
    <style>
        :root {
            --page: #f2f6f3;
            --surface: rgba(255, 255, 255, 0.92);
            --surface-strong: #ffffff;
            --surface-soft: rgba(244, 248, 246, 0.88);
            --ink: #10222e;
            --muted: #5a6d78;
            --line: rgba(16, 34, 46, 0.12);
            --line-strong: rgba(16, 34, 46, 0.18);
            --primary: #143e5a;
            --primary-strong: #0d2b40;
            --accent: #b9782f;
            --accent-soft: rgba(185, 120, 47, 0.12);
            --success: #1e7a46;
            --warn: #b42318;
            --shadow-hero: 0 34px 90px rgba(8, 23, 35, 0.22);
            --shadow-soft: 0 18px 54px rgba(15, 23, 42, 0.08);
            --radius-xl: 30px;
            --radius-lg: 22px;
            --radius-md: 18px;
            --radius-sm: 14px;
            --font-body: Aptos, "Segoe UI", Tahoma, sans-serif;
            --font-display: Georgia, "Times New Roman", serif;
        }

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background:
                radial-gradient(circle at top left, rgba(185, 120, 47, 0.11), transparent 30%),
                radial-gradient(circle at top right, rgba(20, 62, 90, 0.14), transparent 34%),
                linear-gradient(180deg, #f9fbf8 0%, var(--page) 100%);
            color: var(--ink);
            font: 15px/1.65 var(--font-body);
            padding: 18px 14px 34px;
        }

        button,
        input,
        textarea,
        select {
            font: inherit;
        }

        button {
            color: inherit;
        }

        img,
        svg {
            display: block;
            max-width: 100%;
        }

        a {
            color: inherit;
        }

        [hidden] {
            display: none !important;
        }

        .survey-page {
            max-width: 1320px;
            margin: 0 auto;
        }

        .masthead {
            position: relative;
            overflow: hidden;
            min-height: min(92svh, 760px);
            border-radius: 34px;
            padding: clamp(22px, 3vw, 38px);
            color: #f8fafc;
            background:
                linear-gradient(140deg, rgba(7, 24, 37, 0.95), rgba(20, 62, 90, 0.88) 52%, rgba(185, 120, 47, 0.52)),
                linear-gradient(180deg, #113751, #184d70);
            box-shadow: var(--shadow-hero);
            isolation: isolate;
        }

        .masthead::before,
        .masthead::after {
            content: "";
            position: absolute;
            border-radius: 999px;
            pointer-events: none;
            opacity: 0.85;
        }

        .masthead::before {
            inset: auto auto -22% -10%;
            width: 360px;
            height: 360px;
            background: radial-gradient(circle, rgba(185, 120, 47, 0.34), transparent 66%);
        }

        .masthead::after {
            inset: 12% -10% auto auto;
            width: 420px;
            height: 420px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.11), transparent 70%);
        }

        .masthead__grid {
            position: relative;
            z-index: 1;
            display: grid;
            gap: 24px;
            grid-template-columns: minmax(0, 1.6fr) minmax(300px, 0.95fr);
            min-height: inherit;
            align-items: end;
        }

        .masthead__grid--single {
            grid-template-columns: minmax(0, 1fr);
            align-items: end;
            justify-items: stretch;
            text-align: left;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            width: fit-content;
            padding: 8px 14px;
            border-radius: 999px;
            border: 1px solid rgba(255, 255, 255, 0.14);
            background: rgba(255, 255, 255, 0.08);
            color: rgba(248, 250, 252, 0.88);
            font-size: 0.76rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .masthead__copy {
            display: grid;
            gap: 18px;
            align-content: end;
            padding-top: 14px;
        }

        .masthead__grid--single .masthead__copy {
            width: min(100%, 860px);
            justify-items: start;
            align-content: end;
            padding-top: 0;
            margin-inline: 0 auto;
        }

        .masthead h1 {
            margin: 0;
            max-width: 12ch;
            font: 700 clamp(2.45rem, 5vw, 5.1rem)/0.94 var(--font-display);
            letter-spacing: -0.03em;
            text-wrap: balance;
        }

        .masthead--compact-title {
            min-height: min(52svh, 430px);
        }

        .masthead--compact-title .masthead__copy {
            gap: 14px;
        }

        .masthead--compact-title h1 {
            max-width: 20ch;
            font-size: clamp(1.75rem, 3vw, 2.8rem);
            line-height: 1.08;
            letter-spacing: -0.02em;
        }

        .masthead__lead {
            margin: 0;
            max-width: 62ch;
            color: rgba(241, 245, 249, 0.92);
            font-size: 1.03rem;
            line-height: 1.65;
        }

        .masthead__grid--single .masthead__lead {
            max-width: 58ch;
            font-size: 0.96rem;
        }

        .masthead__grid--single .eyebrow,
        .masthead__grid--single .masthead__meta {
            justify-self: start;
        }

        .masthead__meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .meta-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 42px;
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.14);
            color: rgba(248, 250, 252, 0.94);
        }

        .meta-pill strong {
            font-size: 0.72rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: rgba(226, 232, 240, 0.78);
        }

        .meta-pill span {
            font-weight: 700;
        }

        .masthead__panel {
            align-self: stretch;
            display: grid;
            gap: 18px;
            align-content: end;
        }

        .briefing {
            border-radius: 28px;
            padding: 24px;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.12), rgba(255, 255, 255, 0.06));
            border: 1px solid rgba(255, 255, 255, 0.14);
            backdrop-filter: blur(18px);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
        }

        .briefing__title {
            margin: 0 0 10px;
            font-size: 1.05rem;
            font-weight: 800;
        }

        .briefing p {
            margin: 0;
            color: rgba(241, 245, 249, 0.86);
        }

        .briefing-list {
            list-style: none;
            padding: 0;
            margin: 18px 0 0;
            display: grid;
            gap: 12px;
        }

        .briefing-list li {
            display: grid;
            gap: 4px;
            padding-top: 12px;
            border-top: 1px solid rgba(255, 255, 255, 0.12);
        }

        .briefing-list li:first-child {
            padding-top: 0;
            border-top: 0;
        }

        .briefing-list strong {
            font-size: 0.78rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: rgba(226, 232, 240, 0.8);
        }

        .briefing-list span {
            color: #ffffff;
            font-weight: 700;
        }

        .workspace {
            display: grid;
            gap: 22px;
            grid-template-columns: minmax(280px, 340px) minmax(0, 1fr);
            margin-top: 22px;
            align-items: start;
        }

        .workspace--single {
            grid-template-columns: minmax(0, 1fr);
        }

        .survey-rail {
            position: sticky;
            top: 18px;
            display: grid;
            gap: 16px;
        }

        .main-stage {
            display: grid;
            gap: 18px;
        }

        .inline-nav-shell {
            border: 1px solid rgba(16, 34, 46, 0.08);
            background: var(--surface);
            box-shadow: var(--shadow-soft);
            backdrop-filter: blur(18px);
            border-radius: 24px;
            padding: 14px;
        }

        .rail-panel,
        .surface,
        .action-dock,
        .result-surface {
            border: 1px solid rgba(16, 34, 46, 0.08);
            background: var(--surface);
            box-shadow: var(--shadow-soft);
            backdrop-filter: blur(18px);
        }

        .rail-panel {
            border-radius: 28px;
            padding: 20px;
        }

        .rail-panel h2 {
            margin: 8px 0 6px;
            font-size: 1.1rem;
            line-height: 1.2;
        }

        .rail-panel p {
            margin: 0;
            color: var(--muted);
        }

        .rail-stats {
            display: grid;
            gap: 12px;
            margin-top: 18px;
        }

        .rail-stat {
            display: grid;
            gap: 4px;
            padding-top: 12px;
            border-top: 1px solid var(--line);
        }

        .rail-stat:first-child {
            padding-top: 0;
            border-top: 0;
        }

        .rail-stat span {
            font-size: 0.76rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--muted);
        }

        .rail-stat strong {
            font-size: 1rem;
            line-height: 1.35;
            color: var(--ink);
        }

        .step-nav {
            display: grid;
            gap: 10px;
        }

        .step-nav--inline {
            grid-auto-flow: column;
            grid-auto-columns: minmax(220px, 1fr);
            overflow-x: auto;
            padding-bottom: 4px;
            scroll-snap-type: x proximity;
        }

        .step-link {
            width: 100%;
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            gap: 12px;
            align-items: start;
            padding: 14px 14px 14px 12px;
            border-radius: 20px;
            border: 1px solid transparent;
            background: rgba(255, 255, 255, 0.78);
            text-align: left;
            cursor: pointer;
            transition: transform 0.18s ease, background 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease;
        }

        .step-nav--inline .step-link {
            min-height: 100%;
            scroll-snap-align: start;
        }

        .step-link:disabled {
            cursor: default;
            opacity: 0.72;
        }

        .step-link.is-available:not(:disabled):hover {
            transform: translateY(-1px);
            border-color: rgba(20, 62, 90, 0.2);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
        }

        .step-link.is-current {
            border-color: rgba(20, 62, 90, 0.18);
            background: linear-gradient(180deg, rgba(20, 62, 90, 0.06), rgba(255, 255, 255, 0.92));
        }

        .step-link.is-complete {
            background: linear-gradient(180deg, rgba(30, 122, 70, 0.08), rgba(255, 255, 255, 0.9));
        }

        .step-link__index {
            width: 34px;
            height: 34px;
            border-radius: 999px;
            display: grid;
            place-items: center;
            background: rgba(20, 62, 90, 0.08);
            color: var(--primary);
            font-weight: 800;
            font-size: 0.9rem;
        }

        .step-link.is-complete .step-link__index {
            background: rgba(30, 122, 70, 0.12);
            color: var(--success);
        }

        .step-link__body {
            display: grid;
            gap: 3px;
            min-width: 0;
        }

        .step-link__body strong {
            font-size: 0.96rem;
            line-height: 1.3;
        }

        .step-link__body small {
            color: var(--muted);
            font-size: 0.84rem;
        }

        .step-link__status {
            color: var(--accent);
            font-size: 0.74rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .step-link--section .step-link__index {
            background: rgba(20, 62, 90, 0.08);
            background: color-mix(in srgb, var(--section-accent, var(--primary)) 14%, white);
            color: var(--section-accent, var(--primary));
        }

        .step-link--section.is-current {
            border-color: rgba(20, 62, 90, 0.18);
            border-color: color-mix(in srgb, var(--section-accent, var(--primary)) 26%, white);
            background: linear-gradient(180deg,
                    color-mix(in srgb, var(--section-accent, var(--primary)) 8%, white),
                    rgba(255, 255, 255, 0.96));
        }

        .step-link--section.is-complete {
            background: linear-gradient(180deg,
                    color-mix(in srgb, var(--section-accent, var(--primary)) 10%, white),
                    rgba(255, 255, 255, 0.94));
        }

        .step-link--section .step-link__status {
            color: var(--section-accent, var(--accent));
        }

        .surface {
            border-radius: 32px;
            overflow: hidden;
        }

        .surface__head {
            padding: 20px 22px 18px;
            border-bottom: 1px solid var(--line);
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(247, 250, 248, 0.88));
        }

        .status-row {
            display: grid;
            gap: 14px;
            grid-template-columns: minmax(0, 1fr) minmax(220px, 280px);
            align-items: end;
        }

        .status-copy {
            display: grid;
            gap: 6px;
        }

        .status-copy strong {
            font-size: 1.08rem;
        }

        .status-copy span {
            color: var(--muted);
        }

        .progress-stack {
            display: grid;
            gap: 8px;
        }

        .progress-meter {
            width: 100%;
            height: 10px;
            border-radius: 999px;
            background: rgba(20, 62, 90, 0.09);
            overflow: hidden;
        }

        .progress-meter > span {
            display: block;
            width: 0;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            transition: width 0.24s ease;
        }

        .progress-meta {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            color: var(--muted);
            font-size: 0.86rem;
        }

        .surface__body {
            padding: 24px 22px 26px;
        }

        .alert {
            border-radius: 18px;
            padding: 14px 16px;
            margin-bottom: 18px;
            border: 1px solid;
        }

        .alert strong {
            display: block;
            margin-bottom: 4px;
        }

        .alert ul {
            margin: 8px 0 0;
            padding-left: 18px;
        }

        .alert-danger {
            background: #fff5f5;
            border-color: rgba(180, 35, 24, 0.16);
            color: var(--warn);
        }

        .steps-stage {
            display: grid;
        }

        .step {
            display: none;
        }

        .step.is-active {
            display: block;
            animation: step-in 280ms ease;
        }

        @keyframes step-in {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .step-header {
            display: grid;
            gap: 10px;
            margin-bottom: 22px;
        }

        .step-header__top {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
        }

        .step-kicker {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            width: fit-content;
            padding: 8px 14px;
            border-radius: 999px;
            background: var(--accent-soft);
            color: var(--accent);
            font-size: 0.76rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .section-step .step-kicker,
        .section-step .question-tag {
            background: rgba(20, 62, 90, 0.06);
            background: color-mix(in srgb, var(--section-accent, var(--primary)) 10%, white);
            color: var(--section-accent, var(--primary));
        }

        .section-step {
            padding: 20px;
            border-radius: 30px;
            border: 1px solid color-mix(in srgb, var(--section-accent, var(--primary)) 18%, white);
            background:
                linear-gradient(180deg,
                    color-mix(in srgb, var(--section-accent, var(--primary)) 9%, white),
                    rgba(255, 255, 255, 0.98) 28%,
                    color-mix(in srgb, var(--section-accent, var(--primary)) 5%, white));
            box-shadow: 0 24px 48px rgba(15, 23, 42, 0.08);
        }

        .section-step .step-header {
            padding: 22px;
            border-radius: 26px;
            border: 1px solid color-mix(in srgb, var(--section-accent, var(--primary)) 18%, white);
            background:
                linear-gradient(135deg,
                    color-mix(in srgb, var(--section-accent, var(--primary)) 14%, white),
                    rgba(255, 255, 255, 0.97));
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7);
        }

        .section-step .question-block {
            border-color: color-mix(in srgb, var(--section-accent, var(--primary)) 14%, white);
            background:
                linear-gradient(180deg,
                    color-mix(in srgb, var(--section-accent, var(--primary)) 5%, white),
                    rgba(255, 255, 255, 0.98) 26%,
                    color-mix(in srgb, var(--section-accent, var(--primary)) 3%, white));
        }

        .section-step .question-block:focus-within {
            border-color: rgba(20, 62, 90, 0.2);
            border-color: color-mix(in srgb, var(--section-accent, var(--primary)) 24%, white);
        }

        .section-step .scale-item strong,
        .section-step .slider-output,
        .section-step .question-tag {
            color: var(--section-accent, var(--primary));
        }

        .step-counter {
            color: var(--muted);
            font-size: 0.86rem;
            font-weight: 700;
        }

        .step-header h2,
        .step-header h3 {
            margin: 0;
            font-size: clamp(1.7rem, 3vw, 2.45rem);
            line-height: 1.02;
            letter-spacing: -0.02em;
            font-family: var(--font-display);
        }

        .step-header p {
            margin: 0;
            max-width: 62ch;
            color: var(--muted);
            font-size: 1rem;
        }

        .intro-panel {
            display: grid;
            gap: 18px;
        }

        .intro-scene {
            display: grid;
            gap: 20px;
            grid-template-columns: minmax(280px, 0.9fr) minmax(0, 1.15fr);
            align-items: start;
        }

        .intro-scene__panel {
            position: relative;
            overflow: hidden;
            border-radius: 28px;
            padding: clamp(20px, 3vw, 28px);
            border: 1px solid rgba(16, 34, 46, 0.08);
            box-shadow: var(--shadow-soft);
        }

        .intro-scene__panel--welcome {
            color: #f8fafc;
            background:
                radial-gradient(circle at top right, rgba(255, 255, 255, 0.14), transparent 34%),
                linear-gradient(150deg, #102c42 0%, #1a4a69 52%, #b9782f 100%);
        }

        .intro-scene__panel--welcome::after {
            content: "";
            position: absolute;
            inset: auto -12% -28% auto;
            width: 220px;
            height: 220px;
            border-radius: 999px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.16), transparent 68%);
            pointer-events: none;
        }

        .intro-scene__eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            width: fit-content;
            padding: 8px 12px;
            border-radius: 999px;
            border: 1px solid rgba(255, 255, 255, 0.16);
            background: rgba(255, 255, 255, 0.08);
            color: rgba(248, 250, 252, 0.92);
            font-size: 0.75rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .intro-scene__title {
            margin: 18px 0 10px;
            max-width: 12ch;
            font: 700 clamp(1.85rem, 3vw, 2.8rem)/1.06 var(--font-display);
            letter-spacing: -0.02em;
        }

        .intro-scene__text {
            margin: 0;
            max-width: 34rem;
            color: rgba(241, 245, 249, 0.9);
            font-size: 0.98rem;
            line-height: 1.7;
        }

        .intro-scene__facts {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            margin-top: 26px;
        }

        .intro-fact {
            display: grid;
            gap: 4px;
            padding: 14px 14px 12px;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.09);
            border: 1px solid rgba(255, 255, 255, 0.12);
        }

        .intro-fact strong {
            font-size: 1.15rem;
            font-weight: 800;
            color: #ffffff;
        }

        .intro-fact span {
            font-size: 0.76rem;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: rgba(226, 232, 240, 0.82);
        }

        .intro-scene__panel--form {
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(245, 248, 246, 0.94));
        }

        .intro-form-head {
            display: flex;
            align-items: start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 18px;
        }

        .intro-form-kicker {
            display: inline-block;
            margin-bottom: 8px;
            color: var(--primary);
            font-size: 0.78rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .intro-form-head h3 {
            margin: 0;
            color: var(--ink);
            font: 700 clamp(1.35rem, 2vw, 1.9rem)/1.08 var(--font-display);
            letter-spacing: -0.02em;
        }

        .intro-form-head p {
            margin: 8px 0 0;
            color: var(--muted);
        }

        .intro-form-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
            padding: 9px 12px;
            border-radius: 999px;
            background: rgba(20, 62, 90, 0.08);
            border: 1px solid rgba(20, 62, 90, 0.12);
            color: var(--primary);
            font-size: 0.74rem;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .intro-grid--profile {
            gap: 16px;
        }

        .intro-scene__panel--form .field {
            padding: 16px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.92);
            border: 1px solid rgba(16, 34, 46, 0.08);
        }

        .intro-panel__copy {
            display: grid;
            gap: 10px;
            padding: 20px;
            border-radius: 24px;
            background:
                linear-gradient(180deg, rgba(20, 62, 90, 0.06), rgba(255, 255, 255, 0.84));
            border: 1px solid rgba(20, 62, 90, 0.08);
        }

        .intro-panel__copy strong {
            font-size: 0.8rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--primary);
        }

        .intro-panel__copy p {
            margin: 0;
            color: var(--muted);
        }

        .intro-grid {
            display: grid;
            gap: 14px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .field,
        .question-block {
            display: grid;
            gap: 10px;
        }

        .field {
            padding: 18px;
            border-radius: 22px;
            background: var(--surface-soft);
            border: 1px solid rgba(16, 34, 46, 0.08);
        }

        .field label,
        .question-label {
            font-weight: 800;
            line-height: 1.35;
            color: var(--ink);
        }

        .field-note,
        .question-note,
        .question-hint {
            color: var(--muted);
            font-size: 0.9rem;
        }

        .required {
            color: var(--warn);
            margin-left: 4px;
        }

        input[type="text"],
        input[type="email"],
        input[type="url"],
        input[type="date"],
        input[type="datetime-local"],
        input[type="number"],
        textarea,
        select,
        input[type="file"] {
            width: 100%;
            min-height: 52px;
            border: 1px solid rgba(16, 34, 46, 0.14);
            border-radius: 16px;
            padding: 13px 14px;
            color: var(--ink);
            background: rgba(255, 255, 255, 0.97);
            outline: none;
            transition: border-color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
        }

        textarea {
            min-height: 132px;
            resize: vertical;
        }

        input::placeholder,
        textarea::placeholder {
            color: #7b8c97;
        }

        input:focus,
        textarea:focus,
        select:focus,
        input[type="file"]:focus {
            border-color: rgba(20, 62, 90, 0.34);
            box-shadow: 0 0 0 4px rgba(20, 62, 90, 0.08);
        }

        input[type="file"] {
            padding: 10px 12px;
        }

        input[type="file"]::file-selector-button {
            border: 0;
            margin-right: 12px;
            padding: 10px 14px;
            border-radius: 12px;
            background: rgba(20, 62, 90, 0.08);
            color: var(--primary);
            font-weight: 800;
            cursor: pointer;
        }

        select {
            appearance: none;
            background-image:
                linear-gradient(45deg, transparent 50%, var(--primary) 50%),
                linear-gradient(135deg, var(--primary) 50%, transparent 50%);
            background-position:
                calc(100% - 18px) calc(50% - 3px),
                calc(100% - 12px) calc(50% - 3px);
            background-size: 6px 6px, 6px 6px;
            background-repeat: no-repeat;
            padding-right: 36px;
        }

        .question-grid {
            display: grid;
            gap: 16px;
        }

        .question-block {
            padding: 20px;
            border-radius: 24px;
            border: 1px solid rgba(16, 34, 46, 0.08);
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.97), rgba(246, 249, 247, 0.92));
            transition: border-color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
        }

        .question-block:focus-within {
            border-color: rgba(20, 62, 90, 0.2);
            box-shadow: 0 16px 32px rgba(20, 62, 90, 0.08);
        }

        .question-block.is-invalid {
            border-color: rgba(180, 35, 24, 0.26);
            box-shadow: 0 0 0 4px rgba(180, 35, 24, 0.05);
        }

        .question-top {
            display: grid;
            gap: 6px;
        }

        .question-label-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
            justify-content: space-between;
        }

        .question-tag {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            width: fit-content;
            padding: 6px 10px;
            border-radius: 999px;
            background: rgba(20, 62, 90, 0.06);
            color: var(--primary);
            font-size: 0.74rem;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .question-stack {
            display: grid;
            gap: 12px;
        }

        .choice-list {
            display: grid;
            gap: 10px;
        }

        .choice-list--split {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .choice-item {
            position: relative;
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            gap: 12px;
            align-items: start;
            padding: 14px 16px;
            border-radius: 18px;
            border: 1px solid rgba(16, 34, 46, 0.1);
            background: rgba(255, 255, 255, 0.92);
            cursor: pointer;
            transition: transform 0.18s ease, border-color 0.18s ease, background 0.18s ease, box-shadow 0.18s ease;
        }

        .choice-item:hover {
            transform: translateY(-1px);
            border-color: rgba(20, 62, 90, 0.18);
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.06);
        }

        .choice-item.is-selected {
            border-color: rgba(20, 62, 90, 0.2);
            background: linear-gradient(180deg, rgba(20, 62, 90, 0.06), rgba(255, 255, 255, 0.96));
        }

        .choice-item input {
            margin: 3px 0 0;
        }

        .choice-item__body {
            display: grid;
            gap: 3px;
        }

        .choice-item__body strong {
            font-size: 0.97rem;
            line-height: 1.35;
        }

        .choice-item__body span {
            color: var(--muted);
            font-size: 0.88rem;
        }

        .scale-grid {
            display: grid;
            gap: 10px;
            grid-template-columns: repeat(auto-fit, minmax(88px, 1fr));
        }

        .scale-item {
            min-height: 112px;
            align-content: center;
            justify-items: center;
            text-align: center;
            gap: 8px;
        }

        .scale-item input {
            margin: 0;
        }

        .scale-item strong {
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--primary);
        }

        .scale-item span {
            color: var(--muted);
            font-size: 0.8rem;
        }

        .scale-labels {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            color: var(--muted);
            font-size: 0.86rem;
        }

        .scale-points {
            display: grid;
            gap: 10px;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        }

        .scale-point {
            display: grid;
            gap: 4px;
            padding: 10px 12px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.84);
            border: 1px solid rgba(16, 34, 46, 0.08);
        }

        .scale-point strong {
            color: var(--primary);
            font-size: 0.88rem;
        }

        .scale-point span {
            color: var(--muted);
            font-size: 0.83rem;
            line-height: 1.35;
        }

        .slider-panel {
            display: grid;
            gap: 14px;
            padding: 18px;
            border-radius: 22px;
            background: rgba(20, 62, 90, 0.04);
            border: 1px solid rgba(20, 62, 90, 0.08);
        }

        .slider-head {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
        }

        .slider-output {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 8px 14px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.96);
            border: 1px solid rgba(16, 34, 46, 0.08);
            font-weight: 800;
            color: var(--primary);
        }

        .slider-selected-label {
            color: var(--muted);
            font-size: 0.9rem;
            font-weight: 600;
        }

        input[type="range"] {
            width: 100%;
            height: 10px;
            border-radius: 999px;
            appearance: none;
            background: linear-gradient(90deg, var(--primary) var(--slider-percent, 0%), rgba(20, 62, 90, 0.14) var(--slider-percent, 0%));
            outline: none;
        }

        input[type="range"]::-webkit-slider-thumb {
            appearance: none;
            width: 24px;
            height: 24px;
            border-radius: 999px;
            border: 4px solid #ffffff;
            background: var(--accent);
            box-shadow: 0 8px 16px rgba(185, 120, 47, 0.24);
            cursor: pointer;
        }

        input[type="range"]::-moz-range-thumb {
            width: 24px;
            height: 24px;
            border: 4px solid #ffffff;
            border-radius: 999px;
            background: var(--accent);
            box-shadow: 0 8px 16px rgba(185, 120, 47, 0.24);
            cursor: pointer;
        }

        .file-caption {
            color: var(--muted);
            font-size: 0.88rem;
        }

        .matrix-wrap {
            overflow-x: auto;
            border-radius: 22px;
            border: 1px solid rgba(16, 34, 46, 0.08);
            background: rgba(255, 255, 255, 0.94);
        }

        .matrix-table {
            width: 100%;
            min-width: 620px;
            border-collapse: collapse;
        }

        .matrix-table th,
        .matrix-table td {
            padding: 14px 12px;
            text-align: center;
            border-bottom: 1px solid rgba(16, 34, 46, 0.08);
            vertical-align: middle;
        }

        .matrix-table thead th {
            background: rgba(20, 62, 90, 0.05);
            font-size: 0.82rem;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: var(--primary);
        }

        .matrix-table th:first-child,
        .matrix-table td:first-child {
            text-align: left;
            min-width: 240px;
        }

        .matrix-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .question-error,
        .field-error {
            color: var(--warn);
            font-size: 0.86rem;
            font-weight: 700;
        }

        .action-dock {
            position: sticky;
            bottom: 12px;
            z-index: 25;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-top: 18px;
            padding: 16px 18px;
            border-radius: 26px;
        }

        .action-dock__meta {
            display: grid;
            gap: 4px;
            min-width: 0;
        }

        .action-dock__meta span {
            color: var(--muted);
            font-size: 0.78rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .action-dock__meta strong {
            font-size: 1.02rem;
            line-height: 1.3;
        }

        .action-dock__meta small {
            color: var(--muted);
            font-size: 0.88rem;
        }

        .action-dock__buttons {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .btn {
            appearance: none;
            border: 0;
            min-height: 48px;
            padding: 12px 18px;
            border-radius: 999px;
            font-weight: 800;
            letter-spacing: 0.01em;
            cursor: pointer;
            transition: transform 0.18s ease, opacity 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn:disabled {
            opacity: 0.58;
            cursor: default;
            transform: none;
        }

        .btn-primary {
            color: #ffffff;
            background: linear-gradient(90deg, var(--primary-strong), var(--primary));
            box-shadow: 0 16px 28px rgba(13, 43, 64, 0.2);
        }

        .btn-secondary {
            color: var(--ink);
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(16, 34, 46, 0.12);
        }

        .btn-success {
            color: #ffffff;
            background: linear-gradient(90deg, #1d6a3f, var(--success));
            box-shadow: 0 16px 28px rgba(30, 122, 70, 0.2);
        }

        .result-surface {
            margin-top: 22px;
            border-radius: 32px;
            padding: clamp(22px, 3vw, 34px);
            display: grid;
            gap: 18px;
        }

        .result-icon {
            width: 70px;
            height: 70px;
            display: grid;
            place-items: center;
            border-radius: 24px;
            background: linear-gradient(135deg, #1d6a3f, #2ea060);
            color: #ffffff;
            font-size: 1.7rem;
            font-weight: 900;
            box-shadow: 0 20px 32px rgba(30, 122, 70, 0.22);
        }

        .result-surface h2 {
            margin: 0;
            font: 700 clamp(1.9rem, 3vw, 2.8rem)/1 var(--font-display);
        }

        .result-surface p {
            margin: 0;
            color: var(--muted);
            max-width: 60ch;
        }

        .result-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .survey-body--simple {
            background: #f6f8fb;
        }

        .survey-page--simple {
            max-width: 980px;
        }

        .survey-page--simple .masthead {
            min-height: auto;
            padding: 24px 26px;
            border-radius: 20px;
            color: var(--ink);
            background: #ffffff;
            border: 1px solid rgba(16, 34, 46, 0.1);
            box-shadow: none;
        }

        .survey-page--simple .masthead::before,
        .survey-page--simple .masthead::after {
            display: none;
        }

        .survey-page--simple .masthead__grid,
        .survey-page--simple .masthead__grid--single {
            gap: 10px;
            min-height: auto;
            align-items: start;
        }

        .survey-page--simple .eyebrow {
            padding: 6px 10px;
            border-radius: 999px;
            border: 1px solid #dbe5ec;
            background: #f5f8fb;
            color: var(--primary);
            font-size: 0.72rem;
        }

        .survey-page--simple .masthead__copy,
        .survey-page--simple .masthead__grid--single .masthead__copy {
            width: 100%;
            gap: 10px;
            margin-inline: 0;
        }

        .survey-page--simple .masthead h1,
        .survey-page--simple .masthead--compact-title h1 {
            max-width: none;
            color: var(--ink);
            font: 700 clamp(1.45rem, 2.5vw, 2.1rem)/1.25 var(--font-body);
            letter-spacing: -0.02em;
        }

        .survey-page--simple .masthead__lead,
        .survey-page--simple .masthead__grid--single .masthead__lead {
            max-width: 68ch;
            color: var(--muted);
            font-size: 0.94rem;
            line-height: 1.6;
        }

        .survey-page--simple .inline-nav-shell,
        .survey-page--simple .surface,
        .survey-page--simple .action-dock,
        .survey-page--simple .result-surface {
            border: 1px solid rgba(16, 34, 46, 0.1);
            background: #ffffff;
            box-shadow: none;
            backdrop-filter: none;
            border-radius: 18px;
        }

        .survey-page--simple .inline-nav-shell {
            padding: 10px;
        }

        .survey-page--simple .step-nav--inline {
            gap: 8px;
            grid-auto-columns: minmax(180px, 210px);
            padding-bottom: 2px;
        }

        .survey-page--simple .step-link {
            padding: 10px 12px;
            border-radius: 14px;
            border: 1px solid #dde5ec;
            background: #ffffff;
            box-shadow: none;
        }

        .survey-page--simple .step-link.is-available:not(:disabled):hover,
        .survey-page--simple .choice-item:hover,
        .survey-page--simple .btn:hover {
            transform: none;
            box-shadow: none;
        }

        .survey-page--simple .step-link.is-current {
            border-color: #c7d8e6;
            background: #f6fafc;
        }

        .survey-page--simple .step-link.is-complete {
            border-color: #dbe7de;
            background: #f8fbf8;
        }

        .survey-page--simple .step-link__index {
            width: 30px;
            height: 30px;
            background: #eef4f8;
            font-size: 0.82rem;
        }

        .survey-page--simple .step-link__body strong {
            font-size: 0.9rem;
        }

        .survey-page--simple .step-link__body small,
        .survey-page--simple .step-link__status {
            font-size: 0.78rem;
        }

        .survey-page--simple .surface {
            overflow: visible;
        }

        .survey-page--simple .surface__head {
            padding: 16px 18px;
            border-bottom: 1px solid rgba(16, 34, 46, 0.08);
            background: #ffffff;
        }

        .survey-page--simple .status-row {
            grid-template-columns: 1fr;
            align-items: start;
        }

        .survey-page--simple .status-copy {
            gap: 4px;
        }

        .survey-page--simple .status-copy strong {
            font-size: 1rem;
        }

        .survey-page--simple .status-copy span {
            font-size: 0.84rem;
        }

        .survey-page--simple .surface__body {
            padding: 18px;
        }

        .survey-page--simple .step {
            animation: none;
        }

        .survey-page--simple .step-header,
        .survey-page--simple .section-step .step-header {
            gap: 8px;
            margin-bottom: 16px;
            padding: 0;
            border: 0;
            border-radius: 0;
            background: none;
            box-shadow: none;
        }

        .survey-page--simple .step-kicker,
        .survey-page--simple .section-step .step-kicker,
        .survey-page--simple .question-tag {
            padding: 5px 9px;
            border-radius: 999px;
            background: #f3f7fa;
            color: var(--primary);
            font-size: 0.7rem;
        }

        .survey-page--simple .step-counter {
            font-size: 0.82rem;
        }

        .survey-page--simple .step-header h2,
        .survey-page--simple .step-header h3 {
            font: 700 clamp(1.2rem, 1.8vw, 1.55rem)/1.3 var(--font-body);
            color: var(--ink);
            letter-spacing: -0.01em;
        }

        .survey-page--simple .step-header p {
            font-size: 0.92rem;
        }

        .survey-page--simple .intro-scene {
            grid-template-columns: 1fr;
            gap: 14px;
        }

        .survey-page--simple .intro-scene__panel {
            padding: 18px;
            border-radius: 18px;
            border: 1px solid #dde5ec;
            box-shadow: none;
        }

        .survey-page--simple .intro-scene__panel--welcome {
            background: #f8fbfd;
            color: var(--ink);
        }

        .survey-page--simple .intro-scene__panel--welcome::after {
            display: none;
        }

        .survey-page--simple .intro-scene__eyebrow {
            padding: 6px 10px;
            border: 1px solid #dbe5ec;
            background: #ffffff;
            color: var(--primary);
            font-size: 0.72rem;
        }

        .survey-page--simple .intro-scene__title {
            margin: 12px 0 8px;
            max-width: none;
            font: 700 1.28rem/1.3 var(--font-body);
            color: var(--ink);
        }

        .survey-page--simple .intro-scene__text {
            color: var(--muted);
            font-size: 0.92rem;
            line-height: 1.6;
        }

        .survey-page--simple .intro-scene__facts {
            display: none;
        }

        .survey-page--simple .intro-form-head {
            margin-bottom: 14px;
            align-items: start;
        }

        .survey-page--simple .intro-form-head h3 {
            font: 700 1.08rem/1.3 var(--font-body);
            color: var(--ink);
        }

        .survey-page--simple .intro-form-head p {
            font-size: 0.9rem;
        }

        .survey-page--simple .intro-form-badge {
            background: #f5f8fb;
            border-color: #dbe5ec;
            color: var(--primary);
            font-size: 0.72rem;
        }

        .survey-page--simple .intro-grid,
        .survey-page--simple .question-grid {
            gap: 14px;
        }

        .survey-page--simple .field,
        .survey-page--simple .question-block,
        .survey-page--simple .section-step .question-block {
            padding: 16px;
            border-radius: 16px;
            border: 1px solid #dde5ec;
            background: #ffffff;
            box-shadow: none;
        }

        .survey-page--simple .section-step {
            padding: 0;
            border: 0;
            border-radius: 0;
            background: none;
            box-shadow: none;
        }

        .survey-page--simple .question-block:focus-within,
        .survey-page--simple .section-step .question-block:focus-within {
            border-color: #c8d7e4;
            box-shadow: none;
        }

        .survey-page--simple .question-label-row {
            gap: 8px;
        }

        .survey-page--simple .choice-list {
            gap: 8px;
        }

        .survey-page--simple .choice-item {
            padding: 12px 13px;
            border-radius: 14px;
            border: 1px solid #dde5ec;
            background: #ffffff;
        }

        .survey-page--simple .choice-item.is-selected {
            border-color: #c7d8e6;
            background: #f7fbfe;
        }

        .survey-page--simple .choice-item__body strong {
            font-size: 0.93rem;
        }

        .survey-page--simple .choice-item__body span,
        .survey-page--simple .field-note,
        .survey-page--simple .question-note,
        .survey-page--simple .question-hint {
            font-size: 0.84rem;
        }

        .survey-page--simple .scale-item,
        .survey-page--simple .scale-point,
        .survey-page--simple .slider-panel {
            border-radius: 14px;
            border: 1px solid #dde5ec;
            background: #ffffff;
            box-shadow: none;
        }

        .survey-page--simple .slider-panel {
            padding: 14px;
        }

        .survey-page--simple .matrix-wrap {
            border-radius: 16px;
            border-color: #dde5ec;
            box-shadow: none;
        }

        .survey-page--simple .matrix-table thead th {
            background: #f7fafc;
        }

        .survey-page--simple .action-dock {
            bottom: 10px;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 16px;
        }

        .survey-page--simple .action-dock__meta strong {
            font-size: 0.96rem;
        }

        .survey-page--simple .action-dock__meta small {
            font-size: 0.82rem;
        }

        .survey-page--simple .btn {
            min-height: 44px;
            padding: 10px 16px;
            border-radius: 12px;
            box-shadow: none;
        }

        .survey-page--simple .btn-primary,
        .survey-page--simple .btn-success {
            background: var(--primary);
            color: #ffffff;
        }

        .survey-page--simple .btn-secondary {
            background: #ffffff;
            border: 1px solid #dbe5ec;
        }

        @media (max-width: 1120px) {
            .masthead__grid,
            .workspace,
            .status-row {
                grid-template-columns: 1fr;
            }

            .survey-rail {
                position: static;
            }
        }

        @media (max-width: 860px) {
            body {
                padding-inline: 10px;
            }

            .masthead {
                min-height: auto;
                border-radius: 28px;
            }

            .masthead h1 {
                max-width: 13ch;
            }

            .intro-scene,
            .intro-grid,
            .choice-list--split {
                grid-template-columns: 1fr;
            }

            .step-nav {
                grid-auto-flow: column;
                grid-auto-columns: minmax(240px, 1fr);
                overflow-x: auto;
                padding-bottom: 2px;
                scroll-snap-type: x proximity;
            }

            .step-link {
                scroll-snap-align: start;
            }

            .surface__head,
            .surface__body,
            .rail-panel,
            .action-dock {
                padding-inline: 16px;
            }
        }

        @media (max-width: 720px) {
            .matrix-table,
            .matrix-table thead,
            .matrix-table tbody,
            .matrix-table tr,
            .matrix-table th,
            .matrix-table td {
                display: block;
                width: 100%;
                min-width: 0;
            }

            .matrix-table thead {
                display: none;
            }

            .matrix-table tbody {
                display: grid;
                gap: 12px;
                padding: 12px;
            }

            .matrix-table tr {
                border-radius: 18px;
                border: 1px solid rgba(16, 34, 46, 0.08);
                padding: 12px 14px;
                background: rgba(255, 255, 255, 0.96);
            }

            .matrix-table td {
                border-bottom: 0;
                padding: 8px 0;
            }

            .matrix-table td:first-child {
                min-width: 0;
                font-weight: 800;
                padding-bottom: 10px;
                margin-bottom: 4px;
                border-bottom: 1px solid rgba(16, 34, 46, 0.08);
            }

            .matrix-table td:not(:first-child) {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
            }

            .matrix-table td:not(:first-child)::before {
                content: attr(data-column-label);
                color: var(--muted);
                font-size: 0.86rem;
                font-weight: 700;
                text-align: left;
            }
        }

        @media (max-width: 640px) {
            .masthead h1,
            .step-header h2,
            .step-header h3,
            .result-surface h2 {
                line-height: 1.04;
            }

            .intro-scene__facts,
            .intro-form-head {
                grid-template-columns: 1fr;
                flex-direction: column;
            }

            .action-dock {
                flex-direction: column;
                align-items: stretch;
            }

            .action-dock__buttons {
                width: 100%;
                justify-content: stretch;
            }

            .action-dock__buttons .btn {
                flex: 1 1 auto;
                width: 100%;
            }

            .progress-meta,
            .step-header__top,
            .slider-head {
                align-items: flex-start;
                flex-direction: column;
            }

            .choice-item {
                padding: 13px 14px;
            }
        }
    </style>
</head>

<body class="{{ $useSimpleLayout ? 'survey-body--simple' : '' }}">
    <div class="survey-page{{ $useSimpleLayout ? ' survey-page--simple' : '' }}">
        <section class="masthead{{ $useCompactTitle ? ' masthead--compact-title' : '' }}">
            <div class="masthead__grid{{ $showBriefingPanel ? '' : ' masthead__grid--single' }}">
                <div class="masthead__copy">
                    <span class="eyebrow">ATTP Monitoring, Evaluation and Learning</span>
                    <h1>{{ $surveyTitle }}</h1>
                    <p class="masthead__lead">
                        {{ data_get($surveyConfig, 'intro', 'Please complete the survey carefully. Move section by section, review your answers, and submit once you are satisfied.') }}
                    </p>
                    @if ($showHeaderMeta)
                        <div class="masthead__meta">
                            <div class="meta-pill">
                                <strong>Indicator</strong>
                                <span>{{ $link->indicator->name }}</span>
                            </div>
                            <div class="meta-pill">
                                <strong>Methodology</strong>
                                <span>{{ $methodology->name }}</span>
                            </div>
                            <div class="meta-pill">
                                <strong>Sections</strong>
                                <span>{{ $sectionCount }}</span>
                            </div>
                        </div>
                    @endif
                </div>

                @if ($showBriefingPanel)
                    <div class="masthead__panel">
                        <div class="briefing">
                            <p class="briefing__title">Survey briefing</p>
                            <p>
                                Your responses help strengthen coordination, delivery, and future workshop design.
                                Questions may adapt based on the answers you provide.
                            </p>
                            <ul class="briefing-list">
                                <li>
                                    <strong>Time needed</strong>
                                    <span>{{ $estimatedMinutes > 0 ? $estimatedMinutes . ' minute' . ($estimatedMinutes === 1 ? '' : 's') : 'Flexible completion time' }}</span>
                                </li>
                                <li>
                                    <strong>Completion flow</strong>
                                    <span>One section at a time with clear Back and Next controls.</span>
                                </li>
                                <li>
                                    <strong>Confidentiality</strong>
                                    <span>Responses are handled as survey feedback and reviewed in context.</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                @endif
            </div>
        </section>

        @if (session('success'))
            <section class="result-surface">
                <div class="result-icon">&#10003;</div>
                <div>
                    <h2>Response submitted</h2>
                    <p>{{ session('success') }}</p>
                </div>
                <div class="result-actions">
                    <a class="btn btn-secondary" href="{{ route('public.me.indicators.surveys.show', ['token' => $link->public_token]) }}">
                        Submit another response
                    </a>
                </div>
            </section>
        @else
            <form method="POST"
                enctype="multipart/form-data"
                action="{{ route('public.me.indicators.surveys.submit', ['token' => $link->public_token]) }}"
                id="publicSurveyForm"
                novalidate>
                @csrf

                <div class="workspace{{ $showSideNavigation ? '' : ' workspace--single' }}">
                    @if ($showSideNavigation)
                        <aside class="survey-rail">
                            <section class="rail-panel" @if (! $showSidebarGuide) hidden @endif>
                                <span class="eyebrow" style="background: rgba(20, 62, 90, 0.06); border-color: rgba(20, 62, 90, 0.1); color: var(--primary);">Survey guide</span>
                                <h2>Move through the questionnaire with confidence.</h2>
                                <p>
                                    Use the section list to revisit completed sections. The current section updates live as you answer.
                                </p>

                                <div class="rail-stats">
                                    <div class="rail-stat">
                                        <span>Visible sections</span>
                                        <strong id="railSectionCount">{{ $sectionCount }}</strong>
                                    </div>
                                    <div class="rail-stat">
                                        <span>Current step</span>
                                        <strong id="railCurrentStep">Introduction</strong>
                                    </div>
                                    <div class="rail-stat">
                                        <span>Questions in view</span>
                                        <strong id="railQuestionCount">Respondent profile</strong>
                                    </div>
                                    <div class="rail-stat">
                                        <span>Progress detail</span>
                                        <strong id="railAnsweredCount">0% complete</strong>
                                    </div>
                                </div>
                            </section>

                            <nav class="step-nav" aria-label="Survey sections" id="stepNav">
                                <button type="button" class="step-link is-current is-available" data-step-nav="intro">
                                    <span class="step-link__index">0</span>
                                    <span class="step-link__body">
                                        <strong>Introduction</strong>
                                        <small data-nav-count>Respondent profile</small>
                                        <span class="step-link__status" data-nav-status>Current</span>
                                    </span>
                                </button>

                                @foreach ($normalSections as $sectionIndex => $section)
                                    @php
                                        $sectionKey = (string) ($section['key'] ?? ('section_' . $sectionIndex));
                                        $sectionColor = (string) data_get($section, 'color', '#143E5A');
                                        $sectionQuestionCount = collect($section['questions'] ?? [])
                                            ->filter(fn ($question) => strtolower((string) data_get($question, 'effective_flow_type', data_get($question, 'flow_type', 'normal'))) !== 'special')
                                            ->count();
                                    @endphp
                                    <button type="button" class="step-link step-link--section" data-step-nav="{{ $sectionKey }}" style="--section-accent: {{ $sectionColor }};">
                                        <span class="step-link__index">{{ $sectionIndex + 1 }}</span>
                                        <span class="step-link__body">
                                            <strong>{{ $section['title'] }}</strong>
                                            <small data-nav-count>{{ $sectionQuestionCount }} question{{ $sectionQuestionCount === 1 ? '' : 's' }}</small>
                                            <span class="step-link__status" data-nav-status>Upcoming</span>
                                        </span>
                                    </button>
                                @endforeach
                            </nav>
                        </aside>
                    @endif

                    <main class="main-stage">
                        @if (! $showSideNavigation)
                            <div class="inline-nav-shell">
                                <nav class="step-nav step-nav--inline" aria-label="Survey sections" id="stepNav">
                                    <button type="button" class="step-link is-current is-available" data-step-nav="intro">
                                        <span class="step-link__index">0</span>
                                        <span class="step-link__body">
                                            <strong>Introduction</strong>
                                            <small data-nav-count>Respondent profile</small>
                                            <span class="step-link__status" data-nav-status>Current</span>
                                        </span>
                                    </button>

                                    @foreach ($normalSections as $sectionIndex => $section)
                                        @php
                                            $sectionKey = (string) ($section['key'] ?? ('section_' . $sectionIndex));
                                            $sectionColor = (string) data_get($section, 'color', '#143E5A');
                                            $sectionQuestionCount = collect($section['questions'] ?? [])
                                                ->filter(fn ($question) => strtolower((string) data_get($question, 'effective_flow_type', data_get($question, 'flow_type', 'normal'))) !== 'special')
                                                ->count();
                                        @endphp
                                        <button type="button" class="step-link step-link--section" data-step-nav="{{ $sectionKey }}" style="--section-accent: {{ $sectionColor }};">
                                            <span class="step-link__index">{{ $sectionIndex + 1 }}</span>
                                            <span class="step-link__body">
                                                <strong>{{ $section['title'] }}</strong>
                                                <small data-nav-count>{{ $sectionQuestionCount }} question{{ $sectionQuestionCount === 1 ? '' : 's' }}</small>
                                                <span class="step-link__status" data-nav-status>Upcoming</span>
                                            </span>
                                        </button>
                                    @endforeach
                                </nav>
                            </div>
                        @endif

                        <section class="surface">
                            <div class="surface__head">
                                <div class="status-row">
                                    <div class="status-copy">
                                        <span class="eyebrow" style="background: rgba(185, 120, 47, 0.08); border-color: rgba(185, 120, 47, 0.12); color: var(--accent);">Survey progress</span>
                                        <strong id="progressLabel">Introduction</strong>
                                        <span id="progressMeta">Step 0 of {{ $sectionCount }}</span>
                                    </div>

                                    <div class="progress-stack" @if (! $showProgressTracker) hidden @endif>
                                        <div class="progress-meter">
                                            <span id="progressBarFill"></span>
                                        </div>
                                        <div class="progress-meta">
                                            <span id="progressPercent">0% complete</span>
                                            <span id="progressDescriptor">Review and continue</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="surface__body">
                                @if ($errors->any())
                                    <div class="alert alert-danger">
                                        <strong>Please review the highlighted survey items.</strong>
                                        <ul>
                                            @foreach ($errors->all() as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                <div class="steps-stage">
                                    <section class="step is-active"
                                        data-step-id="intro"
                                        data-step-kind="intro"
                                        data-step-label="Introduction">
                                        @if ($showSideNavigation)
                                            <div class="step-header">
                                                <div class="step-header__top">
                                                    <span class="step-kicker">Welcome</span>
                                                    <span class="step-counter">Step 0 of {{ $sectionCount }}</span>
                                                </div>
                                                <h2>Before you begin</h2>
                                                @if ($showIntroGuidance)
                                                    <p>
                                                        Capture your respondent details, then move through each section below.
                                                        You can return to any completed section before you submit your response.
                                                    </p>
                                                @endif
                                            </div>

                                            <div class="intro-panel">
                                                @if ($showIntroGuidance)
                                                    <div class="intro-panel__copy">
                                                        <strong>How this form works</strong>
                                                        <p>
                                                            Each screen focuses on one section only. Some questions or sections may appear
                                                            only when they are relevant to your previous responses.
                                                        </p>
                                                    </div>
                                                @endif

                                                <div class="intro-grid">
                                        @else
                                            <div class="intro-scene">
                                                <div class="intro-scene__panel intro-scene__panel--welcome">
                                                    <span class="intro-scene__eyebrow">Welcome</span>
                                                    <h2 class="intro-scene__title">Share your workshop feedback.</h2>
                                                    <p class="intro-scene__text">
                                                        Start with your respondent details, then continue through the survey section by section.
                                                    </p>

                                                    <div class="intro-scene__facts">
                                                        <div class="intro-fact">
                                                            <strong>{{ $estimatedMinutes > 0 ? $estimatedMinutes : '10' }}</strong>
                                                            <span>{{ $estimatedMinutes === 1 ? 'Minute' : 'Minutes' }}</span>
                                                        </div>
                                                        <div class="intro-fact">
                                                            <strong>{{ $sectionCount }}</strong>
                                                            <span>Sections</span>
                                                        </div>
                                                        <div class="intro-fact">
                                                            <strong>4</strong>
                                                            <span>Required fields</span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="intro-scene__panel intro-scene__panel--form">
                                                    <div class="intro-form-head">
                                                        <div>
                                                            <span class="intro-form-kicker">Respondent details</span>
                                                            <h3>Complete your details to begin</h3>
                                                            <p>These details are required before you move into the survey sections.</p>
                                                        </div>
                                                        <span class="intro-form-badge">All fields required</span>
                                                    </div>

                                                    <div class="intro-grid intro-grid--profile">
                                        @endif
                                                    <div class="field">
                                                        <label for="respondent_name">
                                                            {{ data_get($respondentNameConfig, 'label', 'Your name') }}
                                                            @if ($respondentNameRequired)
                                                                <span aria-hidden="true">*</span>
                                                            @endif
                                                        </label>
                                                        <input id="respondent_name"
                                                            type="text"
                                                            name="respondent_name"
                                                            value="{{ old('respondent_name') }}"
                                                            placeholder="{{ data_get($respondentNameConfig, 'placeholder', 'Enter your full name') }}"
                                                            data-respondent-field
                                                            @if ($respondentNameRequired) required aria-required="true" @endif>
                                                        @if ($showRespondentNotes)
                                                            <div class="field-note">This helps the team interpret your feedback.</div>
                                                        @endif
                                                        @error('respondent_name')
                                                            <div class="field-error">{{ $message }}</div>
                                                        @enderror
                                                    </div>

                                                    <div class="field">
                                                        <label for="respondent_email">
                                                            {{ data_get($respondentEmailConfig, 'label', 'Your email') }}
                                                            @if ($respondentEmailRequired)
                                                                <span aria-hidden="true">*</span>
                                                            @endif
                                                        </label>
                                                        <input id="respondent_email"
                                                            type="email"
                                                            name="respondent_email"
                                                            value="{{ old('respondent_email') }}"
                                                            placeholder="{{ data_get($respondentEmailConfig, 'placeholder', 'name@example.org') }}"
                                                            data-respondent-field
                                                            @if ($respondentEmailRequired) required aria-required="true" @endif>
                                                        @if ($showRespondentNotes)
                                                            <div class="field-note">Optional, but useful for follow-up clarification.</div>
                                                        @endif
                                                        @error('respondent_email')
                                                            <div class="field-error">{{ $message }}</div>
                                                        @enderror
                                                    </div>

                                                    <div class="field">
                                                        <label for="respondent_phone">
                                                            {{ data_get($respondentPhoneConfig, 'label', 'Phone') }}
                                                            @if ($respondentPhoneRequired)
                                                                <span aria-hidden="true">*</span>
                                                            @endif
                                                        </label>
                                                        <input id="respondent_phone"
                                                            type="text"
                                                            name="respondent_phone"
                                                            value="{{ old('respondent_phone') }}"
                                                            placeholder="{{ data_get($respondentPhoneConfig, 'placeholder', 'Enter a phone contact') }}"
                                                            data-respondent-field
                                                            @if ($respondentPhoneRequired) required aria-required="true" @endif>
                                                        @if ($showRespondentNotes)
                                                            <div class="field-note">Optional.</div>
                                                        @endif
                                                        @error('respondent_phone')
                                                            <div class="field-error">{{ $message }}</div>
                                                        @enderror
                                                    </div>

                                                    <div class="field">
                                                        <label for="respondent_organization">
                                                            {{ data_get($respondentOrganizationConfig, 'label', 'Organization or agency') }}
                                                            @if ($respondentOrganizationRequired)
                                                                <span aria-hidden="true">*</span>
                                                            @endif
                                                        </label>
                                                        <input id="respondent_organization"
                                                            type="text"
                                                            name="respondent_organization"
                                                            value="{{ old('respondent_organization') }}"
                                                            placeholder="{{ data_get($respondentOrganizationConfig, 'placeholder', 'Enter your institution or team') }}"
                                                            data-respondent-field
                                                            @if ($respondentOrganizationRequired) required aria-required="true" @endif>
                                                        @if ($showRespondentNotes)
                                                            <div class="field-note">This helps group responses by participation context.</div>
                                                        @endif
                                                        @error('respondent_organization')
                                                            <div class="field-error">{{ $message }}</div>
                                                        @enderror
                                                    </div>
                                        @if ($showSideNavigation)
                                                </div>
                                            </div>
                                        @else
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    </section>

                                    @foreach ($normalSections as $sectionIndex => $section)
                                        @php
                                            $sectionKey = (string) ($section['key'] ?? ('section_' . $sectionIndex));
                                            $sectionColor = (string) data_get($section, 'color', '#143E5A');
                                            $normalSectionQuestions = collect($section['questions'] ?? [])
                                                ->filter(fn ($question) => strtolower((string) data_get($question, 'effective_flow_type', data_get($question, 'flow_type', 'normal'))) !== 'special')
                                                ->values();
                                        @endphp
                                        <section class="step section-step"
                                            data-step-id="{{ $sectionKey }}"
                                            data-step-kind="section"
                                            data-step-flow="normal"
                                            data-step-label="{{ $section['title'] }}"
                                            data-section-key="{{ $sectionKey }}"
                                            data-section-visibility='@json($section['visibility'] ?? [])'
                                            style="--section-accent: {{ $sectionColor }};">
                                            <div class="step-header">
                                                <div class="step-header__top">
                                                    <span class="step-kicker">Section {{ $sectionIndex + 1 }}</span>
                                                    <span class="step-counter">Section {{ $sectionIndex + 1 }} of {{ $sectionCount }}</span>
                                                </div>
                                                <h3>{{ $section['title'] }}</h3>
                                                <p>{{ $section['description'] ?? 'Complete each question in this section, then continue when you are ready.' }}</p>
                                            </div>

                                            <div class="question-grid">
                                                @foreach ($normalSectionQuestions as $questionIndex => $question)
                                                    @include('me.surveys._question_block', [
                                                        'question' => $question,
                                                        'sectionIndex' => $sectionIndex,
                                                        'questionIndex' => $questionIndex,
                                                    ])
                                                @endforeach
                                            </div>
                                        </section>
                                    @endforeach

                                    @foreach ($specialSections as $specialSectionIndex => $section)
                                        @php
                                            $sectionKey = (string) ($section['key'] ?? ('special_section_' . $specialSectionIndex));
                                            $sectionColor = (string) data_get($section, 'color', '#143E5A');
                                            $specialSectionQuestions = collect($section['questions'] ?? [])
                                                ->filter(fn ($question) => strtolower((string) data_get($question, 'effective_flow_type', data_get($question, 'flow_type', 'normal'))) !== 'special')
                                                ->values();
                                        @endphp
                                        <section class="step section-step branch-step"
                                            hidden
                                            data-step-id="special_section__{{ $sectionKey }}"
                                            data-step-kind="branch-section"
                                            data-step-flow="special"
                                            data-step-label="{{ $section['title'] }}"
                                            data-branch-target-type="section"
                                            data-branch-target-key="{{ $sectionKey }}"
                                            style="--section-accent: {{ $sectionColor }};">
                                            <div class="step-header">
                                                <div class="step-header__top">
                                                    <span class="step-kicker">Special Follow-up</span>
                                                    <span class="step-counter">Triggered page</span>
                                                </div>
                                                <h3>{{ $section['title'] }}</h3>
                                                <p>{{ $section['description'] ?: 'This page opens only when one of your previous answers requires a special follow-up.' }}</p>
                                            </div>

                                            <div class="question-grid">
                                                @foreach ($specialSectionQuestions as $questionIndex => $question)
                                                    @include('me.surveys._question_block', [
                                                        'question' => $question,
                                                        'sectionIndex' => $specialSectionIndex,
                                                        'questionIndex' => $questionIndex,
                                                    ])
                                                @endforeach
                                            </div>
                                        </section>
                                    @endforeach

                                    @foreach ($specialQuestions as $specialQuestionIndex => $question)
                                        @php
                                            $questionKey = (string) ($question['key'] ?? ('special_question_' . $specialQuestionIndex));
                                            $sectionColor = (string) data_get($question, 'section_color', '#143E5A');
                                        @endphp
                                        <section class="step section-step branch-step"
                                            hidden
                                            data-step-id="special_question__{{ $questionKey }}"
                                            data-step-kind="branch-question"
                                            data-step-flow="special"
                                            data-step-label="{{ $question['label'] ?? 'Special follow-up question' }}"
                                            data-branch-target-type="question"
                                            data-branch-target-key="{{ $questionKey }}"
                                            style="--section-accent: {{ $sectionColor }};">
                                            <div class="step-header">
                                                <div class="step-header__top">
                                                    <span class="step-kicker">Special Follow-up</span>
                                                    <span class="step-counter">{{ data_get($question, 'section_title', 'Follow-up page') }}</span>
                                                </div>
                                                <h3>{{ $question['label'] ?? 'Follow-up question' }}</h3>
                                                <p>{{ $question['hint'] ?: 'This question is outside the normal flow and opens only when a previous answer sends you here.' }}</p>
                                            </div>

                                            <div class="question-grid">
                                                @include('me.surveys._question_block', [
                                                    'question' => $question,
                                                    'sectionIndex' => data_get($question, 'section_index', 0),
                                                    'questionIndex' => data_get($question, 'question_index', $specialQuestionIndex),
                                                ])
                                            </div>
                                        </section>
                                    @endforeach
                                </div>
                            </div>
                        </section>

                        <section class="action-dock">
                            <div class="action-dock__meta">
                                <span>Current step</span>
                                <strong id="actionStepTitle">Introduction</strong>
                                <small id="actionStepMeta">Review the introduction and continue when ready.</small>
                            </div>

                            <div class="action-dock__buttons">
                                <button type="button" class="btn btn-secondary" id="globalBackButton" hidden>Back</button>
                                <button type="button" class="btn btn-primary" id="globalNextButton">Start survey</button>
                                <button type="button" class="btn btn-success" id="globalSubmitButton" hidden>Submit survey</button>
                            </div>
                        </section>
                    </main>
                </div>
            </form>
        @endif
    </div>

    @if (!session('success'))
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const form = document.getElementById('publicSurveyForm');

                if (!form) {
                    return;
                }

                const progressLabel = document.getElementById('progressLabel');
                const progressMeta = document.getElementById('progressMeta');
                const progressPercent = document.getElementById('progressPercent');
                const progressDescriptor = document.getElementById('progressDescriptor');
                const progressFill = document.getElementById('progressBarFill');
                const railSectionCount = document.getElementById('railSectionCount');
                const railCurrentStep = document.getElementById('railCurrentStep');
                const railQuestionCount = document.getElementById('railQuestionCount');
                const railAnsweredCount = document.getElementById('railAnsweredCount');
                const actionStepTitle = document.getElementById('actionStepTitle');
                const actionStepMeta = document.getElementById('actionStepMeta');
                const backButton = document.getElementById('globalBackButton');
                const nextButton = document.getElementById('globalNextButton');
                const submitButton = document.getElementById('globalSubmitButton');
                const allSteps = Array.from(form.querySelectorAll('.step'));
                const navItems = Array.from(document.querySelectorAll('[data-step-nav]'));
                const respondentFields = Array.from(form.querySelectorAll('[data-respondent-field]'));

                let currentStepId = 'intro';
                let navigationHistory = [];
                let branchContextStack = [];
                let activeSpecialSteps = new Set();

                function setText(node, value) {
                    if (node) {
                        node.textContent = value;
                    }
                }

                function parseJsonAttribute(element, attribute) {
                    try {
                        return JSON.parse(element.getAttribute(attribute) || '{}');
                    } catch (error) {
                        return {};
                    }
                }

                function comparableValues(value) {
                    if (Array.isArray(value)) {
                        return value.flat(Infinity)
                            .filter((item) => item !== null && item !== undefined && String(item).trim() !== '')
                            .map((item) => String(item).trim().toLowerCase());
                    }

                    if (value && typeof value === 'object') {
                        return Object.values(value)
                            .filter((item) => item !== null && item !== undefined && String(item).trim() !== '')
                            .map((item) => String(item).trim().toLowerCase());
                    }

                    if (value !== null && value !== undefined && String(value).trim() !== '') {
                        return [String(value).trim().toLowerCase()];
                    }

                    return [];
                }

                function hasValue(value) {
                    return comparableValues(value).length > 0;
                }

                function stepId(step) {
                    return step?.getAttribute('data-step-id') || '';
                }

                function stepById(id) {
                    return allSteps.find((step) => stepId(step) === id) || null;
                }

                function isSpecialStep(step) {
                    return step?.getAttribute('data-step-flow') === 'special';
                }

                function normalSectionSteps() {
                    return allSteps.filter((step) => step.getAttribute('data-step-kind') === 'section' && step.getAttribute('data-step-flow') !== 'special' && !step.hidden);
                }

                function currentStep() {
                    return stepById(currentStepId) || stepById('intro');
                }

                function answerForQuestion(questionKey) {
                    const questionBlock = form.querySelector(`.question-block[data-question-key="${questionKey}"]`);
                    if (!questionBlock) {
                        return null;
                    }

                    const questionType = questionBlock.getAttribute('data-question-type');

                    if (questionType === 'checkbox' || questionType === 'multiselect') {
                        const checkedChoices = Array.from(questionBlock.querySelectorAll('input[type="checkbox"]:checked'))
                            .map((input) => input.value);

                        if (checkedChoices.length > 0) {
                            return checkedChoices;
                        }

                        return Array.from(questionBlock.querySelectorAll('select option:checked'))
                            .map((option) => option.value);
                    }

                    if (questionType === 'matrix') {
                        const matrix = {};
                        Array.from(questionBlock.querySelectorAll('tbody tr')).forEach((row) => {
                            const checked = row.querySelector('input[type="radio"]:checked');
                            if (!checked) {
                                return;
                            }

                            const name = checked.name || '';
                            const match = name.match(/\[([^\]]+)\]$/);
                            if (match) {
                                matrix[match[1]] = checked.value;
                            }
                        });

                        return matrix;
                    }

                    if (questionType === 'file') {
                        return Array.from(questionBlock.querySelectorAll('input[type="file"]'))
                            .flatMap((input) => Array.from(input.files || []).map((file) => file.name));
                    }

                    const checkedRadio = questionBlock.querySelector('input[type="radio"]:checked');
                    if (checkedRadio) {
                        return checkedRadio.value;
                    }

                    const field = questionBlock.querySelector('input:not([type="radio"]):not([type="checkbox"]), textarea, select');
                    return field ? field.value : null;
                }

                function matchesVisibility(visibility) {
                    const questionKey = (visibility?.question_key || '').toString().trim();
                    const values = Array.isArray(visibility?.values)
                        ? visibility.values.map((item) => String(item).trim().toLowerCase()).filter(Boolean)
                        : [];

                    if (!questionKey || values.length === 0) {
                        return true;
                    }

                    const currentValues = comparableValues(answerForQuestion(questionKey));
                    if (currentValues.length === 0) {
                        return false;
                    }

                    return currentValues.some((item) => values.includes(item));
                }

                function setInputsDisabled(container, disabled) {
                    container.querySelectorAll('input, textarea, select').forEach((field) => {
                        field.disabled = disabled;
                    });
                }

                function countVisibleQuestions(step) {
                    if (!step || step.getAttribute('data-step-kind') === 'intro') {
                        return 0;
                    }

                    return step.querySelectorAll('.question-block:not([hidden])').length;
                }

                function countAnsweredQuestions(step) {
                    if (!step || step.getAttribute('data-step-kind') === 'intro') {
                        return 0;
                    }

                    return Array.from(step.querySelectorAll('.question-block:not([hidden])'))
                        .filter((questionBlock) => hasValue(answerForQuestion(questionBlock.getAttribute('data-question-key'))))
                        .length;
                }

                function countCompletedRespondentFields() {
                    return respondentFields.filter((field) => String(field.value || '').trim() !== '').length;
                }

                function updateSelectionStates() {
                    form.querySelectorAll('[data-choice-item]').forEach((item) => {
                        const input = item.querySelector('input');
                        item.classList.toggle('is-selected', Boolean(input?.checked));
                    });
                }

                function updateSliderDisplays() {
                    form.querySelectorAll('[data-slider-input]').forEach((input) => {
                        const questionKey = input.getAttribute('data-slider-input');
                        const output = form.querySelector(`[data-slider-value="${questionKey}"]`);
                        const labelOutput = form.querySelector(`[data-slider-selected-label="${questionKey}"]`);
                        const min = Number(input.min || 0);
                        const max = Number(input.max || 100);
                        const value = Number(input.value || min);
                        const percent = max > min ? ((value - min) / (max - min)) * 100 : 0;
                        const labelMapRaw = input.getAttribute('data-slider-label-map') || '{}';
                        let labelMap = {};

                        try {
                            labelMap = JSON.parse(labelMapRaw);
                        } catch (error) {
                            labelMap = {};
                        }

                        input.style.setProperty('--slider-percent', `${percent}%`);

                        if (output) {
                            output.textContent = input.value;
                        }

                        if (labelOutput) {
                            labelOutput.textContent = labelMap[String(input.value)] || 'Adjust to the most appropriate point on the scale.';
                        }
                    });
                }

                function updateFileCaptions() {
                    form.querySelectorAll('[data-file-input]').forEach((input) => {
                        const questionKey = input.getAttribute('data-file-input');
                        const caption = form.querySelector(`[data-file-caption="${questionKey}"]`);
                        if (!caption) {
                            return;
                        }

                        const names = Array.from(input.files || []).map((file) => file.name);
                        caption.textContent = names.length > 0 ? names.join(', ') : 'No file selected yet.';
                    });
                }

                function routeStepId(targetType, targetKey) {
                    if (targetType === 'section') {
                        return `special_section__${targetKey}`;
                    }

                    if (targetType === 'question') {
                        return `special_question__${targetKey}`;
                    }

                    return '';
                }

                function matchedTargetStepIdForQuestionBlock(questionBlock) {
                    const route = parseJsonAttribute(questionBlock, 'data-question-route');
                    const targetType = (route?.target_type || '').toString().trim();
                    const targetKey = (route?.target_key || '').toString().trim();
                    const values = Array.isArray(route?.values)
                        ? route.values.map((item) => String(item).trim().toLowerCase()).filter(Boolean)
                        : [];

                    if (!targetType || !targetKey || values.length === 0) {
                        return '';
                    }

                    const answerValues = comparableValues(answerForQuestion(questionBlock.getAttribute('data-question-key')));
                    if (answerValues.length === 0 || !answerValues.some((item) => values.includes(item))) {
                        return '';
                    }

                    const targetStepId = routeStepId(targetType, targetKey);
                    return stepById(targetStepId) ? targetStepId : '';
                }

                function reachableNormalQuestionBlocks() {
                    const blocks = [];

                    allSteps
                        .filter((step) => step.getAttribute('data-step-kind') === 'section' && step.getAttribute('data-step-flow') !== 'special')
                        .forEach((step) => {
                            const sectionVisible = matchesVisibility(parseJsonAttribute(step, 'data-section-visibility'));
                            if (!sectionVisible) {
                                return;
                            }

                            step.querySelectorAll('.question-block').forEach((questionBlock) => {
                                if (questionBlock.getAttribute('data-question-flow') === 'special') {
                                    return;
                                }

                                if (matchesVisibility(parseJsonAttribute(questionBlock, 'data-question-visibility'))) {
                                    blocks.push(questionBlock);
                                }
                            });
                        });

                    return blocks;
                }

                function collectTriggeredSpecialSteps() {
                    const reachable = new Set();
                    const processedQuestions = new Set();
                    const pendingBlocks = [...reachableNormalQuestionBlocks()];

                    while (pendingBlocks.length > 0) {
                        const questionBlock = pendingBlocks.shift();
                        const questionKey = questionBlock.getAttribute('data-question-key') || '';

                        if (!questionKey || processedQuestions.has(questionKey)) {
                            continue;
                        }

                        processedQuestions.add(questionKey);

                        const targetStepId = matchedTargetStepIdForQuestionBlock(questionBlock);
                        if (!targetStepId || reachable.has(targetStepId)) {
                            continue;
                        }

                        reachable.add(targetStepId);

                        const targetStep = stepById(targetStepId);
                        if (!targetStep) {
                            continue;
                        }

                        if (targetStep.getAttribute('data-step-kind') === 'branch-question') {
                            const branchQuestionKey = targetStep.getAttribute('data-branch-target-key') || '';
                            const branchQuestionBlock = targetStep.querySelector(`.question-block[data-question-key="${branchQuestionKey}"]`);
                            if (branchQuestionBlock && matchesVisibility(parseJsonAttribute(branchQuestionBlock, 'data-question-visibility'))) {
                                pendingBlocks.push(branchQuestionBlock);
                            }
                            continue;
                        }

                        targetStep.querySelectorAll('.question-block').forEach((targetBlock) => {
                            if (targetBlock.getAttribute('data-question-flow') === 'special') {
                                return;
                            }

                            if (matchesVisibility(parseJsonAttribute(targetBlock, 'data-question-visibility'))) {
                                pendingBlocks.push(targetBlock);
                            }
                        });
                    }

                    return reachable;
                }

                function refreshStepQuestionVisibility(step) {
                    let visibleQuestionCount = 0;
                    const stepKind = step.getAttribute('data-step-kind');
                    const stepFlow = step.getAttribute('data-step-flow') || 'normal';

                    step.querySelectorAll('.question-block').forEach((questionBlock) => {
                        let questionVisible = matchesVisibility(parseJsonAttribute(questionBlock, 'data-question-visibility'));

                        if (stepFlow !== 'special' && questionBlock.getAttribute('data-question-flow') === 'special') {
                            questionVisible = false;
                        }

                        if (stepKind === 'branch-question') {
                            questionVisible = questionVisible
                                && questionBlock.getAttribute('data-question-key') === step.getAttribute('data-branch-target-key');
                        }

                        questionBlock.hidden = !questionVisible;
                        setInputsDisabled(questionBlock, !questionVisible);

                        if (questionVisible) {
                            visibleQuestionCount += 1;
                        }
                    });

                    return visibleQuestionCount;
                }

                function referenceNormalStepId() {
                    const activeStep = currentStep();
                    if (activeStep && !isSpecialStep(activeStep) && !activeStep.hidden) {
                        return stepId(activeStep);
                    }

                    for (let index = navigationHistory.length - 1; index >= 0; index -= 1) {
                        const historyStep = stepById(navigationHistory[index]);
                        if (historyStep && !isSpecialStep(historyStep) && !historyStep.hidden) {
                            return stepId(historyStep);
                        }
                    }

                    return 'intro';
                }

                function syncStepNavigation() {
                    const sequence = [stepById('intro'), ...normalSectionSteps()].filter(Boolean);
                    const referenceId = referenceNormalStepId();
                    const referenceIndex = Math.max(0, sequence.findIndex((step) => stepId(step) === referenceId));
                    const specialActive = isSpecialStep(currentStep());

                    navItems.forEach((item) => {
                        const targetStep = stepById(item.getAttribute('data-step-nav'));
                        if (!targetStep || targetStep.hidden) {
                            item.hidden = true;
                            return;
                        }

                        const targetIndex = sequence.findIndex((step) => stepId(step) === stepId(targetStep));
                        const isCurrent = targetIndex === referenceIndex;
                        const isComplete = targetIndex < referenceIndex;
                        const isAvailable = targetIndex <= referenceIndex;
                        const countNode = item.querySelector('[data-nav-count]');
                        const statusNode = item.querySelector('[data-nav-status]');

                        item.hidden = false;
                        item.disabled = !isAvailable;
                        item.classList.toggle('is-current', isCurrent);
                        item.classList.toggle('is-complete', isComplete);
                        item.classList.toggle('is-available', isAvailable);

                        if (countNode) {
                            if (targetStep.getAttribute('data-step-kind') === 'intro') {
                                countNode.textContent = 'Respondent profile';
                            } else {
                                const count = countVisibleQuestions(targetStep);
                                countNode.textContent = `${count} question${count === 1 ? '' : 's'}`;
                            }
                        }

                        if (statusNode) {
                            statusNode.textContent = specialActive && isCurrent
                                ? 'Follow-up active'
                                : (isCurrent ? 'Current' : (isComplete ? 'Completed' : 'Upcoming'));
                        }
                    });
                }

                function nextNormalStepId(fromStepId) {
                    const sections = normalSectionSteps();
                    if (fromStepId === 'intro') {
                        return stepId(sections[0]) || null;
                    }

                    const currentIndex = sections.findIndex((step) => stepId(step) === fromStepId);
                    if (currentIndex < 0) {
                        return null;
                    }

                    return stepId(sections[currentIndex + 1]) || null;
                }

                function peekBaseNextDestination() {
                    const activeStep = currentStep();
                    if (!activeStep) {
                        return null;
                    }

                    if (isSpecialStep(activeStep)) {
                        const context = branchContextStack[branchContextStack.length - 1];
                        if (!context) {
                            return nextNormalStepId(referenceNormalStepId());
                        }

                        return context.pendingTargets[0] || context.returnToId || null;
                    }

                    return nextNormalStepId(stepId(activeStep));
                }

                function consumeBaseNextDestination() {
                    const activeStep = currentStep();
                    if (!activeStep) {
                        return null;
                    }

                    if (isSpecialStep(activeStep)) {
                        const context = branchContextStack[branchContextStack.length - 1];
                        if (!context) {
                            return nextNormalStepId(referenceNormalStepId());
                        }

                        if (context.pendingTargets.length > 0) {
                            return context.pendingTargets.shift();
                        }

                        branchContextStack.pop();
                        return context.returnToId || null;
                    }

                    return nextNormalStepId(stepId(activeStep));
                }

                function updateProgress() {
                    const activeStep = currentStep();
                    const totalSections = normalSectionSteps().length;

                    if (!activeStep) {
                        return;
                    }

                    const activeKind = activeStep.getAttribute('data-step-kind');
                    const referenceId = referenceNormalStepId();
                    const referenceIndex = normalSectionSteps().findIndex((step) => stepId(step) === referenceId);
                    const completedNormalSteps = referenceIndex >= 0 ? referenceIndex + 1 : 0;
                    const numerator = activeKind === 'intro' ? 0 : completedNormalSteps;
                    const denominator = Math.max(totalSections, 1);
                    const percent = Math.max(0, Math.min(100, Math.round((numerator / denominator) * 100)));
                    const hasNextStep = Boolean(peekBaseNextDestination());
                    const questionCount = countVisibleQuestions(activeStep);
                    const answeredCount = countAnsweredQuestions(activeStep);

                    setText(progressLabel, activeStep.getAttribute('data-step-label') || 'Survey');

                    if (activeKind === 'intro') {
                        setText(progressMeta, `Step 0 of ${totalSections}`);
                    } else if (isSpecialStep(activeStep)) {
                        setText(progressMeta, `Follow-up after step ${Math.max(completedNormalSteps, 1)} of ${totalSections}`);
                    } else {
                        setText(progressMeta, `Step ${completedNormalSteps} of ${totalSections}`);
                    }

                    if (progressFill) {
                        progressFill.style.width = `${percent}%`;
                    }

                    setText(progressPercent, `${percent}% complete`);
                    setText(progressDescriptor, activeKind === 'intro'
                        ? 'Review and continue'
                        : (isSpecialStep(activeStep)
                            ? `${answeredCount} of ${questionCount} answered in this follow-up`
                            : `${answeredCount} of ${questionCount} answered in this section`));

                    setText(railSectionCount, totalSections);
                    setText(railCurrentStep, activeStep.getAttribute('data-step-label') || 'Survey');
                    setText(railQuestionCount, activeKind === 'intro'
                        ? 'Respondent profile'
                        : `${questionCount} visible question${questionCount === 1 ? '' : 's'}`);
                    setText(railAnsweredCount, activeKind === 'intro'
                        ? `${countCompletedRespondentFields()} of ${respondentFields.length} profile fields filled`
                        : `${answeredCount} of ${questionCount} answered`);

                    setText(actionStepTitle, activeStep.getAttribute('data-step-label') || 'Survey');
                    setText(actionStepMeta, activeKind === 'intro'
                        ? 'Review the introduction and continue when ready.'
                        : (isSpecialStep(activeStep)
                            ? 'This is a special follow-up page triggered by one of your previous answers.'
                            : (hasNextStep ? 'Complete this section before moving forward.' : 'Review this section, then submit your survey.')));

                    backButton.hidden = navigationHistory.length === 0;
                    nextButton.hidden = !hasNextStep;
                    submitButton.hidden = hasNextStep;
                    nextButton.textContent = activeKind === 'intro'
                        ? 'Start survey'
                        : (isSpecialStep(activeStep) ? 'Continue' : 'Next section');
                }

                function updateActiveStepClasses() {
                    allSteps.forEach((step) => step.classList.remove('is-active'));
                    const activeStep = currentStep();
                    if (activeStep) {
                        activeStep.classList.add('is-active');
                    }

                    syncStepNavigation();
                    updateProgress();
                }

                function scrollStepIntoView() {
                    const active = currentStep();
                    if (!active) {
                        return;
                    }

                    const top = active.getBoundingClientRect().top + window.scrollY - 24;
                    window.scrollTo({
                        top: Math.max(top, 0),
                        behavior: 'smooth',
                    });
                }

                function navigateToStep(targetStepId, options = {}) {
                    const targetStep = stepById(targetStepId);
                    if (!targetStep || targetStep.hidden) {
                        return;
                    }

                    if (options.pushHistory !== false && currentStepId && currentStepId !== targetStepId) {
                        navigationHistory.push(currentStepId);
                    }

                    currentStepId = targetStepId;
                    updateActiveStepClasses();

                    if (options.scroll !== false) {
                        scrollStepIntoView();
                    }
                }

                function clearClientErrors(container) {
                    if (!container) {
                        return;
                    }

                    container.querySelectorAll('.question-error[data-client-error="1"]').forEach((item) => item.remove());
                    container.querySelectorAll('.question-block.is-invalid').forEach((item) => item.classList.remove('is-invalid'));
                }

                function appendClientError(questionBlock, message) {
                    const error = document.createElement('div');
                    error.className = 'question-error';
                    error.dataset.clientError = '1';
                    error.textContent = message;
                    questionBlock.classList.add('is-invalid');
                    questionBlock.appendChild(error);
                }

                function failQuestion(questionBlock, message) {
                    appendClientError(questionBlock, message);
                    questionBlock.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center',
                    });
                    const firstField = questionBlock.querySelector('input, textarea, select');
                    if (firstField && typeof firstField.focus === 'function') {
                        firstField.focus({ preventScroll: true });
                    }
                    return false;
                }

                function validateStep(step) {
                    if (!step) {
                        return true;
                    }

                    if (step.getAttribute('data-step-kind') === 'intro') {
                        for (const field of step.querySelectorAll('input, textarea, select')) {
                            if (!field.reportValidity()) {
                                field.focus();
                                return false;
                            }
                        }
                        return true;
                    }

                    clearClientErrors(step);

                    for (const field of step.querySelectorAll('.question-block:not([hidden]) input:not([type="checkbox"]):not([type="radio"]), .question-block:not([hidden]) textarea, .question-block:not([hidden]) select')) {
                        if (!field.reportValidity()) {
                            const questionBlock = field.closest('.question-block');
                            if (questionBlock) {
                                questionBlock.classList.add('is-invalid');
                            }
                            field.focus();
                            return false;
                        }
                    }

                    for (const questionBlock of step.querySelectorAll('.question-block:not([hidden])')) {
                        const required = questionBlock.getAttribute('data-question-required') === '1';
                        const questionType = questionBlock.getAttribute('data-question-type');
                        const maxSelections = Number.parseInt(questionBlock.getAttribute('data-question-max-selections') || '', 10);
                        const minSelections = Number.parseInt(questionBlock.getAttribute('data-question-min-selections') || '', 10);

                        if (questionType === 'checkbox' || questionType === 'multiselect') {
                            const selected = questionBlock.querySelectorAll('input[type="checkbox"]:checked').length;

                            if (required && selected === 0) {
                                return failQuestion(questionBlock, 'Select at least one option.');
                            }

                            if (!Number.isNaN(minSelections) && selected < minSelections) {
                                return failQuestion(questionBlock, `Select at least ${minSelections} option(s).`);
                            }

                            if (!Number.isNaN(maxSelections) && selected > maxSelections) {
                                return failQuestion(questionBlock, `Select no more than ${maxSelections} option(s).`);
                            }
                        }

                        if (questionType === 'file' && required) {
                            const hasFile = questionBlock.querySelector('input[type="file"]')?.files?.length > 0;
                            if (!hasFile) {
                                return failQuestion(questionBlock, 'Please upload a file before continuing.');
                            }
                        }

                        if ((questionType === 'radio' || questionType === 'scale') && required) {
                            const selected = questionBlock.querySelector('input[type="radio"]:checked');
                            if (!selected) {
                                return failQuestion(questionBlock, 'Please choose one option before continuing.');
                            }
                        }

                        if (questionType === 'matrix' && required) {
                            const missingRow = Array.from(questionBlock.querySelectorAll('tbody tr'))
                                .find((row) => !row.querySelector('input[type="radio"]:checked'));

                            if (missingRow) {
                                return failQuestion(questionBlock, 'Please answer every row in this grid.');
                            }
                        }
                    }

                    return true;
                }

                function refreshVisibility() {
                    const activeId = currentStepId;
                    activeSpecialSteps = collectTriggeredSpecialSteps();

                    allSteps.forEach((step) => {
                        const kind = step.getAttribute('data-step-kind');
                        const flow = step.getAttribute('data-step-flow') || 'normal';

                        if (kind === 'intro') {
                            step.hidden = false;
                            return;
                        }

                        if (flow === 'special') {
                            const visibleQuestionCount = refreshStepQuestionVisibility(step);
                            step.hidden = !activeSpecialSteps.has(stepId(step)) || visibleQuestionCount === 0;
                            if (step.hidden) {
                                setInputsDisabled(step, true);
                            }
                            return;
                        }

                        const sectionVisible = matchesVisibility(parseJsonAttribute(step, 'data-section-visibility'));
                        const visibleQuestionCount = sectionVisible ? refreshStepQuestionVisibility(step) : 0;

                        step.hidden = !sectionVisible || visibleQuestionCount === 0;
                        if (step.hidden) {
                            setInputsDisabled(step, true);
                        }
                    });

                    const current = stepById(activeId);
                    if (!current || current.hidden) {
                        currentStepId = referenceNormalStepId();
                    }

                    updateActiveStepClasses();
                }

                function matchedTargetsForStep(step) {
                    const seen = new Set();
                    return Array.from(step.querySelectorAll('.question-block:not([hidden])'))
                        .map((questionBlock) => matchedTargetStepIdForQuestionBlock(questionBlock))
                        .filter((targetStepId) => {
                            if (!targetStepId || seen.has(targetStepId) || targetStepId === currentStepId) {
                                return false;
                            }

                            const alreadyQueued = branchContextStack.some((context) => context.pendingTargets.includes(targetStepId));
                            if (alreadyQueued) {
                                return false;
                            }

                            seen.add(targetStepId);
                            return true;
                        });
                }

                function advanceSurvey() {
                    const activeStep = currentStep();
                    if (!activeStep) {
                        return;
                    }

                    const matchedTargets = matchedTargetsForStep(activeStep);
                    if (matchedTargets.length > 0) {
                        branchContextStack.push({
                            originStepId: isSpecialStep(activeStep) ? referenceNormalStepId() : stepId(activeStep),
                            returnToId: peekBaseNextDestination(),
                            pendingTargets: matchedTargets.slice(1),
                        });
                        navigateToStep(matchedTargets[0], { scroll: true, pushHistory: true });
                        return;
                    }

                    const nextStepId = consumeBaseNextDestination();
                    if (nextStepId) {
                        navigateToStep(nextStepId, { scroll: true, pushHistory: true });
                        return;
                    }

                    form.submit();
                }

                function goBack() {
                    while (navigationHistory.length > 0) {
                        const targetStepId = navigationHistory.pop();
                        const targetStep = stepById(targetStepId);

                        if (!targetStep || targetStep.hidden) {
                            continue;
                        }

                        if (!isSpecialStep(targetStep)) {
                            branchContextStack = [];
                        }

                        currentStepId = targetStepId;
                        updateActiveStepClasses();
                        scrollStepIntoView();
                        return;
                    }
                }

                function handleLiveUpdates(event) {
                    const questionBlock = event.target.closest('.question-block');
                    if (questionBlock) {
                        questionBlock.classList.remove('is-invalid');
                        questionBlock.querySelectorAll('.question-error[data-client-error="1"]').forEach((item) => item.remove());
                    }

                    if (!isSpecialStep(currentStep())) {
                        branchContextStack = [];
                    }

                    updateSelectionStates();
                    updateSliderDisplays();
                    updateFileCaptions();
                    refreshVisibility();
                }

                form.addEventListener('input', handleLiveUpdates);
                form.addEventListener('change', handleLiveUpdates);

                nextButton.addEventListener('click', () => {
                    if (!validateStep(currentStep())) {
                        return;
                    }

                    advanceSurvey();
                });

                backButton.addEventListener('click', () => {
                    goBack();
                });

                submitButton.addEventListener('click', () => {
                    if (!validateStep(currentStep())) {
                        return;
                    }

                    form.submit();
                });

                navItems.forEach((item) => {
                    item.addEventListener('click', () => {
                        const targetStep = stepById(item.getAttribute('data-step-nav'));
                        if (!targetStep || targetStep.hidden) {
                            return;
                        }

                        const sequence = [stepById('intro'), ...normalSectionSteps()].filter(Boolean);
                        const targetIndex = sequence.findIndex((step) => stepId(step) === stepId(targetStep));
                        const referenceIndex = sequence.findIndex((step) => stepId(step) === referenceNormalStepId());

                        if (targetIndex < 0 || targetIndex > referenceIndex) {
                            return;
                        }

                        branchContextStack = [];
                        navigateToStep(stepId(targetStep), { scroll: true, pushHistory: true });
                    });
                });

                updateSelectionStates();
                updateSliderDisplays();
                updateFileCaptions();
                refreshVisibility();

                const firstServerError = form.querySelector('.question-error, .field-error');
                if (firstServerError) {
                    const parentStep = firstServerError.closest('.step');
                    if (parentStep && !parentStep.hidden) {
                        currentStepId = stepId(parentStep);
                        updateActiveStepClasses();
                        scrollStepIntoView();
                    }
                }
            });
        </script>
    @endif
</body>

</html>
