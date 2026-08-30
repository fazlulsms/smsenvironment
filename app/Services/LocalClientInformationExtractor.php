<?php

namespace App\Services;

class LocalClientInformationExtractor
{
    public function extract(string $text): array
    {
        $data = ClientInformationExtractor::blankData();
        $normalized = trim(preg_replace("/\r\n?/", "\n", $text));
        $lines = collect(explode("\n", $normalized))
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->values();

        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $normalized, $match)) {
            $data['email'] = $match[0];
        }

        $withoutEmails = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', ' ', $normalized);
        if (preg_match('/\b(?:https?:\/\/)?(?:www\.)[a-z0-9][a-z0-9\-]*(?:\.[a-z0-9][a-z0-9\-]*)+\b/i', $withoutEmails, $match)) {
            $data['website'] = $match[0];
        }

        if (preg_match('/(?:\+?88)?01[3-9]\d{8}\b/', $normalized, $match)) {
            $data['phone'] = $match[0];
        }

        if (preg_match('/\b(\d{4})\b/', $normalized, $match)) {
            $data['postal_code'] = $match[1];
        }

        if ($city = $this->detectCity($normalized)) {
            $data['city'] = $city;
        }

        if (preg_match('/\bBangladesh\b/i', $normalized)) {
            $data['country'] = 'Bangladesh';
        }

        $semantic = $this->extractFromLines($lines, $data['email']);

        return array_replace($data, array_filter($semantic, fn ($value) => filled($value)));
    }

    private function extractFromLines($lines, ?string $email): array
    {
        $data = ClientInformationExtractor::blankData();
        $available = $lines
            ->reject(fn (string $line) => $email && str_contains($line, $email))
            ->map(fn (string $line) => preg_replace('/^(?:E-?mail|Email|To)\s*:\s*/i', '', $line))
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->values();

        foreach ($available as $index => $line) {
            if ($this->looksLikeAddress($line)) {
                $data['address'] = $line;

                continue;
            }

            if ($this->looksLikeCompany($line)) {
                if ($data['company_name'] && ! $data['parent_company']) {
                    $data['parent_company'] = $data['company_name'];
                    $data['company_name'] = $line;

                    continue;
                }

                if (! $data['company_name']) {
                    $data['company_name'] = $line;
                }

                continue;
            }

            if ($person = $this->personDesignationFromLine($line)) {
                $data['contact_person'] = $person['contact_person'];
                $data['designation'] = $person['designation'];

                continue;
            }

            if ($this->looksLikeDesignation($line) && ! $data['designation']) {
                $data['designation'] = $line;

                if (! $data['contact_person'] && $index > 0) {
                    $previous = $available[$index - 1];
                    if (! $this->looksLikeCompany($previous) && ! $this->looksLikeAddress($previous)) {
                        $data['contact_person'] = $this->normalizePersonName($previous);
                    }
                }
            }
        }

        return $data;
    }

    private function personDesignationFromLine(string $line): ?array
    {
        if (! preg_match('/^(?:Mr\.?|Ms\.?|Mrs\.?)?\s*([A-Za-z .]+?)\s*[-:]\s*(.+)$/i', $line, $match)) {
            return null;
        }

        if (! $this->looksLikeDesignation($match[2])) {
            return null;
        }

        return [
            'contact_person' => $this->normalizePersonName($match[1]),
            'designation' => trim($match[2]),
        ];
    }

    private function normalizePersonName(string $name): string
    {
        return trim(preg_replace('/^(?:Mr\.?|Ms\.?|Mrs\.?)\s+/i', '', trim($name)));
    }

    private function looksLikeCompany(string $line): bool
    {
        return (bool) preg_match('/\b(?:Limited|Ltd\.?|Company|Garments|Industries|Industrial|Factory|Group|Park|Corporation|Corp\.?|Apparels|Textiles)\b/i', $line);
    }

    private function looksLikeDesignation(string $line): bool
    {
        return (bool) preg_match('/\b(?:Manager|Director|Officer|Executive|CEO|Chief|Compliance|Managing|Engineer|Coordinator|In[- ]Charge|Head)\b/i', $line);
    }

    private function looksLikeAddress(string $line): bool
    {
        return (bool) preg_match('/\d|Road|Rd\.?|Street|St\.?|Avenue|Ave\.?|Holding|House|Bazar|PO:|Dhaka|Chattogram|Chittagong|Gazipur|Savar|Dhamrai|Bangladesh/i', $line)
            && ! filter_var($line, FILTER_VALIDATE_EMAIL);
    }

    private function detectCity(string $text): ?string
    {
        foreach (['Chattogram', 'Chittagong', 'Dhaka', 'Gazipur', 'Savar'] as $city) {
            if (preg_match('/\b'.preg_quote($city, '/').'\b/i', $text)) {
                return $city === 'Chittagong' ? 'Chattogram' : $city;
            }
        }

        return null;
    }
}
