$sshExe = "C:\Windows\System32\OpenSSH\ssh.exe"
$hosts = @("dev.mework360.com.br", "deployer.mework360.com.br")
$user = "mecloud360"

foreach ($h in $hosts) {
    Write-Host "`n=== Testing SSH to $h ===" -ForegroundColor Cyan
    $result = & $sshExe -o ConnectTimeout=10 -o BatchMode=yes -o StrictHostKeyChecking=no "$user@$h" echo SSH_OK 2>&1
    if ($result -match "SSH_OK") {
        Write-Host "SUCCESS: acesso OK em $h" -ForegroundColor Green
    } elseif ($result -match "Permission denied") {
        Write-Host "FAIL: chave nao autorizada em $h" -ForegroundColor Red
    } else {
        Write-Host "FAIL (rc=$LASTEXITCODE): $result" -ForegroundColor Yellow
    }
}
