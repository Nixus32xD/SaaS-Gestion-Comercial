<?php

namespace App\Domain\Tenancy\Services;

use App\Domain\Branches\Models\Branch;
use App\Domain\Rbac\Models\Permission;
use App\Domain\Settings\Models\Setting;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TenantOnboardingService
{
    /**
     * @param array<string, mixed> $data
     * @return array{user: User, tenant: Tenant, branch: Branch}
     */
    public function onboardOwner(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $tenant = Tenant::query()->create([
                'uuid' => (string) Str::uuid(),
                'name' => $data['business_name'],
                'slug' => $this->buildUniqueSlug($data['business_name']),
                'currency' => strtoupper((string) ($data['currency'] ?? 'ARS')),
                'status' => 'active',
            ]);

            $mainBranch = $tenant->branches()->create([
                'name' => $data['branch_name'] ?: 'Casa Central',
                'code' => 'MAIN',
                'is_main' => true,
                'status' => 'active',
            ]);

            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'is_active' => true,
            ]);

            $membership = $tenant->memberships()->create([
                'user_id' => $user->id,
                'default_branch_id' => $mainBranch->id,
                'is_owner' => true,
                'status' => 'active',
                'joined_at' => now(),
            ]);

            $ownerRole = $tenant->roles()->create([
                'name' => 'Dueno',
                'slug' => 'owner',
                'description' => 'Acceso completo al tenant',
                'is_system' => true,
            ]);

            $permissionIds = Permission::query()->pluck('id');
            if ($permissionIds->isNotEmpty()) {
                $ownerRole->permissions()->sync($permissionIds);
            }

            $user->roles()->syncWithoutDetaching([
                $ownerRole->id => [
                    'tenant_id' => $tenant->id,
                    'branch_id' => $mainBranch->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            Setting::query()->create([
                'tenant_id' => $tenant->id,
                'key' => 'business.general',
                'value' => [
                    'currency' => $tenant->currency,
                    'timezone' => $tenant->timezone,
                    'locale' => $tenant->locale,
                    'branch_id' => $mainBranch->id,
                    'owner_membership_id' => $membership->id,
                ],
            ]);

            return [
                'user' => $user,
                'tenant' => $tenant,
                'branch' => $mainBranch,
            ];
        });
    }

    private function buildUniqueSlug(string $businessName): string
    {
        $base = Str::slug($businessName);
        $root = $base === '' ? 'tenant' : $base;
        $slug = $root;
        $counter = 1;

        while (Tenant::query()->where('slug', $slug)->exists()) {
            $slug = sprintf('%s-%d', $root, $counter);
            $counter++;
        }

        return $slug;
    }
}
