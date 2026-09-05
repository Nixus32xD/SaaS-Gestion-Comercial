<script setup>
import AppPanel from '@/Components/AppPanel.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    branch: { type: Object, required: true },
    current: { type: Object, default: null },
    history: { type: Object, required: true },
    can_adjust: { type: Boolean, default: false },
});

const openingForm = useForm({ opening_amount: '', opening_notes: '' });
const movementForm = useForm({ type: 'manual_income', amount: '', description: '' });
const closingForm = useForm({ counted_amount: '', closing_notes: '' });

const money = (value) => new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(Number(value || 0));
const formatDate = (value) => (value ? new Intl.DateTimeFormat('es-AR', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value)) : '—');
const movementLabel = (type) => ({
    manual_income: 'Ingreso manual', manual_expense: 'Egreso manual', cash_sale: 'Venta en efectivo',
    refund: 'Devolución', adjustment_in: 'Ajuste de ingreso', adjustment_out: 'Ajuste de egreso',
}[type] || type);
const expectedClose = computed(() => Number(props.current?.expected_amount || 0));
const closingDifference = computed(() => Number(closingForm.counted_amount || 0) - expectedClose.value);
const movementOptions = computed(() => ([
    { value: 'manual_income', label: 'Ingreso manual' },
    { value: 'manual_expense', label: 'Egreso manual' },
    ...(props.can_adjust ? [{ value: 'adjustment_in', label: 'Ajuste autorizado de ingreso' }, { value: 'adjustment_out', label: 'Ajuste autorizado de egreso' }] : []),
]));

const open = () => openingForm.post(route('cash-register.open'), { preserveScroll: true });
const addMovement = () => movementForm.post(route('cash-register.movements.store'), {
    preserveScroll: true,
    onSuccess: () => movementForm.reset('amount', 'description'),
});
const close = () => closingForm.post(route('cash-register.close'), { preserveScroll: true });
</script>

