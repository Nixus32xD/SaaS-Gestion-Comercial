<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            Schema::create('permissions', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 100)->unique();
                $table->string('module', 100);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('name', 120);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->unique(['business_id', 'code']);
            $table->unique(['id', 'business_id'], 'roles_id_business_id_unique');
            });
        }
        if (! Schema::hasTable('permission_role')) {
            Schema::create('permission_role', function (Blueprint $table): void {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['permission_id', 'role_id']);
            });
        }
        if (! Schema::hasTable('role_user')) {
            Schema::create('role_user', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('business_id');
            $table->timestamps();
            $table->primary(['user_id', 'role_id']);
            $table->index(['business_id', 'user_id']);
            $table->foreign(['user_id', 'business_id'], 'role_user_user_business_foreign')->references(['id', 'business_id'])->on('users')->cascadeOnDelete();
            $table->foreign(['role_id', 'business_id'], 'role_user_role_business_foreign')->references(['id', 'business_id'])->on('roles')->cascadeOnDelete();
            });
        }
        if (! Schema::hasTable('branch_user')) {
            Schema::create('branch_user', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('business_id');
            $table->timestamps();
            $table->primary(['user_id', 'branch_id']);
            $table->index(['business_id', 'branch_id']);
            $table->foreign(['user_id', 'business_id'], 'branch_user_user_business_foreign')->references(['id', 'business_id'])->on('users')->cascadeOnDelete();
            $table->foreign(['branch_id', 'business_id'], 'branch_user_branch_business_foreign')->references(['id', 'business_id'])->on('branches')->cascadeOnDelete();
            });
        }
        if (! Schema::hasTable('business_access_audits')) {
            Schema::create('business_access_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->unsignedBigInteger('subject_user_id')->nullable();
            $table->string('event', 80);
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'subject_user_id', 'created_at']);
            });
        }
        if (! Schema::hasColumn('businesses', 'owner_user_id')) {
            Schema::table('businesses', function (Blueprint $table): void {
                $table->unsignedBigInteger('owner_user_id')->nullable()->after('owner_name');
                $table->index('owner_user_id');
            });
        }

        $now = now();
        foreach (config('authorization.permissions', []) as $code => $module) {
            DB::table('permissions')->updateOrInsert(['code' => $code], ['module' => $module, 'updated_at' => $now, 'created_at' => $now]);
        }
        DB::table('businesses')->orderBy('id')->each(function (object $business) use ($now): void {
            $permissionIds = DB::table('permissions')->pluck('id', 'code');
            foreach (config('authorization.roles', []) as $code => $template) {
                DB::table('roles')->updateOrInsert(
                    ['business_id' => $business->id, 'code' => $code],
                    ['name' => $template['name'], 'is_system' => true, 'updated_at' => $now, 'created_at' => $now]
                );
                $roleId = DB::table('roles')->where('business_id', $business->id)->where('code', $code)->value('id');
                $ids = $template['permissions'] === ['*'] ? $permissionIds->all() : $permissionIds->only($template['permissions'])->all();
                foreach ($ids as $permissionId) {
                    DB::table('permission_role')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId, 'created_at' => $now, 'updated_at' => $now]);
                }
            }
            DB::table('roles')->updateOrInsert(
                ['business_id' => $business->id, 'code' => 'legacy_staff'],
                ['name' => 'Staff legado', 'is_system' => true, 'updated_at' => $now, 'created_at' => $now]
            );
            $staffRoleId = DB::table('roles')->where('business_id', $business->id)->where('code', 'legacy_staff')->value('id');
            $staffPermissions = $permissionIds->except(['fiscal.settings.view', 'fiscal.settings.manage', 'fiscal.credentials.manage', 'mercadopago.settings.view', 'mercadopago.settings.manage', 'users.view', 'users.manage', 'roles.view', 'roles.assign', 'roles.manage', 'branches.manage', 'notifications.manage', 'sales.settings.manage']);
            foreach ($staffPermissions as $permissionId) {
                DB::table('permission_role')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $staffRoleId, 'created_at' => $now, 'updated_at' => $now]);
            }
            $administratorRoleId = DB::table('roles')->where('business_id', $business->id)->where('code', 'administrator')->value('id');
            $branchIds = DB::table('branches')->where('business_id', $business->id)->where('is_active', true)->pluck('id');
            DB::table('users')->where('business_id', $business->id)->whereIn('role', ['admin', 'staff'])->orderBy('id')->each(function (object $user) use ($business, $now, $administratorRoleId, $staffRoleId, $branchIds): void {
                DB::table('role_user')->insertOrIgnore(['user_id' => $user->id, 'role_id' => $user->role === 'admin' ? $administratorRoleId : $staffRoleId, 'business_id' => $business->id, 'created_at' => $now, 'updated_at' => $now]);
                foreach ($branchIds as $branchId) {
                    DB::table('branch_user')->insertOrIgnore(['user_id' => $user->id, 'branch_id' => $branchId, 'business_id' => $business->id, 'created_at' => $now, 'updated_at' => $now]);
                }
            });
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropIndex(['owner_user_id']);
            $table->dropColumn('owner_user_id');
        });
        Schema::dropIfExists('business_access_audits');
        Schema::dropIfExists('branch_user');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
    }
};
