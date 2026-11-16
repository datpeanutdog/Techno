$ErrorActionPreference = 'Stop'

$wifiAddress = Get-NetIPAddress -AddressFamily IPv4 -InterfaceAlias 'Wi-Fi' -AddressState Preferred -ErrorAction SilentlyContinue |
    Where-Object { $_.IPAddress -notlike '169.254.*' } |
    Select-Object -First 1 -ExpandProperty IPAddress

if (-not $wifiAddress) {
    throw 'No active Wi-Fi IPv4 address was found. Connect this PC to Wi-Fi and try again.'
}

$portsInUse = @(8000, 8081) | Where-Object {
    Get-NetTCPConnection -State Listen -LocalPort $_ -ErrorAction SilentlyContinue
}

if ($portsInUse) {
    throw "Port(s) $($portsInUse -join ', ') are already in use. Close the existing Laravel/Expo server windows and try again."
}

$quotedRoot = "'" + $PSScriptRoot.Replace("'", "''") + "'"
$laravelCommand = "Set-Location -LiteralPath $quotedRoot; php artisan serve --host=0.0.0.0 --port=8000"
$expoCommand = "`$env:REACT_NATIVE_PACKAGER_HOSTNAME='$wifiAddress'; Set-Location -LiteralPath '$($PSScriptRoot.Replace("'", "''"))\mobile'; npm start -- --lan"

Start-Process powershell.exe -ArgumentList "-NoExit -ExecutionPolicy Bypass -Command `"$laravelCommand`""
Start-Process powershell.exe -ArgumentList "-NoExit -ExecutionPolicy Bypass -Command `"$expoCommand`""

Write-Output "PC website: http://localhost:8000"
Write-Output "Phone website/API: http://$wifiAddress`:8000"
Write-Output "Expo Go: exp://$wifiAddress`:8081"
Write-Output 'Leave both server windows open while using the apps.'
