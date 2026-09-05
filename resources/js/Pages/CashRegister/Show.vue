<script setup>
import AppPanel from '@/Components/AppPanel.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ branch: { type: Object, required: true }, session: { type: Object, required: true } });
const money = (value) => new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(Number(value || 0));
const formatDate = (value) => (value ? new Intl.DateTimeFormat('es-AR', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value)) : '—');
const movementLabel = (type) => ({
    manual_income: 'Ingreso manual', manual_expense: 'Egreso manual', cash_sale: 'Venta en efectivo',
    refund: 'Devolución', adjustment_in: 'Ajuste de ingreso', adjustment_out: 'Ajuste de egreso',
}[type] || type);
</script>

<template>
    <Head title="Detalle de caja" />
    <AuthenticatedLayout>
        <template #header><div><p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-100/70">Operación · Caja</p><h2 class="mt-1 text-2xl font-bold text-slate-100">Detalle de caja · {{ branch.name }}</h2></div></template>
        <AppPanel title="Resumen de sesión" :subtitle="`Abierta el ${formatDate(session.opened_at)} por ${session.opened_by || 'usuario'}.`">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"><div class="app-subsection"><p class="text-xs uppercase text-cyan-100/70">Inicial</p><p class="mt-1 text-lg font-bold">{{ money(session.opening_amount) }}</p></div><div class="app-subsection"><p class="text-xs uppercase text-cyan-100/70">Esperado</p><p class="mt-1 text-lg font-bold">{{ money(session.expected_amount) }}</p></div><div class="app-subsection"><p class="text-xs uppercase text-cyan-100/70">Contado</p><p class="mt-1 text-lg font-bold">{{ session.counted_amount === null ? '—' : money(session.counted_amount) }}</p></div><div class="app-subsection"><p class="text-xs uppercase text-cyan-100/70">Diferencia</p><p class="mt-1 text-lg font-bold">{{ session.difference_amount === null ? '—' : money(session.difference_amount) }}</p></div></div>
        </AppPanel>
        <AppPanel class="mt-6" title="Movimientos registrados">
            <div class="grid gap-3 md:hidden"><article v-for="movement in session.movements" :key="movement.id" class="rounded-xl border border-cyan-100/15 bg-slate-950/30 p-4"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="font-semibold text-slate-100">{{ movementLabel(movement.type) }}</p><p class="mt-1 text-xs text-slate-400">{{ formatDate(movement.occurred_at) }} · {{ movement.created_by || 'Sistema' }}</p></div><p class="shrink-0 text-lg font-bold" :class="movement.amount >= 0 ? 'text-emerald-200' : 'text-rose-200'">{{ movement.amount >= 0 ? '+' : '' }}{{ money(movement.amount) }}</p></div><p class="mt-3 break-words text-sm text-slate-300">{{ movement.description || 'Sin descripción.' }}</p></article><p v-if="session.movements.length === 0" class="rounded-xl border border-dashed border-cyan-100/20 p-4 text-center text-sm text-slate-400">No hay movimientos registrados.</p></div>
            <div class="hidden overflow-x-auto md:block"><table class="min-w-full text-left text-sm"><thead class="border-b border-cyan-100/15 text-xs uppercase tracking-wide text-cyan-100/70"><tr><th class="px-3 py-3">Fecha</th><th class="px-3 py-3">Tipo</th><th class="px-3 py-3">Detalle</th><th class="px-3 py-3">Usuario</th><th class="px-3 py-3 text-right">Importe</th></tr></thead><tbody><tr v-for="movement in session.movements" :key="movement.id" class="border-b border-cyan-100/10"><td class="px-3 py-3">{{ formatDate(movement.occurred_at) }}</td><td class="px-3 py-3">{{ movementLabel(movement.type) }}</td><td class="px-3 py-3">{{ movement.description || '—' }}</td><td class="px-3 py-3">{{ movement.created_by || 'Sistema' }}</td><td class="px-3 py-3 text-right" :class="movement.amount >= 0 ? 'text-emerald-200' : 'text-rose-200'">{{ money(movement.amount) }}</td></tr></tbody></table></div>
            <template #footer><Link :href="route('cash-register.index')" class="inline-flex min-h-11 items-center text-sm font-semibold text-cyan-200 hover:text-white">Volver a Caja</Link></template>
        </AppPanel>
    </AuthenticatedLayout>
</template>
