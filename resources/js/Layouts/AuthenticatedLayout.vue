<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import FlashNotifications from '@/Components/FlashNotifications.vue';
import SidebarLink from '@/Components/SidebarLink.vue';
import { Link, router, usePage } from '@inertiajs/vue3';

const sidebarOpen = ref(false);
const sidebar = ref(null);
const menuButton = ref(null);
let bodyOverflow = '';
let documentOverflow = '';
const page = usePage();

const drawerFocusableSelector = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
].join(', ');

const isSuperAdmin = computed(() => Boolean(page.props.auth?.is_super_admin));
const can = (permission) => Boolean(page.props.auth?.is_owner || page.props.auth?.permissions?.includes(permission));
const canManageUsers = computed(() => can('users.view'));
const hasElectronicBilling = computed(() => Boolean(page.props.modules?.electronic_billing?.enabled));
const hasMultipleBranches = computed(() => (
    Array.isArray(page.props.branches)
        ? page.props.branches.length > 1
        : Boolean(page.props.business?.has_multiple_branches)
));
const subscriptionNotice = computed(() => (
    !isSuperAdmin.value && page.props.business_subscription?.show_notice
        ? page.props.business_subscription
        : null
));

const subscriptionNoticeClass = computed(() => {
    if (!subscriptionNotice.value) return '';

    return subscriptionNotice.value.tone === 'rose'
        ? 'border-rose-200/45 bg-rose-400/15 text-rose-100'
        : 'border-amber-200/45 bg-amber-400/15 text-amber-50';
});

const navigation = computed(() => {
    if (isSuperAdmin.value) {
        return [
            { label: 'Administración', items: [
                { label: 'Comercios y funciones', route: 'admin.businesses.index', pattern: 'admin.businesses.*', icon: 'AD' },
                { label: 'Catálogo global', route: 'admin.global-products.index', pattern: 'admin.global-products.*', icon: 'CG' },
                { label: 'Guía comercial', route: 'admin.commercial-guide.index', pattern: 'admin.commercial-guide.*', icon: 'GC' },
                { label: 'Mi cuenta', route: 'profile.edit', pattern: 'profile.*', icon: 'US' },
            ] },
        ];
    }

    return [
        { label: 'Operación', items: [
            ...(can('dashboard.view') ? [{ label: 'Dashboard', route: 'dashboard', pattern: 'dashboard', icon: 'DB' }] : []),
            ...(can('sales.view') ? [{ label: 'Ventas', route: 'sales.index', pattern: 'sales.*', icon: 'VT' }] : []),
            ...(can('purchases.view') ? [{ label: 'Compras', route: 'purchases.index', pattern: 'purchases.*', icon: 'CP' }] : []),
            ...(can('cash_register.view') ? [{ label: 'Caja', route: 'cash-register.index', pattern: 'cash-register.*', icon: 'CJ' }] : []),
            ...(can('suppliers.view') ? [{ label: 'Proveedores', route: 'suppliers.index', pattern: 'suppliers.*', icon: 'PV' }] : []),
        ] },
        { label: 'Inventario', items: [
            ...(can('products.view') ? [{ label: 'Productos', route: 'products.index', pattern: 'products.*', icon: 'PR' }] : []),
            ...(can('categories.view') ? [{ label: 'Categorías', route: 'categories.index', pattern: 'categories.*', icon: 'CT' }] : []),
            ...(can('inventory.transfer') ? [{ label: 'Transferencias', route: 'inventory.transfers.index', pattern: 'inventory.transfers.*', icon: 'TR' }] : []),
        ] },
        { label: 'Clientes', items: [
            ...(can('customers.view') ? [{ label: 'Clientes', route: 'customers.index', pattern: 'customers.*', icon: 'CL' }] : []),
            ...(can('accounts_receivable.view') ? [{ label: 'Cuenta corriente', route: 'customer-accounts.index', pattern: 'customer-accounts.*', icon: 'CC' }] : []),
        ] },
        { label: 'Integraciones', items: [
            ...(hasElectronicBilling.value && can('fiscal.view') ? [{ label: 'Facturación electrónica', route: 'electronic-billing.index', pattern: 'electronic-billing.*', icon: 'FE' }] : []),
            ...(can('mercadopago.settings.view') ? [{ label: 'Mercado Pago', route: 'mercadopago-settings.edit', pattern: 'mercadopago-settings.*', icon: 'MP' }] : []),
        ] },
        { label: 'Administración', items: [
            ...(canManageUsers.value ? [{ label: 'Usuarios y permisos', route: 'users.index', pattern: 'users.*', icon: 'US' }] : []),
            ...(can('notifications.manage') ? [{ label: 'Notificaciones', route: 'notifications.edit', pattern: 'notifications.*', icon: 'NT' }] : []),
            { label: 'Mi cuenta', route: 'profile.edit', pattern: 'profile.*', icon: 'US' },
        ] },
    ].filter((group) => group.items.length > 0);
});

