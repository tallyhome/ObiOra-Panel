<div>
    @include('monitoring::partials.monitoring-nav')

    <div class="mb-4">
        <h1 class="h3 mb-1">Status page publique</h1>
        <p class="text-muted small mb-0">Page `/status` accessible sans authentification (sans IP ni secrets).</p>
    </div>

    <div class="card obiora-card" style="max-width: 640px;">
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label small fw-medium">URL publique</label>
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control" readonly value="{{ $publicUrl }}">
                    <a href="{{ $publicUrl }}" target="_blank" class="btn btn-outline-secondary">Ouvrir</a>
                </div>
            </div>
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" wire:model="isEnabled" id="statusEnabled">
                <label class="form-check-label" for="statusEnabled">Status page activée</label>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-medium">Titre</label>
                <input type="text" wire:model="title" class="form-control">
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" wire:model="noindex" id="statusNoindex">
                <label class="form-check-label" for="statusNoindex">Meta noindex (ne pas indexer par les moteurs)</label>
            </div>
            <button type="button" wire:click="save" class="btn btn-primary btn-sm">Enregistrer</button>
        </div>
    </div>
</div>
