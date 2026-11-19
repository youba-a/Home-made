# Depuis la racine du projet : . .\outils\activer.ps1
# Ces changements concernent uniquement le terminal courant et ses processus.
& {
    $projectRoot = Split-Path -Parent $PSScriptRoot
    $projectPhpDir = 'C:\php83'
    $projectMysqlDir = 'C:\wamp64\bin\mysql\mysql8.3.0\bin'
    $projectPhpConfig = Join-Path $projectRoot '.local\php'

    foreach ($requiredPath in @(
        (Join-Path $projectPhpDir 'php.exe'),
        (Join-Path $projectMysqlDir 'mysql.exe'),
        (Join-Path $projectPhpConfig 'php.ini')
    )) {
        if (-not (Test-Path -LiteralPath $requiredPath)) {
            throw "Fichier requis introuvable : $requiredPath"
        }
    }

    $otherPaths = @($env:Path -split ';' | Where-Object {
        $_ -and $_ -ne $projectPhpDir -and $_ -ne $projectMysqlDir
    })
    $env:Path = (@($projectPhpDir, $projectMysqlDir) + $otherPaths) -join ';'
    $env:PHPRC = $projectPhpConfig
    # Empeche le chargement d'une configuration supplementaire d'un autre projet.
    $env:PHP_INI_SCAN_DIR = Join-Path $projectPhpConfig 'conf.d'

    Write-Host 'Environnement Home Made active dans ce terminal.' -ForegroundColor Green
    Write-Host 'PHP 8.3 (C:\php83), Composer, Node.js/npm et client MySQL disponibles.'
    Write-Host 'Le serveur MySQL doit etre demarre separement.'
}
