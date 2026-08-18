<script setup>
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    businesses: {
        type: [Array, Object],
        default: () => ({ data: [] }),
    },
    billing_overview: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({ search: '' }),
    },
});

const search = ref(props.filters.search || '');

const businessRows = computed(() => (
    Array.isArray(props.businesses?.data) ? props.businesses.data : props.businesses
));

const paginationLinks = computed(() => (
    Array.isArray(props.businesses?.links) ? props.businesses.links : []
));

const hasPagination = computed(() => Number(props.businesses?.last_page || 1) > 1);

const submitSearch = () => {
    const term = search.value.trim();

    router.get(
        route('admin.businesses.index'),
        term ? { search: term } : {},
        { preserveState: true, replace: true },
    );
};

const clearSearch = () => {
    search.value = '';

    router.get(route('admin.businesses.index'), {}, { preserveState: true, replace: true });
};

const archiveBusiness = (business) => {
    const products = Number(business.products_count) || 0;
    const message = products > 0
        ? `Archivar "${business.name}"? Se ocultara del listado y se bloqueara el acceso, pero se conservan ${products} productos y su historial.`
        : `Archivar "${business.name}"? Se ocultara del listado y se bloqueara el acceso.`;

    if (!window.confirm(message)) {
        return;
    }

    router.delete(route('admin.businesses.archive', business.id), {
        preserveScroll: true,
    });
};

const statusBadgeClass = (tone) => {
    if (tone === 'emerald') return 'bg-emerald-100 text-emerald-700';
    if (tone === 'amber') return 'bg-amber-100 text-amber-700';
    if (tone === 'rose') return 'bg-rose-100 text-rose-700';

    return 'bg-slate-200 text-slate-700';
};
</script>

