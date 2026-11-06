@echo off
chcp 65001 >nul
title NewFoobot - Foobot Management Tool
cls

echo ================================================================================
echo   NEWFOOBOT - Foobot Network Configuration Tool (EN / IT)
echo ================================================================================
echo.
echo Starting Foobot Management Tool...
echo.

powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0foobot_manager.ps1"

echo.
pause
