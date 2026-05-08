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
            transition: border-color .15s;
        }
        .form-input:focus { border-color: #63b3ed; }
        .form-input.error { border-color: #fc8181; }
        .error-msg { font-size: .78rem; color: #fc8181; margin-top: .3rem; }
        .password-wrap { position: relative; }
        .toggle-pw {
            position: absolute;
            right: .75rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #718096;
            cursor: pointer;
            font-size: .8rem;
            padding: 0;
        }
        .toggle-pw:hover { color: #a0aec0; }
        .check-row {
            display: flex;
            align-items: center;
            gap: .5rem;
            margin-bottom: 1.5rem;
        }
        .check-row input[type=checkbox] { accent-color: #63b3ed; }
        .check-row label { font-size: .8125rem; color: #a0aec0; }
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
            transition: background .15s, opacity .15s;
        }
        .btn-primary:hover { background: #2c5282; }
        .btn-primary:disabled { opacity: .6; cursor: not-allowed; }
        h2 {
            font-size: 1.1rem;
            font-weight: 600;
            color: #e2e8f0;
            margin-bottom: 1.5rem;
            text-align: center;
        }
    </style>

    <h2>Entrar no sistema</h2>

    <form wire:submit="login" x-data="{ showPw: false }">
        <div class="form-group">
            <label class="form-label" for="email">E-mail</label>
            <input
                id="email"
                type="email"
                wire:model="email"
                class="form-input {{ $errors->has('email') ? 'error' : '' }}"
                autofocus
                autocomplete="email"
                placeholder="operador@empresa.com"
            >
            @error('email')
                <div class="error-msg">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label class="form-label" for="password">Senha</label>
            <div class="password-wrap">
                <input
                    id="password"
                    :type="showPw ? 'text' : 'password'"
                    wire:model="password"
                    class="form-input {{ $errors->has('password') ? 'error' : '' }}"
                    autocomplete="current-password"
                    placeholder="••••••••••••"
                    style="padding-right: 3rem;"
                >
                <button type="button" class="toggle-pw" @click="showPw = !showPw" tabindex="-1">
                    <span x-text="showPw ? 'ocultar' : 'mostrar'"></span>
                </button>
            </div>
            @error('password')
                <div class="error-msg">{{ $message }}</div>
            @enderror
        </div>

        <div class="check-row">
            <input type="checkbox" id="remember" wire:model="remember">
            <label for="remember">Manter conectado</label>
        </div>

        <button type="submit" class="btn-primary" wire:loading.attr="disabled">
            <span wire:loading.remove>Entrar</span>
            <span wire:loading>Autenticando...</span>
        </button>
    </form>
</div>
