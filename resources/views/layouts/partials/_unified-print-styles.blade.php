{{-- Shared full-record printing for the admin and vendor workspaces. --}}
<style id="ba-unified-print-styles">
    #ba-smart-print-root { display: none; }
    .ba-print-progress {
        position: fixed; z-index: 100000; inset-inline-end: 22px; inset-block-end: 22px;
        min-width: 290px; max-width: calc(100vw - 32px); display: flex; align-items: center; gap: 13px;
        padding: 15px 17px; border: 1px solid #d9e4f2; border-radius: 15px;
        color: #172033; background: #fff; box-shadow: 0 16px 42px rgba(15, 23, 42, .18);
    }
    .ba-print-progress[hidden] { display: none !important; }
    .ba-print-progress__spinner {
        width: 32px; height: 32px; flex: 0 0 32px; border: 3px solid #dbeafe;
        border-top-color: #2563eb; border-radius: 50%; animation: ba-print-spin .75s linear infinite;
    }
    .ba-print-progress__title, .ba-print-progress__detail { display: block; }
    .ba-print-progress__title { margin-bottom: 3px; font-size: 13px; }
    .ba-print-progress__detail { color: #718096; font-size: 11px; }
    @keyframes ba-print-spin { to { transform: rotate(360deg); } }
    :root[data-ba-admin-theme="dark"] .ba-print-progress,
    html[data-vendor-theme="dark"] .ba-print-progress {
        color: #edf3ff; border-color: #344665; background: #172238;
        box-shadow: 0 18px 46px rgba(0, 0, 0, .38);
    }
    :root[data-ba-admin-theme="dark"] .ba-print-progress__detail,
    html[data-vendor-theme="dark"] .ba-print-progress__detail { color: #aebdd3; }

    @media print {
        @page { size: auto; margin: 12mm; }
        body.ba-smart-printing > *:not(#ba-smart-print-root) { display: none !important; }
        body.ba-smart-printing #ba-smart-print-root { display: block !important; }
        html, body { background: #fff !important; color: #111827 !important; color-scheme: light !important; }
        .navbar, .navbar-vertical, .sidebar, .header, .footer, .v2-header, .v2-sidebar,
        .v2-rail, .v2-ctxpanel, .v2-global-search, .loader-container, #loading,
        .toast-container, .swal2-container, .modal, .offcanvas, .no-print, .print-hide,
        [data-print-hidden], button, .btn, .btn-group, .pagination, .dropdown-menu { display: none !important; }
        main, .v2-main, .content, .content.container-fluid, .container-fluid, .table-responsive {
            width: 100% !important; max-width: none !important; margin: 0 !important;
            padding: 0 !important; overflow: visible !important; background: #fff !important;
        }
        .card, .card-header, .card-body, .card-footer, .table, .table > :not(caption) > * > * {
            background: #fff !important; color: #111827 !important; border-color: #cbd5e1 !important;
            box-shadow: none !important;
        }
        .card { break-inside: avoid; }
        #ba-smart-print-root { width: 100% !important; color: #111827 !important; background: #fff !important; }
        #ba-smart-print-root .card:has(table), #ba-smart-print-root .table-responsive,
        #ba-smart-print-root table, #ba-smart-print-root tbody { break-inside: auto !important; page-break-inside: auto !important; }
        .ba-print-report-header {
            display: flex !important; align-items: center; justify-content: space-between; gap: 18px;
            margin-bottom: 8mm; padding-bottom: 4mm; border-bottom: 2px solid #1f5fbf;
        }
        .ba-print-report-brand { display: flex; align-items: center; gap: 12px; }
        .ba-print-report-brand img { width: auto; max-width: 44mm !important; height: 13mm; object-fit: contain; }
        .ba-print-report-brand span, .ba-print-report-brand strong, .ba-print-report-brand small { display: block; }
        .ba-print-report-brand strong { color: #172033 !important; font-size: 16px; line-height: 1.3; }
        .ba-print-report-brand small, .ba-print-report-meta { color: #64748b !important; font-size: 9px; }
        .ba-print-report-meta { text-align: end; }
        .ba-print-field-value { display: inline-block; color: #111827 !important; }
        table { width: 100% !important; font-size: 10.5px !important; border-collapse: collapse !important; }
        thead { display: table-header-group; }
        tfoot { display: table-footer-group; }
        tr, img, svg { break-inside: avoid !important; page-break-inside: avoid !important; }
        th, td { padding: 6px 7px !important; vertical-align: middle !important; }
        #ba-smart-print-root .table-responsive { overflow: visible !important; }
        #ba-smart-print-root [class*="max-h-"], #ba-smart-print-root [style*="max-height"] { max-height: none !important; overflow: visible !important; }
        #ba-smart-print-root .collapse { display: block !important; height: auto !important; }
        #ba-smart-print-root .position-fixed, #ba-smart-print-root .sticky-top,
        #ba-smart-print-root [style*="position: fixed"] { position: static !important; inset: auto !important; }
        input, textarea, select, .form-control, .form-select, .input-group-text {
            display: inline-block !important; background: transparent !important; color: #111827 !important;
            border: 0 !important; box-shadow: none !important; padding: 0 !important; opacity: 1 !important;
        }
        textarea { resize: none !important; }
        a { color: inherit !important; text-decoration: none !important; }
        a[href]::after { content: none !important; }
    }
</style>
