@echo off
title Reset login GitHub — calavy
cd /d "%~dp0"
set "PATH=C:\Program Files\Git\bin;%PATH%"

echo.
echo === Reset login GitHub ===
echo Repo: https://github.com/calavy/pwa_nailulmuna-turso
echo.
cmdkey /delete:git:https://github.com 2>nul
if errorlevel 1 (
    echo Entri git:https://github.com tidak ada atau sudah dihapus.
) else (
    echo Entri git:https://github.com dihapus.
)
echo.
echo Push lagi — username: calavy, password: PAT ^(bukan password login^)
git push -u origin main
echo.
pause
