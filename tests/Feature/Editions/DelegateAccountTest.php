<?php

declare(strict_types=1);

namespace Tests\Feature\Editions;

use App\Models\GamesEdition;
use App\Models\User;
use App\Services\DelegateAccountService;
use App\Services\UserAccountService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesActors;
use Tests\TestCase;

/** F2 (D3): el organizador crea cuentas, pero solo con rol delegado. */
class DelegateAccountTest extends TestCase
{
    use CreatesActors, RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return $overrides + ['name' => 'Delegado Nuevo', 'email' => 'delegado.nuevo@example.com', 'password' => 'secreto123'];
    }

    public function test_admin_creates_a_delegate_account(): void
    {
        $user = app(DelegateAccountService::class)->createQuickAccount($this->payload(), $this->admin());

        $this->assertSame(['delegado'], $user->getRoleNames()->all());
        $this->assertTrue(Hash::check('secreto123', $user->password));
        $this->assertTrue((bool) $user->fresh()->is_active);
    }

    public function test_organizer_with_an_edition_creates_a_delegate_account(): void
    {
        $organizer = $this->organizer();
        GamesEdition::factory()->create(['organizer_id' => $organizer->id]);

        $user = app(DelegateAccountService::class)->createQuickAccount($this->payload(), $organizer);

        $this->assertSame(['delegado'], $user->getRoleNames()->all());
    }

    public function test_organizer_without_edition_is_forbidden(): void
    {
        $organizer = $this->organizer();
        GamesEdition::factory()->create(); // de otro organizador

        $this->expectException(AuthorizationException::class);
        app(DelegateAccountService::class)->createQuickAccount($this->payload(), $organizer);
    }

    public function test_delegate_cannot_create_accounts(): void
    {
        try {
            app(DelegateAccountService::class)->createQuickAccount($this->payload(), $this->delegate());
            $this->fail('Un delegado creó una cuenta.');
        } catch (AuthorizationException) {
            $this->assertDatabaseMissing('users', ['email' => 'delegado.nuevo@example.com']);
        }
    }

    public function test_role_cannot_be_injected(): void
    {
        $organizer = $this->organizer();
        GamesEdition::factory()->create(['organizer_id' => $organizer->id]);

        $user = app(DelegateAccountService::class)->createQuickAccount(
            $this->payload(['role' => 'admin', 'roles' => ['admin', 'organizador'], 'is_active' => false]),
            $organizer,
        );

        $this->assertSame(['delegado'], $user->getRoleNames()->all());
        $this->assertFalse($user->hasRole('admin'));
    }

    public function test_organizer_cannot_use_the_users_crud(): void
    {
        $organizer = $this->organizer();
        GamesEdition::factory()->create(['organizer_id' => $organizer->id]);

        $this->actingAs($organizer)->get(route('admin.users.index'))->assertForbidden();

        try {
            app(UserAccountService::class)->register(
                ['name' => 'Otro Admin', 'email' => 'otro.admin@example.com', 'password' => 'secreto123', 'is_active' => true],
                ['admin'],
                $organizer,
            );
            $this->fail('Un organizador creó una cuenta con el CRUD de usuarios.');
        } catch (AuthorizationException) {
            $this->assertSame(0, User::where('email', 'otro.admin@example.com')->count());
        }
    }
}
