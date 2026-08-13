<?php

namespace App\Services\Commercial;

/**
 * Provider-independent contract for turning a free-text commercial instruction
 * (WhatsApp/email) into a structured extraction. Mirrors the Smart Paste provider
 * layer so Gemini stays swappable and the extractor can be tested with a fake.
 */
interface CommercialInstructionProvider
{
    public function name(): string;

    public function isConfigured(): bool;

    /** @return array structured, untrusted extraction (never IDs) */
    public function extract(string $text): array;
}
