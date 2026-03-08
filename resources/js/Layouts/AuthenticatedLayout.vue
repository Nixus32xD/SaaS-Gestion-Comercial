<script setup>
import { computed, ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
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
    <div class="app-theme min-h-screen">
        <div
            v-if="sidebarOpen"
            class="fixed inset-0 z-30 bg-slate-950/70 lg:hidden"
            @click="closeSidebar"
        />

        <aside
            class="fixed inset-y-0 left-0 z-40 flex w-72 flex-col border-r border-cyan-100/15 bg-slate-950/70 px-5 py-4 text-slate-100 shadow-xl backdrop-blur-xl transition-transform duration-300 ease-out"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
        >
            <Link
                :href="isSuperAdmin ? route('admin.businesses.index') : route('dashboard')"
                class="flex items-center gap-3 rounded-xl px-2 py-2"
                @click="closeSidebar"
            >
                <ApplicationLogo class="h-9 w-9 fill-current text-cyan-300" />
                <div>
                    <p class="text-sm font-semibold tracking-wide text-white">Gestor Comercial</p>
                    <p class="text-xs text-cyan-100/70">SaaS Multi Comercio</p>
                </div>
            </Link>

            <div class="mt-5 rounded-xl border border-cyan-100/15 bg-slate-950/45 p-3">
                <p class="text-xs uppercase tracking-wider text-cyan-100/70">Perfil</p>
                <p class="mt-1 truncate text-sm font-semibold text-white">{{ $page.props.auth.user?.name }}</p>
                <p class="mt-2 text-xs uppercase tracking-wider text-cyan-100/70">Rol</p>
                <p class="mt-1 truncate text-sm text-slate-200">{{ isSuperAdmin ? 'Superadmin' : 'Admin comercio' }}</p>
                <template v-if="!isSuperAdmin">
                    <p class="mt-2 text-xs uppercase tracking-wider text-cyan-100/70">Comercio</p>
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

            <div class="mt-auto border-t border-cyan-100/15 pt-4">
                <p class="text-xs uppercase tracking-wider text-cyan-100/70">Sesion activa</p>
                <p class="mt-1 truncate text-sm text-slate-200">{{ $page.props.auth.user?.email }}</p>
                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="mt-3 inline-flex w-full items-center justify-center rounded-lg border border-rose-300/40 bg-rose-400/12 px-3 py-2 text-sm font-semibold text-rose-100 transition hover:bg-rose-400/24"
                >
                    Cerrar sesion
                </Link>
            </div>
        </aside>

        <div class="lg:pl-72">
            <header class="sticky top-0 z-20 border-b border-cyan-100/15 bg-slate-950/45 backdrop-blur-xl">
                <div class="flex h-16 items-center justify-between px-4 sm:px-6 lg:px-8">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-lg border border-cyan-100/20 bg-slate-900/60 p-2 text-cyan-100 shadow-sm lg:hidden"
                        @click="sidebarOpen = !sidebarOpen"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <div class="ml-3">
                        <p class="text-sm font-semibold text-slate-100">{{ isSuperAdmin ? 'Panel superadmin' : ($page.props.business?.name ?? 'Comercio') }}</p>
                        <p class="text-xs text-slate-300">{{ $page.props.auth.user?.email }}</p>
                    </div>
                </div>
            </header>

            <main class="px-4 py-6 sm:px-6 lg:px-8">
                <header v-if="$slots.header" class="app-section mb-6">
                    <slot name="header" />
                </header>
                <section
                    v-if="$page.props.flash?.success || $page.props.flash?.error"
                    class="mb-6 rounded-2xl border p-4 text-sm"
                    :class="$page.props.flash?.error ? 'border-rose-200/45 bg-rose-400/15 text-rose-100' : 'border-emerald-200/45 bg-emerald-400/15 text-emerald-100'"
                >
                    {{ $page.props.flash?.error || $page.props.flash?.success }}
                </section>
                <section
                    v-if="$page.props.expiration_alerts?.length"
                    class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
                >
                    <p class="font-semibold">Alertas de vencimiento ({{ $page.props.expiration_alerts.length }})</p>
                    <ul class="mt-2 space-y-1">
                        <li v-for="alert in $page.props.expiration_alerts" :key="alert.purchase_item_id">
                            {{ alert.product_name }} · vence {{ alert.expires_at }}
                            <span class="font-medium">
                                ({{ alert.status === 'expired' ? 'vencido' : `faltan ${alert.days_remaining} dias` }})
                            </span>
                        </li>
                    </ul>
                </section>
                <section
                    v-if="$page.props.errors && Object.keys($page.props.errors).length"
                    class="mb-6 rounded-2xl border border-rose-200/45 bg-rose-400/15 p-4 text-sm text-rose-100"
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
