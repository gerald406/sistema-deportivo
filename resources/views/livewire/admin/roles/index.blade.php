<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-gray-900">Roles y permisos</h1>
        <p class="text-sm text-gray-500">
            El sistema tiene exactamente 3 roles fijos. Aquí solo se ajustan los permisos de
            <span class="font-medium">organizador</span> y <span class="font-medium">delegado</span>.
        </p>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        @foreach ($roles as $role)
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-900/5">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-semibold capitalize text-gray-900">{{ $role->name }}</h2>
                    @if (! $this->isEditable($role->name))
                        <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">
                            <i class="fa-solid fa-lock text-[10px]"></i> Fijo
                        </span>
                    @endif
                </div>
                @if (! $this->isEditable($role->name))
                    <p class="mt-1 text-xs text-gray-400">
                        Este rol tiene acceso total al sistema (bypass de autorización), sus permisos no son editables.
                    </p>
                @endif

                <div class="mt-4 space-y-2">
                    @foreach ($permissions as $permission)
                        <label class="flex items-center gap-2">
                            <input type="checkbox" value="{{ $permission->name }}"
                                   wire:model="rolePermissions.{{ $role->name }}"
                                   @disabled(! $this->isEditable($role->name))
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 disabled:opacity-50">
                            <span class="text-sm text-gray-700">{{ $permission->name }}</span>
                        </label>
                    @endforeach
                </div>

                @if ($this->isEditable($role->name))
                    <button wire:click="save('{{ $role->name }}')" type="button"
                            class="mt-4 w-full rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                        <span wire:loading.remove wire:target="save('{{ $role->name }}')">Guardar cambios</span>
                        <span wire:loading wire:target="save('{{ $role->name }}')"><i class="fa-solid fa-spinner fa-spin"></i> Guardando...</span>
                    </button>
                @endif
            </div>
        @endforeach
    </div>
</div>
