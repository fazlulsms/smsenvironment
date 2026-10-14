<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Deterministic, format-independent client-information parser. It is the
 * reliable fallback when the AI provider is unavailable (and also validates /
 * supplements AI output). It reads information by MEANING and LABELS, never by
 * line position, so reordering lines or changing the label style does not
 * change the extracted facts.
 *
 * Hard guarantees (no field contamination):
 *   - A label word ("Designation", "Mobile", "Group") is never a value.
 *   - A phone/email/website never lands in Address.
 *   - "Group:" maps to Parent/Group company, never Company Name.
 *   - Multiline addresses stop at the next recognised label.
 *   - Unknown fields stay null (nothing is guessed to fill the form).
 */
class LocalClientInformationExtractor
{
    /** Canonical field => recognised label synonyms (compared case/space/punctuation-insensitively). */
    private const LABELS = [
        'company_name' => ['company name', 'company', 'factory name', 'factory', 'organization', 'organisation', 'firm', 'establishment', 'client name', 'client', 'business name', 'concern name', 'mill name', 'কোম্পানি', 'প্রতিষ্ঠান'],
        'parent_company' => ['parent/group company', 'parent group company', 'parent company', 'group company', 'parent', 'group', 'holding', 'holdings', 'mother company', 'গ্রুপ'],
        'contact_person' => ['contact person', 'contact name', 'contact', 'name', 'attention', 'attn', 'concern person', 'focal person', 'reference person', 'person', 'নাম'],
        'designation' => ['designation', 'title', 'position', 'role', 'job title', 'post', 'পদবি', 'পদবী'],
        'department' => ['department', 'dept', 'section', 'division', 'বিভাগ'],
        'email' => ['email', 'e-mail', 'e mail', 'mail', 'email address', 'e-mail address', 'ইমেইল', 'ই-মেইল'],
        'phone' => ['phone', 'mobile', 'mob', 'cell', 'cellphone', 'tel', 'telephone', 'contact no', 'contact number', 'phone no', 'mobile no', 'cell no', 'whatsapp', 'hotline', 'gsm', 'ফোন', 'মোবাইল'],
        'website' => ['website', 'web', 'web site', 'site', 'url', 'web address', 'webpage', 'ওয়েবসাইট'],
        'address' => ['address', 'factory address', 'office address', 'corporate office', 'head office', 'registered office', 'location', 'full address', 'mailing address', 'add', 'ঠিকানা'],
        'city' => ['city', 'town', 'শহর'],
        'postal_code' => ['postal code', 'post code', 'postcode', 'zip', 'zip code', 'zipcode'],
        'country' => ['country', 'nation', 'দেশ'],
    ];

    private const COMPANY_KEYWORDS = ['limited', 'ltd', 'company', 'co.', 'garments', 'garment', 'industries', 'industry', 'industrial', 'factory', 'corporation', 'corp', 'apparels', 'apparel', 'textiles', 'textile', 'mills', 'mill', 'knitwear', 'knit', 'knitting', 'fashions', 'fashion', 'sweaters', 'sweater', 'accessories', 'enterprise', 'trading', 'composite', 'spinning', 'dyeing', 'washing', 'packaging', 'plastics', 'chemical', 'chemicals', 'foods', 'tannery', 'leather', 'ceramics', 'pharmaceuticals', 'agro', 'interlining', 'fabrics', 'fabric', 'denim', 'yarn', 'poultry', 'cement', 'steel', 'pvt'];

    private const GROUP_KEYWORDS = ['group', 'holdings', 'holding', 'conglomerate'];

    private const DESIGNATION_KEYWORDS = ['manager', 'director', 'officer', 'executive', 'ceo', 'cfo', 'coo', 'cto', 'chief', 'compliance', 'managing', 'engineer', 'coordinator', 'in-charge', 'in charge', 'incharge', 'head', 'proprietor', 'owner', 'partner', 'president', 'vice president', 'vp', 'gm', 'dgm', 'agm', 'supervisor', 'merchandiser', 'consultant', 'secretary', 'accountant', 'administrator', 'specialist', 'lead', 'analyst'];

    private const NAME_TITLES = ['md', 'md.', 'mohammad', 'mohammed', 'muhammad', 'mr', 'mr.', 'mrs', 'mrs.', 'ms', 'ms.', 'dr', 'dr.', 'engr', 'engr.', 'eng', 'alhaj', 'al-haj'];

