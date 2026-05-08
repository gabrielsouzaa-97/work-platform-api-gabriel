<div>
    <style>
        .form-group { margin-bottom: 1.25rem; }
        .form-label {
            display: block;
            font-size: .8125rem;
            font-weight: 500;
            color: #a0aec0;
            margin-bottom: .375rem;
        }
        .form-input {
            width: 100%;
            background: #0f1117;
            border: 1px solid #2d3748;
            border-radius: 6px;
            padding: .625rem .875rem;
            color: #e2e8f0;
            font-size: .9rem;
            outline: none;
        }
        .form-input:focus { border-color: #63b3ed; }
        .form-input.error { border-color: #fc8181; }
        .error-msg { font-size: .78rem; color: #fc8181; margin-top: .3rem; }
        .form-hint { font-size: .78rem; color: #718096; margin-top: .3rem; }
        .btn-primary {
            width: 100%;
            background: #2b6cb0;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: .75rem 1rem;
            font-size: .9375rem;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-primary:hover { background: #2c5282; }
        .btn-primary:disabled { opacity: .6; cursor: not-allowed; }
        h2 {
            font-size: 1.1rem;
            font-weight: 600;
            color: #e2e8f0;
            margin-bottom: .375rem;
            text-align: center;
        }
        .welcome-sub {
            text-align: center;
            color: #718096;
            font-size: .875rem;
            margin-bottom: 1.5rem;
        }
    </style>

    <h2>Ativar conta</h2>
    <p class="welcome-sub">Ola, <strong style="color:#e2e8f0">{{ $operator->name }}</strong>! Defina sua senha abaixo.</p>

    <form wire:submit="acceptInvite">
        <div class="form-group">
            <label class="form-label" for="password">Senha</label>
            <input
                id="password"
                type="password"
                wire:model="password"
                class="form-input {{ $errors->has('password') ? 'error' : '' }}"
                autofocus
                autocomplete="new-password"
            >
            @error('password')
                <div class="error-msg">{{ $message }}</div>
            @enderror
            <div class="form-hint">Minimo 12 caracteres.</div>
        </div>

        <div class="form-group">
            <label class="form-label" for="password_confirmation">Confirmar senha</label>
            <input
                id="password_confirmation"
                type="password"
                wire:model="password_confirmation"
                class="form-input"
                autocomplete="new-password"
            >
        </div>

        <button type="submit" class="btn-primary" wire:loading.attr="disabled">
            <span wire:loading.remove>Ativar conta e entrar</span>
            <span wire:loading>Ativando...</span>
        </button>
    </form>
</div>
