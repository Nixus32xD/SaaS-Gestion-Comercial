<script setup>
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    business_name: { type: String, required: true },
    filters: { type: Object, required: true },
    sales: { type: Array, default: () => [] },
    summary: { type: Object, required: true },
});

const money = (value) => new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    minimumFractionDigits: 2,
}).format(Number(value) || 0);

const paymentMethodLabel = (value) => (value === 'transfer' ? 'Transferencia' : (value ? 'Efectivo' : 'Sin cobro inicial'));
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

const activeFilters = computed(() => {
    const filters = [];

    if (props.filters.search) filters.push(`Busqueda: ${props.filters.search}`);
    if (props.filters.month) filters.push(`Mes: ${props.filters.month}`);
    if (props.filters.sale_sector_id) filters.push('Sector filtrado');
    if (props.filters.payment_destination_id) filters.push('Cuenta filtrada');

    return filters;
});

const printPage = () => window.print();
const closePage = () => window.close();
</script>

<template>
    <Head title="Imprimir ventas" />

    <div class="min-h-screen bg-slate-100 px-4 py-6 text-slate-900 print:bg-white print:px-0 print:py-0">
        <div class="mx-auto max-w-6xl space-y-6">
            <header class="rounded-3xl bg-white p-6 shadow-sm print:hidden">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h1 class="text-3xl font-bold">Listado de ventas</h1>
                        <p class="mt-2 text-sm text-slate-500">{{ business_name }} · generado {{ summary.printed_at }}</p>
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <button type="button" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700" @click="printPage">Imprimir</button>
                        <button type="button" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100" @click="closePage">Cerrar</button>
                    </div>
                </div>
            </header>

            <section class="rounded-3xl bg-white p-6 shadow-sm print:rounded-none print:shadow-none">
                <div class="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Resumen</p>
                        <h2 class="mt-2 text-2xl font-bold">Ventas filtradas</h2>
                        <p class="mt-2 text-sm text-slate-500">{{ business_name }} · {{ filters.month ? `mes filtrado ${filters.month}` : 'sin filtro por mes' }}</p>
                    </div>
                    <div class="grid gap-3 text-sm sm:grid-cols-3">
                        <div class="rounded-2xl border border-slate-200 px-4 py-3">
                            <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Ventas</p>
                            <p class="mt-2 text-2xl font-bold">{{ summary.sales_count }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 px-4 py-3">
                            <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Total</p>
                            <p class="mt-2 text-2xl font-bold">{{ money(summary.total_amount) }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 px-4 py-3">
                            <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Pendiente</p>
                            <p class="mt-2 text-2xl font-bold">{{ money(summary.pending_amount) }}</p>
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-2" v-if="activeFilters.length">
                    <span v-for="filterItem in activeFilters" :key="filterItem" class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                        {{ filterItem }}
                    </span>
                </div>

                <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-slate-500">Venta</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-500">Cliente</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-500">Cobro</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-500">Items</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-500">Total</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-500">Pendiente</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-500">Comprobante</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            <tr v-for="sale in sales" :key="sale.id">
                                <td class="px-4 py-3 align-top">
                                    <p class="font-semibold">{{ sale.sale_number || `VENTA-${sale.id}` }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ sale.sold_at || '-' }} · {{ sale.user || 'Sin usuario' }}</p>
                                    <p v-if="summary.advanced_sale_settings_enabled" class="mt-1 text-xs text-slate-500">{{ sale.sale_sector || 'Sin sector' }} · {{ sale.payment_destination || 'Sin cuenta' }}</p>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <p class="font-medium">{{ sale.customer || 'Consumidor final' }}</p>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <div class="flex flex-col gap-2">
                                        <StatusBadge surface="light" :tone="paymentStatusTone(sale.payment_status)" :label="paymentStatusLabel(sale.payment_status)" />
                                        <span class="text-xs text-slate-500">{{ paymentMethodLabel(sale.payment_method) }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-top font-semibold">{{ sale.items_count }}</td>
                                <td class="px-4 py-3 align-top font-semibold">{{ money(sale.total) }}</td>
                                <td class="px-4 py-3 align-top font-semibold" :class="Number(sale.pending_amount) > 0 ? 'text-amber-700' : 'text-emerald-700'">{{ money(sale.pending_amount) }}</td>
                                <td class="px-4 py-3 align-top">
                                    <StatusBadge surface="light" :tone="sale.has_receipt ? 'highlight' : 'neutral'" :label="sale.has_receipt ? 'Adjunto' : 'Sin adjunto'" />
                                </td>
                            </tr>
                            <tr v-if="!sales.length">
                                <td colspan="7" class="px-4 py-6 text-center text-slate-500">No hay ventas para imprimir con los filtros actuales.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</template>
