<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: system-ui, sans-serif; background: #f7fafc; color: #2d3748; margin: 0; padding: 2rem; }
        .container { max-width: 520px; margin: 0 auto; background: #fff; border-radius: 8px; padding: 2rem; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
        h1 { font-size: 1.25rem; color: #1a202c; margin-bottom: 1rem; }
        p { line-height: 1.6; margin-bottom: 1rem; }
        .btn { display: inline-block; background: #2b6cb0; color: #fff; text-decoration: none; padding: .75rem 1.5rem; border-radius: 6px; font-weight: 600; margin: 1rem 0; }
        .url-fallback { font-size: .8rem; color: #718096; word-break: break-all; }
        .footer { font-size: .8rem; color: #a0aec0; margin-top: 2rem; border-top: 1px solid #e2e8f0; padding-top: 1rem; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Voce foi convidado para o meWork360</h1>
        <p>Ola, <strong>{{ $operator->name }}</strong>!</p>
        <p>
            Um administrador criou uma conta para voce no painel de operacoes meWork360
            com o perfil <strong>{{ $operator->role }}</strong>.
        </p>
        <p>Clique no botao abaixo para definir sua senha e ativar o acesso:</p>
        <a href="{{ $signedUrl }}" class="btn">Ativar minha conta</a>
        <p>
            <strong>Atencao:</strong> Este link expira em <strong>48 horas</strong>.
            Apos este prazo, solicite ao administrador um reenvio.
        </p>
        <p class="url-fallback">
            Se o botao nao funcionar, copie e cole este link no navegador:<br>
            {{ $signedUrl }}
        </p>
        <div class="footer">
            Este e-mail foi gerado automaticamente pelo sistema meWork360.<br>
            Nao responda a este e-mail.
        </div>
    </div>
</body>
</html>
