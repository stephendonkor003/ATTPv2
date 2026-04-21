<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ data_get($surveyConfig, 'title', 'Public Survey') }}</title>
    @php
        $sectionCount = count($sections);
        $estimatedMinutes = (int) data_get($surveyConfig, 'estimated_minutes', 0);
    @endphp
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

        .masthead h1 {
            margin: 0;
            max-width: 12ch;
            font: 700 clamp(2.45rem, 5vw, 5.1rem)/0.94 var(--font-display);
            letter-spacing: -0.03em;
        }

        .masthead__lead {
            margin: 0;
            max-width: 62ch;
            color: rgba(241, 245, 249, 0.92);
            font-size: 1.03rem;
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

        .survey-rail {
            position: sticky;
            top: 18px;
            display: grid;
            gap: 16px;
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

<body>
    <div class="survey-page">
        <section class="masthead">
            <div class="masthead__grid">
                <div class="masthead__copy">
                    <span class="eyebrow">ATTP Monitoring, Evaluation and Learning</span>
                    <h1>{{ data_get($surveyConfig, 'title', 'Public Survey') }}</h1>
                    <p class="masthead__lead">
                        {{ data_get($surveyConfig, 'intro', 'Please complete the survey carefully. Move section by section, review your answers, and submit once you are satisfied.') }}
                    </p>
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
                </div>

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

                <div class="workspace">
                    <aside class="survey-rail">
                        <section class="rail-panel">
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

                            @foreach ($sections as $sectionIndex => $section)
                                @php
                                    $sectionKey = (string) ($section['key'] ?? ('section_' . $sectionIndex));
                                    $sectionColor = (string) data_get($section, 'color', '#143E5A');
                                    $sectionQuestionCount = count($section['questions'] ?? []);
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

                    <main>
                        <section class="surface">
                            <div class="surface__head">
                                <div class="status-row">
                                    <div class="status-copy">
                                        <span class="eyebrow" style="background: rgba(185, 120, 47, 0.08); border-color: rgba(185, 120, 47, 0.12); color: var(--accent);">Survey progress</span>
                                        <strong id="progressLabel">Introduction</strong>
                                        <span id="progressMeta">Step 0 of {{ $sectionCount }}</span>
                                    </div>

                                    <div class="progress-stack">
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
                                        <div class="step-header">
                                            <div class="step-header__top">
                                                <span class="step-kicker">Welcome</span>
                                                <span class="step-counter">Step 0 of {{ $sectionCount }}</span>
                                            </div>
                                            <h2>Before you begin</h2>
                                            <p>
                                                Capture your respondent details, then move through each section below.
                                                You can return to any completed section before you submit your response.
                                            </p>
                                        </div>

                                        <div class="intro-panel">
                                            <div class="intro-panel__copy">
                                                <strong>How this form works</strong>
                                                <p>
                                                    Each screen focuses on one section only. Some questions or sections may appear
                                                    only when they are relevant to your previous responses.
                                                </p>
                                            </div>

                                            <div class="intro-grid">
                                                <div class="field">
                                                    <label for="respondent_name">Your name</label>
                                                    <input id="respondent_name"
                                                        type="text"
                                                        name="respondent_name"
                                                        value="{{ old('respondent_name') }}"
                                                        placeholder="Enter your full name"
                                                        data-respondent-field>
                                                    <div class="field-note">This helps the team interpret your feedback.</div>
                                                    @error('respondent_name')
                                                        <div class="field-error">{{ $message }}</div>
                                                    @enderror
                                                </div>

                                                <div class="field">
                                                    <label for="respondent_email">Your email</label>
                                                    <input id="respondent_email"
                                                        type="email"
                                                        name="respondent_email"
                                                        value="{{ old('respondent_email') }}"
                                                        placeholder="name@example.org"
                                                        data-respondent-field>
                                                    <div class="field-note">Optional, but useful for follow-up clarification.</div>
                                                    @error('respondent_email')
                                                        <div class="field-error">{{ $message }}</div>
                                                    @enderror
                                                </div>

                                                <div class="field">
                                                    <label for="respondent_phone">Phone</label>
                                                    <input id="respondent_phone"
                                                        type="text"
                                                        name="respondent_phone"
                                                        value="{{ old('respondent_phone') }}"
                                                        placeholder="Enter a phone contact"
                                                        data-respondent-field>
                                                    <div class="field-note">Optional.</div>
                                                    @error('respondent_phone')
                                                        <div class="field-error">{{ $message }}</div>
                                                    @enderror
                                                </div>

                                                <div class="field">
                                                    <label for="respondent_organization">Organization or agency</label>
                                                    <input id="respondent_organization"
                                                        type="text"
                                                        name="respondent_organization"
                                                        value="{{ old('respondent_organization') }}"
                                                        placeholder="Enter your institution or team"
                                                        data-respondent-field>
                                                    <div class="field-note">This helps group responses by participation context.</div>
                                                    @error('respondent_organization')
                                                        <div class="field-error">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                        </div>
                                    </section>

                                    @foreach ($sections as $sectionIndex => $section)
                                        @php
                                            $sectionKey = (string) ($section['key'] ?? ('section_' . $sectionIndex));
                                            $sectionColor = (string) data_get($section, 'color', '#143E5A');
                                        @endphp
                                        <section class="step section-step"
                                            data-step-id="{{ $sectionKey }}"
                                            data-step-kind="section"
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
                                                @foreach ($section['questions'] as $questionIndex => $question)
                                                    @php
                                                        $type = strtolower((string) ($question['type'] ?? 'text'));
                                                        $questionKey = (string) ($question['key'] ?? ('question_' . $sectionIndex . '_' . $questionIndex));
                                                        $required = (bool) ($question['required'] ?? false);
                                                        $oldValue = old('answers.' . $questionKey);
                                                        $options = collect($question['options'] ?? [])->filter()->values()->all();
                                                        $matrixRows = collect($question['rows'] ?? [])->values();
                                                        $matrixColumns = collect($question['columns'] ?? [])->values();
                                                        $scaleMin = (int) data_get($question, 'scale.min', 1);
                                                        $scaleMax = (int) data_get($question, 'scale.max', 5);
                                                        $scaleStep = max((int) data_get($question, 'scale.step', 1), 1);
                                                        $sliderValue = is_scalar($oldValue) && trim((string) $oldValue) !== ''
                                                            ? (string) $oldValue
                                                            : (string) $scaleMin;
                                                        $selectionMin = data_get($question, 'min_selections');
                                                        $selectionMax = data_get($question, 'max_selections');
                                                        $questionTag = match ($type) {
                                                            'textarea' => 'Long text',
                                                            'select' => 'Dropdown',
                                                            'multiselect' => 'Multi select',
                                                            'radio' => 'Single choice',
                                                            'checkbox' => 'Checkbox',
                                                            'scale' => 'Scale',
                                                            'slider' => 'Slider',
                                                            'file' => 'File upload',
                                                            'url' => 'Link',
                                                            'matrix' => 'Grid',
                                                            'datetime' => 'Date and time',
                                                            default => ucfirst($type),
                                                        };
                                                        $questionNote = null;

                                                        if (in_array($type, ['checkbox', 'multiselect'], true)) {
                                                            $notes = [];
                                                            if (is_numeric($selectionMin)) {
                                                                $notes[] = 'Select at least ' . (int) $selectionMin . '.';
                                                            }
                                                            if (is_numeric($selectionMax)) {
                                                                $notes[] = 'Select no more than ' . (int) $selectionMax . '.';
                                                            }
                                                            $questionNote = implode(' ', $notes) ?: 'Choose all that apply.';
                                                        } elseif ($type === 'matrix') {
                                                            $questionNote = 'Provide one response for each row.';
                                                        } elseif ($type === 'slider') {
                                                            $questionNote = 'Move the slider to the value that best reflects your view.';
                                                        } elseif ($type === 'file') {
                                                            $questionNote = 'Attach one supporting file if required.';
                                                        }
                                                    @endphp

                                                    <div class="question-block"
                                                        data-question-key="{{ $questionKey }}"
                                                        data-question-type="{{ $type }}"
                                                        data-question-visibility='@json($question['visibility'] ?? [])'
                                                        data-question-required="{{ $required ? '1' : '0' }}"
                                                        data-question-max-selections="{{ data_get($question, 'max_selections') }}"
                                                        data-question-min-selections="{{ data_get($question, 'min_selections') }}">
                                                        <div class="question-top">
                                                            <div class="question-label-row">
                                                                <div class="question-label">
                                                                    {{ $question['label'] ?? ('Question ' . ($questionIndex + 1)) }}
                                                                    @if ($required)
                                                                        <span class="required">*</span>
                                                                    @endif
                                                                </div>
                                                                <span class="question-tag">{{ $questionTag }}</span>
                                                            </div>

                                                            @if (!empty($question['hint']))
                                                                <div class="question-hint">{{ $question['hint'] }}</div>
                                                            @endif

                                                            @if ($questionNote)
                                                                <div class="question-note">{{ $questionNote }}</div>
                                                            @endif
                                                        </div>

                                                        <div class="question-stack">
                                                            @if ($type === 'textarea')
                                                                <textarea name="answers[{{ $questionKey }}]" {{ $required ? 'required' : '' }} placeholder="Type your response here">{{ is_scalar($oldValue) ? $oldValue : '' }}</textarea>
                                                            @elseif ($type === 'select')
                                                                <select name="answers[{{ $questionKey }}]" {{ $required ? 'required' : '' }}>
                                                                    <option value="">Select an option</option>
                                                                    @foreach ($options as $option)
                                                                        <option value="{{ $option }}" @selected((string) $oldValue === (string) $option)>{{ $option }}</option>
                                                                    @endforeach
                                                                </select>
                                                            @elseif ($type === 'multiselect')
                                                                @php
                                                                    $oldChoices = is_array($oldValue) ? $oldValue : [];
                                                                @endphp
                                                                <div class="choice-list {{ count($options) > 4 ? '' : 'choice-list--split' }}" data-multiselect-group="{{ $questionKey }}">
                                                                    @foreach ($options as $option)
                                                                        <label class="choice-item" data-choice-item>
                                                                            <input type="checkbox" name="answers[{{ $questionKey }}][]" value="{{ $option }}"
                                                                                @checked(in_array($option, $oldChoices, true))>
                                                                            <span class="choice-item__body">
                                                                                <strong>{{ $option }}</strong>
                                                                                <span>Select this response</span>
                                                                            </span>
                                                                        </label>
                                                                    @endforeach
                                                                </div>
                                                            @elseif ($type === 'radio')
                                                                <div class="choice-list {{ count($options) > 4 ? '' : 'choice-list--split' }}">
                                                                    @foreach ($options as $option)
                                                                        <label class="choice-item" data-choice-item>
                                                                            <input type="radio" name="answers[{{ $questionKey }}]" value="{{ $option }}"
                                                                                {{ $required ? 'required' : '' }}
                                                                                @checked((string) $oldValue === (string) $option)>
                                                                            <span class="choice-item__body">
                                                                                <strong>{{ $option }}</strong>
                                                                                <span>Select one option</span>
                                                                            </span>
                                                                        </label>
                                                                    @endforeach
                                                                </div>
                                                            @elseif ($type === 'checkbox')
                                                                @php
                                                                    $oldChoices = is_array($oldValue) ? $oldValue : [];
                                                                @endphp
                                                                <div class="choice-list {{ count($options) > 4 ? '' : 'choice-list--split' }}" data-checkbox-group="{{ $questionKey }}">
                                                                    @foreach ($options as $option)
                                                                        <label class="choice-item" data-choice-item>
                                                                            <input type="checkbox" name="answers[{{ $questionKey }}][]" value="{{ $option }}"
                                                                                @checked(in_array($option, $oldChoices, true))>
                                                                            <span class="choice-item__body">
                                                                                <strong>{{ $option }}</strong>
                                                                                <span>Select all that apply</span>
                                                                            </span>
                                                                        </label>
                                                                    @endforeach
                                                                </div>
                                                            @elseif ($type === 'scale')
                                                                <div class="scale-grid">
                                                                    @for ($value = $scaleMin; $value <= $scaleMax; $value++)
                                                                        <label class="choice-item scale-item" data-choice-item>
                                                                            <input type="radio" name="answers[{{ $questionKey }}]" value="{{ $value }}"
                                                                                {{ $required ? 'required' : '' }}
                                                                                @checked((string) $oldValue === (string) $value)>
                                                                            <strong>{{ $value }}</strong>
                                                                            <span>Rating</span>
                                                                        </label>
                                                                    @endfor
                                                                </div>

                                                                @if (data_get($question, 'scale.min_label') || data_get($question, 'scale.max_label'))
                                                                    <div class="scale-labels">
                                                                        <span>{{ data_get($question, 'scale.min_label') }}</span>
                                                                        <span>{{ data_get($question, 'scale.max_label') }}</span>
                                                                    </div>
                                                                @endif
                                                            @elseif ($type === 'slider')
                                                                <div class="slider-panel">
                                                                    <div class="slider-head">
                                                                        <div class="slider-output">
                                                                            <span>Selected value</span>
                                                                            <strong data-slider-value="{{ $questionKey }}">{{ $sliderValue }}</strong>
                                                                        </div>
                                                                        <div class="file-caption">Adjust to the most appropriate point on the scale.</div>
                                                                    </div>

                                                                    <input type="range"
                                                                        min="{{ $scaleMin }}"
                                                                        max="{{ $scaleMax }}"
                                                                        step="{{ $scaleStep }}"
                                                                        name="answers[{{ $questionKey }}]"
                                                                        value="{{ $sliderValue }}"
                                                                        data-slider-input="{{ $questionKey }}">

                                                                    <div class="scale-labels">
                                                                        <span>{{ data_get($question, 'scale.min_label') ?: $scaleMin }}</span>
                                                                        <span>{{ data_get($question, 'scale.max_label') ?: $scaleMax }}</span>
                                                                    </div>
                                                                </div>
                                                            @elseif ($type === 'file')
                                                                <input type="file" name="answers[{{ $questionKey }}]" {{ $required ? 'required' : '' }} data-file-input="{{ $questionKey }}">
                                                                <div class="file-caption" data-file-caption="{{ $questionKey }}">No file selected yet.</div>
                                                            @elseif ($type === 'url')
                                                                <input type="url"
                                                                    name="answers[{{ $questionKey }}]"
                                                                    value="{{ is_scalar($oldValue) ? $oldValue : '' }}"
                                                                    placeholder="https://example.org/reference"
                                                                    {{ $required ? 'required' : '' }}>
                                                            @elseif ($type === 'matrix')
                                                                @php
                                                                    $oldMatrix = is_array($oldValue) ? $oldValue : [];
                                                                @endphp
                                                                <div class="matrix-wrap" data-matrix-group="{{ $questionKey }}">
                                                                    <table class="matrix-table">
                                                                        <thead>
                                                                            <tr>
                                                                                <th>Item</th>
                                                                                @foreach ($matrixColumns as $column)
                                                                                    <th>{{ data_get($column, 'label', $column) }}</th>
                                                                                @endforeach
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody>
                                                                            @foreach ($matrixRows as $row)
                                                                                @php
                                                                                    $rowKey = (string) data_get($row, 'key', 'row_' . $loop->index);
                                                                                    $selectedColumn = $oldMatrix[$rowKey] ?? null;
                                                                                @endphp
                                                                                <tr>
                                                                                    <td>{{ data_get($row, 'label', $row) }}</td>
                                                                                    @foreach ($matrixColumns as $column)
                                                                                        @php
                                                                                            $columnKey = (string) data_get($column, 'key', 'column_' . $loop->index);
                                                                                            $columnValue = (string) data_get($column, 'label', $columnKey);
                                                                                        @endphp
                                                                                        <td data-column-label="{{ $columnValue }}">
                                                                                            <input type="radio"
                                                                                                name="answers[{{ $questionKey }}][{{ $rowKey }}]"
                                                                                                value="{{ $columnValue }}"
                                                                                                @checked((string) $selectedColumn === (string) $columnValue)>
                                                                                        </td>
                                                                                    @endforeach
                                                                                </tr>
                                                                            @endforeach
                                                                        </tbody>
                                                                    </table>
                                                                </div>
                                                            @else
                                                                <input type="{{ match ($type) {
                                                                    'number', 'email', 'date' => $type,
                                                                    'datetime' => 'datetime-local',
                                                                    default => 'text',
                                                                } }}"
                                                                    name="answers[{{ $questionKey }}]"
                                                                    value="{{ is_scalar($oldValue) ? $oldValue : '' }}"
                                                                    {{ $required ? 'required' : '' }}
                                                                    placeholder="{{ match ($type) {
                                                                        'email' => 'name@example.org',
                                                                        'number' => 'Enter a numeric response',
                                                                        'date' => 'Select a date',
                                                                        'datetime' => 'Select date and time',
                                                                        default => 'Type your response',
                                                                    } }}">
                                                            @endif
                                                        </div>

                                                        @error('answers.' . $questionKey)
                                                            <div class="question-error">{{ $message }}</div>
                                                        @enderror
                                                    </div>
                                                @endforeach
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
                let currentVisibleStepIndex = 0;

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

                function visibleSteps() {
                    return allSteps.filter((step) => !step.hidden);
                }

                function visibleSections() {
                    return visibleSteps().filter((step) => step.getAttribute('data-step-kind') === 'section');
                }

                function answerForQuestion(questionKey) {
                    const questionBlock = form.querySelector(`[data-question-key="${questionKey}"]`);
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

                function currentStep() {
                    return visibleSteps()[currentVisibleStepIndex] || visibleSteps()[0] || null;
                }

                function countVisibleQuestions(step) {
                    if (!step || step.getAttribute('data-step-kind') !== 'section') {
                        return 0;
                    }

                    return step.querySelectorAll('.question-block:not([hidden])').length;
                }

                function countAnsweredQuestions(step) {
                    if (!step || step.getAttribute('data-step-kind') !== 'section') {
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
                        const min = Number(input.min || 0);
                        const max = Number(input.max || 100);
                        const value = Number(input.value || min);
                        const percent = max > min ? ((value - min) / (max - min)) * 100 : 0;

                        input.style.setProperty('--slider-percent', `${percent}%`);

                        if (output) {
                            output.textContent = input.value;
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

                function syncStepNavigation() {
                    const steps = visibleSteps();

                    navItems.forEach((item) => {
                        const targetStep = stepById(item.getAttribute('data-step-nav'));
                        if (!targetStep || targetStep.hidden) {
                            item.hidden = true;
                            return;
                        }

                        const stepIndex = steps.indexOf(targetStep);
                        const isCurrent = stepIndex === currentVisibleStepIndex;
                        const isComplete = stepIndex < currentVisibleStepIndex;
                        const isAvailable = stepIndex <= currentVisibleStepIndex;
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
                            statusNode.textContent = isCurrent ? 'Current' : (isComplete ? 'Completed' : 'Upcoming');
                        }
                    });
                }

                function updateProgress() {
                    const steps = visibleSteps();
                    const activeStep = currentStep();
                    const totalSections = visibleSections().length;

                    if (!activeStep) {
                        return;
                    }

                    const activeKind = activeStep.getAttribute('data-step-kind');
                    const activeSectionIndex = steps
                        .slice(0, currentVisibleStepIndex + 1)
                        .filter((step) => step.getAttribute('data-step-kind') === 'section')
                        .length;
                    const numerator = activeKind === 'intro' ? 0 : activeSectionIndex;
                    const denominator = Math.max(totalSections, 1);
                    const percent = Math.max(0, Math.min(100, Math.round((numerator / denominator) * 100)));
                    const isLastStep = currentVisibleStepIndex >= steps.length - 1;
                    const questionCount = countVisibleQuestions(activeStep);
                    const answeredCount = countAnsweredQuestions(activeStep);

                    progressLabel.textContent = activeStep.getAttribute('data-step-label') || 'Survey';
                    progressMeta.textContent = activeKind === 'intro'
                        ? `Step 0 of ${totalSections}`
                        : `Step ${activeSectionIndex} of ${totalSections}`;
                    progressFill.style.width = `${percent}%`;
                    progressPercent.textContent = `${percent}% complete`;
                    progressDescriptor.textContent = activeKind === 'intro'
                        ? 'Review and continue'
                        : `${answeredCount} of ${questionCount} answered in this section`;

                    railSectionCount.textContent = totalSections;
                    railCurrentStep.textContent = activeStep.getAttribute('data-step-label') || 'Survey';
                    railQuestionCount.textContent = activeKind === 'intro'
                        ? 'Respondent profile'
                        : `${questionCount} visible question${questionCount === 1 ? '' : 's'}`;
                    railAnsweredCount.textContent = activeKind === 'intro'
                        ? `${countCompletedRespondentFields()} of ${respondentFields.length} profile fields filled`
                        : `${answeredCount} of ${questionCount} answered`;

                    actionStepTitle.textContent = activeStep.getAttribute('data-step-label') || 'Survey';
                    actionStepMeta.textContent = activeKind === 'intro'
                        ? 'Review the introduction and continue when ready.'
                        : (isLastStep ? 'Review this section, then submit your survey.' : 'Complete this section before moving forward.');

                    backButton.hidden = currentVisibleStepIndex === 0;
                    nextButton.hidden = isLastStep;
                    submitButton.hidden = !isLastStep;
                    nextButton.textContent = activeKind === 'intro' ? 'Start survey' : 'Next section';
                }

                function updateActiveStepClasses() {
                    const steps = visibleSteps();
                    const safeIndex = Math.max(0, Math.min(currentVisibleStepIndex, steps.length - 1));
                    currentVisibleStepIndex = safeIndex;

                    allSteps.forEach((step) => step.classList.remove('is-active'));
                    if (steps[safeIndex]) {
                        steps[safeIndex].classList.add('is-active');
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

                function setCurrentStep(index, options = {}) {
                    const steps = visibleSteps();
                    if (steps.length === 0) {
                        return;
                    }

                    currentVisibleStepIndex = Math.max(0, Math.min(index, steps.length - 1));
                    updateActiveStepClasses();

                    if (options.scroll) {
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
                    const activeId = stepId(currentStep());

                    allSteps.forEach((step) => {
                        if (step.getAttribute('data-step-kind') !== 'section') {
                            return;
                        }

                        const sectionVisible = matchesVisibility(parseJsonAttribute(step, 'data-section-visibility'));
                        let visibleQuestionCount = 0;

                        step.querySelectorAll('.question-block').forEach((questionBlock) => {
                            const questionVisible = sectionVisible && matchesVisibility(parseJsonAttribute(questionBlock, 'data-question-visibility'));
                            questionBlock.hidden = !questionVisible;
                            setInputsDisabled(questionBlock, !questionVisible);

                            if (questionVisible) {
                                visibleQuestionCount += 1;
                            }
                        });

                        step.hidden = !sectionVisible || visibleQuestionCount === 0;
                    });

                    const steps = visibleSteps();
                    const sameStepIndex = steps.findIndex((step) => stepId(step) === activeId);
                    currentVisibleStepIndex = sameStepIndex >= 0
                        ? sameStepIndex
                        : Math.max(0, Math.min(currentVisibleStepIndex, steps.length - 1));

                    updateActiveStepClasses();
                }

                function handleLiveUpdates(event) {
                    const questionBlock = event.target.closest('.question-block');
                    if (questionBlock) {
                        questionBlock.classList.remove('is-invalid');
                        questionBlock.querySelectorAll('.question-error[data-client-error="1"]').forEach((item) => item.remove());
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

                    setCurrentStep(currentVisibleStepIndex + 1, { scroll: true });
                });

                backButton.addEventListener('click', () => {
                    setCurrentStep(currentVisibleStepIndex - 1, { scroll: true });
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
                        const steps = visibleSteps();
                        const targetIndex = steps.indexOf(targetStep);

                        if (targetIndex < 0 || targetIndex > currentVisibleStepIndex) {
                            return;
                        }

                        setCurrentStep(targetIndex, { scroll: true });
                    });
                });

                updateSelectionStates();
                updateSliderDisplays();
                updateFileCaptions();
                refreshVisibility();

                const firstServerError = form.querySelector('.question-error, .field-error');
                if (firstServerError) {
                    const parentStep = firstServerError.closest('.step');
                    if (parentStep) {
                        const steps = visibleSteps();
                        const targetIndex = steps.indexOf(parentStep);
                        if (targetIndex >= 0) {
                            setCurrentStep(targetIndex, { scroll: true });
                        }
                    }
                }
            });
        </script>
    @endif
</body>

</html>
