@extends('biteslot-connector::wizard.layout')

@section('title', 'Map your products')

@section('content')
    <div class="bs-card">
        <h2>3. Map each product to a BiteSlot POS item</h2>
        <p class="help">Pick the matching POS item for every storefront product. Only mapped products can be
            ordered &mdash; an unmapped product is rejected at checkout, so a wrong item never reaches the kitchen.</p>

        <div class="bs-stats">
            <div class="bs-stat"><b id="s-total">{{ $summary['total'] }}</b><span>products</span></div>
            <div class="bs-stat"><b id="s-mapped">{{ $summary['mapped'] }}</b><span>mapped</span></div>
            <div class="bs-stat"><b id="s-unmapped">{{ $summary['unmapped'] }}</b><span>unmapped</span></div>
            <div class="bs-stat"><b id="s-pos">{{ $summary['pos_count'] }}</b><span>POS items</span></div>
        </div>

        <div class="bs-toolbar">
            <input type="text" id="q" placeholder="Search products…">
            <select id="status">
                <option value="all">All</option>
                <option value="unmapped" selected>Unmapped only</option>
                <option value="mapped">Mapped only</option>
            </select>
            <button class="btn btn-ghost" id="auto">Auto-match by SKU</button>
        </div>

        <table>
            <thead>
                <tr><th>Your product</th><th>SKU</th><th style="width:38%">BiteSlot POS item</th><th>Status</th></tr>
            </thead>
            <tbody id="rows"><tr><td colspan="4" class="muted">Loading…</td></tr></tbody>
        </table>

        <div class="bs-actions">
            <span class="muted" id="pager"></span>
            <span>
                <button class="btn btn-ghost" id="prev" disabled>&larr; Prev</button>
                <button class="btn btn-ghost" id="next" disabled>Next &rarr;</button>
            </span>
        </div>
    </div>

    <div class="bs-card">
        <div class="bs-actions">
            <a class="btn btn-ghost" href="{{ route('biteslot.setup.step2') }}">&larr; Back</a>
            <span class="muted" id="finish-note">Map every product to finish.</span>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    let posItems = [];
    let page = 1, lastPage = 1;
    const qEl = document.getElementById('q'), statusEl = document.getElementById('status'), rowsEl = document.getElementById('rows');

    function posOptions(selected) {
        let html = '<option value="">— not mapped —</option>';
        posItems.forEach(i => {
            const label = i.name + (i.sku ? ' (' + i.sku + ')' : '') + (i.price != null ? ' · ' + i.price : '');
            html += '<option value="' + i.pos_item_id + '"' + (i.pos_item_id == selected ? ' selected' : '') + '>'
                + BS.esc(label) + '</option>';
        });
        return html;
    }

    function applySummary(s) {
        document.getElementById('s-total').textContent = s.total;
        document.getElementById('s-mapped').textContent = s.mapped;
        document.getElementById('s-unmapped').textContent = s.unmapped;
        document.getElementById('s-pos').textContent = s.pos_count;
        document.getElementById('finish-note').textContent =
            s.unmapped === 0 && s.total > 0 ? '✓ All products mapped — you’re done!' : (s.unmapped + ' product(s) still need mapping.');
    }

    async function changeMap(localId, posId, rowEl) {
        try {
            const r = await BS.post(BS.routes.map, { local_product_id: localId, pos_item_id: posId || null });
            applySummary(r);
            const pill = rowEl.querySelector('.pill');
            pill.className = 'pill ' + (r.mapped ? 'mapped' : 'unmapped');
            pill.textContent = r.mapped ? 'Mapped' : 'Unmapped';
        } catch (e) { BS.flash(e.message, false); }
    }

    function render(rows) {
        if (!rows.length) { rowsEl.innerHTML = '<tr><td colspan="4" class="muted">No products match.</td></tr>'; return; }
        rowsEl.innerHTML = '';
        rows.forEach(p => {
            const tr = document.createElement('tr');
            const mapped = p.pos_item_id != null;
            tr.innerHTML =
                '<td><b>' + BS.esc(p.local_name || ('#' + p.local_product_id)) + '</b>'
                    + '<div class="muted" style="font-size:12px">ID ' + BS.esc(p.local_product_id)
                    + (p.local_price != null ? ' · ' + BS.esc(p.local_price) : '') + '</div></td>'
                + '<td class="muted">' + BS.esc(p.local_sku || '—') + '</td>'
                + '<td><select>' + posOptions(p.pos_item_id) + '</select></td>'
                + '<td><span class="pill ' + (mapped ? 'mapped' : 'unmapped') + '">' + (mapped ? 'Mapped' : 'Unmapped') + '</span></td>';
            tr.querySelector('select').addEventListener('change', e => changeMap(p.local_product_id, e.target.value, tr));
            rowsEl.appendChild(tr);
        });
    }

    async function load() {
        rowsEl.innerHTML = '<tr><td colspan="4" class="muted">Loading…</td></tr>';
        const url = BS.routes.products + '?page=' + page
            + '&q=' + encodeURIComponent(qEl.value) + '&status=' + statusEl.value;
        try {
            const r = await BS.get(url);
            lastPage = r.meta.last_page;
            render(r.data);
            document.getElementById('pager').textContent = 'Page ' + r.meta.current_page + ' of ' + r.meta.last_page + ' · ' + r.meta.total + ' product(s)';
            document.getElementById('prev').disabled = page <= 1;
            document.getElementById('next').disabled = page >= lastPage;
        } catch (e) { BS.flash(e.message, false); }
    }

    document.getElementById('auto').addEventListener('click', async function () {
        this.disabled = true; this.innerHTML = '<span class="spin"></span> Matching…';
        try {
            const r = await BS.post(BS.routes.autoMap, {});
            applySummary(r);
            BS.flash('Auto-matched ' + r.auto_mapped + ' product(s) by SKU.', true);
            await load();
        } catch (e) { BS.flash(e.message, false); }
        this.disabled = false; this.innerHTML = 'Auto-match by SKU';
    });

    let t; qEl.addEventListener('input', () => { clearTimeout(t); t = setTimeout(() => { page = 1; load(); }, 300); });
    statusEl.addEventListener('change', () => { page = 1; load(); });
    document.getElementById('prev').addEventListener('click', () => { if (page > 1) { page--; load(); } });
    document.getElementById('next').addEventListener('click', () => { if (page < lastPage) { page++; load(); } });

    (async function init() {
        try {
            const { items } = await BS.get(BS.routes.posItems);
            posItems = items;
            applySummary(@json($summary));
            await load();
        } catch (e) { BS.flash(e.message, false); }
    })();
</script>
@endpush
