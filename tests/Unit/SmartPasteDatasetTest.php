<?php

namespace Tests\Unit;

use App\Services\LocalClientInformationExtractor;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Format-independence regression dataset for the deterministic extractor (the
 * reliable path when AI is unavailable). 30+ real-world formatting variations.
 * Every case also runs universal anti-contamination invariants: a label word is
 * never a value, and a phone/email never lands in the address.
 */
class SmartPasteDatasetTest extends TestCase
{
    private function extract(string $text): array
    {
        return (new LocalClientInformationExtractor)->extract($text);
    }

    /** The exact confirmed production failure must now map perfectly. */
    public function test_wendler_exact_mapping(): void
    {
        $out = $this->extract(implode("\n", [
            'Contact Person: Md. Saidul Islam Babul',
            'Designation: Manager – HR & Compliance',
            'Address:',
            'Rampur, Trishal-Balipara Road,',
            'Trishal-2220, Mymensingh, Bangladesh',
            'Mobile: +880 1318 583378',
            'Email: saidul.islam@wendlerinside.com',
            'Website: www.wendlerinside.com',
            'Group: Wendler Interlining Group',
        ]));

        $this->assertNull($out['company_name'], 'Group must not become company name');
        $this->assertSame('Wendler Interlining Group', $out['parent_company']);
        $this->assertSame('Md. Saidul Islam Babul', $out['contact_person']);
        $this->assertSame('Manager – HR & Compliance', $out['designation']);
        $this->assertSame('saidul.islam@wendlerinside.com', $out['email']);
        $this->assertSame('+880 1318 583378', $out['phone']);
        $this->assertSame('www.wendlerinside.com', $out['website']);
        $this->assertSame('Rampur, Trishal-Balipara Road', $out['address']);
        $this->assertSame('2220', $out['postal_code']);
        $this->assertSame('Bangladesh', $out['country']);
        $this->assertStringContainsString('Trishal', (string) $out['city']);
    }

    #[DataProvider('variations')]
    public function test_variation(string $input, array $expect): void
    {
        $out = $this->extract($input);

        foreach ($expect as $field => $value) {
            if ($value === '*filled*') {
                $this->assertNotNull($out[$field], "$field should be filled");
            } elseif ($value === '*null*') {
                $this->assertNull($out[$field], "$field should be null");
            } else {
                $this->assertSame($value, $out[$field], "$field mismatch");
            }
        }

        $this->assertNoContamination($out);
    }

    private function assertNoContamination(array $out): void
    {
        foreach (['company_name', 'parent_company', 'contact_person', 'designation', 'address', 'city'] as $f) {
            if (is_string($out[$f])) {
                $this->assertDoesNotMatchRegularExpression(
                    '/\b(Mobile|Phone|Email|E-mail|Website|Designation|Group|Address|Contact Person|Attn)\s*:/i',
                    $out[$f],
                    "$f must not contain a field label"
                );
            }
        }

        if ($out['address'] && $out['phone']) {
            $this->assertStringNotContainsString(
                preg_replace('/\D/', '', $out['phone']),
                preg_replace('/\D/', '', $out['address']),
                'Phone digits must not appear in the address'
            );
        }
        if ($out['address'] && $out['email']) {
            $this->assertStringNotContainsString($out['email'], $out['address'], 'Email must not appear in the address');
        }
        if ($out['email']) {
            $this->assertNotFalse(filter_var($out['email'], FILTER_VALIDATE_EMAIL), 'Email must be valid');
        }
        if ($out['company_name'] && $out['parent_company']) {
            $this->assertNotSame(
                mb_strtolower($out['company_name']),
                mb_strtolower($out['parent_company']),
                'Company and parent must differ'
            );
        }
    }

