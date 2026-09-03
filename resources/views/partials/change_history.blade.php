{{--
    Compact, read-only change history for a record. Pass:
      $histories  — a Collection of App\Models\RecordHistory (already loaded, newest first)
    Renders nothing when there is no history. Raw before/after detail is tucked
    behind a per-row expander so the main page stays uncluttered.
--}}
@if (!empty($histories) && count($histories))
    <div class="card mt-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="eyebrow">Change History</span>
                <span class="cell-sub">{{ count($histories) }} event{{ count($histories) === 1 ? '' : 's' }}</span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width:150px">Date / Time</th>
                            <th style="width:150px">User</th>
                            <th style="width:90px">Action</th>
                            <th>Changes</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($histories as $h)
                        @php $changes = $h->readableChanges(); @endphp
                        <tr>
                            <td class="cell-sub">{{ $h->created_at?->format('d M Y H:i') }}</td>
                            <td class="cell-sub">{{ $h->changedBy?->name ?? 'System' }}</td>
                            <td><span class="badge-soft {{ $h->actionBadgeClass() }}">{{ $h->actionLabel() }}</span></td>
                            <td>
                                @if ($h->note)
                                    <div class="cell-sub">{{ $h->note }}</div>
                                @endif
                                @if (count($changes))
                                    @foreach (array_slice($changes, 0, 3) as $c)
                                        <div class="small"><b>{{ $c['label'] }}:</b>
                                            <span class="text-secondary">{{ \Illuminate\Support\Str::limit($c['from'], 40) }}</span>
                                            <span class="mx-1">&rarr;</span>
                                            <span>{{ \Illuminate\Support\Str::limit($c['to'], 40) }}</span>
                                        </div>
                                    @endforeach
                                    @if (count($changes) > 3)
                                        <details class="mt-1">
                                            <summary class="cell-sub" style="cursor:pointer">+{{ count($changes) - 3 }} more field{{ count($changes) - 3 === 1 ? '' : 's' }}</summary>
                                            @foreach (array_slice($changes, 3) as $c)
                                                <div class="small"><b>{{ $c['label'] }}:</b>
                                                    <span class="text-secondary">{{ \Illuminate\Support\Str::limit($c['from'], 40) }}</span>
                                                    <span class="mx-1">&rarr;</span>
                                                    <span>{{ \Illuminate\Support\Str::limit($c['to'], 40) }}</span>
                                                </div>
                                            @endforeach
                                        </details>
                                    @endif
                                @elseif (!$h->note)
                                    <span class="cell-sub">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif
