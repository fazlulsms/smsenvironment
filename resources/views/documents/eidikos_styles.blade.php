@php
    // Eidikos brand palette — injected as literal hex (DomPDF has no CSS variables).
    $blue = $entity?->primary_color ?: '#1d4ed8';
    $green = $entity?->secondary_color ?: '#15803d';
    $grey = $entity?->accent_color ?: '#64748b';
    $ink = '#1f2937';
    $line = '#cbd5e1';
    $soft = '#f1f5f9';
@endphp
<style>
    @page { margin: 31mm 14mm 22mm; }
    body { font-family: DejaVu Sans, sans-serif; color: {{ $ink }}; font-size: 9.6px; line-height: 1.4; }

    /* ---- Running header (brand) ---- */
    .eidikos-document .e-header { position: fixed; top: -27mm; left: 0; right: 0; height: 25mm; }
    .eidikos-document .eh-table { width: 100%; border-collapse: collapse; margin: 0; }
    .eidikos-document .eh-table td { border: 0; padding: 0; vertical-align: middle; }
    .eidikos-document .eh-logo-cell { width: 20mm; }
    .eidikos-document .eh-logo { width: 18mm; height: auto; max-height: 18mm; }
    .eidikos-document .eh-mark { width: 16mm; height: 16mm; border: 2px solid {{ $blue }}; color: {{ $blue }};
        text-align: center; font-size: 15px; font-weight: 700; letter-spacing: 1px; line-height: 15mm; }
    .eidikos-document .eh-brand-cell { padding-left: 3mm; }
    .eidikos-document .eh-name { color: {{ $blue }}; font-size: 21px; font-weight: 700; letter-spacing: 2px; line-height: 1.05; }
    .eidikos-document .eh-name span { color: {{ $green }}; }
    .eidikos-document .eh-addr { color: {{ $grey }}; font-size: 8.1px; line-height: 1.32; margin-top: 2px; }
    .eidikos-document .e-header-rule { position: fixed; top: -2mm; left: 0; right: 0;
        border-top: 2.4px solid {{ $blue }}; }
    .eidikos-document .e-header-rule:after { content: ""; display: block; border-top: 1.2px solid {{ $green }}; margin-top: 1px; }

    /* ---- Document title ---- */
    .eidikos-document .e-title { text-align: center; margin: 1mm 0 3mm; }
    .eidikos-document .e-title h1 { margin: 0; color: {{ $ink }}; font-size: 15px; font-weight: 700;
        letter-spacing: 5px; text-transform: uppercase; }
    .eidikos-document .e-title .e-title-sub { margin-top: 1.5mm; height: 0; border-top: 1px solid {{ $line }}; }

    /* ---- Info block (reference | client) ---- */
    .eidikos-document .e-info { width: 100%; border-collapse: collapse; margin: 0 0 3mm;
        border: 1px solid {{ $line }}; }
    .eidikos-document .e-info > tbody > tr > td { border: 1px solid {{ $line }}; padding: 0; vertical-align: top; width: 50%; }
    .eidikos-document .e-info-h { background: {{ $blue }}; color: #fff; font-size: 8.2px; font-weight: 700;
        letter-spacing: 1.2px; text-transform: uppercase; padding: 2px 6px; }
    .eidikos-document .e-kv { width: 100%; border-collapse: collapse; }
    .eidikos-document .e-kv td { border: 0; border-bottom: 1px solid #e8edf3; padding: 2px 6px; font-size: 9px; vertical-align: top; }
    .eidikos-document .e-kv tr:last-child td { border-bottom: 0; }
    .eidikos-document .e-kv .e-k { width: 34%; color: {{ $grey }}; font-weight: 700; }
    .eidikos-document .e-kv .e-v { color: {{ $ink }}; }

    /* ---- Commercial table ---- */
    .eidikos-document .e-comm { width: 100%; border-collapse: collapse; margin: 0; border: 1px solid {{ $blue }}; }
    .eidikos-document .e-comm th { background: {{ $blue }}; color: #fff; font-size: 8.4px; font-weight: 700;
        letter-spacing: 0.6px; text-transform: uppercase; padding: 4px 7px; border: 1px solid {{ $blue }}; text-align: left; }
    .eidikos-document .e-comm td { border: 1px solid {{ $line }}; padding: 4px 7px; font-size: 9.2px; vertical-align: top; }
    .eidikos-document .e-c-sn { width: 9%; text-align: center; }
    .eidikos-document .e-c-amt { width: 26%; text-align: right; white-space: nowrap; }
    .eidikos-document .e-p-title { font-weight: 700; color: {{ $ink }}; }
    .eidikos-document .e-p-desc { color: #45505f; font-size: 8.7px; margin-top: 1px; }
    .eidikos-document .e-inc { color: {{ $grey }}; font-size: 8.4px; margin-top: 2px; }
    .eidikos-document .e-inc-list { margin: 1px 0 0 13px; padding: 0; }
    .eidikos-document .e-inc-list li { margin: 0.5px 0; font-size: 8.7px; }

    /* ---- Currency summary ---- */
    .eidikos-document .e-sum-wrap { width: 100%; border-collapse: collapse; margin: 2.5mm 0 0; }
    .eidikos-document .e-sum-wrap > tbody > tr > td { border: 0; padding: 0; vertical-align: top; }
    .eidikos-document .e-sum-spacer { width: 42%; }
    .eidikos-document .e-sum { width: 58%; border-collapse: collapse; border: 1px solid {{ $line }}; }
    .eidikos-document .e-sum td { border: 0; padding: 2px 8px; font-size: 9.2px; }
    .eidikos-document .e-sum .e-sum-l { color: {{ $grey }}; white-space: nowrap; }
    .eidikos-document .e-sum .e-sum-r { text-align: right; white-space: nowrap; color: {{ $ink }}; }
    .eidikos-document .e-sum .e-sum-total td { border-top: 1.6px solid {{ $blue }}; font-weight: 700; color: {{ $blue }}; font-size: 10.4px; }
    .eidikos-document .e-sum .e-sum-base td { border-top: 1px dashed {{ $line }}; }
    .eidikos-document .e-sum .e-sum-basetotal td { font-weight: 700; color: {{ $green }}; }
    .eidikos-document .e-rate { background: {{ $soft }}; color: {{ $ink }}; font-size: 8.6px; }
    .eidikos-document .e-words { margin: 2.5mm 0 0; font-size: 9.2px; }
    .eidikos-document .e-words strong { color: {{ $blue }}; }
    .eidikos-document .e-schedule { margin: 1.5mm 0 0; color: #45505f; font-size: 8.9px; font-style: italic; }

    /* ---- Section heading ---- */
    .eidikos-document .e-sec { margin: 3mm 0 1.5mm; }
    .eidikos-document .e-sec-h { color: {{ $blue }}; font-size: 10px; font-weight: 700; letter-spacing: 1.6px;
        text-transform: uppercase; border-bottom: 1.6px solid {{ $green }}; padding-bottom: 1.5px; }

    /* ---- Payment details (two columns) ---- */
    .eidikos-document .e-pay { width: 100%; border-collapse: collapse; margin: 0; border: 1px solid {{ $line }}; }
    .eidikos-document .e-pay > tbody > tr > td { border: 1px solid {{ $line }}; padding: 0; vertical-align: top; }
    .eidikos-document .e-pay-bank { width: 50%; }
    .eidikos-document .e-pay-terms { width: 50%; }
    .eidikos-document .e-col-h { background: {{ $soft }}; color: {{ $blue }}; font-size: 8.2px; font-weight: 700;
        letter-spacing: 1px; text-transform: uppercase; padding: 3px 7px; border-bottom: 1px solid {{ $line }}; }
    .eidikos-document .e-bank { width: 100%; border-collapse: collapse; }
    .eidikos-document .e-bank td { border: 0; border-bottom: 1px solid #eef2f6; padding: 2.6px 7px; font-size: 9px; vertical-align: top; }
    .eidikos-document .e-bank tr:last-child td { border-bottom: 0; }
    .eidikos-document .e-bank .e-k { width: 38%; color: {{ $grey }}; font-weight: 700; }
    .eidikos-document .e-terms-list { margin: 0; padding: 5px 7px 5px 20px; }
    .eidikos-document .e-terms-list li { margin: 0 0 2.5px; font-size: 8.7px; line-height: 1.3; }

    /* ---- Contact + note ---- */
    .eidikos-document .e-foot-row { width: 100%; border-collapse: collapse; margin: 3mm 0 0; }
    .eidikos-document .e-foot-row > tbody > tr > td { border: 0; padding: 0; vertical-align: bottom; }
    .eidikos-document .e-contact-h { color: {{ $blue }}; font-size: 9px; font-weight: 700; letter-spacing: 1.4px;
        text-transform: uppercase; margin-bottom: 1.5px; }
    .eidikos-document .e-contact-name { font-weight: 700; color: {{ $ink }}; font-size: 9.6px; }
    .eidikos-document .e-contact-line { color: #45505f; font-size: 8.7px; line-height: 1.35; }
    .eidikos-document .e-contact-org { color: {{ $blue }}; font-weight: 700; letter-spacing: 1px; }
    .eidikos-document .e-note { text-align: right; color: {{ $grey }}; font-size: 8.4px; font-style: italic; }
    .eidikos-document .e-note strong { color: {{ $ink }}; font-style: normal; }

    /* ---- Fixed footer (no QR / no verification) ---- */
    .eidikos-document .e-footer { position: fixed; left: 0; right: 0; bottom: -18mm; height: 16mm;
        border-top: 2px solid {{ $blue }}; padding-top: 2mm; text-align: center; }
    .eidikos-document .e-footer .ef-name { color: {{ $blue }}; font-size: 9.4px; font-weight: 700; letter-spacing: 2px; }
    .eidikos-document .e-footer .ef-name span { color: {{ $green }}; }
    .eidikos-document .e-footer .ef-line { color: {{ $grey }}; font-size: 7.9px; line-height: 1.34; margin-top: 1px; }
</style>
