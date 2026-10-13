---
name: implementar-fase
description: Usar al implementar, retomar o cerrar una fase del plan de la Olimpiada Puno 2026 (docs/fases/F*.md). Obliga a seguir el orden de fases, a no olvidar migraciones, rutas, permisos ni menú, y a no avanzar sin pasar la puerta de pruebas.
---

# Implementar una fase

El plan vive en `docs/fases/`. Este procedimiento se sigue completo cada vez; no se salta ningún
paso aunque la fase parezca pequeña.

## 1. Antes de tocar código

1. Leer `docs/fases/00-plan-maestro.md` completo. La tabla **Estado** dice cuál es la fase actual:
   la primera que no está en ✅.
2. Si la fase anterior no tiene su puerta registrada como aprobada, **no se empieza**: se cierra
   esa primero.
3. Leer `docs/fases/00-especificacion.md` y el archivo `F<n>-*.md` de la fase actual completos.
4. Confirmar la rama: `git branch --show-current` debe ser `feature/olimpiada-puno-2026`.
   `git status` debe estar limpio, salvo lo que la fase diga recuperar.
5. Leer los archivos que la fase lista en "Leer antes de empezar".

## 2. Durante la fase

- Una tarea a la vez, en el orden del archivo. Dentro de cada tarea: escribir la prueba, verla
  fallar, implementar, verla pasar, commit.
- Usar exactamente los nombres de las secciones **Interfaces** y de los inventarios del plan
  maestro (tablas, columnas, enums, firmas, rutas, permisos). Si un nombre tiene que cambiar, se
  cambia primero en el plan maestro y en toda fase que lo consuma, en el mismo commit.
- Marcar cada casilla `- [ ]` → `- [x]` en el archivo de la fase al terminar el paso.
- Cada vez que se agrega algo, repasar esta lista y aplicar lo que corresponda:

| Si agregas… | No olvides… |
|---|---|
| Una migración | `php artisan migrate` en la base de trabajo; `down()` funcional; el modelo (`$fillable`, `casts()`, relaciones en ambos sentidos); la factory |
| Una columna | `$fillable`, `casts()`, reglas de validación, `validationAttributes()`, `resetForm()`, el formulario y el listado de la vista |
| Un modelo | Factory; si entra en una relación polimórfica, `Relation::enforceMorphMap()` |
| Un enum | `label()` en español; el `cast` en el modelo; usar `->value` en consultas |
| Un Service | Revalidar la autorización dentro; `delete()` devuelve `true` o el mensaje |
| Un componente Livewire | `authorize()` o `abort_unless()` en **cada** acción; `sortableFields()`; ids del navegador acotados a su padre |
| Una pantalla | Ruta en `routes/web.php`; entrada en `config/admin-menu.php`; su nombre en `RoutesSmokeTest::adminRoutes()`; `#[Title]` |
| Un permiso o cambio de rol | `RolePermissionSeeder` **y** una migración de datos; `forgetCachedPermissions()` |
| Una regla de las bases | Su fila en "Reglas que cumple" con el nombre de la prueba |
| Un texto visible | Español, con el vocabulario de la especificación §1 |

- Si aparece algo que el plan no previó (una tabla más, una ruta más, una decisión de negocio):
  detenerse, anotarlo en el plan maestro y en la fase, y solo entonces implementarlo. Una
  decisión de negocio que no esté en las bases ni en las decisiones D1–D15 se pregunta al
  cliente; no se asume.

## 3. La puerta

Al terminar la última tarea, ejecutar en este orden y pegar la salida relevante en la respuesta:

1. `php artisan test`
2. `php artisan view:cache` y `php artisan view:clear`
3. `php artisan route:list --except-vendor`
4. `php artisan migrate:status`
5. La búsqueda de palabras prohibidas que indique la fase
6. Recorrer la tabla "Reglas que cumple": cada prueba nombrada existe y está en verde

La fase **cumple** solo si los seis puntos pasan. Si alguno falla: corregir dentro de la fase y
repetir los seis. No se marca una prueba como omitida para pasar.

## 4. Cerrar

1. En `docs/fases/00-plan-maestro.md`, tabla **Estado**: `✅` y en **Puerta** la fecha y el
   resumen (`AAAA-MM-DD · N pruebas, 0 fallos`). Si no cumple: `❌` y el motivo.
2. Actualizar `CLAUDE.md` según la tarea de documentación de la fase.
3. Commit de cierre: `F<n>: cierre de fase, puerta aprobada`.
4. Informar al cliente: qué reglas de las bases quedaron cubiertas, qué supuestos necesitan su
   confirmación y qué puede probar ya en el navegador.
5. Solo entonces pasar a la fase siguiente.