    /** Known Bangladeshi cities/districts for best-effort city detection. */
    private const KNOWN_CITIES = ['Dhaka', 'Chattogram', 'Chittagong', 'Gazipur', 'Narayanganj', 'Savar', 'Mymensingh', 'Trishal', 'Khulna', 'Rajshahi', 'Sylhet', 'Bogura', 'Bogra', 'Cumilla', 'Comilla', 'Narsingdi', 'Tangail', 'Ashulia', 'Dhamrai', 'Tongi', 'Rangpur', 'Barishal', 'Barisal', 'Jashore', 'Jessore', 'Kushtia', 'Pabna', 'Manikganj', 'Munshiganj', 'Kaliakair', 'Valuka', 'Bhaluka'];

    private const COUNTRIES = ['Bangladesh', 'India', 'China', 'Pakistan', 'Vietnam', 'Myanmar', 'Nepal', 'Sri Lanka', 'Germany', 'Turkey', 'Hong Kong', 'Singapore', 'Thailand', 'Indonesia', 'Cambodia'];

    public function extract(string $text): array
    {
        $data = ClientInformationExtractor::blankData();

        $normalized = $this->normalize($text);
        $lines = collect(explode("\n", $normalized))
            ->map(fn (string $line) => $this->stripBullet(trim($line)))
            ->filter(fn (string $line) => $line !== '')
            ->values()
            ->all();

        // Collected address fragments (labelled + unlabelled), assembled at the end.
        $addressLines = [];

        $this->labeledPass($lines, $data, $addressLines);
        $this->unlabeledPass($lines, $data, $addressLines);
        $this->finaliseAddress($addressLines, $data);
        $this->globalSupplements($normalized, $data);
        $this->cleanup($data);

        return $data;
    }

    // ---- Normalisation -----------------------------------------------------

