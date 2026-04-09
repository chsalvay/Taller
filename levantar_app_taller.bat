@echo off
setlocal EnableExtensions EnableDelayedExpansion

set "APP_DIR=%~dp0"
cd /d "%APP_DIR%"

set "PHP_EXE="
for %%P in (
    "%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
    "C:\xampp\php\php.exe"
    "C:\php\php.exe"
) do (
    if exist "%%~P" (
        set "PHP_EXE=%%~P"
        goto :php_found
    )
)

echo [ERROR] No se encontro php.exe. Instala PHP o XAMPP.
pause
exit /b 1

:php_found
set "MARIADB_BIN=C:\Program Files\MariaDB 12.2\bin"
set "MARIADB_INSTALL_DB=%MARIADB_BIN%\mariadb-install-db.exe"
set "MARIADBD_EXE=%MARIADB_BIN%\mariadbd.exe"
set "MARIADB_CLIENT=%MARIADB_BIN%\mariadb.exe"

set "DATA_DIR=%APP_DIR%storage\mariadb-data"
set "LOG_DIR=%APP_DIR%storage\mariadb-logs"
set "LOG_FILE=%LOG_DIR%\mariadb.log"

if not exist "%APP_DIR%.env" (
    if exist "%APP_DIR%.env.example" (
        copy /Y "%APP_DIR%.env.example" "%APP_DIR%.env" >nul
    )
)

if not exist "%DATA_DIR%" mkdir "%DATA_DIR%"
if not exist "%LOG_DIR%" mkdir "%LOG_DIR%"

if not exist "%DATA_DIR%\mysql" (
    if not exist "%MARIADB_INSTALL_DB%" (
        echo [ERROR] No se encontro mariadb-install-db.exe en "%MARIADB_BIN%".
        echo Instala MariaDB.Server o ajusta la ruta en este .bat.
        pause
        exit /b 1
    )
    echo Inicializando MariaDB local...
    "%MARIADB_INSTALL_DB%" -d "%DATA_DIR%"
    if errorlevel 1 (
        echo [ERROR] Fallo la inicializacion de MariaDB.
        pause
        exit /b 1
    )
)

for /f "tokens=5" %%a in ('netstat -ano ^| findstr ":3306" ^| findstr "LISTENING"') do set "DB_PID=%%a"
if "%DB_PID%"=="" (
    if not exist "%MARIADBD_EXE%" (
        echo [ERROR] No se encontro mariadbd.exe en "%MARIADB_BIN%".
        pause
        exit /b 1
    )
    echo Iniciando MariaDB local en 127.0.0.1:3306...
    start "APP_TALLER_DB" /min "%MARIADBD_EXE%" --datadir="%DATA_DIR%" --bind-address=127.0.0.1 --port=3306 --log-error="%LOG_FILE%"
)

set "DB_READY="
for /L %%i in (1,1,15) do (
    "%MARIADB_CLIENT%" -h 127.0.0.1 -P 3306 -u root -e "SELECT 1;" >nul 2>&1
    if not errorlevel 1 (
        set "DB_READY=1"
        goto :db_ready
    )
    timeout /t 1 /nobreak >nul
)

:db_ready
if "%DB_READY%"=="" (
    echo [ERROR] MariaDB no quedo disponible en puerto 3306.
    echo Revisa el log: %LOG_FILE%
    pause
    exit /b 1
)

echo Sincronizando schema...
"%MARIADB_CLIENT%" -h 127.0.0.1 -P 3306 -u root -e "SOURCE %APP_DIR%database\schema.sql"
if errorlevel 1 (
    echo [ERROR] Fallo la importacion de schema.sql
    pause
    exit /b 1
)

for /f "tokens=5" %%a in ('netstat -ano ^| findstr ":8000" ^| findstr "LISTENING"') do (
    taskkill /PID %%a /F >nul 2>&1
)

echo Iniciando APP_TALLER en http://localhost:8000 ...
start "APP_TALLER_WEB" /min "%PHP_EXE%" -S localhost:8000 -t "%APP_DIR%public"

start "" "http://localhost:8000/"
echo APP_TALLER iniciado.
exit /b 0
