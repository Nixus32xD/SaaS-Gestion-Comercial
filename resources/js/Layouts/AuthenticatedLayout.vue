<script setup>
import { computed, ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import SidebarLink from '@/Components/SidebarLink.vue';
import { Link, usePage } from '@inertiajs/vue3';

const sidebarOpen = ref(false);
const page = usePage();

const isSuperAdmin = computed(() => Boolean(page.props.auth?.is_super_admin));

const navigation = computed(() => {
    if (isSuperAdmin.value) {
        return [
            { label: 'Comercios', route: 'admin.businesses.index', pattern: 'admin.businesses.*', icon: 'AD' },
            { label: 'Mi cuenta', route: 'profile.edit', pattern: 'profile.*', icon: 'US' },
        ];
    }

    return [
        { label: 'Dashboard', route: 'dashboard', pattern: 'dashboard', icon: 'DB' },
        { label: 'Productos', route: 'products.index', pattern: 'products.*', icon: 'PR' },
        { label: 'Proveedores', route: 'suppliers.index', pattern: 'suppliers.*', icon: 'PV' },
        { label: 'Ventas', route: 'sales.index', pattern: 'sales.*', icon: 'VT' },
        { label: 'Compras', route: 'purchases.index', pattern: 'purchases.*', icon: 'CP' },
        { label: 'Mi cuenta', route: 'profile.edit', pattern: 'profile.*', icon: 'US' },
    ];
});

const closeSidebar = () => {
    sidebarOpen.value = false;
};
</script>

<template>
    <div class="min-h-screen bg-slate-100">
        <div
            v-if="sidebarOpen"
            class="fixed inset-0 z-30 bg-slate-900/60 lg:hidden"
            @click="closeSidebar"
        />

        <aside
            class="fixed inset-y-0 left-0 z-40 flex w-72 flex-col bg-slate-900 px-5 py-4 text-slate-100 shadow-xl transition-transform duration-300 ease-out"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
        >
            <Link
                :href="isSuperAdmin ? route('admin.businesses.index') : route('dashboard')"
                class="flex items-center gap-3 rounded-xl px-2 py-2"
                @click="closeSidebar"
            >
                <ApplicationLogo class="h-9 w-9 fill-current text-indigo-400" />
                <div>
                    <p class="text-sm font-semibold tracking-wide text-white">Gestor Comercial</p>
                    <p class="text-xs text-slate-400">SaaS Multi Comercio</p>
                </div>
            </Link>

            <div class="mt-5 rounded-xl border border-slate-800 bg-slate-950/30 p-3">
                <p class="text-xs uppercase tracking-wider text-slate-400">Perfil</p>
                <p class="mt-1 truncate text-sm font-semibold text-white">{{ $page.props.auth.user?.name }}</p>
                <p class="mt-2 text-xs uppercase tracking-wider text-slate-400">Rol</p>
                <p class="mt-1 truncate text-sm text-slate-200">{{ isSuperAdmin ? 'Superadmin' : 'Admin comercio' }}</p>
                <template v-if="!isSuperAdmin">
                    <p class="mt-2 text-xs uppercase tracking-wider text-slate-400">Comercio</p>
                    <p class="mt-1 truncate text-sm text-slate-200">{{ $page.props.business?.name ?? 'Sin comercio' }}</p>
                </template>
            </div>

            <nav class="mt-6 space-y-1">
                <SidebarLink
                    v-for="item in navigation"
                    :key="item.route"
                    :href="route(item.route)"
                    :active="route().current(item.pattern)"
                    :icon="item.icon"
                    @click="closeSidebar"
                >
                    {{ item.label }}
                </SidebarLink>
            </nav>

            <div class="mt-auto">
                <Dropdown align="right" width="56">
                    <template #trigger>
                        <button
                            type="button"
                            class="flex w-full items-center justify-between rounded-xl border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-200 transition hover:border-slate-600 hover:bg-slate-700"
                        >
                            <span class="truncate">{{ $page.props.auth.user?.email }}</span>
                            <svg class="h-4 w-4 text-slate-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.51a.75.75 0 01-1.08 0l-4.25-4.51a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </template>
                    <template #content>
                        <DropdownLink :href="route('profile.edit')">Perfil</DropdownLink>
                        <DropdownLink :href="route('logout')" method="post" as="button">Cerrar sesion</DropdownLink>
                    </template>
                </Dropdown>
            </div>
        </aside>

        <div class="lg:pl-72">
            <header class="sticky top-0 z-20 border-b border-slate-200/80 bg-white/90 backdrop-blur">
                <div class="flex h-16 items-center justify-between px-4 sm:px-6 lg:px-8">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white p-2 text-slate-500 shadow-sm lg:hidden"
                        @click="sidebarOpen = !sidebarOpen"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <div class="ml-3">
                        <p class="text-sm font-semibold text-slate-800">{{ isSuperAdmin ? 'Panel superadmin' : ($page.props.business?.name ?? 'Comercio') }}</p>
                        <p class="text-xs text-slate-500">{{ $page.props.auth.user?.email }}</p>
                    </div>
                </div>
            </header>

            <main class="px-4 py-6 sm:px-6 lg:px-8">
                <header v-if="$slots.header" class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <slot name="header" />
                </header>
                <section
                    v-if="$page.props.flash?.success || $page.props.flash?.error"
                    class="mb-6 rounded-2xl border p-4 text-sm"
                    :class="$page.props.flash?.error ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700'"
                >
                    {{ $page.props.flash?.error || $page.props.flash?.success }}
                </section>
                <section
                    v-if="$page.props.errors && Object.keys($page.props.errors).length"
                    class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700"
                >
                    <p class="font-semibold">Revisa los datos del formulario:</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        <li v-for="(message, key) in $page.props.errors" :key="key">{{ message }}</li>
                    </ul>
                </section>
                <slot />
            </main>
        </div>
    </div>
</template>
