$local = Get-NetIPAddress -AddressFamily IPv4 |
    Where-Object { $_.IPAddress -notlike '127.*' -and $_.IPAddress -notlike '169.254.*' } |
    Select-Object -First 1 -ExpandProperty IPAddress

$public = try { (Invoke-RestMethod -Uri 'https://api.ipify.org?format=json' -TimeoutSec 10).ip } catch { 'unknown' }

Write-Host 'Neiro Brain network information'
Write-Host '--------------------------------'
Write-Host "Local IP:  $local"
Write-Host "Public IP: $public"
Write-Host ''
Write-Host "Local URL:   http://neiro-brain.local/"
Write-Host "LAN URL:     http://$local/"
Write-Host "Public URL:  http://$public/  (after router port forwarding)"
Write-Host "Admin:       http://neiro-brain.local/admin"
Write-Host "MCP:         http://neiro-brain.local/mcp/knowledge"
Write-Host ''
Write-Host 'Router port forwarding:'
Write-Host "  TCP 80  -> $local:80"
Write-Host "  TCP 443 -> $local:443"
Write-Host ''
Write-Host 'If the public IP changes, update server_aliases in .osp\project.ini and restart OpenServer.'
