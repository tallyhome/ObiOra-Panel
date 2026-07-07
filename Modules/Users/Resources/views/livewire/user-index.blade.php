<div>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Utilisateurs panel</h1>
            <p class="text-muted mb-0">Comptes, rôles Spatie et accès</p>
        </div>
        @can('users.manage')
            @if(!$showForm)
                <button type="button" wire:click="$set('showForm', true)" class="btn btn-primary btn-sm">Ajouter</button>
            @endif
        @endcan
    </div>

    @if($showForm)
        <div class="card obiora-card mb-4">
            <div class="card-body">
                <h2 class="h6 mb-3">{{ $editingId ? 'Modifier' : 'Nouvel utilisateur' }}</h2>
                <form wire:submit="{{ $editingId ? 'update' : 'create' }}">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Nom</label>
                            <input wire:model="name" type="text" class="form-control obiora-input" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Email</label>
                            <input wire:model="email" type="email" class="form-control obiora-input" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Mot de passe</label>
                            <input wire:model="password" type="password" class="form-control obiora-input" @if(!$editingId) required @endif>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Rôle</label>
                            <select wire:model="role" class="form-select">
                                @foreach($roles as $r)
                                    <option value="{{ $r }}">{{ $r }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check">
                                <input wire:model="is_active" type="checkbox" class="form-check-input" id="userActive">
                                <label class="form-check-label" for="userActive">Actif</label>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
                        <button type="button" wire:click="cancelForm" class="btn btn-outline-secondary btn-sm">Annuler</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="card obiora-card">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Email</th>
                        <th>Rôle</th>
                        <th>Actif</th>
                        @can('users.manage')<th></th>@endcan
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $user)
                        <tr>
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td><span class="badge bg-secondary">{{ $user->roles->first()?->name ?? '—' }}</span></td>
                            <td>{{ $user->is_active ? 'Oui' : 'Non' }}</td>
                            @can('users.manage')
                            <td class="text-end">
                                <button type="button" wire:click="edit({{ $user->id }})" class="btn btn-link btn-sm">Modifier</button>
                                @if($user->id !== auth()->id())
                                    <button type="button"
                                        onclick="obioraConfirmWire(this, 'delete', 'Supprimer', 'Supprimer cet utilisateur ?', {{ $user->id }})"
                                        class="btn btn-link btn-sm text-danger">Supprimer</button>
                                @endif
                            </td>
                            @endcan
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
