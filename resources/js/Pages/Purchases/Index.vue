<script setup>
import AppPanel from '@/Components/AppPanel.vue';
import MetricCard from '@/Components/MetricCard.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const props = defineProps({
    purchases: { type: Object, required: true },
    filters: { type: Object, default: () => ({ search: '' }) },
});

const state = reactive({ search: props.filters.search || '' });

const moneyFormatter = new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    minimumFractionDigits: 2,
});

const money = (value) => moneyFormatter.format(Number(value) || 0);
const visiblePurchases = computed(() => props.purchases.data || []);
const visibleTotal = computed(() => visiblePurchases.value.reduce((carry, purchase) => carry + (Number(purchase.total) || 0), 0));
const averageTicket = computed(() => (
    visiblePurchases.value.length ? visibleTotal.value / visiblePurchases.value.length : 0
));
const suppliersInView = computed(() => new Set(
    visiblePurchases.value.map((purchase) => purchase.supplier).filter(Boolean),
).size);
const itemsInView = computed(() => visiblePurchases.value.reduce((carry, purchase) => carry + (Number(purchase.items_count) || 0), 0));

const filter = () => {
    router.get(route('purchases.index'), { search: state.search }, {
        only: ['filters', 'purchases'],
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const clearFilters = () => {
    state.search = '';
    filter();
};
</script>

<template>
    <Head title="Compras" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold text-slate-100">Compras</h2>
                    <p class="mt-1 text-sm text-slate-300/80">Historial de reposicion con foco en proveedor, fecha y total invertido.</p>
                </div>
                <Link :href="route('purchases.create')" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Nueva compra</Link>
            </div>
        </template>

        <div class="grid gap-6">
            <section class="app-kpi-grid">
                <MetricCard label="Compras visibles" :value="purchases.total" hint="Cantidad total con el filtro actual." />
                <MetricCard label="Inversion visible" :value="money(visibleTotal)" hint="Suma de la pagina actual para lectura rapida." :tone="visibleTotal > 0 ? 'accent' : 'default'" />
                <MetricCard label="Ticket promedio" :value="money(averageTicket)" hint="Promedio por compra visible." />
                <MetricCard label="Proveedores en vista" :value="suppliersInView" :hint="`${itemsInView} items cargados en la pagina actual.`" />
            </section>

            <AppPanel title="Busqueda operativa" subtitle="Encuentra rapido una compra por numero o por observaciones de carga.">
                <template #actions>
                    <button type="button" class="rounded-lg border border-rose-300/35 px-3 py-2 text-sm font-semibold text-rose-100 hover:bg-rose-400/20" @click="clearFilters">
                        Limpiar
                    </button>
                </template>

                <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_auto]">
                    <input
                        v-model="state.search"
                        type="text"
                        class="rounded-xl text-sm"
                        placeholder="Buscar por numero o notas"
                        @keyup.enter="filter"
                    >
                    <button type="button" class="rounded-lg border border-cyan-100/25 px-3 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/70" @click="filter">Buscar</button>
                </div>

                <div v-if="state.search" class="mt-4 app-chip-row">
                    <StatusBadge tone="info" :label="`Busqueda: ${state.search}`" />
                </div>
            </AppPanel>

            <AppPanel title="Listado de compras" subtitle="Prioriza numero, proveedor, fecha e importe para resolver reposiciones y controles.">
                <div class="grid gap-3 md:hidden">
                    <article v-for="purchase in purchases.data" :key="purchase.id" class="rounded-xl border border-cyan-100/20 bg-slate-950/35 p-4 text-sm text-slate-300">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-semibold text-slate-100">{{ purchase.purchase_number || `COMPRA-${purchase.id}` }}</p>
                                <p class="mt-1 text-xs text-slate-400">{{ purchase.purchased_at || '-' }} · {{ purchase.user || 'Sin usuario' }}</p>
                                <p class="mt-1 text-xs text-slate-400">{{ purchase.supplier || 'Sin proveedor' }}</p>
                            </div>
                            <Link :href="route('purchases.show', purchase.id)" class="shrink-0 rounded-lg border border-cyan-100/25 px-3 py-1 text-xs font-semibold text-slate-300 hover:bg-slate-800/70">Ver</Link>
                        </div>

                        <div class="mt-3 grid gap-2 text-xs text-slate-400">
                            <p>Items: <span class="text-slate-200">{{ purchase.items_count }}</span></p>
                            <p>Total: <span class="text-slate-100">{{ money(purchase.total) }}</span></p>
                            <p>Notas: <span class="text-slate-200">{{ purchase.notes || 'Sin notas' }}</span></p>
                        </div>
                    </article>
                </div>

                <div class="hidden overflow-x-auto rounded-xl border border-cyan-100/20 app-table-wrap md:block">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-950/35">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Compra</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Proveedor</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Items</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Total</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80"></th>
                            </tr>
                        </thead>
                        <tbody v-if="purchases.data.length" class="divide-y divide-slate-100">
                            <tr v-for="purchase in purchases.data" :key="purchase.id">
                                <td class="px-3 py-2 align-top">
                                    <p class="font-semibold text-slate-100">{{ purchase.purchase_number || `COMPRA-${purchase.id}` }}</p>
                                    <p class="mt-1 text-xs text-slate-400">{{ purchase.purchased_at || '-' }} · {{ purchase.user || 'Sin usuario' }}</p>
                                    <p class="mt-2 text-xs text-slate-400">{{ purchase.notes || 'Sin notas' }}</p>
                                </td>
                                <td class="px-3 py-2 align-top">
                                    <div class="app-chip-row">
                                        <StatusBadge tone="info" size="sm" :label="purchase.supplier || 'Sin proveedor'" />
                                    </div>
                                </td>
                                <td class="px-3 py-2 font-semibold text-slate-100 align-top">{{ purchase.items_count }}</td>
                                <td class="px-3 py-2 font-semibold text-slate-100 align-top">{{ money(purchase.total) }}</td>
                                <td class="px-3 py-2 text-right align-top">
                                    <Link :href="route('purchases.show', purchase.id)" class="rounded-lg border border-cyan-100/25 px-3 py-1 text-xs font-semibold text-slate-300 hover:bg-slate-800/70">Ver</Link>
                                </td>
                            </tr>
                        </tbody>
                        <tbody v-else>
                            <tr>
                                <td colspan="5" class="px-3 py-6 text-center text-slate-400">No hay compras registradas con los filtros actuales.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="purchases.links?.length" class="mt-4 flex flex-wrap gap-2">
                    <component
                        :is="link.url ? Link : 'span'"
                        v-for="(link, index) in purchases.links"
                        :key="index"
                        :href="link.url || undefined"
                        class="rounded-lg border px-3 py-1 text-xs"
                        :class="link.active ? 'border-cyan-300/50 bg-cyan-400/15 text-cyan-100' : 'border-cyan-100/25 text-slate-300'"
                        v-html="link.label"
                    />
                </div>
            </AppPanel>
        </div>
    </AuthenticatedLayout>
</template>