    private function normalize(string $text): string
    {
        // HTML-derived paste: block tags become line breaks, other tags are
        // dropped, then entities (incl. &nbsp;) are decoded.
        if (preg_match('/<[a-z!\/]/i', $text) || str_contains($text, '&')) {
            $text = preg_replace('/<\s*(br|\/p|\/div|\/li|\/tr|\/h[1-6])\s*\/?\s*>/i', "\n", $text);
            $text = preg_replace('/<[^>]+>/', ' ', $text);
            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        $text = preg_replace("/\r\n?/", "\n", $text);
        $text = str_replace("\t", ' ', $text);
        // A pipe is almost always a field separator in compact one-line contacts.
        $text = str_replace('|', "\n", $text);
        // Collapse runs of spaces (incl. non-breaking) but keep newlines.
        $text = preg_replace('/[ \x{00A0}]+/u', ' ', $text);
        $text = trim($text);

        // A single compact line carrying several fields (it has an email/phone and
        // 2+ commas) is comma-separated — split it so each field is parsed. A pure
        // one-line address (no email/phone) is left intact.
        if (! str_contains($text, "\n") && substr_count($text, ',') >= 2
            && preg_match('/@|(?:\+?\d[\d\s\-()]{6,}\d)/', $text)) {
            $text = preg_replace('/\s*,\s*/', "\n", $text);
        }

        return $text;
    }

    private function stripBullet(string $line): string
    {
        // Remove leading list bullets (•, ·, ▪, ●, ‣, *, →) but NOT hyphens
        // (a hyphen separates "Name - Designation").
        return trim(preg_replace('/^[\x{2022}\x{00B7}\x{25AA}\x{25CF}\x{2023}\x{2043}\*\x{2192}]+\s*/u', '', $line));
    }

    // ---- Pass 1: labelled lines -------------------------------------------

    /**
     * @param  array<int,string>  $lines
     * @param  array<string,mixed>  $data
     * @param  array<int,string>  $addressLines
     */
    private function labeledPass(array &$lines, array &$data, array &$addressLines): void
    {
        $remaining = [];
        $count = count($lines);

        for ($i = 0; $i < $count; $i++) {
            $line = $lines[$i];

            $segments = $this->splitLabelSegments($line);
            if ($segments === null) {
                $remaining[] = $line;

                continue;
            }

            foreach ($segments as $seg) {
                $field = $seg['field'];
                $value = trim($seg['value']);

                // Value on following line(s) when empty after the label.
                if ($value === '') {
                    $value = $this->collectFollowingValue($lines, $i, $count, $field, $addressLines);
                }

                $this->assignLabeled($field, $value, $data, $addressLines);
            }
        }

        $lines = $remaining;
    }

    /**
     * Split a line into one or more {field, value} pairs when it begins with a
     * known label, including several "Label: value" pairs on one line. Returns
     * null when the line is not label-led (so "Mr. Sohel - Compliance Manager"
     * is left for the heuristic pass).
     *
     * @return array<int,array{field:string,value:string}>|null
     */
    private function splitLabelSegments(string $line): ?array
    {
        if (! str_contains($line, ':')) {
            return null;
        }

        // Scan for KNOWN label synonyms followed by a colon, anywhere on the line
        // (handles "Company: X   Mobile: Y" on one line, and Bengali labels).
        $markers = [];
        foreach (self::LABELS as $field => $synonyms) {
            foreach ($synonyms as $synonym) {
                $regex = '/(?:^|[\s|>,;:·•*\x{2022}])('.preg_quote($synonym, '/').')\s*:/iu';
                if (preg_match_all($regex, $line, $mm, PREG_OFFSET_CAPTURE)) {
                    foreach ($mm[1] as $i => $cap) {
                        $markers[] = [
                            'field' => $field,
                            'len' => strlen($cap[0]),
                            'labelStart' => $cap[1],
                            'valueStart' => $mm[0][$i][1] + strlen($mm[0][$i][0]),
                        ];
                    }
                }
            }
        }

        if ($markers === []) {
            return null;
        }

        // One marker per start position — keep the longest (most specific) label.
        usort($markers, fn ($a, $b) => $a['labelStart'] <=> $b['labelStart'] ?: $b['len'] <=> $a['len']);
        $deduped = [];
        $lastEnd = -1;
        foreach ($markers as $m) {
            if ($m['labelStart'] >= $lastEnd) {
                $deduped[] = $m;
                $lastEnd = $m['valueStart'];
            }
        }

        // The first label must be at (or near) the start — otherwise the colon
        // belongs to free text (a sentence), not a field label.
        if ($deduped[0]['labelStart'] > 2) {
            return null;
        }

        $segments = [];
        $total = count($deduped);
        for ($k = 0; $k < $total; $k++) {
            $start = $deduped[$k]['valueStart'];
            $end = $k + 1 < $total ? $deduped[$k + 1]['labelStart'] : strlen($line);
            $value = trim(trim(substr($line, $start, $end - $start)), " \t,;|>•*");
            $segments[] = ['field' => $deduped[$k]['field'], 'value' => $value];
        }

        return $segments;
    }

    private function matchLabel(string $raw): ?string
    {
        $key = $this->canonicalLabel($raw);
        if ($key === '') {
            return null;
        }

        foreach (self::LABELS as $field => $synonyms) {
            foreach ($synonyms as $synonym) {
                if ($this->canonicalLabel($synonym) === $key) {
                    return $field;
                }
            }
        }

        return null;
    }

    private function canonicalLabel(string $raw): string
    {
        // Keep Unicode letters/digits (so Bengali survives), drop spaces/punctuation.
        return preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($raw));
    }

    /**
     * When a label has no inline value, pull the value from the following
     * line(s). For address, keep collecting until the next label; for other
     * fields take only the next non-empty, non-label line.
     *
     * @param  array<int,string>  $lines
     * @param  array<int,string>  $addressLines
     */
    private function collectFollowingValue(array $lines, int &$i, int $count, string $field, array &$addressLines): string
    {
        $collected = [];

        for ($j = $i + 1; $j < $count; $j++) {
            $next = $lines[$j];
            if ($this->splitLabelSegments($next) !== null) {
                break; // next field label reached
            }

            if ($field === 'address') {
                $addressLines[] = $next;
                $lines[$j] = '';          // consume
                $i = $j;

                continue;
            }

            $collected[] = $next;
            $lines[$j] = '';
            $i = $j;
            break; // single-value fields take one line
        }

        return implode(' ', $collected);
    }

