Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Invoke-Phase2Request {
    param(
        [Parameter(Mandatory=$true)][string]$Method,
        [Parameter(Mandatory=$true)][string]$Url,
        [hashtable]$Headers,
        [object]$Body,
        [int]$TimeoutSec = 30
    )

    $start = Get-Date
    $params = @{
        Method = $Method
        Uri = $Url
        TimeoutSec = $TimeoutSec
        ErrorAction = 'Stop'
    }

    if ($Headers) {
        $params.Headers = $Headers
    }

    if ($null -ne $Body) {
        if ($Body -is [string]) {
            $params.Body = $Body
        } else {
            $params.Body = ($Body | ConvertTo-Json -Depth 8)
        }
        if (-not $params.Headers) {
            $params.Headers = @{}
        }
        if (-not $params.Headers.ContainsKey('Content-Type')) {
            $params.Headers['Content-Type'] = 'application/json'
        }
    }

    try {
        $response = Invoke-WebRequest @params
        $elapsedMs = [math]::Round(((Get-Date) - $start).TotalMilliseconds, 2)
        return [PSCustomObject]@{
            Success = $true
            StatusCode = [int]$response.StatusCode
            Body = $response.Content
            ElapsedMs = $elapsedMs
            Error = ''
        }
    } catch {
        $elapsedMs = [math]::Round(((Get-Date) - $start).TotalMilliseconds, 2)
        $status = 0
        $body = ''
        if ($_.Exception.Response -and $_.Exception.Response.StatusCode) {
            $status = [int]$_.Exception.Response.StatusCode
            try {
                $stream = $_.Exception.Response.GetResponseStream()
                if ($stream) {
                    $reader = New-Object System.IO.StreamReader($stream)
                    $body = $reader.ReadToEnd()
                }
            } catch {
                $body = ''
            }
        }
        return [PSCustomObject]@{
            Success = $false
            StatusCode = $status
            Body = $body
            ElapsedMs = $elapsedMs
            Error = $_.Exception.Message
        }
    }
}

function Convert-BodyToJson {
    param([string]$Body)

    if ([string]::IsNullOrWhiteSpace($Body)) {
        return $null
    }

    try {
        return $Body | ConvertFrom-Json -ErrorAction Stop
    } catch {
        return $null
    }
}