<template>
    <Head title="Comercios" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold text-slate-100">Comercios y funciones</h2>
                    <p class="mt-1 text-sm text-slate-300/80">Alta, edicion y configuraciones exclusivas por comercio.</p>
                </div>
                <Link
                    :href="route('admin.businesses.create')"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                >
                    Nuevo comercio
                </Link>
            </div>
        </template>

        <section class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-sm backdrop-blur">
            <div class="grid gap-3 md:grid-cols-5">
                <article
                    v-for="item in props.billing_overview"
                    :key="item.key"
                    class="rounded-2xl border border-cyan-100/15 bg-slate-950/35 p-4"
                >
                    <p class="text-xs uppercase tracking-[0.2em] text-slate-400">{{ item.label }}</p>
                    <p class="mt-2 text-3xl font-bold text-slate-100">{{ item.value }}</p>
                </article>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-base font-semibold text-slate-100">Listado</h3>
                <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                    <input
                        v-model="search"
                        type="text"
                        class="rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400"
                        placeholder="Buscar por nombre, slug, email o plan"
                        @keydown.enter.prevent="submitSearch"
                    >
                    <button
                        type="button"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                        @click="submitSearch"
                    >
                        Buscar
                    </button>
                    <button
                        v-if="search"
                        type="button"
                        class="rounded-lg border border-cyan-100/25 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/70"
                        @click="clearSearch"
                    >
                        Limpiar
                    </button>
                </div>
            </div>

            <div class="mt-4 overflow-x-auto rounded-xl border border-cyan-100/20 app-table-wrap">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-950/35">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-slate-300/80">Comercio</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-300/80">Admin inicial</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-300/80">Estado</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-300/80">Abono</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-300/80">Ventas avanzadas</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-300/80">Productos</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-300/80">Proveedores</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-300/80"></th>
                        </tr>
                    </thead>
                    <tbody v-if="businessRows.length" class="divide-y divide-slate-100">
                        <tr v-for="business in businessRows" :key="business.id">
                            <td class="px-3 py-2">
                                <p class="font-semibold text-slate-100">{{ business.name }}</p>
                                <p class="text-xs text-slate-300/80">{{ business.slug }}</p>
                                <p class="text-xs text-slate-300/80">{{ business.email || '-' }}</p>
                            </td>
                            <td class="px-3 py-2">
                                <p class="font-medium text-slate-200">{{ business.admin_user?.name || '-' }}</p>
                                <p class="text-xs text-slate-300/80">{{ business.admin_user?.email || '-' }}</p>
                            </td>
                            <td class="px-3 py-2">
                                <span
                                    class="rounded-full px-2 py-1 text-xs font-semibold"
                                    :class="business.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'"
                                >
                                    {{ business.is_active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="px-3 py-2">
                                <p class="font-medium text-slate-200">{{ business.billing?.maintenance?.plan_title || 'Sin plan' }}</p>
                                <span
                                    class="mt-1 inline-flex rounded-full px-2 py-1 text-xs font-semibold"
                                    :class="statusBadgeClass(business.billing?.maintenance?.tone)"
                                >
                                    {{ business.billing?.maintenance?.status_label || 'Sin configurar' }}
                                </span>
                                <p class="mt-1 text-xs text-slate-300/80">{{ business.billing?.maintenance?.amount_label || '-' }}</p>
                                <p class="mt-1 text-xs text-slate-300/80">{{ business.billing?.maintenance?.status_message }}</p>
                            </td>
                            <td class="px-3 py-2">
                                <span
                                    class="rounded-full px-2 py-1 text-xs font-semibold"
                                    :class="business.advanced_sale_settings_enabled ? 'bg-cyan-100 text-cyan-700' : 'bg-slate-200 text-slate-700'"
                                >
                                    {{ business.advanced_sale_settings_enabled ? 'Habilitada' : 'No' }}
                                </span>
                                <p class="mt-1 text-xs text-slate-300/80">
                                    Sectores {{ business.active_sale_sectors_count }} | Destinos {{ business.active_payment_destinations_count }}
                                </p>
                            </td>
                            <td class="px-3 py-2 text-slate-200">{{ business.products_count }}</td>
                            <td class="px-3 py-2 text-slate-200">{{ business.suppliers_count }}</td>
                            <td class="px-3 py-2 text-right">
                                <div class="flex justify-end gap-2">
                                    <Link
                                        :href="route('admin.businesses.edit', business.id)"
                                        class="rounded-lg border border-cyan-100/25 px-3 py-1 text-xs font-semibold text-slate-300 hover:bg-slate-800/70"
                                    >
                                        Editar
                                    </Link>
                                    <button
                                        type="button"
                                        class="rounded-lg border border-rose-200/35 px-3 py-1 text-xs font-semibold text-rose-100 hover:bg-rose-400/15"
                                        @click="archiveBusiness(business)"
                                    >
                                        Archivar
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                    <tbody v-else>
                        <tr>
                            <td colspan="8" class="px-3 py-6 text-center text-slate-400">No se encontraron comercios.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex flex-col gap-3 border-t border-cyan-100/10 pt-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-slate-300">
                    Mostrando {{ props.businesses?.from || 0 }}-{{ props.businesses?.to || 0 }} de {{ props.businesses?.total || businessRows.length }} comercios
                </p>

                <nav v-if="hasPagination" class="flex flex-wrap gap-2">
                    <template v-for="link in paginationLinks" :key="`${link.label}-${link.url}`">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            preserve-scroll
                            class="rounded-lg border px-3 py-1.5 text-xs font-semibold"
                            :class="link.active
                                ? 'border-cyan-200/50 bg-cyan-400/15 text-cyan-100'
                                : 'border-cyan-100/20 text-slate-300 hover:bg-slate-800/70'"
                            v-html="link.label"
                        />
                        <span
                            v-else
                            class="rounded-lg border border-cyan-100/10 px-3 py-1.5 text-xs font-semibold text-slate-500"
                            v-html="link.label"
                        />
                    </template>
                </nav>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