    private function assignLabeled(string $field, string $value, array &$data, array &$addressLines): void
    {
        $value = $this->stripLeadingLabel($value);
        if ($value === '') {
            return;
        }

        switch ($field) {
            case 'address':
                $addressLines[] = $value;

                return;
            case 'parent_company':
                $data['parent_company'] = $value;

                return;
            case 'company_name':
                // A value that is really a group ("… Group") belongs to parent.
                if ($this->isGroup($value) && ! $this->hasCompanyKeyword($value)) {
                    $data['parent_company'] ??= $value;
                } else {
                    $data['company_name'] = $value;
                }

                return;
            case 'contact_person':
                $data['contact_person'] = $this->normalizePersonName($value);

                return;
            case 'phone':
                $phone = $this->cleanPhone($value);
                if ($phone !== null) {
                    $data['phone'] ??= $phone;
                }

                return;
            case 'email':
                if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $data['email'] ??= $value;
                }

                return;
            case 'website':
                $data['website'] ??= $this->cleanWebsite($value);

                return;
            default:
                $data[$field] ??= $value;
        }
    }

    // ---- Pass 2: unlabelled heuristics ------------------------------------

    /**
     * @param  array<int,string>  $lines
     * @param  array<int,string>  $addressLines
     */
    private function unlabeledPass(array $lines, array &$data, array &$addressLines): void
    {
        $lines = array_values(array_filter($lines, fn ($l) => trim($l) !== ''));

        foreach ($lines as $index => $line) {
            // Skip pure contact tokens — handled by global supplements.
            if ($this->isEmail($line) || $this->isWebsite($line) || $this->isPhoneLine($line)) {
                continue;
            }

            if ($this->isGroup($line) && ! $this->hasCompanyKeyword($line)) {
                $data['parent_company'] ??= $line;

                continue;
            }

            if ($this->looksLikeCompany($line)) {
                $this->assignCompany($line, $data);

                continue;
            }

            if ($person = $this->personDesignationFromLine($line)) {
                $data['contact_person'] ??= $person['contact_person'];
                $data['designation'] ??= $person['designation'];

                continue;
            }

            if ($this->looksLikeDesignation($line)) {
                $data['designation'] ??= $line;
                if (! $data['contact_person'] && $index > 0) {
                    $previous = $lines[$index - 1];
                    if ($this->looksLikePersonName($previous)) {
                        $data['contact_person'] = $this->normalizePersonName($previous);
                    }
                }

                continue;
            }

            if ($this->looksLikeAddress($line)) {
                $addressLines[] = $line;

                continue;
            }

            // Standalone name only when it carries an explicit courtesy/name title
            // (Md./Mr./Dr./Mohammad…), so arbitrary prose is never a "contact".
            if (! $data['contact_person'] && $this->hasNameTitle($line) && $this->looksLikePersonName($line)) {
                $data['contact_person'] = $this->normalizePersonName($line);
            }
        }
    }

    private function hasNameTitle(string $line): bool
    {
        $first = strtolower(preg_split('/\s+/', trim($line))[0] ?? '');

        return in_array($first, self::NAME_TITLES, true);
    }

    private function assignCompany(string $line, array &$data): void
    {
        // A line ending in Group/Holdings is the parent, even with a company word.
        if ($this->isGroup($line) && ! $data['parent_company']) {
            $data['parent_company'] = $line;

            return;
        }

        if ($data['company_name'] && ! $data['parent_company'] && $this->isGroup($data['company_name'])) {
            $data['parent_company'] = $data['company_name'];
            $data['company_name'] = $line;

            return;
        }

        $data['company_name'] ??= $line;
    }

    // ---- Pass 3: global regex supplements ---------------------------------

    private function globalSupplements(string $text, array &$data): void
    {
        $withoutEmail = $text;
        if (! $data['email'] && preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $m)) {
            $data['email'] = $m[0];
        }
        $withoutEmail = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', ' ', $text);

        if (! $data['website'] && preg_match('/\b(?:https?:\/\/)?(?:www\.)[a-z0-9][a-z0-9\-]*(?:\.[a-z0-9][a-z0-9\-]*)+\b/i', $withoutEmail, $m)) {
            $data['website'] = $this->cleanWebsite($m[0]);
        }

        if (! $data['phone']) {
            $data['phone'] = $this->findPhone($withoutEmail);
        }

        if (! $data['country']) {
            foreach (self::COUNTRIES as $country) {
                if (preg_match('/\b'.preg_quote($country, '/').'\b/i', $text)) {
                    $data['country'] = $country;
                    break;
                }
            }
        }

        if (! $data['city'] && ($city = $this->detectCity($text))) {
            $data['city'] = $city;
        }

        // Postal detection must ignore phone digits (e.g. "1318" inside a phone).
        if (! $data['postal_code']) {
            $phoneless = $withoutEmail;
            if ($data['phone']) {
                $phoneless = str_replace($data['phone'], ' ', $phoneless);
            }
            $phoneless = preg_replace('/\+?\d[\d\s\-()]{6,16}\d/', ' ', $phoneless);
            if ($postal = $this->findPostal($phoneless)) {
                $data['postal_code'] = $postal;
            }
        }
    }

    // ---- Address assembly --------------------------------------------------

    /** @param array<int,string> $addressLines */
    private function finaliseAddress(array $addressLines, array &$data): void
    {
        $addressLines = array_values(array_unique(array_filter(array_map(
            fn ($l) => trim(rtrim(trim($l), ',;')),
            $addressLines
        ), fn ($l) => $l !== '')));

        if ($addressLines === []) {
            return;
        }

        // Separate street lines from pure-locality lines (postal/city/country with
        // no street component). When both exist, the street lines are the address
        // and the locality line yields city/postal/country — so a multiline block
        // like "… Road / Trishal-2220, Mymensingh, Bangladesh" splits correctly,
        // while a single combined line ("80 … Rd, Chattogram 4210") stays whole.
        $streetLines = [];
        $localityLines = [];
        foreach ($addressLines as $line) {
            if (! $this->hasStreetComponent($line) && $this->isLocalityLine($line)) {
                $localityLines[] = $line;
            } else {
                $streetLines[] = $line;
            }
        }

        $localitySource = implode(', ', $localityLines !== [] ? $localityLines : $addressLines);

        if (! $data['postal_code'] && ($postal = $this->findPostal($localitySource))) {
            $data['postal_code'] = $postal;
        }
        if (! $data['country']) {
            foreach (self::COUNTRIES as $country) {
                if (preg_match('/\b'.preg_quote($country, '/').'\b/i', $localitySource)) {
                    $data['country'] = $country;
                    break;
                }
            }
        }
        if (! $data['city']) {
            // A dedicated locality line yields the full "Area, District"; a single
            // combined street line yields just the known city token.
            $data['city'] = $localityLines !== []
                ? $this->cityFromLocality($localitySource, $data)
                : $this->detectCity($localitySource);
        }

        $addressText = implode(', ', $streetLines !== [] ? $streetLines : $addressLines);
        if ($data['country']) {
            $addressText = preg_replace('/,?\s*'.preg_quote($data['country'], '/').'\s*$/i', '', $addressText);
        }

        $data['address'] = trim(rtrim(trim($addressText), ',')) ?: null;
    }

    private function hasStreetComponent(string $line): bool
    {
        return (bool) preg_match('/\b(?:Road|Rd|Street|St|Avenue|Ave|Lane|Holding|House|Plot|Block|Sector|Para|Nagar|Bazar|Bazaar|P\.?O\.?|Post Office|Village|Union|Tower|Floor|Building|Mouza|Highway|Hwy)\b/i', $line);
    }

    private function isLocalityLine(string $line): bool
    {
        return $this->findPostal($line) !== null
            || $this->detectCity($line) !== null
            || (bool) preg_match('/\b('.implode('|', array_map(fn ($c) => preg_quote($c, '/'), self::COUNTRIES)).')\b/i', $line);
    }

    /** City/district text from a locality line, minus postal code and country. */
    private function cityFromLocality(string $source, array $data): ?string
    {
        $city = preg_replace('/-?\s*\b\d{4}\b/', '', $source);
        foreach (self::COUNTRIES as $country) {
            $city = preg_replace('/,?\s*\b'.preg_quote($country, '/').'\b/i', '', $city);
        }
        $city = trim(preg_replace('/\s*,\s*/', ', ', $city));
        $city = trim($city, " ,-\u{2013}");

        if ($city === '' || strlen($city) > 60) {
            return $this->detectCity($source);
        }

        return $city;
    }

    // ---- Field heuristics --------------------------------------------------

    private function personDesignationFromLine(string $line): ?array
    {
        // "Mr. Sohel - Compliance Manager" / "Sohel, Compliance Manager"
        if (! preg_match('/^(.+?)\s*[-–—,|]\s*(.+)$/u', $line, $m)) {
            return null;
        }

        [$left, $right] = [trim($m[1]), trim($m[2])];

        if ($this->looksLikeDesignation($right) && $this->looksLikePersonName($left)) {
            return [
                'contact_person' => $this->normalizePersonName($left),
                'designation' => $right,
            ];
        }

        // Reversed: "Compliance Manager - Mr. Sohel"
        if ($this->looksLikeDesignation($left) && $this->looksLikePersonName($right)) {
            return [
                'contact_person' => $this->normalizePersonName($right),
                'designation' => $left,
            ];
        }

        return null;
    }

    private function normalizePersonName(string $name): string
    {
        $name = trim(preg_replace('/\s+/', ' ', $name));

        // Drop a leading courtesy title but keep "Md." (part of Bangladeshi names).
        return trim(preg_replace('/^(?:Mr|Mrs|Ms|Dr|Engr|Eng)\.?\s+/i', '', $name));
    }

    private function looksLikePersonName(string $line): bool
    {
        $line = trim($line);
        if ($line === '' || $this->isEmail($line) || $this->isWebsite($line)) {
            return false;
        }
        if (preg_match('/\d/', $line)) {
            return false; // names have no digits
        }
        if ($this->hasCompanyKeyword($line) || $this->isGroup($line) || $this->looksLikeDesignation($line)) {
            return false;
        }

        // 1–5 words of letters (allowing Md., ., -).
        return (bool) preg_match('/^(?:[A-Za-z][A-Za-z.\'\-]*\s*){1,5}$/', $line)
            && str_word_count(str_replace('.', ' ', $line)) <= 5;
    }

    private function hasCompanyKeyword(string $line): bool
    {
        foreach (self::COMPANY_KEYWORDS as $kw) {
            if (preg_match('/\b'.preg_quote($kw, '/').'\b/i', $line)) {
                return true;
            }
        }

        return false;
    }

    private function isGroup(string $line): bool
    {
        foreach (self::GROUP_KEYWORDS as $kw) {
            if (preg_match('/\b'.preg_quote($kw, '/').'\b/i', $line)) {
                return true;
            }
        }

        return false;
    }

    private function looksLikeCompany(string $line): bool
    {
        return $this->hasCompanyKeyword($line) || $this->isGroup($line);
    }

    private function looksLikeDesignation(string $line): bool
    {
        foreach (self::DESIGNATION_KEYWORDS as $kw) {
            if (preg_match('/\b'.preg_quote($kw, '/').'\b/i', $line)) {
                // A company line that merely contains "Head office" is not a designation.
                return ! $this->hasCompanyKeyword($line);
            }
        }

        return false;
    }

    private function isPhoneLine(string $line): bool
    {
        $line = trim($line);

        // The whole line is just a phone number (optionally prefixed by + / digits).
        return (bool) preg_match('/^\+?[\d][\d\s\-()]{6,}\d$/', $line) && $this->isValidPhone($line);
    }

    private function looksLikeAddress(string $line): bool
    {
        if ($this->isEmail($line) || $this->isWebsite($line) || $this->isPhoneLine($line)) {
            return false;
        }

        return (bool) preg_match('/\d|\b(?:Road|Rd|Street|St|Avenue|Ave|Lane|Holding|House|Plot|Block|Sector|Bazar|Bazaar|Para|Nagar|Upazila|Thana|Zila|District|P\.?O\.?|Post Office)\b/i', $line)
            || $this->detectCity($line) !== null;
    }

    // ---- Contact-token helpers --------------------------------------------

    private function isEmail(string $line): bool
    {
        return (bool) preg_match('/^[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}$/i', trim($line));
    }

    private function isWebsite(string $line): bool
    {
        $line = trim($line);
        if ($this->isEmail($line)) {
            return false;
        }

        return (bool) preg_match('/^(?:https?:\/\/)?(?:www\.)[a-z0-9\-]+(?:\.[a-z0-9\-]+)+\/?$/i', $line);
    }

    private function cleanWebsite(?string $value): ?string
    {
        if (! $value) {
            return null;
        }
        if (preg_match('/\b(?:https?:\/\/)?(?:www\.)?[a-z0-9][a-z0-9\-]*(?:\.[a-z0-9][a-z0-9\-]*)+\b/i', $value, $m)) {
            return rtrim($m[0], '/');
        }

        return null;
    }

    private function cleanPhone(?string $value): ?string
    {
        if (! $value) {
            return null;
        }
        // Keep only the phone portion of the value (strip any trailing words).
        if (preg_match('/\+?[\d][\d\s\-()]{6,}\d/', $value, $m)) {
            $candidate = trim($m[0]);
            if ($this->isValidPhone($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function findPhone(string $text): ?string
    {
        if (preg_match_all('/\+?\d[\d\s\-()]{6,16}\d/', $text, $matches)) {
            foreach ($matches[0] as $candidate) {
                $candidate = trim($candidate);
                if ($this->isValidPhone($candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    private function isValidPhone(string $candidate): bool
    {
        $digits = preg_replace('/\D/', '', $candidate);
        $len = strlen($digits);

        // Bangladeshi mobile (local 01XXXXXXXXX or intl 8801XXXXXXXXX).
        if (preg_match('/^(?:880)?1[3-9]\d{8}$/', $digits)) {
            return true;
        }

        // Generic international / landline: 9–15 digits.
        return $len >= 9 && $len <= 15;
    }

    private function findPostal(string $text): ?string
    {
        // 4-digit Bangladeshi postal code, possibly glued to a place ("Dhaka-1230").
        if (preg_match('/(?<!\d)(\d{4})(?!\d)/', $text, $m)) {
            return $m[1];
        }

        return null;
    }

    private function detectCity(string $text): ?string
    {
        foreach (self::KNOWN_CITIES as $city) {
            if (preg_match('/\b'.preg_quote($city, '/').'\b/i', $text)) {
                return match (strtolower($city)) {
                    'chittagong' => 'Chattogram',
                    'bogra' => 'Bogura',
                    'comilla' => 'Cumilla',
                    'barisal' => 'Barishal',
                    'jessore' => 'Jashore',
                    'bhaluka' => 'Valuka',
                    default => $city,
                };
            }
        }

        return null;
    }

    private function stripLeadingLabel(string $value): string
    {
        // Defensive: if a value still begins with a known label + colon, drop it.
        if (preg_match('/^([A-Za-z][A-Za-z .\/&-]{0,30}?)\s*:\s*(.*)$/', $value, $m) && $this->matchLabel($m[1]) !== null) {
            return trim($m[2]);
        }

        return $value;
    }

    private function cleanup(array &$data): void
    {
        // Scrub any phone/email/leading-label contamination out of the address.
        if (is_string($data['address'])) {
            $addr = $data['address'];
            if ($data['phone']) {
                $addr = str_replace($data['phone'], '', $addr);
            }
            $addr = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '', $addr);
            $addr = preg_replace('/\+?\d[\d\s\-()]{6,16}\d/', '', $addr);     // stray phone
            $addr = $this->stripLeadingLabel(ltrim($addr, ' :>-•*'));
            $addr = trim(preg_replace('/\s{2,}/', ' ', $addr), ' ,;:-');
            // Require some letters to be a real address.
            $data['address'] = preg_match('/\p{L}/u', $addr) ? $addr : null;
        }

        foreach ($data as $key => $value) {
            if (! is_string($value)) {
                continue;
            }
            // Trim stray leading/trailing separators left by odd punctuation.
            $value = trim($value, " \t:>|•*,-");
            // A value that is only a label word is contamination — reject it.
            if ($value !== '' && $this->matchLabel($value) !== null && ! in_array($key, ['country', 'city'], true)) {
                $value = '';
            }
            $data[$key] = $value === '' ? null : $value;
        }

        // Never let the group value double as the company name.
        if ($data['company_name'] && $data['parent_company']
            && Str::lower($data['company_name']) === Str::lower($data['parent_company'])) {
            $data['company_name'] = null;
        }
    }
}
