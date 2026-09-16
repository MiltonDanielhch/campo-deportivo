# Smoke test de concurrencia real (Fase 4.5, Modulo 4):
# dos POST simultaneos pidiendo EXACTAMENTE la misma franja.
# Esperado: exactamente un 201 (gana) y un 409 (pierde por EXCLUDE).
#
# Uso:  pwsh -File infrastructure/scripts/smoke_concurrencia_reserva.ps1
#       (o desde PowerShell 5: .\infrastructure\scripts\smoke_concurrencia_reserva.ps1)
param(
    [string]$BaseUrl = 'http://localhost:8000/api/v1',
    [string]$Hora = '09:00'
)

$ErrorActionPreference = 'Stop'

# 1) Elegir un campo activo con tarifa vigente desde el endpoint publico
$campos = (Invoke-RestMethod -Uri "$BaseUrl/public/campos").data
$campo = $campos |
    Where-Object { $_.estado -eq 'activo' -and $_.tarifa_vigente -ne $null } |
    Select-Object -First 1

if (-not $campo) {
    throw 'No hay ningun campo activo con tarifa vigente para el smoke test'
}

$fecha = (Get-Date).AddDays(1).ToString('yyyy-MM-dd')
$horaFin = ([datetime]::ParseExact($Hora, 'HH:mm', $null)).AddHours(1).ToString('HH:mm')

$body = @{
    nombre_pagador   = 'Smoke Concurrencia'
    telefono_pagador = '70000001'
    franjas          = @(
        @{
            campo_id    = $campo.id
            fecha       = $fecha
            hora_inicio = $Hora
            hora_fin    = $horaFin
        }
    )
} | ConvertTo-Json -Depth 5

Write-Host "Campo: $($campo.nombre) | Fecha: $fecha | Franja: $Hora-$horaFin"

# 2) Disparar los dos POST al mismo tiempo (jobs paralelos)
$scriptBlock = {
    param($url, $json)
    try {
        $r = Invoke-WebRequest -Uri $url -Method Post -ContentType 'application/json' -Body $json -UseBasicParsing
        return $r.StatusCode
    }
    catch {
        return [int]$_.Exception.Response.StatusCode
    }
}

$j1 = Start-Job -ScriptBlock $scriptBlock -ArgumentList "$BaseUrl/public/solicitudes-reserva", $body
$j2 = Start-Job -ScriptBlock $scriptBlock -ArgumentList "$BaseUrl/public/solicitudes-reserva", $body

$codigos = @()
$codigos += Receive-Job -Job $j1 -Wait
$codigos += Receive-Job -Job $j2 -Wait
Remove-Job -Job $j1, $j2

$codigos = $codigos | Sort-Object
Write-Host "Respuestas HTTP: $($codigos -join ', ')"

if (($codigos -join ',') -eq '201,409') {
    Write-Host 'PASS: una solicitud gano la franja (201) y la otra perdio (409)' -ForegroundColor Green
}
else {
    Write-Host 'FAIL: se esperaba exactamente 201 y 409' -ForegroundColor Red
    exit 1
}
