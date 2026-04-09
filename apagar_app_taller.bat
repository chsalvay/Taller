@echo off
setlocal EnableExtensions

set "APP_DIR=%~dp0"
cd /d "%APP_DIR%"

echo Deteniendo servidor web de APP_TALLER (puerto 8000)...
set "WEB_FOUND="
for /f "tokens=5" %%a in ('netstat -ano ^| findstr ":8000" ^| findstr "LISTENING"') do (
    set "WEB_FOUND=1"
    taskkill /PID %%a /F >nul 2>&1
)

if not defined WEB_FOUND (
    echo No habia proceso escuchando en 8000.
) else (
    echo Servidor web detenido.
)

echo Deteniendo MariaDB local (mariadbd.exe en puerto 3306)...
set "DB_FOUND="
for /f "tokens=5" %%a in ('netstat -ano ^| findstr ":3306" ^| findstr "LISTENING"') do (
    tasklist /FI "PID eq %%a" | find /I "mariadbd.exe" >nul
    if not errorlevel 1 (
        set "DB_FOUND=1"
        taskkill /PID %%a /F >nul 2>&1
    )
)

if not defined DB_FOUND (
    echo No habia mariadbd.exe escuchando en 3306.
) else (
    echo MariaDB local detenida.
)

echo APP_TALLER detenida.
exit /b 0
