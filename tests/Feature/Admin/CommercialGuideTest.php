<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('superadmin can view the commercial guide', function () {
    $superAdmin = User::factory()->superadmin()->create();

    $this->actingAs($superAdmin)
        ->get(route('admin.commercial-guide.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/CommercialGuide/Index')
            ->has('quick_rules', 3)
            ->has('sections', 3)
            ->has('internal_checklists', 2)
            ->has('whatsapp_templates', 7)
        );
});

test('business admin cannot access the commercial guide', function () {
    $admin = User::factory()->businessAdmin()->create();

    $this->actingAs($admin)
        ->get(route('admin.commercial-guide.index'))
        ->assertForbidden();
});
