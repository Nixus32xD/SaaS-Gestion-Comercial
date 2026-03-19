<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const props = defineProps({
    sales: { type: Object, required: true },
    filters: { type: Object, default: () => ({ search: '' }) },
    advanced_sale_settings: { type: Object, default: () => ({ enabled: false, sale_sectors: [], payment_destinations: [] }) },
    monthly_summary: { type: Object, required: true },
});

const state = reactive({
    search: props.filters.search || '',
    month: props.filters.month || '',
    sale_sector_id: props.filters.sale_sector_id || '',
    payment_destination_id: props.filters.payment_destination_id || '',
});

const filter = () => {
    const params = {};

    if (state.search) params.search = state.search;
    if (state.month) params.month = state.month;
    if (state.sale_sector_id) params.sale_sector_id = state.sale_sector_id;
    if (state.payment_destination_id) params.payment_destination_id = state.payment_destination_id;

    router.get(route('sales.index'), params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['filters', 'sales', 'monthly_summary'],
    });
};

const moneyFormatter = new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    minimumFractionDigits: 2,
});

const money = (value) => moneyFormatter.format(Number(value) || 0);

const paymentMethodLabel = (value) => (value === 'transfer' ? 'Transferencia' : 'Efectivo');
const advancedSaleSettingsEnabled = computed(() => Boolean(props.advanced_sale_settings?.enabled));
const saleSectors = computed(() => (props.advanced_sale_settings?.sale_sectors || []).filter((item) => item.is_active));
const paymentDestinations = computed(() => (props.advanced_sale_settings?.payment_destinations || []).filter((item) => item.is_active));
const summaryMonthLabel = computed(() => new Intl.DateTimeFormat('es-AR', {
    month: 'long',
    year: 'numeric',
}).format(new Date(`${props.monthly_summary.month}-01T00:00:00`)));

const clearFilters = () => {
    state.search = '';
    state.month = '';
    state.sale_sector_id = '';
    state.payment_destination_id = '';
    filter();
};
</script>

