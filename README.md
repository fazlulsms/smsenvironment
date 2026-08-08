# SMSEA Office

Internal Laravel application for SMS Environmental Alliance document preparation.

## Current Scope

- Client management with Smart Paste extraction
- Service library with reusable document wording
- Bank account setup with default account support
- Quotation and proforma invoice creation
- Automated document content, snapshots, numbering, amount in words, and PDF generation

## Setup

1. Copy `.env.example` to `.env`.
2. Configure database, mail, organization, optional AI, admin, and bank environment values.
3. Run `composer install`.
4. Run `php artisan key:generate`.
5. Run `php artisan migrate --seed`.
6. Run `php artisan serve`.

Seeders do not create demo admin or placeholder bank records unless real `SMSEA_*` environment values are provided.
