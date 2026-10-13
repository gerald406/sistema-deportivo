# F7 — Producción

**Objetivo:** el sistema corre en un hosting con HTTPS, con respaldo diario y un simulacro
completo aprobado antes del 28/10.

**Decisiones que implementa:** D15; requisitos N1, N2, N3. **Requiere:** la tarea 7.1 puede
empezar apenas cierre F1; el resto, tras F4 y de nuevo tras F6.

**Dato pendiente del cliente:** proveedor de hosting, tipo (compartido con cPanel o VPS), dominio,
versión de PHP y de MySQL/MariaDB. Sin eso la tarea 7.1 no puede cerrarse.

## Archivos

| Acción | Ruta |
|---|---|
| Crear | `docs/despliegue.md` (pasos exactos para el hosting elegido) |
| Crear | `scripts/backup-db.sh` (volcado con `mysqldump`, nombre con fecha y hora, conserva los últimos 14) |
| Crear | `tests/Feature/Smoke/ProductionConfigTest.php` |
| Modificar | `.env.example`, `app/Providers/AppServiceProvider.php` |

## Tarea 7.1 — Preparar el despliegue

- [ ] **Paso 1.** Escribir `ProductionConfigTest`:

| Prueba | Afirmación |
|---|---|
| `test_https_is_forced_in_production` | Con `app.env = production`, `url('/')` empieza por `https://` |
| `test_registration_is_disabled` | La ruta `register` no existe o devuelve 404: nadie se crea una cuenta solo |
| `test_login_is_rate_limited` | Seis intentos fallidos seguidos devuelven 429 |
| `test_signed_card_url_rejects_tampering` | `cards.verify` con la firma alterada devuelve 403 |

- [ ] **Paso 2.** Ejecutar; corregir lo que falle (`URL::forceScheme('https')` en producción dentro de `AppServiceProvider::boot()`; `Features::registration()` desactivado en `config/fortify.php`).
- [ ] **Paso 3.** `.env.example`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` con `https`, variables del admin inicial (`config/admin-user.php`), `SESSION_SECURE_COOKIE=true`, `QUEUE_CONNECTION=sync`.
- [ ] **Paso 4.** Verificar en local que el arranque de producción no falla: `php artisan config:cache`, `php artisan route:cache`, `php artisan view:cache`, y luego `php artisan optimize:clear`. Un cierre en una ruta impide `route:cache`: si falla, mover ese cierre a un controlador.
- [ ] **Paso 5.** `npm run build` y confirmar que `public/build/manifest.json` existe.
- [ ] **Paso 6.** Escribir `docs/despliegue.md` para el hosting elegido: subir código, `composer install --no-dev --optimize-autoloader`, `.env`, `php artisan key:generate`, `php artisan migrate --force`, seeders de catálogo y `Puno2026Seeder`, `php artisan storage:link` (o su equivalente si el hosting no permite enlaces simbólicos), cachés, permisos de `storage/` y `bootstrap/cache/`.
- [ ] **Paso 7.** Commit: `F7: Configuración de producción y guía de despliegue`.

## Tarea 7.2 — Respaldo

- [ ] **Paso 1.** `scripts/backup-db.sh` y su programación diaria (cron del hosting) a las 23:00, más una ejecución manual al cierre de cada jornada del evento.
- [ ] **Paso 2.** Prueba de restauración: restaurar el último volcado en una base vacía y abrir el sistema contra ella. Anotar en `docs/despliegue.md` el tiempo que tomó.
- [ ] **Paso 3.** Commit: `F7: Respaldo diario y restauración probada`.

## Tarea 7.3 — Simulacro (se repite tras F4 y tras F6)

Con datos reales de prueba, en el hosting, desde un celular:

| # | Paso | Quién | Resultado esperado |
|---|---|---|---|
| 1 | Crear la cuenta de un delegado e inscribir su delegación en una serie | Organizador | El delegado entra y ve solo su delegación |
| 2 | Registrar 3 participantes y armar el plantel de fútbol, vóley y 100 m | Delegado | Advertencias de edad y tope donde corresponde |
| 3 | Imprimir fichas y carnets | Delegado | PDF correcto; el QR del carnet verifica |
| 4 | Generar la llave de una serie de 5 y de una de 4 | Organizador | Byes correctos |
| 5 | Cargar un resultado con empate y penales; una roja al capitán; dos amarillas | Organizador | Avance en la llave; sanciones de 2 y 1 partidos |
| 6 | Jugar semifinales, final y tercer puesto | Organizador | Podio y puntos en el puntaje general |
| 7 | Registrar el podio directo de ciclismo y de danza | Organizador | Suma según el cuadro; danza suma 0 puntos |
| 8 | Reabrir la final y cambiar el ganador | Organizador | El puntaje general cambia sin duplicar |
| 9 | Ver la página pública sin sesión | Cualquiera | Resultados y puntaje general visibles |
| 10 | Entrar como delegado de otra delegación a un plantel ajeno | Delegado | 403 o no visible |

- [ ] Registrar el resultado de cada paso en `docs/despliegue.md`. Un paso fallido abre una corrección en la fase que corresponda y obliga a repetir su puerta.

## Reglas que cumple

| Regla | Prueba |
|---|---|
| N1: HTTPS | `test_https_is_forced_in_production` |
| Solo admin y organizador crean cuentas (D3) | `test_registration_is_disabled` |
| N2: usable en celular | Simulacro, pasos 1–3 |
| N3: respaldo con restauración probada | Tarea 7.2, paso 2 |

## Puerta de F7

G1–G4, los cachés de producción del paso 4 sin errores, y los 10 pasos del simulacro aprobados.
Registrar en "Estado".