<template>
    <Head title="Ventas" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <h2 class="text-2xl font-bold text-slate-100">Ventas</h2>
                    <p class="mt-1 text-sm text-slate-300/80">Historial de ventas del comercio.</p>
                </div>
                <div class="flex w-full sm:w-auto sm:justify-end">
                    <Link :href="route('sales.create')" class="inline-flex w-full items-center justify-center rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500 sm:w-auto">Nueva venta</Link>
                </div>
            </div>
        </template>

        <div class="grid gap-6">
            <section class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 backdrop-blur p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-slate-100">Resumen mensual</h3>
                        <p class="mt-1 text-sm text-slate-300/80">Mes de referencia: {{ summaryMonthLabel }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-950/35 px-4 py-3 text-sm text-slate-300">
                        <p>Ventas: <strong>{{ monthly_summary.sales_count }}</strong></p>
                        <p class="mt-1 text-slate-100">Total: <strong>{{ money(monthly_summary.total) }}</strong></p>
                    </div>
                </div>

                <div v-if="advancedSaleSettingsEnabled" class="mt-4 grid gap-4 xl:grid-cols-2">
                    <article class="rounded-2xl border border-cyan-100/15 bg-slate-950/35 p-4">
                        <h4 class="text-sm font-semibold text-slate-100">Totales por sector</h4>
                        <ul v-if="monthly_summary.by_sector?.length" class="mt-3 space-y-2 text-sm">
                            <li v-for="sector in monthly_summary.by_sector" :key="sector.id" class="rounded-xl border border-cyan-100/15 bg-slate-900/45 px-3 py-2">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="font-medium text-slate-100">{{ sector.name }}</span>
                                    <span class="text-slate-200">{{ money(sector.total) }}</span>
                                </div>
                                <p class="mt-1 text-xs text-slate-400">{{ sector.sales_count }} ventas</p>
                            </li>
                        </ul>
                        <p v-else class="mt-3 text-sm text-slate-400">Sin ventas por sector en este periodo.</p>
                    </article>

                    <article class="rounded-2xl border border-cyan-100/15 bg-slate-950/35 p-4">
                        <h4 class="text-sm font-semibold text-slate-100">Totales por cuenta de cobro</h4>
                        <ul v-if="monthly_summary.by_payment_destination?.length" class="mt-3 space-y-2 text-sm">
                            <li v-for="destination in monthly_summary.by_payment_destination" :key="destination.id" class="rounded-xl border border-cyan-100/15 bg-slate-900/45 px-3 py-2">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="font-medium text-slate-100">{{ destination.name }}</span>
                                    <span class="text-slate-200">{{ money(destination.total) }}</span>
                                </div>
                                <p class="mt-1 text-xs text-slate-400">{{ destination.sales_count }} ventas</p>
                            </li>
                        </ul>
                        <p v-else class="mt-3 text-sm text-slate-400">Sin ventas por cuenta en este periodo.</p>
                    </article>
                </div>
            </section>

            <section class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 backdrop-blur p-5 shadow-sm">
                <div
                    class="grid gap-3 md:grid-cols-2"
                    :class="advancedSaleSettingsEnabled ? 'xl:grid-cols-[minmax(0,1.35fr)_minmax(0,0.9fr)_minmax(0,1fr)_minmax(0,1fr)_auto]' : 'xl:grid-cols-[minmax(0,1.5fr)_minmax(0,0.9fr)_auto]'"
                >
                    <input
                        v-model="state.search"
                        type="text"
                        class="min-w-0 rounded-xl border-cyan-100/25 text-sm"
                        placeholder="Buscar por numero o notas"
                        @keyup.enter="filter"
                    >
                    <input
                        v-model="state.month"
                        type="month"
                        class="min-w-0 rounded-xl border-cyan-100/25 text-sm"
                    >
                    <select
                        v-if="advancedSaleSettingsEnabled"
                        v-model="state.sale_sector_id"
                        class="min-w-0 rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100"
                    >
                        <option value="">Todos los sectores</option>
                        <option v-for="sector in saleSectors" :key="sector.id" :value="sector.id">{{ sector.name }}</option>
                    </select>
                    <select
                        v-if="advancedSaleSettingsEnabled"
                        v-model="state.payment_destination_id"
                        class="min-w-0 rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100"
                    >
                        <option value="">Todas las cuentas</option>
                        <option v-for="destination in paymentDestinations" :key="destination.id" :value="destination.id">{{ destination.name }}</option>
                    </select>
                    <div
                        class="grid grid-cols-2 gap-2 md:col-span-2"
                        :class="advancedSaleSettingsEnabled ? 'xl:col-span-1 xl:flex xl:justify-end' : 'xl:col-span-1 xl:flex xl:justify-end'"
                    >
                        <button type="button" class="inline-flex items-center justify-center rounded-lg border border-cyan-100/25 px-3 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/70" @click="filter">Buscar</button>
                        <button type="button" class="inline-flex items-center justify-center rounded-lg border border-rose-300/35 px-3 py-2 text-sm font-semibold text-rose-100 hover:bg-rose-400/20" @click="clearFilters">Limpiar</button>
                    </div>
                </div>

                <div class="mt-4 overflow-x-auto rounded-xl border border-cyan-100/20 app-table-wrap">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-950/35">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-slate-300/80">Numero</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-300/80">Fecha</th>
                            <th v-if="advancedSaleSettingsEnabled" class="px-3 py-2 text-left font-medium text-slate-300/80">Sector</th>
                            <th v-if="advancedSaleSettingsEnabled" class="px-3 py-2 text-left font-medium text-slate-300/80">Cuenta</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-300/80">Pago</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-300/80">Items</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-300/80">Total</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-300/80"></th>
                        </tr>
                    </thead>
                    <tbody v-if="sales.data.length" class="divide-y divide-slate-100">
                        <tr v-for="sale in sales.data" :key="sale.id">
                            <td class="px-3 py-2 font-semibold text-slate-100">{{ sale.sale_number || `VENTA-${sale.id}` }}</td>
                            <td class="px-3 py-2">{{ sale.sold_at || '-' }}</td>
                            <td v-if="advancedSaleSettingsEnabled" class="px-3 py-2">{{ sale.sale_sector || '-' }}</td>
                            <td v-if="advancedSaleSettingsEnabled" class="px-3 py-2">{{ sale.payment_destination || '-' }}</td>
                            <td class="px-3 py-2">{{ paymentMethodLabel(sale.payment_method) }}</td>
                            <td class="px-3 py-2">{{ sale.items_count }}</td>
                            <td class="px-3 py-2">{{ money(sale.total) }}</td>
                            <td class="px-3 py-2 text-right">
                                <Link :href="route('sales.show', sale.id)" class="rounded-lg border border-cyan-100/25 px-3 py-1 text-xs font-semibold text-slate-300 hover:bg-slate-800/70">Ver</Link>
                            </td>
                        </tr>
                    </tbody>
                    <tbody v-else>
                        <tr>
                            <td :colspan="advancedSaleSettingsEnabled ? 8 : 6" class="px-3 py-6 text-center text-slate-400">No hay ventas registradas.</td>
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
        </div>
    </AuthenticatedLayout>
</template>

