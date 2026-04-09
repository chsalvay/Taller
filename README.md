# APP_TALLER

Proyecto base en PHP + MySQL para gestion de un taller.

## Acceso al sistema

- Usuario: `Admin`
- Contrasena: `123456`
- Rol: `Admin`

El acceso se valida contra tablas en la base de datos (`usuarios` y `roles`).

## 1) Requisitos

- PHP 8.1 o superior
- Extension PDO MySQL habilitada
- MySQL 8+ o MariaDB
- Opcional: XAMPP

## 2) Configuracion inicial

1. Copiar variables de entorno:
   - Copiar `.env.example` a `.env`
2. Ajustar datos de MySQL en `.env`:
   - `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
3. Crear la base de datos y tabla:
   - Ejecutar `database/schema.sql` desde phpMyAdmin o consola MySQL

## 3) Ejecutar con servidor embebido de PHP

Desde la carpeta del proyecto:

```powershell
cd C:\Users\Chris\Desktop\Taller\APP_TALLER
php -S localhost:8000 -t public
```

Abrir:

- http://localhost:8000/
- http://localhost:8000/health.php

## 4) Ejecutar con XAMPP

1. Copiar `APP_TALLER` dentro de `htdocs` (o crear un VirtualHost apuntando a `public`).
2. Iniciar Apache y MySQL en XAMPP.
3. Importar `database/schema.sql` en phpMyAdmin.
4. Abrir:
   - http://localhost/APP_TALLER/public/
   - http://localhost/APP_TALLER/public/health.php

## 5) Notas de seguridad basicas

- No subir `.env` al repositorio.
- Usar sentencias preparadas (PDO) para todas las consultas.
- Cambiar credenciales por defecto en produccion.
- Desactivar `APP_DEBUG` en produccion.

## 6) Modulos disponibles tras login

Al iniciar sesion correctamente, se accede al panel principal con estos botones:

- Compras
- Clientes
- Ordenes de Trabajo

El usuario con rol Admin accede a todos los modulos.

## 7) Inicio en un clic (.bat)

Se incluye el script `levantar_app_taller.bat` para arrancar todo automaticamente.

Que hace:

- Crea `.env` desde `.env.example` si falta.
- Inicializa MariaDB local en `storage/mariadb-data` (solo la primera vez).
- Levanta MariaDB local en `127.0.0.1:3306`.
- Importa `database/schema.sql` si la tabla principal no existe.
- Reinicia el servidor web PHP y levanta `http://localhost:8000`.

Uso:

```powershell
cd C:\Users\Chris\Desktop\Taller\APP_TALLER
.\levantar_app_taller.bat
```

## 8) Apagado en un clic (.bat)

Se incluye el script `apagar_app_taller.bat` para detener la app.

Que hace:

- Detiene el servidor web que escucha en el puerto `8000`.
- Detiene `mariadbd.exe` que escucha en el puerto `3306`.

Uso:

```powershell
cd C:\Users\Chris\Desktop\Taller\APP_TALLER
.\apagar_app_taller.bat
```
