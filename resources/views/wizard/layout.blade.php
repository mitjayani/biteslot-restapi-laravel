<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>BiteSlot Setup &middot; @yield('title', 'Product Mapping')</title>
    <style>
        :root {
            --bs-bg: #f4f5f7; --bs-card: #ffffff; --bs-border: #e3e6ea;
            --bs-text: #1f2430; --bs-muted: #6b7280; --bs-primary: #e8552d;
            --bs-primary-d: #cf471f; --bs-green: #16a34a; --bs-amber: #d97706;
            --bs-radius: 10px;
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: var(--bs-bg); color: var(--bs-text); line-height: 1.45; }
        .bs-wrap { max-width: 980px; margin: 0 auto; padding: 28px 20px 64px; }
        .bs-brand { display: flex; align-items: center; gap: 10px; font-weight: 700; font-size: 18px; margin-bottom: 6px; }
        .bs-brand .dot { width: 12px; height: 12px; border-radius: 50%; background: var(--bs-primary); display: inline-block; }
        .bs-sub { color: var(--bs-muted); font-size: 14px; margin: 0 0 22px; }
        .bs-steps { display: flex; gap: 8px; margin-bottom: 24px; flex-wrap: wrap; }
        .bs-step { flex: 1 1 0; min-width: 180px; background: var(--bs-card); border: 1px solid var(--bs-border);
            border-radius: var(--bs-radius); padding: 12px 14px; font-size: 13px; color: var(--bs-muted); }
        .bs-step b { display: block; color: var(--bs-text); font-size: 14px; }
        .bs-step.active { border-color: var(--bs-primary); box-shadow: 0 0 0 1px var(--bs-primary); }
        .bs-step.done { border-color: var(--bs-green); }
        .bs-step .n { display: inline-block; width: 20px; height: 20px; border-radius: 50%; background: var(--bs-border);
            color: #fff; text-align: center; font-size: 12px; line-height: 20px; margin-right: 6px; }
        .bs-step.active .n { background: var(--bs-primary); }
        .bs-step.done .n { background: var(--bs-green); }
        .bs-card { background: var(--bs-card); border: 1px solid var(--bs-border); border-radius: var(--bs-radius);
            padding: 22px; margin-bottom: 18px; }
        .bs-card h2 { margin: 0 0 4px; font-size: 18px; }
        .bs-card p.help { color: var(--bs-muted); font-size: 14px; margin: 0 0 18px; }
        label.fld { display: block; font-size: 13px; font-weight: 600; margin: 0 0 6px; }
        select, input[type=text], input[type=number] { width: 100%; padding: 9px 11px; border: 1px solid var(--bs-border);
            border-radius: 8px; font-size: 14px; background: #fff; color: var(--bs-text); }
        .bs-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; }
        .bs-grid .full { grid-column: 1 / -1; }
        .btn { display: inline-flex; align-items: center; gap: 7px; border: 0; cursor: pointer; font-size: 14px;
            font-weight: 600; padding: 10px 16px; border-radius: 8px; text-decoration: none; }
        .btn-primary { background: var(--bs-primary); color: #fff; }
        .btn-primary:hover { background: var(--bs-primary-d); }
        .btn-ghost { background: transparent; color: var(--bs-muted); border: 1px solid var(--bs-border); }
        .btn[disabled] { opacity: .55; cursor: not-allowed; }
        .bs-actions { display: flex; justify-content: space-between; align-items: center; margin-top: 8px; }
        .bs-flash { padding: 11px 14px; border-radius: 8px; font-size: 14px; margin-bottom: 16px; display: none; }
        .bs-flash.ok { background: #ecfdf3; color: #15803d; border: 1px solid #bbf7d0; display: block; }
        .bs-flash.err { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; display: block; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { text-align: left; padding: 9px 10px; border-bottom: 1px solid var(--bs-border); vertical-align: middle; }
        th { font-size: 12px; text-transform: uppercase; letter-spacing: .03em; color: var(--bs-muted); }
        .pill { font-size: 12px; padding: 2px 9px; border-radius: 999px; font-weight: 600; }
        .pill.mapped { background: #ecfdf3; color: #15803d; }
        .pill.unmapped { background: #fff7ed; color: var(--bs-amber); }
        .bs-toolbar { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-bottom: 14px; }
        .bs-toolbar input[type=text], .bs-toolbar select { width: auto; min-width: 180px; }
        .bs-stat { display: inline-flex; flex-direction: column; padding: 10px 16px; border: 1px solid var(--bs-border);
            border-radius: 8px; min-width: 96px; }
        .bs-stat b { font-size: 20px; } .bs-stat span { font-size: 12px; color: var(--bs-muted); }
        .bs-stats { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 18px; }
        .muted { color: var(--bs-muted); }
        .spin { display: inline-block; width: 14px; height: 14px; border: 2px solid #fff; border-top-color: transparent;
            border-radius: 50%; animation: bs-spin .7s linear infinite; }
        @keyframes bs-spin { to { transform: rotate(360deg); } }
        @media (max-width: 640px) { .bs-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="bs-wrap">
    <div class="bs-brand"><span class="dot"></span> BiteSlot Integration</div>
    <p class="bs-sub">Connect your store's products to your BiteSlot POS menu.</p>

    @php($current = $step ?? 1)
    <div class="bs-steps">
        @foreach ([1 => 'Select product table', 2 => 'Sync POS menu', 3 => 'Map products'] as $i => $label)
            <div class="bs-step {{ $current === $i ? 'active' : ($current > $i ? 'done' : '') }}">
                <span class="n">{{ $current > $i ? '✓' : $i }}</span>
                <b>Step {{ $i }}</b>{{ $label }}
            </div>
        @endforeach
    </div>

    <div id="bs-flash" class="bs-flash"></div>

    @yield('content')
</div>

<script>
    window.BS = {
        token: document.querySelector('meta[name=csrf-token]').getAttribute('content'),
        routes: {
            tables:   @json(route('biteslot.setup.tables')),
            columns:  @json(route('biteslot.setup.columns')),
            source:   @json(route('biteslot.setup.source')),
            sync:     @json(route('biteslot.setup.sync')),
            posItems: @json(route('biteslot.setup.pos-items')),
            products: @json(route('biteslot.setup.products')),
            map:      @json(route('biteslot.setup.map')),
            autoMap:  @json(route('biteslot.setup.auto-map')),
            summary:  @json(route('biteslot.setup.summary')),
            step2:    @json(route('biteslot.setup.step2')),
            step3:    @json(route('biteslot.setup.step3')),
        },
        async get(url) { return this._json(await fetch(url, { headers: { 'Accept': 'application/json' } })); },
        async post(url, body) {
            return this._json(await fetch(url, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.token },
                body: JSON.stringify(body || {}),
            }));
        },
        async _json(res) {
            const data = await res.json().catch(() => ({}));
            if (!res.ok) { throw new Error(data.error || ('Request failed (' + res.status + ')')); }
            return data;
        },
        flash(msg, ok) {
            const el = document.getElementById('bs-flash');
            el.textContent = msg; el.className = 'bs-flash ' + (ok ? 'ok' : 'err');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },
        esc(s) { const d = document.createElement('div'); d.textContent = (s == null ? '' : s); return d.innerHTML; },
    };
</script>
@stack('scripts')
</body>
</html>