<template>
    <Head title="Caja" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-100/70">Operación · Caja</p>
                <h2 class="mt-1 text-2xl font-bold text-slate-100">Caja de {{ branch.name }}</h2>
                <p class="mt-1 text-sm text-slate-300/80">El efectivo físico se registra por separado de tarjetas, Mercado Pago y cuenta corriente.</p>
            </div>
        </template>

        <div v-if="!current" class="mx-auto max-w-2xl">
            <AppPanel title="Caja cerrada" subtitle="Abrí la caja antes de registrar efectivo. Solo puede haber una caja física abierta por sucursal.">
                <form class="grid gap-4" @submit.prevent="open">
                    <div class="app-field">
                        <label class="app-field-label">Monto inicial</label>
                        <input v-model.number="openingForm.opening_amount" type="number" min="0" step="0.01" autofocus class="w-full rounded-xl text-sm" placeholder="0,00" />
                        <p v-if="openingForm.errors.opening_amount" class="mt-1 text-xs text-rose-300">{{ openingForm.errors.opening_amount }}</p>
                    </div>
                    <div class="app-field">
                        <label class="app-field-label">Observación (opcional)</label>
                        <textarea v-model="openingForm.opening_notes" rows="3" maxlength="2000" class="w-full rounded-xl text-sm" placeholder="Ej. Fondo inicial de cambio" />
                    </div>
                    <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-3 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50" :disabled="openingForm.processing">Abrir caja</button>
                </form>
            </AppPanel>
        </div>

        <template v-else>
            <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
                <div class="grid gap-6">
                    <AppPanel title="Caja abierta" :subtitle="`Abierta por ${current.opened_by || 'usuario'} el ${formatDate(current.opened_at)}.`" tone="accent">
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <div class="app-subsection"><p class="text-xs uppercase tracking-[0.15em] text-cyan-100/70">Inicial</p><p class="mt-1 text-lg font-bold text-slate-100">{{ money(current.opening_amount) }}</p></div>
                            <div class="app-subsection"><p class="text-xs uppercase tracking-[0.15em] text-cyan-100/70">Ventas efectivo</p><p class="mt-1 text-lg font-bold text-emerald-200">{{ money(current.totals.cash_sale) }}</p></div>
                            <div class="app-subsection"><p class="text-xs uppercase tracking-[0.15em] text-cyan-100/70">Ingresos / egresos</p><p class="mt-1 text-lg font-bold text-slate-100">{{ money(current.totals.manual_income + current.totals.adjustment_in) }} / {{ money(current.totals.manual_expense + current.totals.adjustment_out + current.totals.refund) }}</p></div>
                            <div class="app-subsection border-cyan-300/35 bg-cyan-400/10"><p class="text-xs uppercase tracking-[0.15em] text-cyan-100/80">Saldo esperado</p><p class="mt-1 text-xl font-bold text-cyan-50">{{ money(current.expected_amount) }}</p></div>
                        </div>
                    </AppPanel>

                    <AppPanel title="Movimientos" subtitle="Las ventas en efectivo se agregan automáticamente. Los registros son inmutables; las correcciones se hacen con un contramovimiento.">
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-left text-sm">
                                <thead class="border-b border-cyan-100/15 text-xs uppercase tracking-wide text-cyan-100/70"><tr><th class="px-3 py-3">Fecha</th><th class="px-3 py-3">Tipo</th><th class="px-3 py-3">Detalle</th><th class="px-3 py-3">Usuario</th><th class="px-3 py-3 text-right">Importe</th></tr></thead>
                                <tbody>
                                    <tr v-for="movement in current.movements" :key="movement.id" class="border-b border-cyan-100/10 text-slate-200"><td class="whitespace-nowrap px-3 py-3">{{ formatDate(movement.occurred_at) }}</td><td class="px-3 py-3 font-medium">{{ movementLabel(movement.type) }}</td><td class="px-3 py-3">{{ movement.description || '—' }}</td><td class="px-3 py-3">{{ movement.created_by || 'Sistema' }}</td><td class="px-3 py-3 text-right font-semibold" :class="movement.amount >= 0 ? 'text-emerald-200' : 'text-rose-200'">{{ money(movement.amount) }}</td></tr>
                                    <tr v-if="current.movements.length === 0"><td colspan="5" class="px-3 py-8 text-center text-slate-400">Todavía no hay movimientos.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </AppPanel>
                </div>

                <div class="grid content-start gap-6">
                    <AppPanel title="Ingreso o egreso" subtitle="No uses esta acción para ventas; se incorporan desde el cobro." >
                        <form class="grid gap-4" @submit.prevent="addMovement">
                            <div class="app-field"><label class="app-field-label">Tipo</label><select v-model="movementForm.type" class="w-full rounded-xl text-sm"><option v-for="option in movementOptions" :key="option.value" :value="option.value">{{ option.label }}</option></select></div>
                            <div class="app-field"><label class="app-field-label">Importe</label><input v-model.number="movementForm.amount" type="number" min="0.01" step="0.01" class="w-full rounded-xl text-sm" /><p v-if="movementForm.errors.amount" class="mt-1 text-xs text-rose-300">{{ movementForm.errors.amount }}</p></div>
                            <div class="app-field"><label class="app-field-label">Descripción</label><textarea v-model="movementForm.description" rows="3" maxlength="2000" class="w-full rounded-xl text-sm" placeholder="Motivo del movimiento" /><p v-if="movementForm.errors.description" class="mt-1 text-xs text-rose-300">{{ movementForm.errors.description }}</p></div>
                            <button type="submit" class="rounded-xl border border-cyan-200/30 px-4 py-2.5 text-sm font-semibold text-cyan-50 hover:bg-cyan-300/10 disabled:opacity-50" :disabled="movementForm.processing">Registrar movimiento</button>
                        </form>
                    </AppPanel>
                    <AppPanel title="Cerrar caja" subtitle="El esperado se guarda como foto del momento del cierre.">
                        <form class="grid gap-4" @submit.prevent="close">
                            <div class="rounded-xl border border-cyan-300/30 bg-cyan-400/10 p-3"><p class="text-xs uppercase tracking-wide text-cyan-100/70">Esperado</p><p class="mt-1 text-xl font-bold text-cyan-50">{{ money(expectedClose) }}</p></div>
                            <div class="app-field"><label class="app-field-label">Dinero contado</label><input v-model.number="closingForm.counted_amount" type="number" min="0" step="0.01" class="w-full rounded-xl text-sm" /><p v-if="closingForm.errors.counted_amount" class="mt-1 text-xs text-rose-300">{{ closingForm.errors.counted_amount }}</p></div>
                            <p v-if="closingForm.counted_amount !== ''" class="text-sm text-slate-300">Diferencia proyectada: <span class="font-semibold" :class="closingDifference === 0 ? 'text-emerald-200' : 'text-amber-200'">{{ money(closingDifference) }}</span></p>
                            <div class="app-field"><label class="app-field-label">Observación (opcional)</label><textarea v-model="closingForm.closing_notes" rows="2" maxlength="2000" class="w-full rounded-xl text-sm" /></div>
                            <button type="submit" class="rounded-xl bg-rose-600 px-4 py-3 text-sm font-semibold text-white hover:bg-rose-500 disabled:opacity-50" :disabled="closingForm.processing">Confirmar cierre</button>
                        </form>
                    </AppPanel>
                </div>
            </div>
        </template>

        <AppPanel class="mt-6" title="Historial de cajas" subtitle="Solo se muestran sesiones de la sucursal activa.">
            <div class="overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="border-b border-cyan-100/15 text-xs uppercase tracking-wide text-cyan-100/70"><tr><th class="px-3 py-3">Apertura</th><th class="px-3 py-3">Cierre</th><th class="px-3 py-3">Usuario</th><th class="px-3 py-3">Estado</th><th class="px-3 py-3 text-right">Esperado</th><th class="px-3 py-3 text-right">Contado</th><th class="px-3 py-3 text-right">Diferencia</th><th class="px-3 py-3"></th></tr></thead><tbody><tr v-for="session in history.data" :key="session.id" class="border-b border-cyan-100/10 text-slate-200"><td class="px-3 py-3 whitespace-nowrap">{{ formatDate(session.opened_at) }}</td><td class="px-3 py-3 whitespace-nowrap">{{ formatDate(session.closed_at) }}</td><td class="px-3 py-3">{{ session.opened_by }}</td><td class="px-3 py-3"><span class="rounded-full px-2 py-1 text-xs" :class="session.status === 'open' ? 'bg-emerald-400/15 text-emerald-100' : 'bg-slate-700 text-slate-200'">{{ session.status === 'open' ? 'Abierta' : 'Cerrada' }}</span></td><td class="px-3 py-3 text-right">{{ session.expected_amount_at_close === null ? '—' : money(session.expected_amount_at_close) }}</td><td class="px-3 py-3 text-right">{{ session.counted_amount === null ? '—' : money(session.counted_amount) }}</td><td class="px-3 py-3 text-right">{{ session.difference_amount === null ? '—' : money(session.difference_amount) }}</td><td class="px-3 py-3 text-right"><Link :href="route('cash-register.sessions.show', session.id)" class="text-cyan-200 hover:text-white">Ver movimientos</Link></td></tr><tr v-if="history.data.length === 0"><td colspan="8" class="px-3 py-8 text-center text-slate-400">Todavía no hay sesiones.</td></tr></tbody></table></div>
        </AppPanel>
    </AuthenticatedLayout>
</template>
