<style>
    .management-report { color: #253d47; font-family: DejaVu Sans, Arial, sans-serif; font-size: 8px; line-height: 1.5; }
    .management-report h2, .management-report h3, .management-report h4, .management-report p { margin: 0; }
    .management-report .mg-section { margin: 0; page-break-inside: auto; }
    .management-report .mg-section + .mg-section { page-break-before: always; }
    .management-report .mg-section-heading { padding: 0 0 8px; margin: 0 0 10px; border-bottom: 2px solid #0f766e; page-break-after: avoid; }
    .management-report .mg-section-number { color: #0f766e; font-size: 7px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; }
    .management-report .mg-section-heading h2 { color: #163844; font-size: 16px; line-height: 1.3; margin-top: 3px; }
    .management-report .mg-section-heading p { color: #627a83; font-size: 8px; margin-top: 4px; }
    .management-report .mg-heading { color: #204b54; font-size: 11px; padding-top: 8px; margin: 10px 0 6px; page-break-after: avoid; }
    .management-report .mg-note { padding: 9px 11px; margin: 8px 0 10px; background: #f0f6f7; border-left: 3px solid #509394; color: #4d6771; page-break-inside: auto; }
    .management-report .mg-pending { background: #fff7e6; border-left-color: #b98930; color: #795a25; }
    .management-report .mg-empty { padding: 11px; border: 1px dashed #b9cbd0; background: #f8fafb; color: #637b84; margin: 7px 0 11px; }
    .management-report table { width: 100%; table-layout: fixed; border-collapse: collapse; page-break-inside: auto; margin: 0 0 10px; font-size: 8px; }
    .management-report thead { display: table-header-group; }
    .management-report tfoot { display: table-footer-group; }
    .management-report th, .management-report td { padding: 6px 7px; border: 1px solid #d4e0e4; vertical-align: top; word-wrap: break-word; overflow-wrap: break-word; }
    .management-report th { background: #eaf2f3; color: #335763; font-size: 6.8px; line-height: 1.35; text-transform: uppercase; letter-spacing: .25px; text-align: left; }
    .management-report tbody tr { page-break-inside: avoid; }
    .management-report tbody tr:nth-child(even) td { background: #fafcfc; }
    .management-report .mg-metrics td { background: #f7faf9; border-top: 3px solid #0f766e; padding: 10px 9px; }
    .management-report .mg-metric-label { color: #607d80; display: block; font-size: 7px; }
    .management-report .mg-metric-value { color: #123f47; display: block; font-size: 20px; font-weight: bold; margin-top: 3px; line-height: 1.2; }
    .management-report .mg-small { color: #617981; font-size: 7px; }
    .management-report .mg-subline { display: block; color: #637d84; font-size: 7px; margin-top: 2px; }
    .management-report .mg-center { text-align: center; }
    .management-report .mg-numeric { text-align: right; }
    .management-report .mg-score { color: #075e55; font-weight: bold; }
    .management-report .mg-status { color: #395965; font-size: 7px; font-weight: bold; }
    .management-report .mg-label { color: #57727a; font-size: 7px; font-weight: bold; }
    .management-report .mg-chart-grid { margin: 7px 0 12px; }
    .management-report .mg-chart-grid td { background: #ffffff; padding: 9px; width: 50%; }
    .management-report .mg-chart-grid tr { page-break-inside: avoid; }
    .management-report .mg-chart-title { color: #214753; font-size: 9px; font-weight: bold; margin-bottom: 6px; }
    .management-report .mg-chart-image { display: block; width: auto; max-width: 100%; max-height: 320pt; height: auto; margin: 4px auto; }
    .management-report .mg-chart-note { color: #667e86; font-size: 7px; margin-top: 5px; }
    .management-report .mg-detail-heading { background: #eaf3f2; border-left: 4px solid #0f766e; padding: 8px 10px; margin: 14px 0 5px; page-break-after: avoid; }
    .management-report .mg-detail-heading h4 { font-size: 10px; color: #1b4b50; }
    .management-report .mg-detail-heading p { color: #5d757b; font-size: 7px; margin-top: 3px; }
    .management-report .mg-long-text { white-space: pre-wrap; word-wrap: break-word; overflow-wrap: break-word; margin: 4px 0 9px; page-break-inside: auto; }
    .management-report .mg-comment-label { color: #3d646c; font-weight: bold; margin-top: 7px; page-break-after: avoid; }
    .management-report .mg-insight { border-left: 3px solid #549396; padding: 8px 11px; background: #f2f7f8; margin: 0 0 9px; page-break-inside: auto; }
    .management-report .mg-insight h4 { color: #214d57; font-size: 9px; margin-bottom: 4px; page-break-after: avoid; }
    .management-report .mg-insight-warning { border-left-color: #b88931; background: #fff8e9; }
    .management-report .mg-insight-success { border-left-color: #2e8464; background: #eef8f2; }
    .management-report .mg-list { margin: 4px 0 10px; padding-left: 18px; }
    .management-report .mg-list li { margin-bottom: 5px; padding-left: 2px; }
    .management-report .mg-audit { font-size: 7px; }
    .management-report .mg-audit th { font-size: 6.4px; }
    .management-report .mg-audit-id { color: #647b83; font-size: 6.1px; word-wrap: break-word; }
</style>
