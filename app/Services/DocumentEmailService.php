<?php

namespace App\Services;

use App\Mail\DocumentMail;
use App\Models\DocumentEmailDelivery;
use App\Models\EmailAccount;
use App\Models\ProformaInvoice;
use App\Models\Quotation;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class DocumentEmailService
{
    public function __construct(private DocumentPdfService $pdfs) {}

    public function draft(string $documentType, Model $document): array
    {
        $document->loadMissing('client', 'items.service');
        $settings = Setting::current();
        $client = $document->client_snapshot ?: $document->client?->toArray() ?: [];
        $placeholders = $this->placeholders($documentType, $document, $settings, $client);

        return [
            'to' => $client['email'] ?? '',
            'cc' => $settings->default_email_cc ?? '',
            'subject' => $this->renderTemplate($this->subjectTemplate($documentType, $settings), $placeholders),
            'message' => $this->renderTemplate($this->bodyTemplate($documentType, $settings), $placeholders),
            'attachment_filename' => $this->filename($documentType, $document),
        ];
    }

    public function send(string $documentType, Model $document, array $data, User $sender): DocumentEmailDelivery
    {
        $ccEmails = $this->parseCc($data['cc'] ?? null);
        $account = $this->entityEmailAccount($document);
        $delivery = DocumentEmailDelivery::query()->create([
            'business_entity_id' => $document->business_entity_id,
            'document_type' => $documentType,
            'document_id' => $document->id,
            'to_email' => $data['to'],
            'cc_emails' => $ccEmails,
            'subject' => $data['subject'],
            'body_snapshot' => $data['message'],
            'sent_by' => $sender->id,
            'status' => 'failed',
        ]);

        try {
            [$pdf, $filename] = $this->attachment($documentType, $document);

            $this->mailerFor($account)
                ->to($data['to'])
                ->cc($ccEmails)
                ->send(new DocumentMail(
                    subjectLine: $data['subject'],
                    bodyText: $data['message'],
                    attachmentData: $pdf->output(),
                    attachmentFilename: $filename,
                    fromAddress: $account?->from_address,
                    fromName: $account?->from_name,
                    replyToAddress: $account?->reply_to,
                ));

            $delivery->update([
                'status' => 'sent',
                'sent_at' => now(),
                'error_summary' => null,
            ]);
        } catch (Throwable $exception) {
            $delivery->update([
                'status' => 'failed',
                'error_summary' => Str::limit($exception->getMessage(), 500),
            ]);

            throw $exception;
        }

        return $delivery->fresh();
    }

    public function parseCc(?string $cc): array
    {
        if (! filled($cc)) {
            return [];
        }

        return collect(preg_split('/[,;\r\n]+/', $cc) ?: [])
            ->map(fn ($email) => trim($email))
            ->filter()
            ->values()
            ->all();
    }

    public function invalidCcEmails(?string $cc): array
    {
        return collect($this->parseCc($cc))
            ->reject(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->values()
            ->all();
    }

    /** The entity's default active outgoing email account, if configured. */
    private function entityEmailAccount(Model $document): ?EmailAccount
    {
        if (blank($document->business_entity_id)) {
            return null;
        }

        return EmailAccount::query()
            ->where('business_entity_id', $document->business_entity_id)
            ->where('active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get()
            ->first(fn (EmailAccount $account) => $account->mailerConfig() !== null);
    }

    /**
     * Return a mailer configured for the entity account, or the application's
     * default mailer when the entity has no usable email account configured.
     */
    private function mailerFor(?EmailAccount $account)
    {
        $config = $account?->mailerConfig();

        if ($config === null) {
            return Mail::mailer();
        }

        Config::set('mail.mailers.entity_runtime', $config);

        return Mail::mailer('entity_runtime');
    }

    private function attachment(string $documentType, Model $document): array
    {
        if ($documentType === 'quotation' && $document instanceof Quotation) {
            return [$this->pdfs->quotationPdf($document), $this->pdfs->quotationFilename($document)];
        }

        if ($documentType === 'proforma_invoice' && $document instanceof ProformaInvoice) {
            return [$this->pdfs->proformaInvoicePdf($document), $this->pdfs->proformaInvoiceFilename($document)];
        }

        throw new \InvalidArgumentException('Unsupported document type.');
    }

    private function filename(string $documentType, Model $document): string
    {
        if ($documentType === 'quotation' && $document instanceof Quotation) {
            return $this->pdfs->quotationFilename($document);
        }

        if ($documentType === 'proforma_invoice' && $document instanceof ProformaInvoice) {
            return $this->pdfs->proformaInvoiceFilename($document);
        }

        throw new \InvalidArgumentException('Unsupported document type.');
    }

    private function placeholders(string $documentType, Model $document, Setting $settings, array $client): array
    {
        $serviceName = $this->serviceName($documentType, $document);
        $contact = trim((string) ($client['contact_person'] ?? ''));

        return [
            'entity_name' => $settings->organization_name ?: 'SMS Environmental Alliance',
            'client_name' => $client['company_name'] ?? 'Client',
            'contact_person' => $contact !== '' ? $contact : 'Sir / Madam',
            'service_name' => $serviceName,
            'document_number' => $document->number,
            'prepared_by' => $settings->prepared_by_name ?: 'Authorized Representative',
            'designation' => $settings->prepared_by_designation ?: '',
            'phone' => $settings->phone ?: '',
            'email' => $settings->email ?: '',
            'website' => $settings->website ?: '',
        ];
    }

    private function serviceName(string $documentType, Model $document): string
    {
        if ($documentType === 'proforma_invoice' && filled($document->charge_for)) {
            return $document->charge_for;
        }

        if ($documentType === 'quotation' && filled($document->subject)) {
            $subject = preg_replace('/^quotation for\s+/i', '', (string) $document->subject) ?: '';
            $subject = preg_replace('/\s+(of|-)\s+'.preg_quote($document->client_snapshot['company_name'] ?? '', '/').'$/i', '', $subject) ?: $subject;
            if (filled($subject)) {
                return trim($subject);
            }
        }

        $names = $document->items
            ->map(fn ($item) => $item->service?->short_name ?: $item->service?->name ?: $item->description)
            ->filter()
            ->unique()
            ->values();

        return $names->count() === 1 ? $names->first() : 'Environmental Services';
    }

    private function subjectTemplate(string $documentType, Setting $settings): string
    {
        if ($documentType === 'quotation') {
            return $settings->quotation_email_subject_template ?: 'Quotation for {{service_name}} - {{client_name}}';
        }

        return $settings->proforma_invoice_email_subject_template ?: 'Proforma Invoice for {{service_name}} - {{client_name}}';
    }

    private function bodyTemplate(string $documentType, Setting $settings): string
    {
        if ($documentType === 'quotation') {
            return $settings->quotation_email_body_template ?: "Dear {{contact_person}},\n\nGreetings from {{entity_name}}.\n\nPlease find attached our quotation for {{service_name}} for your review and consideration.\n\nShould you require any clarification or further information regarding the scope, commercial terms or proposed service, please feel free to contact us.\n\nWe look forward to supporting your environmental assessment and compliance requirements.\n\nBest regards,\n\n{{prepared_by}}\n{{designation}}\n{{entity_name}}\n{{phone}}\n{{email}}";
        }

        return $settings->proforma_invoice_email_body_template ?: "Dear {{contact_person}},\n\nGreetings from {{entity_name}}.\n\nPlease find attached the Proforma Invoice for {{service_name}}.\n\nKindly review the attached document and proceed with the payment as per the stated terms.\n\nPlease feel free to contact us if any clarification is required.\n\nBest regards,\n\n{{prepared_by}}\n{{designation}}\n{{entity_name}}\n{{phone}}\n{{email}}";
    }

    private function renderTemplate(string $template, array $placeholders): string
    {
        foreach ($placeholders as $key => $value) {
            $template = str_replace(['{{'.$key.'}}', '{'.$key.'}'], (string) $value, $template);
        }

        return trim($template);
    }
}