    public static function variations(): array
    {
        return [
            'one-line pipe' => [
                'Wendler Interlining Group | Md. Saidul Islam Babul, Manager - HR & Compliance | +8801318583378 | saidul.islam@wendlerinside.com',
                ['parent_company' => 'Wendler Interlining Group', 'company_name' => '*null*', 'contact_person' => 'Md. Saidul Islam Babul', 'designation' => 'Manager - HR & Compliance', 'phone' => '+8801318583378', 'email' => 'saidul.islam@wendlerinside.com'],
            ],
            'email signature' => [
                "Best regards,\nMd. Saidul Islam Babul\nManager - HR & Compliance\nWendler Interlining Group\nMob: +880 1318 583378\nsaidul.islam@wendlerinside.com\nwww.wendlerinside.com",
                ['parent_company' => 'Wendler Interlining Group', 'contact_person' => 'Md. Saidul Islam Babul', 'designation' => 'Manager - HR & Compliance', 'phone' => '+880 1318 583378', 'website' => 'www.wendlerinside.com'],
            ],
            'whatsapp message' => [
                "Assalamu alaikum\nPlease add this client:\nABC Garments Ltd.\nContact: Mr. Karim\nPhone: 01712345678\nDhaka, Bangladesh",
                ['company_name' => 'ABC Garments Ltd.', 'contact_person' => 'Karim', 'phone' => '01712345678', 'country' => 'Bangladesh'],
            ],
            'reordered fields' => [
                "Email: info@zed.com\nGroup: Zed Group\nMobile: 01911223344\nName: Jahangir Alam\nDesignation: Director",
                ['parent_company' => 'Zed Group', 'company_name' => '*null*', 'contact_person' => 'Jahangir Alam', 'designation' => 'Director', 'phone' => '01911223344', 'email' => 'info@zed.com'],
            ],
            'multiline address' => [
                "Opex Group\nHouse 12, Road 5\nGulshan, Dhaka 1212\nBangladesh\nopex@grp.com",
                ['parent_company' => 'Opex Group', 'postal_code' => '1212', 'country' => 'Bangladesh', 'address' => '*filled*'],
            ],
            'group only identity' => [
                "Group: Nipa Group\nContact: Mr. Rahim\nMobile: 01555666777",
                ['parent_company' => 'Nipa Group', 'company_name' => '*null*', 'contact_person' => 'Rahim', 'phone' => '01555666777'],
            ],
            'company plus group' => [
                "ABC Garments Ltd.\nXYZ Group\ninfo@abc.com\nMr. Hasan - Managing Director",
                ['company_name' => 'ABC Garments Ltd.', 'parent_company' => 'XYZ Group', 'contact_person' => 'Hasan', 'designation' => 'Managing Director'],
            ],
            'phone with spaces' => [
                "Shirin Textiles Ltd.\nMobile: +880 1711 223344\nshirin@tex.com",
                ['company_name' => 'Shirin Textiles Ltd.', 'phone' => '+880 1711 223344', 'postal_code' => '*null*'],
            ],
            'phone without spaces' => [
                "Shirin Textiles Ltd.\nMobile:+8801711223344\nshirin@tex.com",
                ['phone' => '+8801711223344', 'postal_code' => '*null*'],
            ],
            'local 01 mobile' => [
                "Phone: 01812345678\nName: Mr. Sohel\nCompany: Delta Apparels Ltd.",
                ['phone' => '01812345678', 'contact_person' => 'Sohel', 'company_name' => 'Delta Apparels Ltd.'],
            ],
            'international phone' => [
                "Shanghai Fabrics Co.\nTel: +86 21 1234 5678\ninfo@shfab.cn\nChina",
                ['company_name' => 'Shanghai Fabrics Co.', 'phone' => '*filled*', 'country' => 'China', 'postal_code' => '*null*'],
            ],
            'multiple contacts takes first' => [
                "Delta Ltd.\nMr. Sohel - Manager\nMr. Karim - Director\n01712345678",
                ['company_name' => 'Delta Ltd.', 'contact_person' => 'Sohel', 'designation' => 'Manager'],
            ],
            'bengali labels' => [
                "নাম: মোঃ সাইদুল ইসলাম\nমোবাইল: 01318583378\nইমেইল: saidul@wendler.com\nগ্রুপ: Wendler Group",
                ['contact_person' => 'মোঃ সাইদুল ইসলাম', 'phone' => '01318583378', 'email' => 'saidul@wendler.com', 'parent_company' => 'Wendler Group'],
            ],
            'mixed bengali english' => [
                "Company: রহিম টেক্সটাইল লিমিটেড\nContact: Mr. Rahim\nMobile: 01711223344",
                ['company_name' => 'রহিম টেক্সটাইল লিমিটেড', 'contact_person' => 'Rahim', 'phone' => '01711223344'],
            ],
            'email and website together' => [
                "Zed Fabrics Ltd.\ninfo@zedfab.com\nwww.zedfab.com\n01911223344",
                ['company_name' => 'Zed Fabrics Ltd.', 'email' => 'info@zedfab.com', 'website' => 'www.zedfab.com', 'phone' => '01911223344'],
            ],
            'missing company' => [
                "Contact: Mr. Jalil\nMobile: 01711000000\nEmail: jalil@x.com",
                ['company_name' => '*null*', 'contact_person' => 'Jalil', 'phone' => '01711000000', 'email' => 'jalil@x.com'],
            ],
            'missing phone' => [
                "Alpha Industries Ltd.\nContact: Mr. Noman\nEmail: noman@alpha.com\nGazipur, Bangladesh",
                ['company_name' => 'Alpha Industries Ltd.', 'phone' => '*null*', 'country' => 'Bangladesh'],
            ],
            'missing address' => [
                "Beta Garments Ltd.\nMr. Faruk - Compliance Officer\n01722334455",
                ['company_name' => 'Beta Garments Ltd.', 'contact_person' => 'Faruk', 'designation' => 'Compliance Officer', 'address' => '*null*'],
            ],
            'no labels' => [
                "Reeyan Knit Wear Limited\nKonabari, Gazipur 1346\nreeyan@knit.com\nMr. Anis - Merchandiser",
                ['company_name' => 'Reeyan Knit Wear Limited', 'postal_code' => '1346', 'email' => 'reeyan@knit.com', 'contact_person' => 'Anis', 'designation' => 'Merchandiser'],
            ],
            'ambiguous name' => [
                "Mr. Md. Abdullah Al Mamun\nSenior Executive\nSunrise Textiles Ltd.",
                ['company_name' => 'Sunrise Textiles Ltd.', 'contact_person' => 'Md. Abdullah Al Mamun', 'designation' => 'Senior Executive'],
            ],
            'unusual punctuation' => [
                '::Company:: Orient Ltd. || Mobile >> 01733445566 || Email >> orient@x.com',
                ['company_name' => 'Orient Ltd.', 'phone' => '01733445566', 'email' => 'orient@x.com'],
            ],
            'line breaks after labels' => [
                "Company:\nOrient Textiles Ltd.\nContact Person:\nMr. Salam\nMobile:\n01744556677",
                ['company_name' => 'Orient Textiles Ltd.', 'contact_person' => 'Salam', 'phone' => '01744556677'],
            ],
            'html derived' => [
                'Company:&nbsp;Vertex Ltd.<br>Mobile: 01755667788<br>Email: vertex@x.com',
                ['company_name' => 'Vertex Ltd.', 'phone' => '01755667788', 'email' => 'vertex@x.com'],
            ],
            'empty input' => [
                '',
                ['company_name' => '*null*', 'email' => '*null*', 'phone' => '*null*', 'address' => '*null*'],
            ],
            'malformed input' => [
                'asdf qwer zxcv 9999999999999999999999',
                ['company_name' => '*null*', 'contact_person' => '*null*', 'email' => '*null*'],
            ],
            'duplicate labels' => [
                "Company: Primary Ltd.\nPhone: 01711111111\nPhone: 01822222222\nEmail: a@b.com",
                ['company_name' => 'Primary Ltd.', 'phone' => '01711111111', 'email' => 'a@b.com'],
            ],
            'tabs and spaces' => [
                "Company:\tNexus Ltd.\t\tMobile:\t01766778899",
                ['company_name' => 'Nexus Ltd.', 'phone' => '01766778899'],
            ],
            'bullet points' => [
                "• Company: Bullet Ltd.\n• Mobile: 01777889900\n• Email: bullet@x.com",
                ['company_name' => 'Bullet Ltd.', 'phone' => '01777889900', 'email' => 'bullet@x.com'],
            ],
            'country variation' => [
                "Hangzhou Silk Ltd.\ninfo@silk.cn\nHangzhou, China",
                ['company_name' => 'Hangzhou Silk Ltd.', 'country' => 'China', 'email' => 'info@silk.cn'],
            ],
            'designation before name' => [
                "Compliance Manager - Mr. Rafiq\nTazreen Fashions Ltd.\n01788990011",
                ['company_name' => 'Tazreen Fashions Ltd.', 'contact_person' => 'Rafiq', 'designation' => 'Compliance Manager'],
            ],
            'attn label' => [
                "M/s. Fakir Apparels Ltd.\nAttn: Mr. Jamal\nNarayanganj-1400, Bangladesh\nfakir@app.com",
                ['company_name' => 'M/s. Fakir Apparels Ltd.', 'contact_person' => 'Jamal', 'postal_code' => '1400', 'country' => 'Bangladesh'],
            ],
            'compact comma line' => [
                'Delta Apparels Ltd., Mr. Sohel - Manager, 01712345678, sohel@delta.com',
                ['company_name' => 'Delta Apparels Ltd.', 'phone' => '01712345678', 'email' => 'sohel@delta.com'],
            ],
        ];
    }
}
