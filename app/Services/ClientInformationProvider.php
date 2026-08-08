<?php

namespace App\Services;

interface ClientInformationProvider
{
    public function name(): string;

    public function isConfigured(): bool;

    public function extract(string $text): array;
}
