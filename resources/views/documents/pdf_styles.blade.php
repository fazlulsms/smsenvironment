<style>
    @page { margin: 32mm 19mm 23mm; }
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
    .footer { position: fixed; left: 0; right: 0; bottom: -17mm; height: 13mm; border-top: 1px solid #d8e2dc; padding-top: 3mm; color: #667085; font-size: 7.5px; }
    .quotation-proposal { font-size: 10.2px; line-height: 1.52; }
    .quotation-proposal .running-header { position: fixed; top: -24mm; left: 0; right: 0; height: 18mm; border-bottom: 2px solid #1f6f4a; padding-bottom: 2mm; }
    .quotation-proposal .rh-table { width: 100%; margin: 0; border-collapse: collapse; }
    .quotation-proposal .rh-table td { border: 0; padding: 0; vertical-align: middle; }
    .quotation-proposal .rh-logo-cell { width: 18mm; }
    .quotation-proposal .rh-logo { width: 16mm; height: auto; max-height: 16mm; }
    .quotation-proposal .rh-brand-cell { padding-left: 3mm; }
    .quotation-proposal .rh-brand { color: #1f6f4a; font-size: 18px; font-weight: 700; line-height: 1.05; }
    .quotation-proposal .rh-tagline { color: #667085; font-size: 8.6px; margin-top: 2px; }
    .quotation-proposal .rh-title-cell { width: 34mm; text-align: right; color: #1f2933; font-size: 16px; font-weight: 700; letter-spacing: 0; }
    .footer-table { width: 100%; margin: 0; border-collapse: collapse; }
    .footer-table td { border: 0; padding: 0 0 2px; font-size: 7.5px; }
    .footer-contact { text-align: center; color: #78847e; font-size: 7.1px; }
    .quotation-proposal .proposal-page { page-break-after: auto; }
    .quotation-proposal .letter-page { page-break-after: always; }
    .quotation-proposal .letter-page { min-height: 221mm; }
    .quotation-proposal .document-kicker { color: #667085; font-size: 10px; font-weight: 700; text-transform: uppercase; margin-bottom: 7px; }
    .quotation-proposal h1 { color: #1f2933; font-size: 25px; margin: 0 0 14px; }
    .quotation-proposal h2 { color: #1f2933; font-size: 16px; margin: 0 0 12px; text-transform: uppercase; }
    .quotation-proposal h3 { color: #1f4f38; font-size: 11.5px; margin: 0 0 7px; text-transform: uppercase; }
    .quotation-proposal p { margin: 7px 0; }
    .quotation-proposal .letter-meta { margin: 0 0 18px; border-collapse: collapse; }
    .quotation-proposal .letter-meta td { border: 0; padding: 0; }
    .quotation-proposal .letter-recipient { margin: 18px 0 14px; }
    .quotation-proposal .subject-box { background: #eef6f1; border-left: 4px solid #1f6f4a; padding: 9px 11px; margin: 14px 0 18px; page-break-inside: avoid; }
    .quotation-proposal .compact-list { margin: 5px 0 0 15px; padding: 0; }
    .quotation-proposal .compact-list li { margin: 2px 0; }
    .quotation-proposal .signature-block { margin-top: 24px; page-break-inside: avoid; }
    .quotation-proposal .signature-space { height: 34px; }
    .quotation-proposal .scope-section { margin-top: 4px; }
    .quotation-proposal .scope-card { margin: 8px 0 13px; }
    .quotation-proposal .service-title { color: #1f2933; font-size: 11px; font-weight: 700; margin-bottom: 4px; }
    .quotation-proposal .financial-section { margin-top: 6px; }
    .quotation-proposal .proposal-table { margin-top: 7px; page-break-inside: auto; }
    .quotation-proposal .proposal-table thead { display: table-header-group; }
    .quotation-proposal .proposal-table tr { page-break-inside: avoid; }
    .quotation-proposal .proposal-table th { font-size: 8.8px; padding: 5px 6px; }
    .quotation-proposal .proposal-table td { font-size: 8.9px; padding: 5px 6px; }
    .quotation-proposal .service-summary { margin-top: 2px; line-height: 1.35; }
    .quotation-proposal .totals { width: 45%; margin-top: 10px; }
    .quotation-proposal .totals td { font-size: 9px; padding: 4px 6px; }
    .quotation-proposal .financial-verification-table { width: 100%; margin-top: 9px; border-collapse: collapse; page-break-inside: avoid; }
    .quotation-proposal .financial-verification-table td { border: 0; padding: 0; vertical-align: top; }
    .quotation-proposal .financial-summary-cell { width: 67%; padding-right: 8mm; }
    .quotation-proposal .financial-summary-cell .totals { width: 62%; margin-top: 0; margin-left: auto; }
    .quotation-proposal .financial-summary-cell .section { margin-top: 9px; }
    .quotation-proposal .verification-cell { width: 33%; text-align: right; }
    .quotation-proposal .verification-block { display: inline-block; width: 32mm; text-align: center; page-break-inside: avoid; }
    .quotation-proposal .verification-block h3 { margin-bottom: 3px; font-size: 8.2px; line-height: 1.08; }
    .quotation-proposal .verification-qr { width: 29mm; height: 29mm; display: block; margin: 0 auto 2px; background: #fff; }
    .quotation-proposal .verification-caption { color: #4f5f58; font-size: 7px; line-height: 1.15; margin: 0 auto 2px; }
    .quotation-proposal .verification-meta { color: #1f2933; font-size: 6.6px; line-height: 1.15; overflow-wrap: break-word; }
    .quotation-proposal .bank-table { width: 70%; margin-top: 5px; }
    .quotation-proposal .bank-table td { border-bottom: 1px solid #e4ebe7; padding: 4px 6px; font-size: 9px; }
    .quotation-proposal .bank-table td:first-child { width: 28%; color: #4f5f58; font-weight: 700; }
    .quotation-proposal .proposal-detail { margin-top: 9px; }
    .quotation-proposal .proposal-detail h3,
    .quotation-proposal .detail-col h3 { margin-bottom: 4px; }
    .quotation-proposal .two-column-details { display: table; width: 100%; margin-top: 8px; }
    .quotation-proposal .detail-col { display: table-cell; width: 50%; vertical-align: top; padding-right: 9px; }
    .quotation-proposal .detail-col + .detail-col { padding-right: 0; padding-left: 9px; }
    .quotation-proposal .document-section { margin-top: 7px; padding-top: 5px; border-top: 1px solid #d8e2dc; }
    .quotation-proposal .document-section h3 { margin-bottom: 4px; }
    .quotation-proposal .document-section p { margin: 0; }
    .quotation-proposal .document-section .compact-list li { margin: 1px 0; line-height: 1.28; }
    .quotation-proposal .terms-section { page-break-before: auto; }
    .quotation-proposal .terms-table { width: 100%; margin-top: 7px; border-collapse: collapse; }
    .quotation-proposal .terms-table td { width: 50%; border: 0; padding: 0 10px 5px 0; vertical-align: top; font-size: 8.8px; line-height: 1.34; page-break-inside: avoid; }
    .quotation-proposal .terms-table td + td { padding-right: 0; padding-left: 10px; }
    .quotation-proposal .avoid-break { page-break-inside: avoid; }
    .quotation-proposal .acceptance-block { margin-top: 8px; border: 1px solid #cfe0d7; padding: 7px 10px; page-break-inside: avoid; }
    .quotation-proposal .acceptance-block h2 { color: #1f4f38; margin-bottom: 7px; }
    .quotation-proposal .acceptance-block p { margin: 3px 0 8px; font-size: 9.2px; }
    .quotation-proposal .acceptance-table { width: 100%; margin-top: 6px; border-collapse: collapse; }
    .quotation-proposal .acceptance-table td { border-bottom: 1px solid #cfd8d3; padding: 5px 6px; height: 12px; }
    .quotation-proposal .acceptance-table td:nth-child(1),
    .quotation-proposal .acceptance-table td:nth-child(3) { width: 18%; font-weight: 700; color: #4f5f58; white-space: nowrap; }
    .quotation-proposal .acceptance-ref { margin-top: 7px; font-size: 8.8px; color: #4f5f58; }
    .quotation-proposal .page-number:before { content: counter(page); }
    .text-center { text-align: center; }
    .proforma-document { font-size: 9.2px; line-height: 1.35; }
    .proforma-document .rh-title-cell { width: 49mm; }
    .proforma-document .invoice-page { page-break-after: auto; }
    .proforma-document .invoice-meta { margin: 0 0 8px; border-collapse: collapse; }
    .proforma-document .invoice-meta td { border: 0; padding: 0; font-size: 9.4px; }
    .proforma-document .client-charge-table { margin: 0 0 8px; border-collapse: collapse; }
    .proforma-document .client-charge-table td { width: 50%; border: 0; padding: 0 10px 0 0; vertical-align: top; font-size: 9.1px; }
    .proforma-document .client-charge-table td + td { padding: 0 0 0 10px; }
    .proforma-document .section { margin-top: 7px; }
    .proforma-document .proposal-table { margin-top: 5px; }
    .proforma-document .proposal-table th { font-size: 8.1px; padding: 4px 5px; }
    .proforma-document .proposal-table td { font-size: 8.2px; padding: 4px 5px; line-height: 1.25; }
    .proforma-document .service-title { font-size: 9.4px; margin-bottom: 2px; }
    .proforma-document .scope-label { margin-top: 2px; font-size: 7.8px; }
    .proforma-document .scope-list { margin: 1px 0 0 12px; }
    .proforma-document .scope-list li { margin: 0; line-height: 1.2; }
    .proforma-document .financial-verification-table { margin-top: 7px; }
    .proforma-document .financial-summary-cell { width: 70%; padding-right: 7mm; }
    .proforma-document .financial-summary-cell .totals { width: 68%; margin-top: 0; margin-left: auto; }
    .proforma-document .financial-summary-cell .section { margin-top: 6px; font-size: 8.3px; line-height: 1.25; }
    .proforma-document .financial-summary-cell .totals td { font-size: 8.4px; padding: 3px 5px; }
    .proforma-document .financial-summary-cell .grand td { font-size: 9.2px; padding-top: 4px; }
    .proforma-document .verification-cell { width: 30%; }
    .proforma-document .verification-block { width: 30mm; }
    .proforma-document .verification-qr { width: 27mm; height: 27mm; }
    .proforma-document .tax-note { margin-top: 4px; color: #4f5f58; font-size: 7.8px; text-align: right; }
    .proforma-document .invoice-lower-table { margin-top: 8px; border-collapse: collapse; page-break-inside: avoid; }
    .proforma-document .invoice-lower-table td { border: 0; padding: 0; vertical-align: top; }
    .proforma-document .bank-cell { width: 58%; padding-right: 8mm; }
    .proforma-document .terms-cell { width: 42%; }
    .proforma-document h3 { margin: 0 0 4px; font-size: 9px; }
    .proforma-document .invoice-bank-table { width: 100%; margin-top: 0; }
    .proforma-document .invoice-bank-table td { border-bottom: 1px solid #e4ebe7; padding: 3px 5px; font-size: 8.2px; }
    .proforma-document .invoice-bank-table td:first-child { width: 29%; color: #4f5f58; font-weight: 700; }
    .proforma-document .invoice-terms { margin-top: 0; font-size: 8.1px; line-height: 1.25; }
    .proforma-document .invoice-terms li { margin: 0 0 2px; }
    .proforma-document .prepared-by { margin-top: 8px; font-size: 8.3px; line-height: 1.28; }
</style>
