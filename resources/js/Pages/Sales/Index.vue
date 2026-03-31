<script setup>
import AppPanel from '@/Components/AppPanel.vue';
import MetricCard from '@/Components/MetricCard.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const props = defineProps({
    sales: { type: Object, required: true },
    filters: { type: Object, default: () => ({ search: '' }) },
    advanced_sale_settings: { type: Object, default: () => ({ enabled: false, sale_sectors: [], payment_destinations: [] }) },
    monthly_summary: { type: Object, required: true },
    receipt_feature_available: { type: Boolean, default: false },
});

const state = reactive({
    search: props.filters.search || '',
    month: props.filters.month || '',
    sale_sector_id: props.filters.sale_sector_id || '',
    payment_destination_id: props.filters.payment_destination_id || '',
});

const moneyFormatter = new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    minimumFractionDigits: 2,
});

const money = (value) => moneyFormatter.format(Number(value) || 0);

const paymentMethodLabel = (value) => (value === 'transfer' ? 'Transferencia' : 'Efectivo');
const paymentStatusLabel = (value) => {
    if (value === 'partial') return 'Parcial';
    if (value === 'pending') return 'Fiada';
    return 'Pagada';
};
const paymentStatusTone = (value) => {
    if (value === 'partial') return 'warning';
    if (value === 'pending') return 'danger';
    return 'success';
};

const advancedSaleSettingsEnabled = computed(() => Boolean(props.advanced_sale_settings?.enabled));
const saleSectors = computed(() => (props.advanced_sale_settings?.sale_sectors || []).filter((item) => item.is_active));
const paymentDestinations = computed(() => (props.advanced_sale_settings?.payment_destinations || []).filter((item) => item.is_active));
const visibleSales = computed(() => props.sales.data || []);
const visibleTotal = computed(() => visibleSales.value.reduce((carry, sale) => carry + (Number(sale.total) || 0), 0));
const visiblePendingTotal = computed(() => visibleSales.value.reduce((carry, sale) => carry + (Number(sale.pending_amount) || 0), 0));
const pendingSalesCount = computed(() => visibleSales.value.filter((sale) => Number(sale.pending_amount) > 0).length);
const averageTicket = computed(() => (
    visibleSales.value.length ? visibleTotal.value / visibleSales.value.length : 0
));
const summaryMonthLabel = computed(() => new Intl.DateTimeFormat('es-AR', {
    month: 'long',
    year: 'numeric',
}).format(new Date(`${props.monthly_summary.month}-01T00:00:00`)));
const activeFilters = computed(() => {
    const filters = [];

    if (state.search) {
        filters.push({ key: 'search', label: `Busqueda: ${state.search}` });
    }

    if (state.month) {
        filters.push({ key: 'month', label: `Mes: ${state.month}` });
    }

    if (state.sale_sector_id) {
        const sector = saleSectors.value.find((item) => String(item.id) === String(state.sale_sector_id));
        filters.push({ key: 'sale_sector_id', label: `Sector: ${sector?.name || state.sale_sector_id}` });
    }

    if (state.payment_destination_id) {
        const destination = paymentDestinations.value.find((item) => String(item.id) === String(state.payment_destination_id));
        filters.push({ key: 'payment_destination_id', label: `Cuenta: ${destination?.name || state.payment_destination_id}` });
    }

    return filters;
});

const currentFilterParams = computed(() => {
    const params = {};

    if (state.search) params.search = state.search;
    if (state.month) params.month = state.month;
    if (state.sale_sector_id) params.sale_sector_id = state.sale_sector_id;
    if (state.payment_destination_id) params.payment_destination_id = state.payment_destination_id;

    return params;
});

const printUrl = computed(() => route('sales.print.index', currentFilterParams.value));

const filter = () => {
    router.get(route('sales.index'), currentFilterParams.value, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['filters', 'sales', 'monthly_summary'],
    });
};

const clearFilters = () => {
    state.search = '';
    state.month = '';
    state.sale_sector_id = '';
    state.payment_destination_id = '';
    filter();
};

