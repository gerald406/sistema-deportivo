#!/usr/bin/env bash
# Respaldo de la base de datos. Envoltura para el cron del hosting:
#
#   0 23 * * * /ruta/al/proyecto/scripts/backup-db.sh >> /ruta/al/proyecto/storage/logs/respaldo.log 2>&1
#
# El trabajo lo hace `php artisan db:backup` (app/Console/Commands/BackupDatabase.php):
# volcado con mysqldump, comprimido, con fecha y hora en el nombre, en
# storage/app/backups; conserva los 14 mas recientes (BACKUP_KEEP en el .env).
# Tambien se corre a mano al cerrar cada jornada del evento.
set -euo pipefail

cd "$(dirname "$0")/.."

# PHP_BIN permite indicar el PHP correcto si el hosting tiene varios
# (en cPanel suele ser algo como /usr/local/bin/ea-php82).
"${PHP_BIN:-php}" artisan db:backup
