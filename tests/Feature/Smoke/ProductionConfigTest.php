<?php

declare(strict_types=1);

namespace Tests\Feature\Smoke;

use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\BuildsCompetition;
use Tests\Concerns\CreatesActors;
use Tests\TestCase;

/**
 * F7: lo que el sistema debe cumplir para publicarse (N1, N3, D3). No
 * depende del proveedor de hosting.
 */
class ProductionConfigTest extends TestCase
{
    use BuildsCompetition, CreatesActors, RefreshDatabase;

    public function test_https_is_forced_in_production(): void
    {
        $this->assertStringStartsWith('http://', url('/'));

        $this->app->detectEnvironment(fn () => 'production');
        (new AppServiceProvider($this->app))->boot();

        $this->assertStringStartsWith('https://', url('/'));
        $this->assertStringStartsWith('https://', route('login'));
        $this->assertStringStartsWith('https://', URL::signedRoute('cards.verify', ['seasonTeamPlayer' => 1]));
    }

    public function test_trusted_proxies_come_from_configuration(): void
    {
        config(['app.trusted_proxies' => '*']);
        (new AppServiceProvider($this->app))->boot();

        // Detras del proxy del hosting, la peticion HTTPS se reconoce como tal
        // (si no, los QR firmados de los carnets darian 403).
        $response = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
            ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-For' => '190.1.2.3'])
            ->get('/up');

        $response->assertOk();
        $this->assertTrue(request()->isSecure());

        TrustProxies::flushState();
    }

    public function test_registration_is_disabled(): void
    {
        // Nadie se crea una cuenta solo: las crean el admin y el organizador (D3).
        $this->assertFalse(Route::has('register'));
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['name' => 'X', 'email' => 'x@example.com', 'password' => 'secreto123', 'password_confirmation' => 'secreto123'])
            ->assertStatus(404);
        $this->assertSame(0, User::count());
    }

    public function test_login_is_rate_limited(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $attempt) {
            $this->post('/login', ['email' => $user->email, 'password' => 'incorrecta'])->assertSessionHasErrors();
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'incorrecta'])->assertStatus(429);
        $this->assertGuest();
    }

    public function test_inactive_account_cannot_sign_in(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->get(route('dashboard'))->assertRedirect();
        $this->assertGuest();
    }

    public function test_signed_card_url_rejects_tampering(): void
    {
        $data = $this->buildCompetition();
        $url = URL::signedRoute('cards.verify', $data->homeEntry);

        $this->get($url)->assertOk();

        // Firma alterada, firma ausente y otro participante con la misma firma.
        $this->get(substr($url, 0, -4).'abcd')->assertForbidden();
        $this->get(route('cards.verify', $data->homeEntry))->assertForbidden();
        $this->get(str_replace("/{$data->homeEntry->id}?", "/{$data->awayEntry->id}?", $url))->assertForbidden();
    }

    public function test_debug_is_off_by_default_and_time_is_local(): void
    {
        // Sin APP_DEBUG en el .env, un error no muestra codigo ni variables.
        $this->assertStringContainsString("env('APP_DEBUG', false)", (string) file_get_contents(config_path('app.php')));
        $this->assertSame('America/Lima', config('app.timezone'));
        $this->assertSame('es', config('app.locale'));
    }

    public function test_backup_fails_cleanly_without_the_dump_tool(): void
    {
        $directory = storage_path('framework/testing/backups-'.uniqid());
        config(['backup.directory' => $directory, 'backup.mysqldump' => 'herramienta-que-no-existe']);

        $this->assertSame(1, Artisan::call('db:backup'));

        // Un respaldo fallido no deja un archivo que parezca valido.
        $this->assertSame([], File::exists($directory) ? File::files($directory) : []);

        File::deleteDirectory($directory);
    }

    public function test_backup_keeps_only_the_most_recent_files(): void
    {
        $directory = storage_path('framework/testing/backups-'.uniqid());
        File::ensureDirectoryExists($directory);
        config(['backup.directory' => $directory, 'backup.keep' => 3]);

        foreach (range(1, 6) as $day) {
            File::put("{$directory}/respaldo-2026-10-0{$day}_230000.sql.gz", 'x');
        }
        File::put("{$directory}/otro-archivo.txt", 'no se toca');

        $this->assertSame(0, Artisan::call('db:backup', ['--prune-only' => true]));

        $names = collect(File::files($directory))->map->getFilename()->sort()->values()->all();
        $this->assertSame([
            'otro-archivo.txt',
            'respaldo-2026-10-04_230000.sql.gz',
            'respaldo-2026-10-05_230000.sql.gz',
            'respaldo-2026-10-06_230000.sql.gz',
        ], $names);

        File::deleteDirectory($directory);
    }
}
