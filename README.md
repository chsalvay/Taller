# APP_TALLER

Aplicacion PHP con base de datos MySQL (XAMPP) para gestion de taller.

## Requisitos

- Windows
- PHP disponible en PATH
- XAMPP instalado en `C:\xampp`

## Inicio rapido

1. Ejecuta `levantar_app_taller.bat`.
2. El script inicia MySQL, sincroniza `database/schema.sql`, levanta PHP y abre el navegador.
3. Accede a `http://127.0.0.1:8000/index.php`.

Credenciales iniciales:

- Usuario: `Admin`
- Contrasena: `123456`

## Apagado

Ejecuta `apagar_app_taller.bat` para cerrar el servidor PHP y solicitar la detencion de MySQL.

## Salud del sistema

Consulta `http://127.0.0.1:8000/health.php` para validar conexion con la base de datos.
