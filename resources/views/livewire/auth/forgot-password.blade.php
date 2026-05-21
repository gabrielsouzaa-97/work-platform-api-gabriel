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
        .back-link {
            display: block;
            text-align: center;
            margin-top: 1rem;
            font-size: .8125rem;
            color: #63b3ed;
            text-decoration: none;
        }
        .back-link:hover { text-decoration: underline; }
        h2 {
            font-size: 1.1rem;
            font-weight: 600;
            color: #e2e8f0;
            margin-bottom: .5rem;
            text-align: center;
        }
        .subtitle {
            font-size: .8125rem;
            color: #718096;
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .success-box {
            background: #1a2a1a;
            border: 1px solid #276749;
            border-radius: 6px;
            padding: 1rem 1.25rem;
            color: #68d391;
            font-size: .875rem;
            line-height: 1.6;
            margin-bottom: 1rem;
        }
    </style>

    <h2>Esqueci minha senha</h2>

    @if ($sent)
        <div class="success-box">
            Se o e-mail informado estiver vinculado a uma conta ativa, enviaremos
            as instrucoes de redefinicao de senha em instantes. Verifique sua caixa de entrada.
        </div>
        <a href="{{ route('login') }}" class="back-link">Voltar ao login</a>
    @else
        <p class="subtitle">Informe seu e-mail e enviaremos um link para redefinir sua senha.</p>

        <form wire:submit="sendResetLink">
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

            <button type="submit" class="btn-primary" wire:loading.attr="disabled">
                <span wire:loading.remove>Enviar link de redefinicao</span>
                <span wire:loading>Enviando...</span>
            </button>
        </form>

        <a href="{{ route('login') }}" class="back-link">Voltar ao login</a>
    @endif
</div>
