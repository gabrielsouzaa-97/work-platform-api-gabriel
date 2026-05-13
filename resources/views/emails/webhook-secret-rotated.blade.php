<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="utf-8"><title>Webhook secret rotacionado</title></head>
<body style="font-family:sans-serif;color:#1a202c;max-width:520px;margin:2rem auto;padding:1.5rem;border:1px solid #e2e8f0;border-radius:8px">
    <h2 style="color:#2b6cb0;margin-bottom:1rem">Webhook Secret Rotacionado</h2>
    <p>O webhook secret do cluster <strong>{{ $cluster->name }}</strong> foi rotacionado.</p>
    <table style="border-collapse:collapse;width:100%;margin:1rem 0;font-size:.9rem">
        <tr>
            <td style="padding:.5rem;color:#718096;width:180px">Cluster</td>
            <td style="padding:.5rem"><strong>{{ $cluster->name }}</strong></td>
        </tr>
        <tr style="background:#f7fafc">
            <td style="padding:.5rem;color:#718096">Nova versão</td>
            <td style="padding:.5rem">v{{ $newHistory->version }}</td>
        </tr>
        <tr>
            <td style="padding:.5rem;color:#718096">Versão anterior válida até</td>
            <td style="padding:.5rem;color:#e53e3e">
                {{ optional($newHistory->valid_from)->subSeconds(1)?->format('d/m/Y H:i') ?? '—' }}
                (grace: {{ config('services.webhook.grace_period_hours', 24) }}h)
            </td>
        </tr>
    </table>
    <p style="color:#718096;font-size:.85rem">Reconfigure o upstream com o novo secret antes do grace period expirar.</p>
</body>
</html>
