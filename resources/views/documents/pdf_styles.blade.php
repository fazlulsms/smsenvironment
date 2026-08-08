<style>
    @page { margin: 60px 34px 38px; }
    body { font-family: DejaVu Sans, sans-serif; color: #1f2933; font-size: 10.5px; line-height: 1.42; }
    .header { border-bottom: 3px solid #1f6f4a; padding-bottom: 10px; margin-bottom: 18px; }
    .brand-line { display: table; width: 100%; }
    .logo-cell { display: table-cell; width: 56px; vertical-align: top; }
    .brand-cell { display: table-cell; vertical-align: top; }
    .logo { max-width: 46px; max-height: 46px; }
    .brand { font-size: 21px; font-weight: 700; color: #1f6f4a; }
    .tagline { color: #5f6f67; }
    .doc-title { text-align: right; font-size: 18px; font-weight: 700; color: #1f2933; }
    .meta { text-align: right; color: #4f5f58; }
    .row { width: 100%; }
    .row:after { content: ""; display: table; clear: both; }
    .col { float: left; width: 50%; }
    .label { color: #667085; font-size: 9px; text-transform: uppercase; font-weight: 700; margin-bottom: 3px; }
    table { width: 100%; border-collapse: collapse; margin-top: 12px; page-break-inside: auto; }
    tr { page-break-inside: avoid; page-break-after: auto; }
    th { background: #eef6f1; color: #1f4f38; text-align: left; border-bottom: 1px solid #b9d4c5; padding: 6px; }
    td { border-bottom: 1px solid #e4ebe7; padding: 6px; vertical-align: top; }
    .text-right { text-align: right; }
    .totals { width: 42%; margin-left: auto; margin-top: 12px; }
    .totals td { border: 0; padding: 4px 7px; }
    .grand td { border-top: 2px solid #1f6f4a; font-weight: 700; font-size: 12px; }
    .section { margin-top: 12px; }
    .scope-label { margin-top: 5px; color: #667085; font-size: 9px; font-weight: 700; }
    .scope-list { margin: 3px 0 0 13px; padding: 0; }
    .scope-list li { margin: 1px 0; }
    .signature { margin-top: 14px; page-break-inside: avoid; }
    .footer { position: fixed; left: 0; right: 0; bottom: -16px; border-top: 1px solid #d8e2dc; padding-top: 6px; color: #667085; font-size: 8.7px; }
    .quotation-proposal { font-size: 10.2px; line-height: 1.48; }
    .quotation-proposal .running-header { position: fixed; top: -45px; left: 0; right: 0; height: 38px; border-bottom: 2px solid #1f6f4a; padding-bottom: 5px; }
    .quotation-proposal .rh-left { float: left; width: 67%; }
    .quotation-proposal .rh-logo { float: left; width: 30px; max-height: 30px; margin-right: 8px; }
    .quotation-proposal .rh-brand { color: #1f6f4a; font-size: 14px; font-weight: 700; }
    .quotation-proposal .rh-tagline { color: #667085; font-size: 8px; }
    .quotation-proposal .rh-meta { float: right; width: 30%; text-align: right; color: #4f5f58; font-size: 8px; line-height: 1.3; }
    .quotation-proposal .proposal-page { page-break-after: always; }
    .quotation-proposal .proposal-page:last-child { page-break-after: auto; }
    .quotation-proposal .letter-page { min-height: 890px; }
    .quotation-proposal .content-page { page-break-after: auto; }
    .quotation-proposal .document-kicker { color: #667085; font-size: 10px; font-weight: 700; text-transform: uppercase; margin-bottom: 7px; }
    .quotation-proposal h1 { color: #1f2933; font-size: 25px; margin: 0 0 14px; }
    .quotation-proposal h2 { color: #1f2933; font-size: 15px; margin: 0 0 10px; text-transform: uppercase; }
    .quotation-proposal h3 { color: #1f2933; font-size: 12px; margin: 0 0 6px; }
    .quotation-proposal p { margin: 7px 0; }
    .quotation-proposal .letter-meta { margin: 0 0 18px; border-collapse: collapse; }
    .quotation-proposal .letter-meta td { border: 0; padding: 0; }
    .quotation-proposal .letter-recipient { margin: 18px 0 14px; }
    .quotation-proposal .subject-box { background: #eef6f1; border-left: 4px solid #1f6f4a; padding: 9px 11px; margin: 14px 0 18px; page-break-inside: avoid; }
    .quotation-proposal .compact-list { margin: 5px 0 0 15px; padding: 0; }
    .quotation-proposal .compact-list li { margin: 2px 0; }
    .quotation-proposal .signature-block { margin-top: 22px; page-break-inside: avoid; }
    .quotation-proposal .signature-space { height: 34px; }
    .quotation-proposal .proposal-table { margin-top: 8px; page-break-inside: auto; }
    .quotation-proposal .proposal-table thead { display: table-header-group; }
    .quotation-proposal .proposal-table tr { page-break-inside: avoid; }
    .quotation-proposal .proposal-table th { font-size: 9.5px; }
    .quotation-proposal .proposal-table td { font-size: 9.8px; }
    .quotation-proposal .terms-section { page-break-before: auto; }
    .quotation-proposal .terms-section p { page-break-inside: avoid; margin-bottom: 8px; }
    .quotation-proposal .avoid-break { page-break-inside: avoid; }
    .quotation-proposal .acceptance-block { margin-top: 8px; }
    .quotation-proposal .acceptance-block h2 { margin-bottom: 5px; }
    .quotation-proposal .acceptance-block p { margin: 3px 0 4px; font-size: 9px; }
    .quotation-proposal .acceptance-table { width: 100%; margin-top: 4px; border-collapse: collapse; }
    .quotation-proposal .acceptance-table td { border-bottom: 1px solid #cfd8d3; padding: 3px 6px; height: 11px; }
    .quotation-proposal .acceptance-table td:nth-child(1),
    .quotation-proposal .acceptance-table td:nth-child(3) { width: 18%; font-weight: 700; color: #4f5f58; }
    .quotation-proposal .acceptance-ref { margin-top: 4px; font-size: 8.8px; color: #4f5f58; }
    .quotation-proposal .page-number:before { content: counter(page); }
</style>
