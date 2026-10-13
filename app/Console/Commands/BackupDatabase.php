<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Volcado completo de la base (estructura, datos y triggers) con mysqldump,
 * comprimido y con fecha y hora en el nombre. Conserva solo los mas
 * recientes (config/backup.php).
 *
 * Uso: php artisan db:backup            (diario por cron, y a mano al cerrar cada jornada)
 *      php artisan db:backup --prune-only   (solo borra los antiguos)
 *
 * Restaurar: ver docs/despliegue.md. No hay comando de restauracion a
 * proposito: sobrescribir la base es una decision que se toma a mano.
 */
class BackupDatabase extends Command
{
    protected $signature = 'db:backup {--prune-only : No respalda; solo borra los volcados antiguos}';

    protected $description = 'Respalda la base de datos en storage/app/backups y conserva los más recientes';

    private const PREFIX = 'respaldo-';

    public function handle(): int
    {
        $directory = (string) config('backup.directory');
        File::ensureDirectoryExists($directory);

        if (! $this->option('prune-only')) {
            $file = $this->dump($directory);

            if ($file === null) {
                return self::FAILURE;
            }

            $this->info('Respaldo creado: '.$file.' ('.number_format(filesize($file) / 1024, 1).' KB)');
        }

        $deleted = $this->prune($directory);

        if ($deleted > 0) {
            $this->line("Volcados antiguos borrados: {$deleted}.");
        }

        return self::SUCCESS;
    }

    /** Ruta del volcado comprimido, o null si fallo (sin dejar archivos a medias). */
    private function dump(string $directory): ?string
    {
        $connection = config('database.connections.'.config('database.default'));

        if (! in_array($connection['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            $this->error('El respaldo solo está preparado para MySQL/MariaDB.');

            return null;
        }

        $plain = $directory.DIRECTORY_SEPARATOR.self::PREFIX.now()->format('Y-m-d_His').'.sql';
        $compressed = $plain.'.gz';

        $process = new Process([
            (string) config('backup.mysqldump'),
            '--host='.$connection['host'],
            '--port='.$connection['port'],
            '--user='.$connection['username'],
            '--single-transaction',
            '--routines',
            '--triggers',
            '--no-tablespaces',
            '--default-character-set=utf8mb4',
            '--result-file='.$plain,
            $connection['database'],
        // La clave va por variable de entorno, no en la linea de comandos
        // (que cualquier usuario del servidor puede ver con ps).
        ], null, ['MYSQL_PWD' => (string) ($connection['password'] ?? '')], null, 300);

        try {
            $process->run();
            $failure = $process->isSuccessful() ? null : trim($process->getErrorOutput());
        } catch (Throwable $e) {
            $failure = $e->getMessage();
        }

        if ($failure === null && (! File::exists($plain) || File::size($plain) === 0)) {
            $failure = 'mysqldump no produjo ningún dato.';
        }

        if ($failure !== null) {
            File::delete([$plain, $compressed]);
            $this->error('No se pudo respaldar la base: '.$failure);
            $this->line('Revisa MYSQLDUMP_PATH en el .env (ruta del ejecutable mysqldump).');

            return null;
        }

        $this->compress($plain, $compressed);
        File::delete($plain);

        return $compressed;
    }

    private function compress(string $source, string $target): void
    {
        $in = fopen($source, 'rb');
        $out = gzopen($target, 'wb9');

        while (! feof($in)) {
            gzwrite($out, (string) fread($in, 1024 * 512));
        }

        fclose($in);
        gzclose($out);
    }

    /** Borra los volcados propios mas antiguos; devuelve cuantos borro. */
    private function prune(string $directory): int
    {
        $keep = max(1, (int) config('backup.keep'));

        $old = collect(File::files($directory))
            ->filter(fn ($file) => str_starts_with($file->getFilename(), self::PREFIX) && str_ends_with($file->getFilename(), '.sql.gz'))
            ->sortByDesc(fn ($file) => $file->getFilename()) // el nombre lleva fecha y hora
            ->values()
            ->slice($keep);

        $old->each(fn ($file) => File::delete($file->getPathname()));

        return $old->count();
    }
}
