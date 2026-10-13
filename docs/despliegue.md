# Despliegue — Olimpiada Puno 2026

Guía para publicar el sistema. Está escrita **sin conocer todavía el proveedor**: los pasos
valen para un hosting compartido con cPanel y para un VPS; donde difieren hay dos variantes.
Cuando se elija el hosting, completar la sección 1 y borrar la variante que no aplique.

## 1. Datos del hosting (pendiente del cliente)

| Dato | Valor |
|---|---|
| Proveedor y plan | _por definir_ |
| Tipo | _compartido con cPanel / VPS_ |
| Dominio o subdominio | _por definir_ |
| Versión de PHP | _por definir_ (mínimo 8.2) |
| MySQL o MariaDB y versión | _por definir_ (probado en MariaDB 10.4) |
| ¿Acceso por SSH? | _por definir_ |
| ¿Tareas programadas (cron)? | _por definir_ |

## 2. Qué debe cumplir el hosting

Preguntar o comprobar **antes** de contratar. Las cuatro primeras son obligatorias.

| # | Requisito | Por qué |
|---|---|---|
| 1 | PHP 8.2 o superior con `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `gd`, `zip`, `bcmath`, `ctype`, `xml`, `zlib` | Laravel 12; `gd` para las fotos en carnets y fichas PDF; `zlib` para comprimir el respaldo |
| 2 | Poder apuntar el dominio a la carpeta `public/` del proyecto | Nada fuera de `public/` debe ser accesible desde el navegador (ahí están el `.env` y los respaldos) |
| 3 | Certificado HTTPS (Let's Encrypt u otro) | Requisito N1; el sistema fuerza HTTPS en producción |
| 4 | Usuario de base de datos con permiso **TRIGGER** | Las migraciones crean dos triggers. En algunos hostings con registro binario activo hace falta además `log_bin_trust_function_creators=1`: si `migrate` falla en el archivo `add_discipline_sport_validation_triggers`, pedir al proveedor que lo active |
| 5 | Cron o "tareas programadas" | Respaldo diario automático. Sin cron, el respaldo se hace a mano |
| 6 | `proc_open` habilitado y `mysqldump` disponible | El comando de respaldo los usa. Si el hosting los bloquea, usar el respaldo de base de datos del propio panel |
| 7 | SSH | No es obligatorio, pero evita subir `vendor/` a mano |

## 3. Preparar el paquete (en la PC de desarrollo)

```bash
git checkout feature/olimpiada-puno-2026      # o main, si ya se fusionó
php artisan test                               # debe terminar sin fallos
npm ci
npm run build                                  # genera public/build/manifest.json
```

`public/build/` **no está en git**: hay que subirlo aparte (o correr `npm run build` en el
servidor si tiene Node).

Verificado en local el 13/10/2026: `config:cache`, `route:cache`, `view:cache` y `event:cache`
terminan sin errores, y `npm run build` genera el manifiesto.

## 4. Subir el código

**Variante A — con SSH (VPS o cPanel con terminal):**

```bash
git clone <repositorio> sistema && cd sistema
composer install --no-dev --optimize-autoloader
# subir public/build desde la PC (scp/rsync) o: npm ci && npm run build
```

**Variante B — sin SSH (solo administrador de archivos o FTP):**

1. En la PC: `composer install --no-dev --optimize-autoloader` en una copia limpia del proyecto.
2. Comprimir todo **menos** `.env`, `node_modules/`, `tests/`, `storage/logs/*.log` y `storage/app/backups/`.
3. Subir y descomprimir **fuera** de `public_html`, por ejemplo en `/home/usuario/sistema`.
4. Apuntar el dominio a `/home/usuario/sistema/public` (cPanel → Dominios → raíz del documento).
   Si el plan no deja cambiar la raíz, copiar el contenido de `public/` a `public_html/` y
   editar en `public_html/index.php` las dos rutas `__DIR__.'/../'` para que apunten a
   `/home/usuario/sistema/`.

## 5. Configurar el `.env`

Copiar `.env.example` a `.env` y completar:

| Variable | Valor |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` (con `true` un error muestra claves y datos) |
| `APP_URL` | `https://el-dominio` |
| `APP_KEY` | se genera con `php artisan key:generate` |
| `DB_*` | datos de la base creada en el hosting |
| `ADMIN_NAME`, `ADMIN_EMAIL`, `ADMIN_PASSWORD` | la primera cuenta de administrador; clave de 8 caracteres o más. Obligatorias: sin ellas el seeder se niega a correr |
| `SESSION_SECURE_COOKIE` | `true` |
| `TRUSTED_PROXIES` | `*` si el hosting tiene un proxy delante (Cloudflare, nginx de cPanel). Ver sección 9 |
| `MYSQLDUMP_PATH` | ruta de `mysqldump` si no está en el PATH |
| `MAIL_*` | datos de correo si se quiere "olvidé mi contraseña"; con `MAIL_MAILER=log` no se envía nada |

## 6. Primer arranque

En este orden. Con SSH se corren tal cual; sin SSH, desde "Terminal" de cPanel o con un cron
de una sola vez por comando.

```bash
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force                          # roles, admin inicial y catálogo de deportes y niveles
php artisan db:seed --class=Puno2026Seeder --force   # olimpiada, sedes, series, 18 delegaciones y cuadro de puntaje
php artisan storage:link                             # fotos y logos
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

- Permisos de escritura para el usuario del servidor web en `storage/` y `bootstrap/cache/`.
- Si `storage:link` falla porque el hosting no permite enlaces simbólicos: crear en cPanel un
  enlace o, en último caso, pedir al proveedor que lo cree
  (`public/storage` → `storage/app/public`).
- `Puno2026Seeder` es no destructivo: si se corre dos veces no duplica ni pisa cambios.
- Después de cambiar el `.env`, repetir `php artisan config:cache`.

Luego, ya en el navegador y como administrador:

1. Crear la cuenta del organizador en **Administración → Usuarios** y asignarlo a la olimpiada
   en **Olimpiadas** (el seeder deja al administrador como organizador si no había ninguno).
2. **Competencias → Generar competencias**; desactivar las pruebas de atletismo que las bases
   no ofrecen en cada nivel.
3. **Fases y llaves → Crear fases estándar** en cada competencia que se vaya a jugar.
4. El organizador crea las cuentas de los delegados en **Delegaciones inscritas**.

## 7. Respaldo

```bash
php artisan db:backup
```

Crea `storage/app/backups/respaldo-AAAA-MM-DD_HHMMSS.sql.gz` (estructura, datos y triggers) y
conserva los 14 más recientes (`BACKUP_KEEP`). Los respaldos tienen DNI y fechas de nacimiento:
no se suben al repositorio ni se dejan en una carpeta pública.

**Programación diaria a las 23:00** (hora del Perú; la aplicación usa `America/Lima`). Una de dos:

```cron
# Opción 1: el programador de Laravel (routes/console.php ya tiene el respaldo a las 23:00)
* * * * * cd /ruta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1

# Opción 2: directo, si el hosting solo permite tareas a hora fija
0 23 * * * /ruta/al/proyecto/scripts/backup-db.sh >> /ruta/al/proyecto/storage/logs/respaldo.log 2>&1
```

Durante el evento (28–30/10, y el 23/10 por el ciclismo): correr además `php artisan db:backup`
**a mano al cerrar cada jornada** y descargar el archivo a una PC o USB. Un respaldo que solo
vive en el mismo servidor no protege si el servidor falla.

### Restaurar

```bash
gunzip -c storage/app/backups/respaldo-AAAA-MM-DD_HHMMSS.sql.gz | mysql -u USUARIO -p BASE
php artisan optimize:clear && php artisan config:cache
```

Restaurar **reemplaza** el contenido de la base por el del respaldo: lo registrado después se
pierde. Si hay duda, restaurar primero en una base nueva y revisar.

**Prueba de restauración (13/10/2026, PC de desarrollo, XAMPP):** el respaldo de la base de
trabajo (45 tablas, 2 triggers, 12 KB comprimido) se restauró en una base vacía en 4,4 s. La
copia tenía las mismas 45 tablas, los 2 triggers, las 26 filas del cuadro, las 18 delegaciones
y ninguna migración pendiente, y la aplicación abrió el puntaje general contra ella.
**Falta repetir esta prueba en el hosting** cuando exista (los permisos y la ruta de
`mysqldump` cambian).

## 8. Actualizar el sistema ya publicado

```bash
php artisan db:backup                 # siempre antes
php artisan down                      # opcional: página de mantenimiento
git pull                              # o subir los archivos cambiados
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

Si cambió algo de la interfaz, subir también `public/build/`. Nunca `migrate:fresh` ni
`migrate:refresh` en producción: borran todo.

## 9. Comprobaciones después de publicar

| # | Comprobar | Si falla |
|---|---|---|
| 1 | `https://dominio/` abre con candado; `http://` redirige | Activar el certificado y la redirección en el panel |
| 2 | `https://dominio/.env` y `https://dominio/storage/app/backups/` dan 404 o 403 | El dominio no apunta a `public/`: corregirlo de inmediato |
| 3 | `https://dominio/up` responde | Revisar `storage/logs/laravel.log` |
| 4 | Entrar con el admin inicial y **cambiar su contraseña** | Revisar `ADMIN_EMAIL` y `ADMIN_PASSWORD` y volver a sembrar |
| 5 | Subir la foto de un participante y verla | Falta `storage:link` o permisos en `storage/` |
| 6 | Generar un carnet PDF, leer su QR con el celular: abre "verificado" | Si da 403: poner `TRUSTED_PROXIES=*` y `php artisan config:cache` |
| 7 | La hora de un registro recién creado es la hora local | Revisar `APP_TIMEZONE` |
| 8 | `php artisan db:backup` crea el archivo | Revisar `MYSQLDUMP_PATH`; si el hosting bloquea `proc_open`, usar el respaldo del panel |
| 9 | `/register` da 404 | No debe existir registro público |

Dependencias externas a tener presentes: los iconos del panel (Font Awesome), las alertas
(SweetAlert2) y los avatares por defecto se cargan desde internet. Quien use el sistema
necesita conexión de todas formas, pero un bloqueo de esos dominios en la red del local se
vería como iconos faltantes o confirmaciones que no aparecen.

## 10. Simulacro (antes del 28/10)

En el hosting, con datos de prueba y **desde un celular**. Registrar aquí el resultado de cada
paso. Un paso fallido se corrige en la fase que corresponda y se repite su puerta.

| # | Paso | Quién | Resultado esperado | Resultado |
|---|---|---|---|---|
| 1 | Crear la cuenta de un delegado e inscribir su delegación en una serie | Organizador | El delegado entra y ve solo su delegación | _pendiente_ |
| 2 | Registrar 3 participantes y armar el plantel de fútbol, vóley y 100 m | Delegado | Advertencias de edad y tope donde corresponde | _pendiente_ |
| 3 | Imprimir fichas y carnets | Delegado | PDF correcto; el QR del carnet verifica | _pendiente_ |
| 4 | Generar la llave de una serie de 5 y de una de 4 | Organizador | Byes correctos | _pendiente_ |
| 5 | Cargar un resultado con empate y penales; una roja al capitán; dos amarillas | Organizador | Avance en la llave; sanciones de 2 y 1 partidos | _pendiente_ |
| 6 | Jugar semifinales, final y tercer puesto | Organizador | Podio y puntos en el puntaje general | _pendiente_ |
| 7 | Registrar el podio directo de ciclismo y de danza | Organizador | Suma según el cuadro; danza suma 0 puntos | _pendiente_ |
| 8 | Reabrir la final y cambiar el ganador | Organizador | El puntaje general cambia sin duplicar | _pendiente_ |
| 9 | Ver la página pública sin sesión | Cualquiera | Resultados y puntaje general visibles | _pendiente_ |
| 10 | Entrar como delegado de otra delegación a un plantel ajeno | Delegado | 403 o no visible | _pendiente_ |

La lógica de los pasos 1, 2 y 4 a 10 ya está cubierta por pruebas automatizadas
(`php artisan test`). El simulacro comprueba lo que esas pruebas no ven: que funciona en el
hosting real, que se puede usar desde un celular y que los PDF y el QR salen bien.
