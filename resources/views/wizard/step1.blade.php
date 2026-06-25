@extends('biteslot-connector::wizard.layout')

@section('title', 'Select your product table')

@section('content')
    <div class="bs-card">
        <h2>1. Select the table that contains your products</h2>
        <p class="help">Pick the database table on your website that holds your products, then tell us which
            column means what. We read it directly &mdash; no need to export or copy anything.</p>

        <div class="bs-grid">
            <div class="full">
                <label class="fld" for="table">Product table</label>
                <select id="table"><option value="">Loading tables&hellip;</option></select>
            </div>

            <div>
                <label class="fld" for="col_id">Product ID column <span class="muted">(required)</span></label>
                <select id="col_id" disabled><option value="">&mdash;</option></select>
            </div>
            <div>
                <label class="fld" for="col_sku">SKU column <span class="muted">(recommended &mdash; enables auto-match)</span></label>
                <select id="col_sku" disabled><option value="">&mdash;</option></select>
            </div>
            <div>
                <label class="fld" for="col_name">Name column</label>
                <select id="col_name" disabled><option value="">&mdash;</option></select>
            </div>
            <div>
                <label class="fld" for="col_price">Price column</label>
                <select id="col_price" disabled><option value="">&mdash;</option></select>
            </div>
            <div class="full">
                <label class="fld" for="col_category">Category column <span class="muted">(optional)</span></label>
                <select id="col_category" disabled><option value="">&mdash;</option></select>
            </div>
        </div>

        <div class="bs-actions">
            <span class="muted" id="preview-note">Select a table to continue.</span>
            <button class="btn btn-primary" id="save" disabled>Import products &amp; continue &rarr;</button>
        </div>
    </div>

    <div class="bs-card" id="preview-card" style="display:none">
        <h2 style="font-size:15px">Preview <span class="muted" id="preview-count"></span></h2>
        <table>
            <thead><tr><th>ID</th><th>SKU</th><th>Name</th><th>Price</th><th>Category</th></tr></thead>
            <tbody id="preview-body"></tbody>
        </table>
    </div>
@endsection

@push('scripts')
<script>
    const saved = @json($saved);
    const roleSelects = ['col_id', 'col_sku', 'col_name', 'col_price', 'col_category'];
    const tableEl = document.getElementById('table');
    const saveBtn = document.getElementById('save');

    function fillSelect(el, options, selected, withBlank) {
        el.innerHTML = '';
        if (withBlank) el.add(new Option('—', ''));
        options.forEach(o => el.add(new Option(o, o, false, o === selected)));
    }

    async function loadColumns(table, preset) {
        roleSelects.forEach(id => { document.getElementById(id).disabled = true; });
        if (!table) return;
        try {
            const { columns, guess } = await BS.get(BS.routes.columns + '?table=' + encodeURIComponent(table));
            roleSelects.forEach(id => {
                const role = id.replace('col_', '');
                const el = document.getElementById(id);
                const sel = (preset && preset[id]) || guess[role] || '';
                fillSelect(el, columns, sel, true);
                el.disabled = false;
                el.onchange = onChange;
            });
            onChange();
        } catch (e) { BS.flash(e.message, false); }
    }

    async function onChange() {
        const table = tableEl.value;
        const idCol = document.getElementById('col_id').value;
        saveBtn.disabled = !(table && idCol);
        document.getElementById('preview-note').textContent =
            saveBtn.disabled ? 'Choose a table and a product ID column to continue.' : 'Ready to import.';
    }

    saveBtn.addEventListener('click', async () => {
        const body = {
            source_table: tableEl.value,
            col_id: document.getElementById('col_id').value,
            col_sku: document.getElementById('col_sku').value || null,
            col_name: document.getElementById('col_name').value || null,
            col_price: document.getElementById('col_price').value || null,
            col_category: document.getElementById('col_category').value || null,
        };
        saveBtn.disabled = true; saveBtn.innerHTML = '<span class="spin"></span> Importing…';
        try {
            const r = await BS.post(BS.routes.source, body);
            BS.flash('Imported ' + r.imported + ' product(s). Next: sync your POS menu.', true);
            setTimeout(() => window.location = BS.routes.step2, 700);
        } catch (e) {
            BS.flash(e.message, false);
            saveBtn.disabled = false; saveBtn.innerHTML = 'Import products & continue →';
        }
    });

    (async function init() {
        try {
            const { tables } = await BS.get(BS.routes.tables);
            fillSelect(tableEl, tables, saved.source_table || '', true);
            tableEl.disabled = false;
            tableEl.onchange = () => loadColumns(tableEl.value, null);
            if (saved.source_table) await loadColumns(saved.source_table, saved);
        } catch (e) { BS.flash(e.message, false); }
    })();
</script>
@endpush
