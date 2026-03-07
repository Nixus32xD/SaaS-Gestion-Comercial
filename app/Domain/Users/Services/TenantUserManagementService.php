<?php

namespace App\Domain\Users\Services;

use App\Domain\Branches\Models\Branch;
use App\Domain\Rbac\Models\Role;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Models\TenantMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TenantUserManagementService
{
    /**
     * @param array<string, mixed> $data
     */
    public function createTenantUser(Tenant $tenant, array $data, int $invitedByUserId): TenantMembership
    {
        return DB::transaction(function () use ($tenant, $data, $invitedByUserId): TenantMembership {
            $branch = Branch::query()
                ->where('tenant_id', $tenant->id)
                ->whereKey($data['default_branch_id'])
                ->firstOrFail();

            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'is_active' => true,
            ]);

            $membership = TenantMembership::query()->create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'default_branch_id' => $branch->id,
                'is_owner' => false,
                'status' => $data['status'],
                'joined_at' => now(),
                'invited_by_user_id' => $invitedByUserId,
            ]);

            if (!empty($data['role_id'])) {
                $role = Role::query()
                    ->where('tenant_id', $tenant->id)
                    ->whereKey($data['role_id'])
                    ->firstOrFail();

                $user->roles()->syncWithoutDetaching([
                    $role->id => [
                        'tenant_id' => $tenant->id,
                        'branch_id' => $branch->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                ]);
            }

            return $membership->load(['user', 'defaultBranch']);
        });
    }

    public function updateMembershipStatus(TenantMembership $membership, string $status): TenantMembership
    {
        $membership->status = $status;
        $membership->save();

        return $membership->fresh(['user', 'defaultBranch']);
    }
}
