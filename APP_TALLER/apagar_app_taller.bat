@echo off
setlocal

set "MYSQL8_BIN=C:\Program Files\MySQL\MySQL Server 8.4\bin"

echo.
echo [APP_TALLER] Cerrando servicios...

for /f "tokens=5" %%p in ('netstat -ano ^| findstr ":8000" ^| findstr "LISTENING"') do (
	taskkill /PID %%p /F >nul 2>&1
)

if exist "%MYSQL8_BIN%\mysqladmin.exe" (
	"%MYSQL8_BIN%\mysqladmin.exe" -u root -h 127.0.0.1 -P 3306 shutdown >nul 2>&1
	echo [INFO] MySQL 8.4 detenido.
) else (
	taskkill /IM mysqld.exe /F >nul 2>&1
)

echo [OK] Cierre solicitado.
echo.
exit /b 0
