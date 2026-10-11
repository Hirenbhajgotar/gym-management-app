$ErrorActionPreference = "Continue"

$outputFile = "docs\codebase\.codebase-scan.txt"
$outDir = [System.IO.Path]::GetDirectoryName($outputFile)
if (-not (Test-Path $outDir)) {
    New-Item -ItemType Directory -Path $outDir -Force | Out-Null
}

$sb = [System.Text.StringBuilder]::new()

function Add-Section($title, $content) {
    [void]$sb.AppendLine("`n=== $title ===")
    if ($content -is [array]) {
        if ($content.Count -gt 0) {
            foreach ($line in $content) {
                [void]$sb.AppendLine($line)
            }
        } else {
            [void]$sb.AppendLine("None found.")
        }
    } elseif ($null -ne $content -and $content.ToString().Trim().Length -gt 0) {
        [void]$sb.AppendLine($content.ToString())
    } else {
        [void]$sb.AppendLine("None found.")
    }
}

# 1. Directory Tree
$excludeDirs = @("node_modules", ".git", "dist", "build", "out", ".next", ".nuxt", "__pycache__", ".venv", "venv", ".tox", "target", "vendor", "coverage", ".nyc_output", "generated", ".cache", ".turbo", ".yarn", ".pnp", "bin", "obj", ".gemini", ".sisyphus", ".ocr")
$allFiles = Get-ChildItem -Path . -Recurse -Depth 3 | Where-Object {
    $rel = $_.FullName.Substring((Get-Location).Path.Length).TrimStart('\', '/')
    $parts = $rel.Split([char[]]@('\', '/'))
    $excluded = $false
    foreach ($p in $parts) {
        if ($excludeDirs -contains $p) {
            $excluded = $true
            break
        }
    }
    -not $excluded
} | Select-Object -First 200 | ForEach-Object {
    $_.FullName.Substring((Get-Location).Path.Length).TrimStart('\', '/') -replace '\\', '/'
}
Add-Section "DIRECTORY TREE (max depth 3, source files only)" $allFiles

# 2. Manifest files
$manifests = @("package.json", "package-lock.json", "yarn.lock", "pnpm-lock.yaml", "bun.lockb", "composer.json", "composer.lock", "Makefile", "Dockerfile", "docker-compose.yml")
$manifestContent = @()
foreach ($m in $manifests) {
    if (Test-Path $m) {
        $manifestContent += "--- $m ---"
        $lines = Get-Content $m -TotalCount 80
        $manifestContent += ($lines -join "`n")
    }
}
Add-Section "STACK DETECTION (manifest files)" $manifestContent

# 3. Entry Points
$entries = @("src/index.ts", "src/index.js", "src/main.ts", "src/main.js", "index.php", "public/index.php", "artisan")
$foundEntries = @()
foreach ($e in $entries) {
    if (Test-Path $e) {
        $foundEntries += "Found: $e"
    }
}
Add-Section "ENTRY POINTS" $foundEntries

# 4. Linting and formatting
$lintFiles = @(".eslintrc", ".eslintrc.json", ".editorconfig", "tsconfig.json", "pint.json", "phpstan.neon", "rector.php")
$foundLint = @()
foreach ($l in $lintFiles) {
    if (Test-Path $l) {
        $foundLint += "Found: $l"
    }
}
Add-Section "LINTING AND FORMATTING CONFIG" $foundLint

# 5. Environment templates
$envs = @(".env.example", ".env.template", ".env.sample", ".env.testing")
$envContent = @()
foreach ($envFile in $envs) {
    if (Test-Path $envFile) {
        $envContent += "--- $envFile ---"
        $lines = Get-Content $envFile -TotalCount 80
        $envContent += ($lines -join "`n")
    }
}
Add-Section "ENVIRONMENT VARIABLE TEMPLATES" $envContent

# 6. TODOs
$todoPatterns = @("TODO", "FIXME", "HACK")
$foundTodos = @()
$srcFiles = Get-ChildItem -Path . -Recurse -Include *.php,*.js,*.ts,*.json | Where-Object {
    $rel = $_.FullName.Substring((Get-Location).Path.Length).TrimStart('\', '/')
    $parts = $rel.Split([char[]]@('\', '/'))
    $excluded = $false
    $checkDirs = $excludeDirs + @("tests", "test", "__tests__")
    foreach ($p in $parts) {
        if ($checkDirs -contains $p) {
            $excluded = $true
            break
        }
    }
    -not $excluded
}

foreach ($f in $srcFiles) {
    if ($foundTodos.Count -ge 60) { break }
    $lines = Get-Content $f.FullName
    $lineNum = 1
    $rel = $f.FullName.Substring((Get-Location).Path.Length).TrimStart('\', '/') -replace '\\', '/'
    foreach ($line in $lines) {
        if ($foundTodos.Count -ge 60) { break }
        foreach ($pat in $todoPatterns) {
            if ($line -match $pat) {
                $foundTodos += "$($rel):$($lineNum): $($line.Trim())"
                break
            }
        }
        $lineNum++
    }
}
Add-Section "TODO / FIXME / HACK (production code only, test dirs excluded)" $foundTodos

# 7. Git info
$commits = git log --oneline -n 20 2>$null
Add-Section "GIT RECENT COMMITS (last 20)" $commits

$churn = git log --since="90 days ago" --name-only --pretty=format: 2>$null | Where-Object { $_.Trim() -ne "" } | Group-Object | Sort-Object Count -Descending | Select-Object -First 20 | ForEach-Object { "{0,4} {1}" -f $_.Count, $_.Name }
Add-Section "HIGH-CHURN FILES (last 90 days, top 20)" $churn

# 8. Monorepo
Add-Section "MONOREPO SIGNALS" @("No monorepo signals detected.")

# 9. Metrics
$metricsOutput = @()
$allCountedFiles = Get-ChildItem -Path . -Recurse -File | Where-Object {
    $rel = $_.FullName.Substring((Get-Location).Path.Length).TrimStart('\', '/')
    $parts = $rel.Split([char[]]@('\', '/'))
    $excluded = $false
    foreach ($p in $parts) {
        if ($excludeDirs -contains $p) { $excluded = $true; break }
    }
    -not $excluded
}

$byExt = $allCountedFiles | Group-Object Extension | Sort-Object Count -Descending
$totalLines = 0
foreach ($f in $allCountedFiles) {
    if ($f.Length -lt 1000000 -and @(".php", ".js", ".ts", ".json", ".css", ".blade.php") -contains $f.Extension) {
        try {
            $totalLines += (Get-Content $f.FullName | Measure-Object -Line).Lines
        } catch {}
    }
}

$metricsOutput += "Total files scanned: $($allCountedFiles.Count)"
$metricsOutput += "Total lines of code: $totalLines"
$metricsOutput += ""
$metricsOutput += "Files by extension:"
foreach ($g in $byExt) {
    $ext = if ($g.Name) { $g.Name } else { "[no extension]" }
    $metricsOutput += "  $ext: $($g.Count)"
}
$metricsOutput += ""
$metricsOutput += "Top 10 largest files:"
$largest = $allCountedFiles | Sort-Object Length -Descending | Select-Object -First 10
foreach ($lf in $largest) {
    $rel = $lf.FullName.Substring((Get-Location).Path.Length).TrimStart('\', '/') -replace '\\', '/'
    $sizeKb = [math]::Round($lf.Length / 1024, 1)
    $metricsOutput += "$rel`: $sizeKb KB"
}
Add-Section "CODE METRICS" $metricsOutput

# 10. CI/CD
$ci = @()
if (Test-Path ".github\workflows") { $ci += "CI/CD: GitHub Actions" }
Add-Section "CI/CD PIPELINES" $ci

# 11. Containers
$containers = @()
if (Test-Path "Dockerfile") { $containers += "Container: Docker found" }
if (Test-Path "docker-compose.yml") { $containers += "Orchestration: Docker Compose found" }
Add-Section "CONTAINERS & ORCHESTRATION" $containers

# 12. Security
$sec = @()
if (Test-Path ".github\dependabot.yml") { $sec += "Security: dependabot" }
Add-Section "SECURITY & COMPLIANCE" $sec

# 13. Performance
Add-Section "PERFORMANCE & TESTING" @("No performance testing configs detected.")

[void]$sb.AppendLine("`n=== SCAN COMPLETE ===`n")

[System.IO.File]::WriteAllText($outputFile, $sb.ToString(), [System.Text.Encoding]::UTF8)
Write-Output "Successfully wrote scan to $outputFile"
