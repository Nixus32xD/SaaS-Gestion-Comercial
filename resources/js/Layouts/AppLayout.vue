<script setup>
import { computed, ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import SidebarLink from '@/Components/SidebarLink.vue';
import { Link, usePage } from '@inertiajs/vue3';

const sidebarOpen = ref(false);
const page = usePage();

const isSuperAdmin = computed(() => Boolean(page.props.auth?.is_super_admin));
const canManageUsers = computed(() => page.props.auth?.role === 'admin');
const features = computed(() => page.props.features ?? { stock: true, appointments: false });

const navigation = computed(() => {
    if (isSuperAdmin.value) {
        return [
            { label: 'Comercios y funciones', route: 'admin.businesses.index', pattern: 'admin.businesses.*', icon: 'AD' },
            { label: 'Catalogo global', route: 'admin.global-products.index', pattern: 'admin.global-products.*', icon: 'CG' },
            { label: 'Guia comercial', route: 'admin.commercial-guide.index', pattern: 'admin.commercial-guide.*', icon: 'GC' },
            { label: 'Mi cuenta', route: 'profile.edit', pattern: 'profile.*', icon: 'US' },
        ];
    }

    return [
        { label: 'Dashboard', route: 'dashboard', pattern: 'dashboard', icon: 'DB' },
        ...(features.value.stock ? [
            { label: 'Stock / Productos', route: 'stock.products.index', pattern: 'stock.*', icon: 'ST' },
        ] : []),
        ...(features.value.appointments ? [
            { label: 'Turnos', route: 'appointments.dashboard', pattern: 'appointments.*', icon: 'AP' },
        ] : []),
        ...(canManageUsers.value ? [{ label: 'Usuarios', route: 'users.index', pattern: 'users.*', icon: 'US' }] : []),
        ...(canManageUsers.value ? [{ label: 'Notificaciones', route: 'notifications.edit', pattern: 'notifications.*', icon: 'NT' }] : []),
        { label: 'Mi cuenta', route: 'profile.edit', pattern: 'profile.*', icon: 'US' },
    ];
});
</script>

<template>
    <div class="min-h-screen text-slate-100" style="background: linear-gradient(120deg, #05264e 0%, #0f172a 45%, #101f4d 100%);">
        <aside class="fixed inset-y-0 left-0 z-40 hidden w-72 flex-col border-r border-cyan-100/15 bg-slate-950/70 px-5 py-4 lg:flex">
            <Link :href="isSuperAdmin ? route('admin.businesses.index') : route('dashboard')" class="flex items-center gap-3 rounded-xl px-2 py-2">
                <ApplicationLogo class="h-9 w-9 fill-current text-cyan-300" />
                <p class="text-sm font-semibold tracking-wide text-white">Gestor Comercial</p>
            </Link>
            <nav class="mt-6 space-y-1">
                <SidebarLink v-for="item in navigation" :key="item.route" :href="route(item.route)" :active="route().current(item.pattern)" :icon="item.icon">{{ item.label }}</SidebarLink>
            </nav>
        </aside>

        <div class="lg:pl-72">
            <main class="px-4 py-6 sm:px-6 lg:px-8">
                <header v-if="$slots.header" class="mb-6 rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-4">
                    <slot name="header" />
                </header>
                <section v-if="$page.props.flash?.success || $page.props.flash?.error" class="mb-6 rounded-2xl border p-4 text-sm" :class="$page.props.flash?.error ? 'border-rose-200/45 bg-rose-400/15 text-rose-100' : 'border-emerald-200/45 bg-emerald-400/15 text-emerald-100'">
                    {{ $page.props.flash?.error || $page.props.flash?.success }}
                </section>
                <slot />
            </main>
        </div>
    </div>
</template>
