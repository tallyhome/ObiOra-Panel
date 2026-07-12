<div>
    @include('monitoring::partials.monitoring-nav')

    <div class="mb-4">
        <h1 class="h3 mb-1">Préférences</h1>
        <p class="text-muted small mb-0">Affichage des dates et heures dans le monitoring.</p>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card obiora-card">
                <div class="card-header">Fuseau horaire</div>
                <div class="card-body">
                    <label class="form-label small fw-medium" for="userTimezone">Timezone</label>
                    <select id="userTimezone" wire:model.live="timezone" class="form-select mb-2">
                        @foreach($timezoneChoices as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="small text-muted mb-3">
                        Toutes les dates du monitoring s'affichent dans ce fuseau.
                        <br>Aperçu : <strong>{{ $previewTime }}</strong>
                    </p>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="save" wire:loading.attr="disabled">
                        Enregistrer
                    </button>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card obiora-card">
                <div class="card-header">Profil</div>
                <div class="card-body small">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Nom</dt>
                        <dd class="col-sm-8">{{ auth()->user()?->name }}</dd>
                        <dt class="col-sm-4">Email</dt>
                        <dd class="col-sm-8">{{ auth()->user()?->email }}</dd>
                    </dl>
                    <p class="text-muted mt-3 mb-0">Licence et mises à jour : <a href="{{ route('settings.index') }}">Paramètres panel</a>.</p>
                </div>
            </div>
        </div>
    </div>

    <p class="small text-muted mt-3 mb-0">Heures en {{ $timezoneFooter }} — {{ $nowLabel }}</p>
</div>
