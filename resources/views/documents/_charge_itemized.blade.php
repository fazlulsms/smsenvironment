@isset($chargeParticulars)
    <div class="mb-2" data-cpi-widget style="position:relative;max-width:520px">
        <input class="form-control form-control-sm" data-cpi-search placeholder="Add a charge particular from the library (audit, travel, license…)" autocomplete="off">
        <div data-cpi-suggest style="position:absolute;z-index:30;left:0;right:0;max-height:200px;overflow:auto;background:#fff;border:1px solid #e6e2f5;border-radius:8px;display:none"></div>
    </div>
@endisset
<div class="table-responsive">
    <table class="table align-middle" id="itemsTable">
        <thead><tr><th>Service / Particular</th><th style="width:18%" class="num">Amount</th><th style="width:44px"></th></tr></thead>
        <tbody>
        @foreach ($items as $index => $item)
            @include('documents.item_row', ['index' => $index, 'item' => $item])
        @endforeach
        </tbody>
    </table>
</div>
<div class="row justify-content-end">
    <div class="col-md-5 col-lg-4">
        <div class="d-flex justify-content-between py-1"><span class="text-secondary">Subtotal</span><strong class="num" id="subtotal">0.00</strong></div>
        <label class="form-label mt-2">Adjustment</label>
        <input class="form-control num calc" type="number" step="0.01" name="adjustment" value="{{ old('adjustment', $document->adjustment ?? 0) }}">
        <div class="d-flex justify-content-between align-items-center py-2 mt-2 px-2 rounded" style="background:var(--brand-050)">
            <span class="fw-semibold">Total</span>
            <strong class="num" style="font-size:18px"><span class="cur">{{ $currency }}</span> <span id="grandTotal">0.00</span></strong>
        </div>
    </div>
</div>
