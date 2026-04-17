@echo off
setlocal

set "PROJECT_ROOT=%~dp0"
if "%PROJECT_ROOT:~-1%"=="\" set "PROJECT_ROOT=%PROJECT_ROOT:~0,-1%"
set "XAMPP_ROOT=C:\xampp"
set "MYSQL_START=%XAMPP_ROOT%\mysql_start.bat"
set "MYSQL_CLI=%XAMPP_ROOT%\mysql\bin\mysql.exe"

echo.
echo [APP_TALLER] Iniciando entorno local...

where php >nul 2>&1
if errorlevel 1 (
	echo [ERROR] No se encontro PHP en PATH.
	echo         Instala PHP o abre este script desde una terminal con PHP disponible.
	exit /b 1
)

if not exist "%MYSQL_START%" (
	echo [ERROR] No se encontro mysql_start.bat en %XAMPP_ROOT%.
	echo         Verifica la instalacion de XAMPP.
	exit /b 1
)

netstat -ano | findstr ":3306" | findstr "LISTENING" >nul
if errorlevel 1 (
	echo [INFO] Iniciando MySQL de XAMPP...
	start "APP_TALLER_MYSQL" cmd /c "\"%MYSQL_START%\""

	for /L %%i in (1,1,20) do (
		powershell -NoProfile -Command "try { $c = New-Object System.Net.Sockets.TcpClient; $c.Connect('127.0.0.1',3306); if ($c.Connected) { $c.Close(); exit 0 } else { exit 1 } } catch { exit 1 }" >nul 2>&1
		if not errorlevel 1 goto mysql_ready
		timeout /t 1 /nobreak >nul
	)

	echo [WARN] MySQL no respondio en 3306 dentro del tiempo esperado.
) else (
	echo [INFO] MySQL ya estaba activo en 3306.
)

:mysql_ready
if exist "%MYSQL_CLI%" (
	if exist "%PROJECT_ROOT%\database\schema.sql" (
		echo [INFO] Sincronizando esquema de base de datos...
		"%MYSQL_CLI%" -u root < "%PROJECT_ROOT%\database\schema.sql"
		if errorlevel 1 (
			echo [WARN] No se pudo importar schema.sql automaticamente.
			echo        Verifica credenciales de root en MySQL.
		) else (
			echo [OK] Esquema listo.
		)
	)
) else (
	echo [WARN] No se encontro mysql.exe en XAMPP. Se omite importacion de esquema.
)

netstat -ano | findstr ":8000" | findstr "LISTENING" >nul
if errorlevel 1 (
	echo [INFO] Iniciando servidor web en http://127.0.0.1:8000 ...
	start "APP_TALLER_PHP" cmd /k "cd /d \"%PROJECT_ROOT%\" && php -S 127.0.0.1:8000 -t public"
) else (
	echo [INFO] Ya existe un proceso escuchando en 8000.
)

start "" "http://127.0.0.1:8000/index.php"

echo.
echo [OK] Sistema iniciado.
echo      Usuario: Admin
echo      Contrasena: 123456
echo.
exit /b 0
