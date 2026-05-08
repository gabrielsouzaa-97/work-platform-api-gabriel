<div>
    <style>
        .page-header { margin-bottom: 1.5rem; }
        .page-title { font-size: 1.25rem; font-weight: 600; color: #e2e8f0; }
        .card {
            background: #1a1d27;
            border: 1px solid #2d3748;
            border-radius: 8px;
            padding: 1.5rem;
            max-width: 480px;
        }
        .form-group { margin-bottom: 1.25rem; }
        .form-label {
            display: block;
            font-size: .8125rem;
            font-weight: 500;
            color: #a0aec0;
            margin-bottom: .375rem;
        }
        .form-input, .form-select {
            width: 100%;
            background: #0f1117;
            border: 1px solid #2d3748;
            border-radius: 6px;
            padding: .625rem .875rem;
            color: #e2e8f0;
            font-size: .9rem;
            outline: none;
        }
        .form-input:focus, .form-select:focus { border-color: #63b3ed; }
        .form-input.error, .form-select.error { border-color: #fc8181; }
        .error-msg { font-size: .78rem; color: #fc8181; margin-top: .3rem; }
        .form-hint { font-size: .78rem; color: #718096; margin-top: .3rem; }
        .btn-row { display: flex; gap: .75rem; margin-top: 1.5rem; }
        .btn-primary {
            background: #2b6cb0;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: .625rem 1.25rem;
            font-size: .9rem;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-primary:hover { background: #2c5282; }
        .btn-primary:disabled { opacity: .6; cursor: not-allowed; }
        .btn-secondary {
            background: none;
            border: 1px solid #4a5568;
            color: #a0aec0;
            border-radius: 6px;
            padding: .625rem 1.25rem;
            font-size: .9rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-secondary:hover { border-color: #718096; color: #e2e8f0; }
    </style>

    <div class="page-header">
        <h1 class="page-title">Novo Operador</h1>
    </div>

    <div class="card">
        <form wire:submit="save">
            <div class="form-group">
                <label class="form-label" for="name">Nome completo</label>
                <input
                    id="name"
                    type="text"
                    wire:model="name"
                    class="form-input {{ $errors->has('name') ? 'error' : '' }}"
                    autofocus
                    autocomplete="name"
                >
                @error('name')
                    <div class="error-msg">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="email">E-mail</label>
                <input
                    id="email"
                    type="email"
                    wire:model="email"
                    class="form-input {{ $errors->has('email') ? 'error' : '' }}"
                    autocomplete="off"
                >
                @error('email')
                    <div class="error-msg">{{ $message }}</div>
                @enderror
                <div class="form-hint">Um convite sera enviado para este endeco.</div>
            </div>

            <div class="form-group">
                <label class="form-label" for="role">Perfil de acesso</label>
                <select
                    id="role"
                    wire:model="role"
                    class="form-select {{ $errors->has('role') ? 'error' : '' }}"
                >
                    <option value="operador">Operador</option>
                    <option value="suporte">Suporte</option>
                    <option value="admin">Admin</option>
                </select>
                @error('role')
                    <div class="error-msg">{{ $message }}</div>
                @enderror
            </div>

            <div class="btn-row">
                <button type="submit" class="btn-primary" wire:loading.attr="disabled">
                    <span wire:loading.remove>Enviar convite</span>
                    <span wire:loading>Enviando...</span>
                </button>
                <a href="{{ route('operators.index') }}" class="btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
