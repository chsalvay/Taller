@echo off
setlocal

set "MYSQL8_BIN=C:\Program Files\MySQL\MySQL Server 8.4\bin"
set "MYSQL_PORT=3307"

echo.
echo [APP_TALLER] Cerrando servicios...

for /f "tokens=5" %%p in ('netstat -ano ^| findstr ":8000" ^| findstr "LISTENING"') do (
	taskkill /PID %%p /F >nul 2>&1
)

if exist "%MYSQL8_BIN%\mysqladmin.exe" (
	"%MYSQL8_BIN%\mysqladmin.exe" -u root -h 127.0.0.1 -P %MYSQL_PORT% shutdown >nul 2>&1
	if errorlevel 1 (
		echo [WARN] No se pudo cerrar MySQL por mysqladmin en %MYSQL_PORT%. Intentando por PID...
		for /f "tokens=5" %%p in ('netstat -ano ^| findstr ":%MYSQL_PORT%" ^| findstr "LISTENING"') do (
			taskkill /PID %%p /F >nul 2>&1
		)
	) else (
		echo [INFO] MySQL 8.4 detenido en puerto %MYSQL_PORT%.
	)
) else (
	echo [WARN] No se encontro mysqladmin.exe. Intentando cierre por PID en %MYSQL_PORT%...
	for /f "tokens=5" %%p in ('netstat -ano ^| findstr ":%MYSQL_PORT%" ^| findstr "LISTENING"') do (
		taskkill /PID %%p /F >nul 2>&1
	)
)

echo [OK] Cierre solicitado.
echo.
exit /b 0
