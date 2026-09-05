<?php

namespace App\Services\Authorization;

use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessAccessAudit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BusinessAuthorizationService
{
    public function provision(Business $business): void
    {
        $permissions = collect(config('authorization.permissions', []));

        foreach ($permissions as $code => $module) {
            Permission::query()->firstOrCreate(['code' => $code], ['module' => $module]);
        }

        $permissionIds = Permission::query()->pluck('id', 'code');
        foreach (config('authorization.roles', []) as $code => $template) {
            $role = Role::query()->firstOrCreate(
                ['business_id' => $business->id, 'code' => $code],
                ['name' => $template['name'], 'is_system' => true],
            );
            $codes = $template['permissions'];
            $role->permissions()->sync($codes === ['*'] ? $permissionIds->values()->all() : $permissionIds->only($codes)->values()->all());
        }
    }

    public function legacyRole(Business $business, string $legacyRole): Role
    {
        $code = $legacyRole === 'admin' ? 'administrator' : 'legacy_staff';
        $this->provision($business);

        if ($code !== 'legacy_staff') {
            return Role::query()->where('business_id', $business->id)->where('code', $code)->firstOrFail();
        }

        $role = Role::query()->firstOrCreate(
            ['business_id' => $business->id, 'code' => $code],
            ['name' => 'Staff legado', 'is_system' => true],
        );
        $role->permissions()->sync(Permission::query()->whereNotIn('code', [
            'fiscal.settings.view', 'fiscal.settings.manage', 'fiscal.credentials.manage',
            'mercadopago.settings.view', 'mercadopago.settings.manage', 'users.view', 'users.manage',
            'roles.view', 'roles.assign', 'roles.manage', 'branches.manage', 'notifications.manage', 'sales.settings.manage',
        ])->pluck('id')->all());

        return $role;
    }

    /** @param list<int> $roleIds @param list<int> $branchIds */
    public function updateUserAccess(User $actor, User $subject, array $roleIds, array $branchIds): void
    {
        $business = $actor->business;
        if ($business === null || (int) $subject->business_id !== (int) $business->id) {
            abort(403);
        }
        if ($subject->isOwner()) {
            abort(403, 'El propietario no puede ser modificado desde la gestión de accesos.');
        }
        if ((int) $actor->id === (int) $subject->id) {
            abort(403, 'No podés modificar tus propios privilegios.');
        }

        $assignable = $this->assignableRoleIds($actor, $business);
        if (array_diff($roleIds, $assignable) !== []) {
            abort(403, 'No podés asignar roles que exceden tus permisos.');
        }
        $validBranches = Branch::query()->forBusiness($business->id)->active()->whereIn('id', $branchIds)->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (count($validBranches) !== count(array_unique($branchIds))) {
            abort(403, 'Una sucursal seleccionada no pertenece al comercio actual.');
        }

        DB::transaction(function () use ($actor, $subject, $business, $roleIds, $validBranches): void {
            $before = $this->accessSnapshot($subject);
            $subject->roles()->syncWithPivotValues($roleIds, ['business_id' => $business->id]);
            $subject->branches()->syncWithPivotValues($validBranches, ['business_id' => $business->id]);
            BusinessAccessAudit::query()->create([
                'business_id' => $business->id,
                'actor_user_id' => $actor->id,
                'subject_user_id' => $subject->id,
                'event' => BusinessAccessAudit::UPDATED,
                'before' => $before,
                'after' => $this->accessSnapshot($subject->fresh(['roles', 'branches'])),
            ]);
        });
    }

    /** @return list<int> */
    public function assignableRoleIds(User $actor, Business $business): array
    {
        if ($actor->isOwner() || $actor->can('roles.manage')) {
            return Role::query()->forBusiness($business->id)->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        if (! $actor->can('roles.assign')) {
            return [];
        }

        return $actor->roles()->where('roles.business_id', $business->id)->pluck('roles.id')->map(fn ($id) => (int) $id)->all();
    }

    /** @return array{role_ids:list<int>,branch_ids:list<int>} */
    public function accessSnapshot(User $user): array
    {
        return [
            'role_ids' => $user->roles()->pluck('roles.id')->map(fn ($id) => (int) $id)->sort()->values()->all(),
            'branch_ids' => $user->branches()->pluck('branches.id')->map(fn ($id) => (int) $id)->sort()->values()->all(),
        ];
    }
}
