<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GamesEditionTeam;
use App\Models\Player;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PlayerRegistrationService
{
    /**
     * Campos que llegan del formulario. created_by y documents_verified_at
     * NUNCA se toman de $data: el primero lo fija register() y el segundo
     * solo setDocumentsVerified().
     */
    private const FORM_FIELDS = [
        'dni', 'first_name', 'last_name', 'birth_date', 'gender', 'staff_type', 'employment_type', 'photo_path', 'is_active',
    ];

    /**
     * Resolucion de nombramiento o contrato (PDF, D20). Va al disco PRIVADO:
     * solo se descarga por la ruta players.resolution, que autoriza con
     * PlayerPolicy::view (su delegacion, el organizador y el admin).
     */
    public const RESOLUTION_DISK = 'local';

    public const RESOLUTION_DIR = 'resolutions';

    public const RESOLUTION_MAX_KB = 5120;

    /**
     * @param array{dni: string, first_name: string, last_name: string, birth_date: string, gender?: string|null, staff_type?: string|null, employment_type?: string|null, is_active: bool} $data
     */
    public function register(array $data, User $actor): Player
    {
        $player = new Player($this->formData($data));
        $player->created_by = $actor->id;
        $player->save();

        return $player;
    }

    public function update(Player $player, array $data): Player
    {
        $player->update($this->formData($data));

        return $player;
    }

    /**
     * Marca (o desmarca) que se revisaron el DNI y los documentos del
     * participante. Solo el admin o el organizador de una olimpiada en la
     * que el participante esta a su alcance; nunca el delegado.
     */
    public function setDocumentsVerified(Player $player, bool $verified, User $actor): Player
    {
        if (! $this->canVerifyDocuments($actor, $player)) {
            throw new AuthorizationException('Solo el organizador de la olimpiada o el administrador verifican documentos.');
        }

        $player->forceFill(['documents_verified_at' => $verified ? ($player->documents_verified_at ?? now()) : null])->save();

        return $player;
    }

    public function canVerifyDocuments(User $actor, Player $player): bool
    {
        if ($actor->hasRole('admin')) {
            return true;
        }

        return $actor->hasRole('organizador')
            && $actor->can('editions.manage')
            && $this->isVisibleTo($actor, $player);
    }

    /** @return array<string, mixed> */
    private function formData(array $data): array
    {
        $data = array_intersect_key($data, array_flip(self::FORM_FIELDS));

        foreach (['gender', 'staff_type', 'employment_type'] as $optional) {
            if (array_key_exists($optional, $data) && $data[$optional] === '') {
                $data[$optional] = null;
            }
        }

        return $data;
    }

    /**
     * Sin soft deletes, un participante con historial (season_team_player,
     * RESTRICT) no se puede borrar fisicamente. Se detecta antes de
     * intentarlo en vez de dejar que MySQL truene con un error 1451.
     *
     * Devuelve true o el mensaje exacto de por que no se pudo borrar.
     */
    public function delete(Player $player): bool|string
    {
        if ($player->seasonTeamPlayers()->exists()) {
            return 'No se puede eliminar: el participante tiene inscripciones registradas. Desactívalo en su lugar.';
        }

        try {
            $player->delete();
        } catch (QueryException) {
            return 'No se puede eliminar: el participante tiene historial asociado. Desactívalo en su lugar.';
        }

        // Sin esto la foto queda huerfana en storage/app/public/players.
        if ($player->photo_path) {
            Storage::disk('public')->delete($player->photo_path);
        }

        if ($player->resolution_path) {
            Storage::disk(self::RESOLUTION_DISK)->delete($player->resolution_path);
        }

        return true;
    }

    /**
     * Quien sube, reemplaza o quita la resolucion:
     *   - el admin y el organizador que verifica documentos, siempre;
     *   - el delegado que gestiona al participante (PlayerPolicy::update),
     *     solo mientras los documentos NO esten verificados: el archivo
     *     verificado queda como evidencia para reclamos.
     */
    public function canManageResolution(User $actor, Player $player): bool
    {
        if ($this->canVerifyDocuments($actor, $player)) {
            return true;
        }

        return $player->documents_verified_at === null && Gate::forUser($actor)->allows('update', $player);
    }

    /** Sube o reemplaza la resolucion. El archivo anterior se borra despues de guardar. */
    public function attachResolution(Player $player, UploadedFile $file, User $actor): Player
    {
        if (! $this->canManageResolution($actor, $player)) {
            throw new AuthorizationException($player->documents_verified_at
                ? 'Los documentos ya están verificados: solo el organizador puede reemplazar la resolución.'
                : 'No puedes cambiar la resolución de este participante.');
        }

        // Se revisa el contenido (no solo la extension) aunque el componente ya valide.
        if ($file->getMimeType() !== 'application/pdf' || $file->getSize() > self::RESOLUTION_MAX_KB * 1024) {
            throw ValidationException::withMessages(['newResolution' => 'La resolución debe ser un PDF de hasta 5 MB.']);
        }

        $previous = $player->resolution_path;
        $path = $file->store(self::RESOLUTION_DIR, self::RESOLUTION_DISK);

        $player->forceFill([
            'resolution_path' => $path,
            'resolution_uploaded_at' => now(),
            'resolution_uploaded_by' => $actor->id,
        ])->save();

        if ($previous && $previous !== $path) {
            Storage::disk(self::RESOLUTION_DISK)->delete($previous);
        }

        return $player;
    }

    public function removeResolution(Player $player, User $actor): Player
    {
        if (! $this->canManageResolution($actor, $player)) {
            throw new AuthorizationException('No puedes quitar la resolución de este participante.');
        }

        $previous = $player->resolution_path;

        $player->forceFill(['resolution_path' => null, 'resolution_uploaded_at' => null, 'resolution_uploaded_by' => null])->save();

        if ($previous) {
            Storage::disk(self::RESOLUTION_DISK)->delete($previous);
        }

        return $player;
    }

    /**
     * Participantes que $actor ve en /participantes (union si tiene varios roles).
     * Player es un catalogo global: el alcance no es un where directo sino
     * via su roster: es visible si tiene al menos un convocado visible en
     * /planteles (SeasonTeamPlayerService::scopeVisibleTo(): organizador ->
     * olimpiada suya, delegado -> delegado EFECTIVO, union si tiene ambos).
     *   - Admin: todos.
     *   - Organizador o delegado: tambien los que el mismo creo
     *     (players.created_by) y aun no tienen roster; sin esto el delegado
     *     perderia de vista (y no podria corregir) un participante recien creado
     *     antes de inscribirlo en /planteles.
     *   - Tambien los que registraron sus companeros de delegacion
     *     (delegationMateIds()): el titular y el suplente comparten los
     *     participantes aunque aun no esten en un plantel, y el organizador
     *     ve los que registraron los delegados de su olimpiada.
     *   - Cualquier otro: ninguno.
     *
     * Es tambien el alcance del buscador al armar un plantel: nadie
     * encuentra ni agrega participantes de otra delegacion.
     */
    public function scopeVisibleTo(Builder $query, User $actor): Builder
    {
        if ($actor->hasRole('admin')) {
            return $query;
        }

        if (! $actor->hasAnyRole(['organizador', 'delegado'])) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(fn ($q) => $q
            ->whereIn('players.created_by', [$actor->id, ...$this->delegationMateIds($actor)])
            ->orWhereHas('seasonTeamPlayers', fn ($roster) => app(SeasonTeamPlayerService::class)->scopeVisibleTo($roster, $actor)));
    }

    /**
     * Delegados (titular y suplente) de las delegaciones de las que $actor
     * es delegado y, si es organizador, de todas las inscritas en sus
     * olimpiadas.
     *
     * @return list<int>
     */
    private function delegationMateIds(User $actor): array
    {
        $isDelegate = $actor->hasRole('delegado');
        $isOrganizer = $actor->hasRole('organizador');

        return GamesEditionTeam::query()
            ->where(function ($w) use ($actor, $isDelegate, $isOrganizer) {
                $w->whereRaw('1 = 0');

                if ($isDelegate) {
                    $w->orWhere('delegate_id', $actor->id)->orWhere('substitute_delegate_id', $actor->id);
                }

                if ($isOrganizer) {
                    $w->orWhereHas('edition', fn ($e) => $e->where('organizer_id', $actor->id));
                }
            })
            ->get(['delegate_id', 'substitute_delegate_id'])
            ->flatMap(fn (GamesEditionTeam $entry) => [$entry->delegate_id, $entry->substitute_delegate_id])
            ->filter()->unique()->values()->all();
    }

    /**
     * El participante esta dentro del alcance de $actor (lo usa
     * PlayerPolicy::view): misma regla que scopeVisibleTo(), aplicada a un
     * solo registro.
     */
    public function isVisibleTo(User $actor, Player $player): bool
    {
        return $this->scopeVisibleTo(Player::query(), $actor)->whereKey($player->id)->exists();
    }
}