const closeSidebar = () => {
    sidebarOpen.value = false;
};

const toggleSidebar = () => {
    sidebarOpen.value = !sidebarOpen.value;
};

const handleEscape = (event) => {
    if (event.key === 'Escape' && sidebarOpen.value) {
        closeSidebar();
    }
};

const handleFocusTrap = (event) => {
    if (!sidebarOpen.value || event.key !== 'Tab') return;

    const focusable = Array.from(sidebar.value?.querySelectorAll(drawerFocusableSelector) ?? [])
        .filter((element) => element.getClientRects().length > 0);

    if (!focusable.length) {
        event.preventDefault();
        sidebar.value?.focus();
        return;
    }

    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
};

watch(sidebarOpen, async (isOpen) => {
    if (isOpen) {
        bodyOverflow = document.body.style.overflow;
        documentOverflow = document.documentElement.style.overflow;
        document.body.style.overflow = 'hidden';
        document.documentElement.style.overflow = 'hidden';
        await nextTick();
        sidebar.value?.focus();
        return;
    }

    document.body.style.overflow = bodyOverflow;
    document.documentElement.style.overflow = documentOverflow;
    await nextTick();
    menuButton.value?.focus();
});

onMounted(() => {
    window.addEventListener('keydown', handleEscape);
    window.addEventListener('keydown', handleFocusTrap);
});
onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleEscape);
    window.removeEventListener('keydown', handleFocusTrap);
    document.body.style.overflow = bodyOverflow;
    document.documentElement.style.overflow = documentOverflow;
});

const changeBranch = (event) => {
    router.put(route('branches.current.update'), {
        branch_id: Number(event.target.value),
    }, {
        preserveScroll: true,
    });
};
</script>

