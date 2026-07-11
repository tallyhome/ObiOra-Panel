<div class="card obiora-card shadow-sm">
    <div class="card-body p-4">
        <h2 class="h5 mb-4">{{ __('panel.auth.title') }}</h2>

        @if (session('error'))
            <div class="alert alert-warning small py-2">{{ session('error') }}</div>
        @endif

        <form wire:submit="login">
            <div class="mb-3">
                <label class="form-label" for="email">{{ __('panel.auth.email') }}</label>
                <input wire:model="email" type="email" id="email" class="form-control @error('email') is-invalid @enderror" autofocus>
                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label class="form-label" for="password">{{ __('panel.auth.password') }}</label>
                <input wire:model="password" type="password" id="password" class="form-control @error('password') is-invalid @enderror">
                @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3 form-check">
                <input wire:model="remember" type="checkbox" class="form-check-input" id="remember">
                <label class="form-check-label" for="remember">{{ __('panel.auth.remember') }}</label>
            </div>

            <button type="submit" class="btn btn-primary w-100" wire:loading.attr="disabled">
                <span wire:loading.remove>{{ __('panel.auth.submit') }}</span>
                <span wire:loading>{{ __('panel.auth.submitting') }}</span>
            </button>
        </form>
    </div>
</div>
