<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    period: { type: Object, required: true },
    branch_scope: { type: String, required: true },
    current_branch: { type: Object, required: true },
    branches: { type: Array, default: () => [] },
    report: { type: Object, required: true },
});

const month = ref(props.period.month);
const scope = ref(props.branch_scope);
const records = computed(() => props.report.purchase_records || []);
const money = (value) => new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(Number(value || 0));
const documentLabel = (type) => ({ invoice_a: 'Factura A', invoice_b: 'Factura B', invoice_c: 'Factura C', invoice_m: 'Factura M', ticket: 'Ticket' }[type] || type || '—');

const reload = () => router.get(route('fiscal.vat-dashboard'), {
    month: month.value,
    branch_scope: scope.value,
}, { preserveState: true, replace: true });
const exportCsv = () => {
    window.location.assign(route('fiscal.vat-dashboard', { month: month.value, branch_scope: scope.value, export: 'csv' }));
};
</script>

<template>
    <Head title="Resumen IVA" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div><p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-100/70">Fiscal</p><h2 class="mt-1 text-2xl font-bold text-slate-100">Resumen IVA mensual</h2><p class="mt-1 text-sm text-slate-300/80">Débito y crédito fiscal como referencia operativa por comprobantes registrados.</p></div>
                <Link :href="route('electronic-billing.index')" class="rounded-lg border border-cyan-100/25 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/70">Libro IVA Ventas</Link>
            </div>
        </template>

        <div class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-sm backdrop-blur">
            <div class="flex flex-wrap items-end gap-3">
                <label class="grid gap-1 text-sm text-slate-300"><span class="text-xs uppercase tracking-[0.18em] text-slate-400">Periodo</span><input v-model="month" type="month" class="rounded-lg border border-cyan-100/20 bg-slate-950/50 px-3 py-2 text-slate-100"></label>
                <label class="grid gap-1 text-sm text-slate-300"><span class="text-xs uppercase tracking-[0.18em] text-slate-400">Sucursal</span><select v-model="scope" class="rounded-lg border border-cyan-100/20 bg-slate-950/50 px-3 py-2 text-slate-100"><option value="current">{{ current_branch.name }}</option><option v-if="branches.length > 1" value="all">Todas las sucursales</option></select></label>
                <button type="button" class="rounded-lg border border-cyan-100/25 px-4 py-2 text-sm font-semibold text-slate-200 hover:bg-slate-800/70" @click="reload">Aplicar</button>
                <button type="button" class="rounded-lg border border-cyan-100/25 px-4 py-2 text-sm font-semibold text-cyan-100 hover:bg-cyan-300/10" @click="exportCsv">Exportar IVA Compras CSV</button>
            </div>
        </div>

        <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <article class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-4"><p class="text-xs uppercase tracking-[0.15em] text-slate-400">Ventas netas</p><p class="mt-2 text-xl font-bold text-slate-100">{{ money(report.sales.net_amount) }}</p></article>
            <article class="rounded-2xl border border-emerald-200/25 bg-emerald-400/10 p-4"><p class="text-xs uppercase tracking-[0.15em] text-emerald-100/75">IVA débito</p><p class="mt-2 text-xl font-bold text-emerald-100">{{ money(report.sales.vat_amount) }}</p></article>
            <article class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-4"><p class="text-xs uppercase tracking-[0.15em] text-slate-400">Compras netas</p><p class="mt-2 text-xl font-bold text-slate-100">{{ money(report.purchases.net_amount) }}</p></article>
            <article class="rounded-2xl border border-amber-200/25 bg-amber-400/10 p-4"><p class="text-xs uppercase tracking-[0.15em] text-amber-100/75">IVA crédito</p><p class="mt-2 text-xl font-bold text-amber-100">{{ money(report.purchases.vat_amount) }}</p></article>
            <article class="rounded-2xl border border-cyan-200/35 bg-cyan-300/10 p-4"><p class="text-xs uppercase tracking-[0.15em] text-cyan-100/75">Débito − crédito</p><p class="mt-2 text-xl font-bold text-cyan-50">{{ money(report.estimated_difference) }}</p></article>
        </div>
        <p class="mt-3 rounded-xl border border-amber-200/25 bg-amber-400/10 px-4 py-3 text-sm text-amber-100">Es una referencia fiscal. Percepciones, saldos técnicos, retenciones y determinaciones de ARCA pueden modificar el importe definitivo.</p>

        <section class="mt-6 rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-sm backdrop-blur">
            <div class="flex flex-wrap items-center justify-between gap-3"><div><h3 class="text-base font-semibold text-slate-100">Libro IVA Compras</h3><p class="mt-1 text-sm text-slate-300/80">{{ records.length }} comprobantes fiscales registrados durante el período.</p></div><p class="text-sm text-slate-300">Total: <strong class="text-slate-100">{{ money(report.purchases.total_amount) }}</strong></p></div>
            <div class="mt-4 hidden overflow-x-auto rounded-xl border border-cyan-100/20 app-table-wrap md:block"><table class="min-w-full divide-y divide-slate-200 text-sm"><thead class="bg-slate-950/35"><tr><th class="px-3 py-2 text-left text-slate-300">Fecha</th><th class="px-3 py-2 text-left text-slate-300">Proveedor</th><th class="px-3 py-2 text-left text-slate-300">Comprobante</th><th class="px-3 py-2 text-right text-slate-300">Neto</th><th class="px-3 py-2 text-right text-slate-300">IVA</th><th class="px-3 py-2 text-right text-slate-300">Total</th></tr></thead><tbody v-if="records.length" class="divide-y divide-slate-100"><tr v-for="record in records" :key="record.id"><td class="px-3 py-2">{{ record.voucher_date }}</td><td class="px-3 py-2"><p class="font-semibold text-slate-100">{{ record.supplier || 'Sin proveedor' }}</p><p class="text-xs text-slate-400">{{ record.supplier_cuit }}</p></td><td class="px-3 py-2">{{ documentLabel(record.document_type) }} {{ record.point_of_sale }}-{{ record.number }}</td><td class="px-3 py-2 text-right">{{ money(record.net_amount) }}</td><td class="px-3 py-2 text-right">{{ money(record.vat_amount) }}</td><td class="px-3 py-2 text-right font-semibold">{{ money(record.total_amount) }}</td></tr></tbody><tbody v-else><tr><td colspan="6" class="px-3 py-7 text-center text-slate-400">No hay compras con comprobante fiscal en este período.</td></tr></tbody></table></div>
            <div class="mt-4 grid gap-3 md:hidden"><article v-for="record in records" :key="record.id" class="rounded-xl border border-cyan-100/20 bg-slate-950/35 p-4 text-sm"><div class="flex justify-between gap-3"><div><p class="text-xs text-slate-400">{{ record.voucher_date }}</p><p class="font-semibold text-slate-100">{{ record.supplier || 'Sin proveedor' }}</p><p class="text-xs text-slate-300">{{ documentLabel(record.document_type) }} {{ record.point_of_sale }}-{{ record.number }}</p></div><p class="font-semibold text-slate-100">{{ money(record.total_amount) }}</p></div><p class="mt-3 text-slate-300">Neto {{ money(record.net_amount) }} · IVA {{ money(record.vat_amount) }}</p></article></div>
        </section>
    </AuthenticatedLayout>
</template>