<template>
    <div
        class="app-shell min-h-screen overflow-x-clip text-slate-100"
        style="background:
            radial-gradient(circle at 85% 15%, rgba(56, 189, 248, 0.24), transparent 40%),
            radial-gradient(circle at 10% 70%, rgba(15, 23, 42, 0.45), transparent 38%),
            linear-gradient(120deg, #05264e 0%, #0f172a 45%, #101f4d 100%);"
    >
        <FlashNotifications />

        <div
            v-if="sidebarOpen"
            class="fixed inset-0 z-30 bg-slate-950/70 2xl:hidden"
            aria-hidden="true"
            @click="closeSidebar"
        />

        <aside
            id="main-navigation"
            ref="sidebar"
            tabindex="-1"
            aria-label="Navegación principal"
            class="fixed inset-y-0 left-0 z-40 flex w-[min(18rem,calc(100vw-1rem))] max-w-[18rem] flex-col overflow-y-auto border-r border-cyan-100/15 bg-slate-950/70 px-5 py-4 text-slate-100 shadow-xl backdrop-blur-xl transition-transform duration-300 ease-out"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full 2xl:translate-x-0'"
        >
            <Link
                :href="isSuperAdmin ? route('admin.businesses.index') : route('dashboard')"
                class="flex items-center gap-3 rounded-xl px-2 py-2"
                @click="closeSidebar"
            >
                <ApplicationLogo class="h-9 w-9 fill-current text-cyan-300" />
                <div>
                    <p class="text-sm font-semibold tracking-wide text-white">Gestor Comercial</p>
                    <p class="text-xs text-cyan-100/70">Operacion por comercio</p>
                </div>
            </Link>

            <div class="mt-5 rounded-xl border border-cyan-100/15 bg-slate-950/45 p-3">
                <p class="text-xs uppercase tracking-wider text-cyan-100/70">Perfil</p>
                <p class="mt-1 truncate text-sm font-semibold text-white">{{ $page.props.auth.user?.name }}</p>
                <p class="mt-2 text-xs uppercase tracking-wider text-cyan-100/70">Rol</p>
                <p class="mt-1 truncate text-sm text-slate-200">{{ isSuperAdmin ? 'Superadmin' : (page.props.auth?.is_owner ? 'Propietario' : (page.props.auth?.roles ?? []).map((role) => role.name).join(', ') || 'Sin roles') }}</p>
                <template v-if="!isSuperAdmin">
                    <p class="mt-2 text-xs uppercase tracking-wider text-cyan-100/70">Comercio</p>
                    <p class="mt-1 truncate text-sm text-slate-200">{{ $page.props.business?.name ?? 'Sin comercio' }}</p>
                    <template v-if="hasMultipleBranches">
                        <p class="mt-2 text-xs uppercase tracking-wider text-cyan-100/70">Sucursal</p>
                        <p class="mt-1 truncate text-sm text-slate-200">{{ $page.props.branch?.name ?? 'Sin sucursal' }}</p>
                    </template>
                </template>
            </div>

            <nav class="mt-6 space-y-5" aria-label="Secciones principales">
                <section v-for="group in navigation" :key="group.label" :aria-label="group.label">
                    <p class="mb-1 px-3 text-[11px] font-semibold uppercase tracking-[0.16em] text-cyan-100/55">{{ group.label }}</p>
                    <div class="space-y-1">
                        <SidebarLink
                            v-for="item in group.items"
                            :key="item.route"
                            :href="route(item.route)"
                            :active="route().current(item.pattern)"
                            :icon="item.icon"
                            @click="closeSidebar"
                        >
                            {{ item.label }}
                        </SidebarLink>
                    </div>
                </section>
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

        <div class="min-w-0 2xl:pl-72" :inert="sidebarOpen || null" :aria-hidden="sidebarOpen ? 'true' : null">
            <header class="sticky top-0 z-20 border-b border-cyan-100/15 bg-slate-950/45 backdrop-blur-xl">
                <div class="flex h-16 items-center justify-between gap-3 px-4 sm:px-6 lg:px-8">
                    <button
                        ref="menuButton"
                        type="button"
                        :aria-label="sidebarOpen ? 'Cerrar navegación' : 'Abrir navegación'"
                        aria-controls="main-navigation"
                        :aria-expanded="sidebarOpen"
                        class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg border border-cyan-100/20 bg-slate-900/60 p-2 text-cyan-100 shadow-sm 2xl:hidden"
                        @click="toggleSidebar"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <div class="ml-3 min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-slate-100">{{ isSuperAdmin ? 'Panel superadmin' : ($page.props.business?.name ?? 'Comercio') }}</p>
                        <p v-if="isSuperAdmin" class="truncate text-xs text-slate-300">{{ $page.props.auth.user?.email }}</p>
                        <label v-else-if="hasMultipleBranches" class="mt-1 flex max-w-xs items-center gap-2 text-xs text-slate-300">
                            <span>Sucursal</span>
                            <select
                                :value="$page.props.branch?.id ?? ''"
                                class="min-w-0 flex-1 rounded-md border border-cyan-100/25 bg-slate-900/80 px-2 py-1 text-xs text-slate-100"
                                :disabled="!$page.props.branch"
                                @change="changeBranch"
                            >
                                <option v-for="branch in ($page.props.branches ?? [])" :key="branch.id" :value="branch.id">
                                    {{ branch.name }}
                                </option>
                            </select>
                        </label>
                    </div>
                </div>
            </header>

            <main class="min-w-0 px-4 py-6 sm:px-6 lg:px-8">
                <header
                    v-if="$slots.header"
                    class="mb-6 rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-4 shadow-[0_20px_45px_rgba(8,47,73,0.36)] backdrop-blur sm:p-5"
                >
                    <slot name="header" />
                </header>
                <section
                    v-if="subscriptionNotice"
                    class="mb-6 rounded-2xl border p-4 text-sm"
                    :class="subscriptionNoticeClass"
                >
                    <p class="font-semibold">{{ subscriptionNotice.status_label }}</p>
                    <p class="mt-1">{{ subscriptionNotice.client_notice || subscriptionNotice.status_message }}</p>
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
