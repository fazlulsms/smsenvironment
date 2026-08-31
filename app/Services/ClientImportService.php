<?php

namespace App\Services;

use App\Models\BusinessEntity;
use App\Models\Client;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent, non-destructive import of the real client master.
 *
 * Matching (against existing rows) is by NORMALIZED COMPANY NAME only. Email and
 * client_code are deliberately NOT used to match: in the real data sister
 * companies share group emails (e.g. two DBL factories on one address) and some
 * distinct businesses reuse a code, so matching on those would wrongly merge
 * separate companies. A record that resolves to exactly one existing client
 * UPDATES it in place (same id, relationships preserved) filling only BLANK
 * fields — it never overwrites data a user already has. A record with no match is
 * created. A record matching two or more existing clients (duplicate masters) is
 * left untouched and reported as ambiguous for manual review. Nothing is ever
 * deleted, and commercial documents are never touched.
 */
class ClientImportService
{
    /** @var array<string, list<int>> */
    private array $byName = [];

    private ?int $entityId = null;

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @return array{created:int, updated:int, unchanged:int, ambiguous:array<int,string>, skipped:array<int,string>}
     */
    public function import(array $records): array
    {
        $this->entityId = BusinessEntity::query()->where('entity_code', 'SMSEA')->value('id');
        $this->buildIndex();

        $created = 0;
        $updated = 0;
        $unchanged = 0;
        $ambiguous = [];
        $skipped = [];

        foreach ($records as $record) {
            $name = trim((string) ($record['company_name'] ?? ''));
            if ($name === '') {
                $skipped[] = 'row with empty company_name';

                continue;
            }

            $candidates = $this->candidateIds($record);

            if (count($candidates) > 1) {
                $ambiguous[] = $name.' (matches ids: '.implode(', ', $candidates).')';

                continue;
            }

            if (count($candidates) === 1) {
                $client = Client::query()->find($candidates[0]);
                if (! $client) {
                    $created += $this->create($record);

                    continue;
                }
                $this->fillBlanks($client, $record) ? $updated++ : $unchanged++;
                $this->indexClient($client); // keep indexes fresh (code/email may now be set)

                continue;
            }

            $created += $this->create($record);
        }

        return compact('created', 'updated', 'unchanged', 'ambiguous', 'skipped');
    }

    private function create(array $record): int
    {
        $client = Client::query()->create(array_merge(
            $this->attributes($record),
            ['business_entity_id' => $this->entityId]
        ));
        $this->indexClient($client);

        return 1;
    }

    /** Fill only blank columns; returns true if anything actually changed. */
    private function fillBlanks(Client $client, array $record): bool
    {
        foreach ($this->attributes($record) as $key => $value) {
            if ($value !== null && $value !== '' && blank($client->{$key})) {
                $client->{$key} = $value;
            }
        }

        if (! $client->isDirty()) {
            return false;
        }

        $client->save();

        return true;
    }

    /** @return array<string, mixed> */
    private function attributes(array $record): array
    {
        return [
            'client_code' => $this->clean($record['client_code'] ?? null),
            'company_name' => $this->clean($record['company_name'] ?? null),
            'contact_person' => $this->clean($record['contact_person'] ?? null),
            'designation' => $this->clean($record['designation'] ?? null),
            'email' => $this->clean($record['email'] ?? null),
            'phone' => $this->clean($record['phone'] ?? null),
            'website' => $this->clean($record['website'] ?? null),
            // address is NOT NULL in the schema — coalesce blank to '' on insert.
            'address' => $this->clean($record['address'] ?? null) ?? '',
            'city' => $this->clean($record['city'] ?? null),
            'postal_code' => $this->clean($record['postal_code'] ?? null),
            'country' => $this->clean($record['country'] ?? null),
        ];
    }

    private function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /** @return list<int> distinct existing client ids matching this record by name. */
    private function candidateIds(array $record): array
    {
        $name = $this->normName($record['company_name'] ?? '');

        return $name === '' ? [] : array_values($this->byName[$name] ?? []);
    }

    private function buildIndex(): void
    {
        DB::table('clients')->select('id', 'company_name')->orderBy('id')
            ->each(function ($row) {
                $client = new Client;
                $client->id = $row->id;
                $client->company_name = $row->company_name;
                $this->indexClient($client);
            });
    }

    private function indexClient(Client $client): void
    {
        if ($name = $this->normName($client->company_name)) {
            $this->byName[$name][] = $client->id;
            $this->byName[$name] = array_values(array_unique($this->byName[$name]));
        }
    }

    private function normName(?string $name): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower((string) $name));
    }
}
