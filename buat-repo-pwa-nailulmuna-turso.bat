@echo off
title Buat repo GitHub — pwa_nailulmuna-turso
cd /d "%~dp0"
set "PATH=C:\Program Files\Git\bin;C:\Program Files\GitHub CLI;%PATH%"

echo.
echo === Buat repo calavy/pwa_nailulmuna-turso ===
echo.

where gh >nul 2>&1
if not errorlevel 1 (
    echo Mencoba buat repo via GitHub CLI...
    gh repo create pwa_nailulmuna-turso --public --source=. --remote=origin --push
    if not errorlevel 1 (
        echo BERHASIL via gh.
        pause
        exit /b 0
    )
    echo gh gagal — lanjut buat manual di browser.
    echo.
)

start "" "https://github.com/new?name=pwa_nailulmuna-turso&description=Varian+Turso+PWA+Nailul+Muna"
echo.
echo Di browser:
echo   1. Pastikan nama: pwa_nailulmuna-turso
echo   2. JANGAN centang Add README
echo   3. Create repository
echo.
pause

git remote set-url origin https://github.com/calavy/pwa_nailulmuna-turso.git
git push -u origin main
pause
