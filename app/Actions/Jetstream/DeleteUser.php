<?php

namespace App\Actions\Jetstream;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Laravel\Jetstream\Contracts\DeletesUsers;

class DeleteUser implements DeletesUsers
{
    /**
     * El auto-borrado de cuenta esta deshabilitado (config/jetstream.php):
     * saltaba las salvaguardas de UserAccountService::delete(). Se rechaza
     * tambien aqui por si la feature se reactiva sin revisar esto.
     */
    public function delete(User $user): void
    {
        throw new AuthorizationException('Las cuentas solo las elimina un administrador desde Administración > Usuarios.');
    }
}
