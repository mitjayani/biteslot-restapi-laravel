@extends('biteslot-connector::wizard.layout')

@section('title', 'Sync your POS menu')

@section('content')
    <div class="bs-card">
        <h2>2. View the products available in your BiteSlot POS</h2>
        <p class="help">We pull your live menu from BiteSlot into this site so you can map against it. Run this
            again any time your POS menu changes. Items with a matching SKU are linked automatically.</p>

        <div class="bs-stats">
            <div class="bs-stat"><b id="pos-count">{{ $posCount }}</b><span>POS items cached</span></div>
            <div class="bs-stat"><b id="auto-count">0</b><span>auto-matched by SKU</span></div>
        </div>

        <p class="muted" id="last-synced">
            @if ($lastSyncedAt) Last synced {{ $lastSyncedAt->diffForHumans() }}. @else Not synced yet. @endif
        </p>

        <div class="bs-actions">
            <a class="btn btn-ghost" href="{{ route('biteslot.setup.step1') }}">&larr; Back</a>
            <span>
                <button class="btn btn-ghost" id="sync">Sync POS menu now</button>
                <button class="btn btn-primary" id="next" {{ $posCount > 0 ? '' : 'disabled' }}>Continue to mapping &rarr;</button>
            </span>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    const syncBtn = document.getElementById('sync');
    const nextBtn = document.getElementById('next');

    syncBtn.addEventListener('click', async () => {
        syncBtn.disabled = true; syncBtn.innerHTML = '<span class="spin"></span> Syncing…';
        try {
            const r = await BS.post(BS.routes.sync, {});
            document.getElementById('pos-count').textContent = r.pos_count;
            document.getElementById('auto-count').textContent = r.auto_mapped;
            document.getElementById('last-synced').textContent = 'Last synced just now.';
            nextBtn.disabled = r.pos_count < 1;
            BS.flash('Synced ' + r.synced + ' POS item(s); auto-matched ' + r.auto_mapped + ' product(s) by SKU.', true);
        } catch (e) { BS.flash(e.message, false); }
        syncBtn.disabled = false; syncBtn.innerHTML = 'Sync POS menu now';
    });

    nextBtn.addEventListener('click', () => window.location = BS.routes.step3);
</script>
@endpush
