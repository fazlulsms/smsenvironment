@php
    $rowScope = collect(preg_split('/\r\n|\r|\n/', (string) ($item['scope_items'] ?? '')))
        ->map(fn ($s) => trim($s))->filter()->values();
@endphp
<tr>
    <td>
        <textarea class="form-control form-control-sm" rows="2" name="items[{{ $index }}][description]" data-description placeholder="Service / particular (multiline allowed)">{{ $item['description'] ?? '' }}</textarea>
        @if ($rowScope->isNotEmpty())
            {{-- Package scope: preserved on the row, hidden by default (progressive disclosure — not the old permanent textarea). --}}
            <div class="mt-1 small" data-scope-wrap>
                <button class="btn btn-link btn-sm p-0 text-decoration-none" type="button" data-scope-toggle>Includes <span data-scope-count>{{ $rowScope->count() }}</span> package items · View / Edit scope</button>
                <textarea class="form-control form-control-sm d-none mt-1" rows="4" name="items[{{ $index }}][scope_items]" data-pkg-scope placeholder="One included item per line">{{ $rowScope->implode("\n") }}</textarea>
            </div>
        @endif
    </td>
    <td><input class="form-control form-control-sm num amount-input" type="number" step="0.01" min="0" name="items[{{ $index }}][amount]" value="{{ $item['amount'] ?? ($item['unit_rate'] ?? 0) }}" data-amount-input required></td>
    <td><button class="btn-icon" type="button" data-remove-row title="Remove"><x-icon name="trash" :size="15" /></button></td>
</tr>
