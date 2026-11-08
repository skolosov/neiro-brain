#Requires -RunAsAdministrator
<#
    Opens TCP 80/443 in Windows Firewall for the Neiro Brain OpenServer project.
    Run from an elevated PowerShell:
        powershell -ExecutionPolicy Bypass -File scripts\open-firewall.ps1
#>

$rules = @(
    @{ Name = 'Neiro Brain HTTP 80';  Port = 80  },
    @{ Name = 'Neiro Brain HTTPS 443'; Port = 443 }
)

foreach ($rule in $rules) {
    if (Get-NetFirewallRule -DisplayName $rule.Name -ErrorAction SilentlyContinue) {
        Write-Host "exists: $($rule.Name)"
        continue
    }

    New-NetFirewallRule `
        -DisplayName $rule.Name `
        -Direction Inbound `
        -Action Allow `
        -Protocol TCP `
        -LocalPort $rule.Port `
        -Profile Any | Out-Null

    Write-Host "created: $($rule.Name) (TCP $($rule.Port))"
}

Write-Host ''
Write-Host 'Windows Firewall rules are ready. Now configure port forwarding on the router:'
Write-Host '  TCP 80  -> 192.168.0.107:80'
Write-Host '  TCP 443 -> 192.168.0.107:443'
