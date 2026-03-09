<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    sales: { type: Object, required: true },
    filters: { type: Object, default: () => ({ search: '' }) },
});

const state = reactive({ search: props.filters.search || '' });

const filter = () => {
    router.get(route('sales.index'), { search: state.search }, { preserveState: true, replace: true });
};

const money = (value) => new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    minimumFractionDigits: 2,
}).format(Number(value) || 0);
</script>

<template>
    <Head title="Ventas" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold text-slate-100">Ventas</h2>
                    <p class="mt-1 text-sm text-slate-300/80">Historial de ventas del comercio.</p>
                </div>
                <Link :href="route('sales.create')" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Nueva venta</Link>
            </div>
        </template>

        <section class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 backdrop-blur p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <input
                    v-model="state.search"
                    type="text"
                    class="rounded-xl border-cyan-100/25 text-sm"
                    placeholder="Buscar por numero o notas"
                    @keyup.enter="filter"
                >
                <button type="button" class="rounded-lg border border-cyan-100/25 px-3 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/70" @click="filter">Buscar</button>
            </div>

            <div class="mt-4 overflow-x-auto rounded-xl border border-cyan-100/20 app-table-wrap">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-950/35">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-slate-300/80">Numero</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-300/80">Fecha</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-300/80">Items</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-300/80">Total</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-300/80"></th>
                        </tr>
                    </thead>
                    <tbody v-if="sales.data.length" class="divide-y divide-slate-100">
                        <tr v-for="sale in sales.data" :key="sale.id">
                            <td class="px-3 py-2 font-semibold text-slate-100">{{ sale.sale_number || `VENTA-${sale.id}` }}</td>
                            <td class="px-3 py-2">{{ sale.sold_at || '-' }}</td>
                            <td class="px-3 py-2">{{ sale.items_count }}</td>
                            <td class="px-3 py-2">{{ money(sale.total) }}</td>
                            <td class="px-3 py-2 text-right">
                                <Link :href="route('sales.show', sale.id)" class="rounded-lg border border-cyan-100/25 px-3 py-1 text-xs font-semibold text-slate-300 hover:bg-slate-800/70">Ver</Link>
                            </td>
                        </tr>
                    </tbody>
                    <tbody v-else>
                        <tr>
                            <td colspan="5" class="px-3 py-6 text-center text-slate-400">No hay ventas registradas.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="sales.links?.length" class="mt-4 flex flex-wrap gap-2">
                <component
                    :is="link.url ? Link : 'span'"
                    v-for="(link, index) in sales.links"
                    :key="index"
                    :href="link.url || undefined"
                    class="rounded-lg border px-3 py-1 text-xs"
                    :class="link.active ? 'border-cyan-300/50 bg-cyan-400/15 text-cyan-100' : 'border-cyan-100/25 text-slate-300'"
                    v-html="link.label"
                />
            </div>
        </section>
    </AuthenticatedLayout>
</template>