const clearSingleFilter = (key) => {
    state[key] = '';
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
                    <p class="mt-1 text-sm text-slate-300/80">Historial operativo con foco en cobros, pendientes y lectura rapida.</p>
                </div>
                <div class="flex w-full sm:w-auto sm:justify-end">
                    <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                        <a :href="printUrl" target="_blank" rel="noopener" class="inline-flex w-full items-center justify-center rounded-xl border border-cyan-100/20 px-4 py-2 text-sm font-semibold text-slate-300 transition hover:bg-slate-800/60 hover:text-slate-100 sm:w-auto">Imprimir listado</a>
                        <Link :href="route('sales.create')" class="inline-flex w-full items-center justify-center rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500 sm:w-auto">Nueva venta</Link>
                    </div>
                </div>
            </div>
        </template>

        <div class="grid gap-6">
            <section class="app-kpi-grid">
                <MetricCard label="Ventas del mes" :value="monthly_summary.sales_count" :hint="`Periodo de referencia: ${summaryMonthLabel}.`" />
                <MetricCard label="Facturacion mensual" :value="money(monthly_summary.total)" hint="Total vendido en el mes seleccionado." :tone="monthly_summary.total > 0 ? 'accent' : 'default'" />
                <MetricCard label="Pendiente visible" :value="money(visiblePendingTotal)" :hint="`${pendingSalesCount} ventas visibles con saldo por cobrar.`" :tone="pendingSalesCount > 0 ? 'warning' : 'success'" />
                <MetricCard label="Ticket visible" :value="money(averageTicket)" hint="Promedio sobre la pagina actual para lectura rapida." />
            </section>

            <AppPanel title="Resumen mensual" subtitle="Lee rapido donde entra el dinero y que canales conviene revisar.">
                <div class="rounded-2xl border border-cyan-100/15 bg-slate-950/35 px-4 py-3 text-sm text-slate-300">
                    <p>Mes de referencia: <strong class="text-slate-100">{{ summaryMonthLabel }}</strong></p>
                    <p class="mt-1">Ventas: <strong class="text-slate-100">{{ monthly_summary.sales_count }}</strong></p>
                    <p class="mt-1">Total mensual: <strong class="text-slate-100">{{ money(monthly_summary.total) }}</strong></p>
                </div>

                <div v-if="advancedSaleSettingsEnabled" class="mt-4 grid gap-4 xl:grid-cols-2">
                    <article class="app-subsection">
                        <h4 class="app-section-title">Totales por sector</h4>
                        <ul v-if="monthly_summary.by_sector?.length" class="mt-3 space-y-2 text-sm">
                            <li v-for="sector in monthly_summary.by_sector" :key="sector.id" class="rounded-xl border border-cyan-100/10 bg-slate-900/55 px-3 py-2">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="font-medium text-slate-100">{{ sector.name }}</span>
                                    <span class="text-slate-200">{{ money(sector.total) }}</span>
                                </div>
                                <p class="mt-1 text-xs text-slate-400">{{ sector.sales_count }} ventas</p>
                            </li>
                        </ul>
                        <p v-else class="mt-3 text-sm text-slate-400">Sin ventas por sector en este periodo.</p>
                    </article>

                    <article class="app-subsection">
                        <h4 class="app-section-title">Totales por cuenta de cobro</h4>
                        <ul v-if="monthly_summary.by_payment_destination?.length" class="mt-3 space-y-2 text-sm">
                            <li v-for="destination in monthly_summary.by_payment_destination" :key="destination.id" class="rounded-xl border border-cyan-100/10 bg-slate-900/55 px-3 py-2">
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
            </AppPanel>

            <AppPanel title="Filtros operativos" subtitle="Busca rapido ventas por cliente, fecha y destino de cobro.">
                <template #actions>
                    <button type="button" class="rounded-lg border border-rose-300/35 px-3 py-2 text-sm font-semibold text-rose-100 hover:bg-rose-400/20" @click="clearFilters">
                        Limpiar
                    </button>
                </template>

                <div
                    class="grid gap-3 md:grid-cols-2"
                    :class="advancedSaleSettingsEnabled ? 'xl:grid-cols-[minmax(0,1.35fr)_minmax(0,0.9fr)_minmax(0,1fr)_minmax(0,1fr)_auto]' : 'xl:grid-cols-[minmax(0,1.5fr)_minmax(0,0.9fr)_auto]'"
                >
                    <input
                        v-model="state.search"
                        type="text"
                        class="min-w-0 rounded-xl text-sm"
                        placeholder="Buscar por numero, notas o cliente"
                        @keyup.enter="filter"
                    >
                    <input
                        v-model="state.month"
                        type="month"
                        class="min-w-0 rounded-xl text-sm"
                    >
                    <select
                        v-if="advancedSaleSettingsEnabled"
                        v-model="state.sale_sector_id"
                        class="min-w-0 rounded-xl bg-slate-950/35 text-sm text-slate-100"
                    >
                        <option value="">Todos los sectores</option>
                        <option v-for="sector in saleSectors" :key="sector.id" :value="sector.id">{{ sector.name }}</option>
                    </select>
                    <select
                        v-if="advancedSaleSettingsEnabled"
                        v-model="state.payment_destination_id"
                        class="min-w-0 rounded-xl bg-slate-950/35 text-sm text-slate-100"
                    >
                        <option value="">Todas las cuentas</option>
                        <option v-for="destination in paymentDestinations" :key="destination.id" :value="destination.id">{{ destination.name }}</option>
                    </select>
                    <div class="grid grid-cols-2 gap-2 md:col-span-2 xl:col-span-1 xl:flex xl:justify-end">
                        <button type="button" class="inline-flex items-center justify-center rounded-lg border border-cyan-100/25 px-3 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/70" @click="filter">Buscar</button>
                        <button type="button" class="inline-flex items-center justify-center rounded-lg border border-cyan-100/25 px-3 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/70" @click="clearFilters">Reset</button>
                    </div>
                </div>

                <div v-if="activeFilters.length" class="mt-4 app-chip-row">
                    <button
                        v-for="filterItem in activeFilters"
                        :key="filterItem.key"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-full border border-cyan-300/30 bg-cyan-400/15 px-3 py-1 text-xs font-semibold text-cyan-100 transition hover:bg-cyan-400/25"
                        @click="clearSingleFilter(filterItem.key)"
                    >
                        <span>{{ filterItem.label }}</span>
                        <span aria-hidden="true">x</span>
                    </button>
                </div>
            </AppPanel>

            <AppPanel title="Listado de ventas" subtitle="Prioriza estado de cobro, cliente y total para resolver rapido cada operacion.">
                <div class="grid gap-3 md:hidden">
                    <article v-for="sale in sales.data" :key="sale.id" class="rounded-xl border border-cyan-100/20 bg-slate-950/35 p-4 text-sm text-slate-300">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-semibold text-slate-100">{{ sale.sale_number || `VENTA-${sale.id}` }}</p>
                                <p class="mt-1 text-xs text-slate-400">{{ sale.sold_at || '-' }} · {{ sale.user || 'Sin usuario' }}</p>
                                <p class="mt-1 text-xs text-slate-400">{{ sale.customer || 'Consumidor final' }}</p>
                            </div>
                            <Link :href="route('sales.show', sale.id)" class="shrink-0 rounded-lg border border-cyan-100/25 px-3 py-1 text-xs font-semibold text-slate-300 hover:bg-slate-800/70">Ver</Link>
                        </div>

                        <div class="mt-3 app-chip-row">
                            <StatusBadge :tone="paymentStatusTone(sale.payment_status)" :label="paymentStatusLabel(sale.payment_status)" size="sm" />
                            <StatusBadge tone="neutral" size="sm" :label="sale.payment_method ? paymentMethodLabel(sale.payment_method) : 'Sin cobro inicial'" />
                            <StatusBadge v-if="receipt_feature_available && sale.has_receipt" tone="highlight" size="sm" label="Con comprobante" />
                        </div>

                        <div v-if="advancedSaleSettingsEnabled" class="mt-3 app-chip-row">
                            <StatusBadge tone="info" size="sm" :label="sale.sale_sector || 'Sin sector'" />
                            <StatusBadge tone="neutral" size="sm" :label="sale.payment_destination || 'Sin cuenta'" />
                        </div>

                        <div class="mt-3 grid gap-2 text-xs text-slate-400">
                            <p>Items: <span class="text-slate-200">{{ sale.items_count }}</span></p>
                            <p>Total: <span class="text-slate-100">{{ money(sale.total) }}</span></p>
                            <p>Pendiente: <span :class="Number(sale.pending_amount) > 0 ? 'text-amber-100' : 'text-emerald-100'" class="font-semibold">{{ money(sale.pending_amount) }}</span></p>
                            <p v-if="receipt_feature_available && sale.has_receipt">
                                Comprobante:
                                <a :href="sale.receipt_download_url" class="font-semibold text-cyan-100 underline underline-offset-2">Descargar</a>
                            </p>
                        </div>
                    </article>
                </div>

                <div class="hidden overflow-x-auto rounded-xl border border-cyan-100/20 app-table-wrap md:block">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-950/35">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Venta</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Cliente</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Cobro</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Items</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Total</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Pendiente</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80"></th>
                            </tr>
                        </thead>
                        <tbody v-if="sales.data.length" class="divide-y divide-slate-100">
                            <tr v-for="sale in sales.data" :key="sale.id">
                                <td class="px-3 py-2 align-top">
                                    <p class="font-semibold text-slate-100">{{ sale.sale_number || `VENTA-${sale.id}` }}</p>
                                    <p class="mt-1 text-xs text-slate-400">{{ sale.sold_at || '-' }} · {{ sale.user || 'Sin usuario' }}</p>
                                    <div v-if="advancedSaleSettingsEnabled" class="mt-2 app-chip-row">
                                        <StatusBadge tone="info" size="sm" :label="sale.sale_sector || 'Sin sector'" />
                                        <StatusBadge tone="neutral" size="sm" :label="sale.payment_destination || 'Sin cuenta'" />
                                    </div>
                                </td>
                                <td class="px-3 py-2 align-top">
                                    <p class="font-medium text-slate-100">{{ sale.customer || 'Consumidor final' }}</p>
                                    <p class="mt-1 text-xs text-slate-400">{{ sale.notes || 'Sin notas' }}</p>
                                </td>
                                <td class="px-3 py-2 align-top">
                                    <div class="app-chip-row">
                                        <StatusBadge :tone="paymentStatusTone(sale.payment_status)" :label="paymentStatusLabel(sale.payment_status)" size="sm" />
                                        <StatusBadge tone="neutral" size="sm" :label="sale.payment_method ? paymentMethodLabel(sale.payment_method) : 'Sin cobro inicial'" />
                                        <StatusBadge v-if="receipt_feature_available && sale.has_receipt" tone="highlight" size="sm" label="Comprobante" />
                                    </div>
                                </td>
                                <td class="px-3 py-2 font-semibold text-slate-100 align-top">{{ sale.items_count }}</td>
                                <td class="px-3 py-2 font-semibold text-slate-100 align-top">{{ money(sale.total) }}</td>
                                <td class="px-3 py-2 align-top">
                                    <span :class="Number(sale.pending_amount) > 0 ? 'text-amber-100' : 'text-emerald-100'" class="font-semibold">
                                        {{ money(sale.pending_amount) }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-right align-top">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <a v-if="receipt_feature_available && sale.has_receipt" :href="sale.receipt_download_url" class="rounded-lg border border-cyan-100/25 px-3 py-1 text-xs font-semibold text-cyan-100 hover:bg-slate-800/70">Comprobante</a>
                                        <Link :href="route('sales.show', sale.id)" class="rounded-lg border border-cyan-100/25 px-3 py-1 text-xs font-semibold text-slate-300 hover:bg-slate-800/70">Ver</Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                        <tbody v-else>
                            <tr>
                                <td colspan="7" class="px-3 py-6 text-center text-slate-400">No hay ventas registradas con los filtros actuales.</td>
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
            </AppPanel>
        </div>
    </AuthenticatedLayout>
</template>
