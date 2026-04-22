@echo off
setlocal

set "BASE_DIR=%~dp0"
if "%BASE_DIR:~-1%"=="\" set "BASE_DIR=%BASE_DIR:~0,-1%"
set "TARGET_SCRIPT=%BASE_DIR%\APP_TALLER\apagar_app_taller.bat"

if not exist "%TARGET_SCRIPT%" (
    echo.
    echo [ERROR] No se encontro el script objetivo:
    echo         %TARGET_SCRIPT%
    exit /b 1
)

echo.
echo [APP_TALLER] Delegando apagado a APP_TALLER\apagar_app_taller.bat ...
call "%TARGET_SCRIPT%"
exit /b %errorlevel%
