@echo off
setlocal

set "XAMPP_ROOT=C:\xampp"
set "MYSQL_STOP=%XAMPP_ROOT%\mysql_stop.bat"

echo.
echo [APP_TALLER] Cerrando servicios...

for /f "tokens=5" %%p in ('netstat -ano ^| findstr ":8000" ^| findstr "LISTENING"') do (
	taskkill /PID %%p /F >nul 2>&1
)

if exist "%MYSQL_STOP%" (
	start "APP_TALLER_MYSQL_STOP" cmd /c "\"%MYSQL_STOP%\""
	echo [INFO] Solicitud de detencion de MySQL enviada.
) else (
	echo [WARN] No se encontro mysql_stop.bat en %XAMPP_ROOT%.
)

rem Refuerzo: si MySQL fue iniciado en consola separada, cerrarlo por proceso.
taskkill /IM mysqld.exe /F >nul 2>&1
taskkill /IM mariadbd.exe /F >nul 2>&1

echo [OK] Cierre solicitado.
echo.
exit /b 0
