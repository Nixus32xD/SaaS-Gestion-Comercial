<script setup>
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    businesses: {
        type: Array,
        default: () => [],
    },
    billing_overview: {
        type: Array,
        default: () => [],
    },
});

const search = ref('');

const filteredBusinesses = computed(() => {
    const term = search.value.trim().toLowerCase();

    if (term === '') {
        return props.businesses;
    }

    return props.businesses.filter((business) => (
        (business.name || '').toLowerCase().includes(term)
        || (business.slug || '').toLowerCase().includes(term)
        || (business.email || '').toLowerCase().includes(term)
        || (business.billing?.maintenance?.plan_title || '').toLowerCase().includes(term)
        || (business.billing?.maintenance?.status_label || '').toLowerCase().includes(term)
    ));
});

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
                <input
                    v-model="search"
                    type="text"
                    class="rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400"
                    placeholder="Buscar por nombre, slug, email o plan"
                >
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
                    <tbody v-if="filteredBusinesses.length" class="divide-y divide-slate-100">
                        <tr v-for="business in filteredBusinesses" :key="business.id">
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
                                    Sectores {{ business.active_sale_sectors_count }} | Cuentas {{ business.active_payment_destinations_count }}
                                </p>
                            </td>
                            <td class="px-3 py-2 text-slate-200">{{ business.products_count }}</td>
                            <td class="px-3 py-2 text-slate-200">{{ business.suppliers_count }}</td>
                            <td class="px-3 py-2 text-right">
                                <Link
                                    :href="route('admin.businesses.edit', business.id)"
                                    class="rounded-lg border border-cyan-100/25 px-3 py-1 text-xs font-semibold text-slate-300 hover:bg-slate-800/70"
                                >
                                    Editar
                                </Link>
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
        </section>
    </AuthenticatedLayout>
</template>
