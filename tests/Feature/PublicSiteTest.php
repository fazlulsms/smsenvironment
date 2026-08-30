<?php

namespace Tests\Feature;

use App\Mail\InquiryNotification;
use App\Models\Client;
use App\Models\ProformaInvoice;
use App\Models\Quotation;
use App\Models\ServiceInquiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The public marketing website: correct positioning and scope, working enquiry
 * form, no internal data leakage, and the Office kept behind authentication.
 */
class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    /** Content that must never appear on the public site. */
    private array $forbidden = [
        'ISO 9001', 'ISO 14001', 'ISO 45001', 'BSCI', 'SMETA', 'SLCP', 'WRAP',
        'CTPAT', 'C-TPAT', 'Social Compliance', 'grievance', 'harassment',
        'working hours', 'supply chain security',
    ];

    public function test_home_is_public_and_on_message(): void
    {
        $res = $this->get('/')->assertOk()
            ->assertSee('Environmental, Chemical &amp; Sustainability Solutions for Responsible Industry', false)
            ->assertSee('Environmental Impact Assessment')
            ->assertSee('Environmental Parameter Testing')
            ->assertSee('Chemical Management')
            ->assertSee('Energy Audit')
            ->assertSee('Request a Proposal');

        foreach ($this->forbidden as $needle) {
            $res->assertDontSee($needle, false);
        }
    }

    public function test_services_page_shows_only_relevant_families(): void
    {
        $res = $this->get(route('public.services'))->assertOk()
            ->assertSee('Environmental Services')
            ->assertSee('Chemical Management Services')
            ->assertSee('Sustainability Services')
            ->assertSee('Environmental &amp; Sustainability Training', false)
            ->assertSee('Environmental Impact Assessment (EIA)')
            ->assertSee('Wastewater / ETP Assessment');

        foreach ($this->forbidden as $needle) {
            $res->assertDontSee($needle, false);
        }
    }

    public function test_training_page_is_environmental_only(): void
    {
        $res = $this->get(route('public.training'))->assertOk()
            ->assertSee('Environmental &amp; Sustainability Training', false)
            ->assertSee('Chemical Management Training')
            ->assertSee('Cleaner Production Training');

        foreach (array_merge($this->forbidden, ['labour', 'wages']) as $needle) {
            $res->assertDontSee($needle, false);
        }
    }

    public function test_static_pages_render(): void
    {
        $this->get(route('public.about'))->assertOk()->assertSee('About');
        $this->get(route('public.contact'))->assertOk()->assertSee('Request a Proposal');
        $this->get(route('public.privacy'))->assertOk()->assertSee('Privacy Policy');
        $this->get(route('public.terms'))->assertOk()->assertSee('Terms of Use');
    }

    public function test_environmental_testing_scope_is_shown(): void
    {
        // Seeded EPT package scope (flagged public by migration) appears on the home page.
        $this->get('/')->assertOk()
            ->assertSee('Stack Emission')
            ->assertSee('ODS Assessment / Inventory');
    }

    public function test_inquiry_form_stores_a_lead_and_notifies(): void
    {
        Mail::fake();

        $res = $this->post(route('public.inquiry'), [
            'name' => 'Rifat Ahmed',
            'company' => 'Green Textiles Ltd.',
            'email' => 'rifat@example.com',
            'phone' => '+8801700000000',
            'service' => 'Environmental Parameter Testing',
            'message' => 'We need parameter testing for our facility.',
            'website_url' => '',
        ]);

        $res->assertRedirect();
        $res->assertSessionHas('inquiry_status');
        $this->assertDatabaseHas('service_inquiries', [
            'email' => 'rifat@example.com',
            'service' => 'Environmental Parameter Testing',
        ]);

        // A notification is sent to SMSEA, with Reply-To set to the submitter.
        Mail::assertSent(InquiryNotification::class, function (InquiryNotification $mail) {
            return $mail->replyToAddress === 'rifat@example.com'
                && $mail->inquiry->email === 'rifat@example.com'
                && $mail->inquiry->service === 'Environmental Parameter Testing';
        });
    }

    public function test_inquiry_recipient_uses_configured_public_email(): void
    {
        Mail::fake();
        config(['mail.inquiry_to' => 'leads@smsenvironment.com']);

        $this->post(route('public.inquiry'), ['name' => 'A B', 'email' => 'a@b.com', 'website_url' => '']);

        Mail::assertSent(InquiryNotification::class, fn (InquiryNotification $mail) => $mail->hasTo('leads@smsenvironment.com'));
    }

    public function test_inquiry_is_saved_even_when_email_delivery_fails(): void
    {
        // Force the mail layer to throw; the lead must still be saved and the
        // visitor must still see a normal confirmation.
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp unavailable'));

        $res = $this->post(route('public.inquiry'), [
            'name' => 'Fatima Noor', 'email' => 'fatima@example.com', 'website_url' => '',
        ]);

        $res->assertRedirect();
        $res->assertSessionHas('inquiry_status');
        $this->assertDatabaseHas('service_inquiries', ['email' => 'fatima@example.com']);
    }

    public function test_inquiry_creates_no_commercial_records(): void
    {
        Mail::fake();

        $this->post(route('public.inquiry'), ['name' => 'X Y', 'email' => 'x@y.com', 'website_url' => '']);

        $this->assertSame(0, Client::query()->withoutGlobalScopes()->count());
        $this->assertSame(0, Quotation::query()->withoutGlobalScopes()->count());
        $this->assertSame(0, ProformaInvoice::query()->withoutGlobalScopes()->count());
    }

    public function test_inquiry_requires_name_and_email(): void
    {
        $this->post(route('public.inquiry'), ['name' => '', 'email' => 'not-an-email'])
            ->assertSessionHasErrors(['name', 'email']);
        $this->assertSame(0, ServiceInquiry::query()->count());
    }

    public function test_inquiry_honeypot_blocks_bots(): void
    {
        $this->post(route('public.inquiry'), [
            'name' => 'Bot', 'email' => 'bot@example.com', 'website_url' => 'http://spam.example',
        ])->assertSessionHasErrors('website_url');
        $this->assertSame(0, ServiceInquiry::query()->count());
    }

    public function test_office_is_still_protected(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('clients.index'))->assertRedirect(route('login'));
        $this->get(route('proforma-invoices.index'))->assertRedirect(route('login'));
    }

    public function test_public_nav_has_verify_document(): void
    {
        $this->get('/')->assertOk()->assertSee('Verify Document');
    }

    public function test_home_has_organization_structured_data(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('application/ld+json', false)
            ->assertSee('ProfessionalService', false)
            ->assertSee('areaServed', false);
    }

    public function test_pages_have_unique_titles(): void
    {
        $home = $this->get('/')->getContent();
        $services = $this->get(route('public.services'))->getContent();
        $training = $this->get(route('public.training'))->getContent();

        $title = fn ($html) => preg_match('/<title>(.*?)<\/title>/s', $html, $m) ? trim($m[1]) : '';
        $this->assertNotSame($title($home), $title($services));
        $this->assertNotSame($title($services), $title($training));
    }

    public function test_sitemap_lists_public_pages_and_excludes_office(): void
    {
        $res = $this->get('/sitemap.xml')->assertOk();
        $res->assertHeader('Content-Type', 'application/xml');
        $res->assertSee(route('public.services'), false);
        $res->assertSee(route('public.training'), false);
        $res->assertSee(route('verify.index'), false);
        $res->assertDontSee('/office', false);
    }

    public function test_robots_disallows_office_and_points_to_sitemap(): void
    {
        $this->get('/robots.txt')->assertOk()
            ->assertSee('Disallow: /office/')
            ->assertSee('Sitemap:');
    }

    public function test_verify_result_page_is_noindex(): void
    {
        $this->get(route('verify.show', 'BOGUS-CODE-0000-0000'))->assertOk()
            ->assertSee('noindex', false)
            ->assertSee('Document not found', false);
    }

    public function test_no_internal_data_leaks_onto_public_pages(): void
    {
        Client::query()->create(['company_name' => 'CONFIDENTIAL CLIENT LTD', 'address' => 'Somewhere']);

        $this->get('/')->assertOk()->assertDontSee('CONFIDENTIAL CLIENT LTD');
        $this->get(route('public.services'))->assertOk()->assertDontSee('CONFIDENTIAL CLIENT LTD');
    }
}
