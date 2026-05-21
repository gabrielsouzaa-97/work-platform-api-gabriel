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
        .warning { background: #fff5f5; border-left: 3px solid #fc8181; padding: .75rem 1rem; border-radius: 0 4px 4px 0; font-size: .875rem; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Redefinicao de Senha</h1>
        <p>Ola, <strong>{{ $operator->name }}</strong>!</p>
        <p>
            Recebemos uma solicitacao para redefinir a senha da sua conta no painel meWork360.
            Clique no botao abaixo para definir uma nova senha:
        </p>
        <a href="{{ $resetUrl }}" class="btn">Redefinir minha senha</a>
        <p class="warning">
            <strong>Atencao:</strong> Este link expira em <strong>60 minutos</strong>.
            Se voce nao solicitou a redefinicao de senha, ignore este e-mail — sua senha permanece inalterada.
        </p>
        <p class="url-fallback">
            Se o botao nao funcionar, copie e cole este link no navegador:<br>
            {{ $resetUrl }}
        </p>
        <div class="footer">
            Este e-mail foi gerado automaticamente pelo sistema meWork360.<br>
            Nao responda a este e-mail.
        </div>
    </div>
</body>
</html>
