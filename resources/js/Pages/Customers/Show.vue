<script setup>
import AppPanel from '@/Components/AppPanel.vue';
import MetricCard from '@/Components/MetricCard.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    customer: { type: Object, required: true },
    movements: { type: Object, required: true },
    recent_sales: { type: Array, default: () => [] },
    recent_reminders: { type: Array, default: () => [] },
});

const money = (value) => new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    minimumFractionDigits: 2,
}).format(Number(value) || 0);

const nowLocalDateTime = () => {
    const date = new Date();
    const offsetMinutes = date.getTimezoneOffset();
    const localDate = new Date(date.getTime() - (offsetMinutes * 60000));

    return localDate.toISOString().slice(0, 16);
};

const paymentForm = useForm({
    amount: '',
    paid_at: nowLocalDateTime(),
    payment_method: 'cash',
    description: '',
});

const emailForm = useForm({});

const remindersEnabled = computed(() => (
    Boolean(props.customer.allow_reminders && props.customer.preferred_reminder_channel !== 'none')
));
const canWhatsapp = computed(() => Boolean(remindersEnabled.value && props.customer.phone));
const canEmail = computed(() => Boolean(remindersEnabled.value && props.customer.email));

const movementTypeLabel = (type) => {
    if (type === 'payment') return 'Pago';
    if (type === 'debt') return 'Deuda';
    return 'Ajuste';
};

const movementTypeClass = (type) => {
    if (type === 'payment') return 'bg-emerald-500/20 text-emerald-100';
    if (type === 'debt') return 'bg-amber-400/20 text-amber-100';
    return 'bg-slate-700/80 text-slate-100';
};

const paymentStatusLabel = (status) => {
    if (status === 'partial') return 'Parcial';
    if (status === 'pending') return 'Fiada';
    return 'Pagada';
};

const submitPayment = () => {
    paymentForm.post(route('customers.payments.store', props.customer.id), {
        preserveScroll: true,
        onSuccess: () => paymentForm.reset('amount', 'description'),
    });
};

const sendEmailReminder = () => {
    emailForm.post(route('customers.reminders.email', props.customer.id), {
        preserveScroll: true,
    });
};

const movementSaleLabel = (movement) => {
    if (movement.sale?.sale_number) {
        return movement.sale.sale_number;
    }

    const allocations = movement.meta?.allocations || [];

    if (!allocations.length) {
        return '-';
    }

    if (allocations.length === 1) {
        return allocations[0].sale_number;
    }

    return `${allocations.length} ventas`;
};

const accountTone = computed(() => {
    if (Number(props.customer.current_balance) <= 0) return 'success';
    if (Number(props.customer.current_balance) >= 100000) return 'danger';

    return 'warning';
});

const accountLabel = computed(() => {
    if (Number(props.customer.current_balance) <= 0) return 'Al dia';
    if (Number(props.customer.current_balance) >= 100000) return 'Deuda alta';

    return 'Saldo pendiente';
});
</script>

<template>
    <Head :title="customer.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-slate-100">{{ customer.name }}</h2>
                    <p class="mt-1 text-sm text-slate-300/80">Ficha del cliente y cuenta corriente.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link :href="route('customers.edit', customer.id)" class="rounded-lg border border-cyan-100/25 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/70">
                        Editar
                    </Link>
                    <Link :href="route('customers.index')" class="rounded-lg border border-cyan-100/25 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/70">
                        Volver
                    </Link>
                </div>
            </div>
        </template>

        <div class="grid gap-6">
            <section class="app-kpi-grid">
                <MetricCard label="Saldo actual" :value="money(customer.current_balance)" :hint="accountLabel" :tone="accountTone" />
                <MetricCard label="Deuda historica" :value="money(customer.debt_total)" hint="Importe total financiado en el tiempo." />
                <MetricCard label="Total pagado" :value="money(customer.paid_total)" hint="Cobros ya imputados al cliente." />
                <MetricCard label="Comprobantes abiertos" :value="customer.open_sales_count" :hint="customer.open_sales_count > 0 ? 'Hay ventas todavia pendientes.' : 'No hay comprobantes abiertos.'" :tone="customer.open_sales_count > 0 ? 'warning' : 'success'" />
            </section>

            <section class="grid gap-4 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]">
                <AppPanel title="Ficha del cliente" subtitle="La informacion clave queda resumida para decidir rapido si cobrar, recordar o solo registrar actividad.">
                    <template #actions>
                        <StatusBadge :tone="accountTone" :label="accountLabel" />
                    </template>

                    <div class="grid gap-3 sm:grid-cols-2 text-sm text-slate-300">
                        <div class="app-subsection">
                            <p class="text-xs uppercase tracking-[0.18em] text-cyan-100/70">Contacto</p>
                            <p class="mt-2"><strong class="text-slate-100">Telefono:</strong> {{ customer.phone || '-' }}</p>
                            <p class="mt-1"><strong class="text-slate-100">Email:</strong> {{ customer.email || '-' }}</p>
                            <p class="mt-1"><strong class="text-slate-100">Direccion:</strong> {{ customer.address || '-' }}</p>
                        </div>
                        <div class="app-subsection">
                            <p class="text-xs uppercase tracking-[0.18em] text-cyan-100/70">Recordatorios</p>
                            <p class="mt-2"><strong class="text-slate-100">Canal:</strong> {{ customer.preferred_reminder_channel }}</p>
                            <p class="mt-1"><strong class="text-slate-100">Habilitados:</strong> {{ customer.allow_reminders ? 'Si' : 'No' }}</p>
                            <p class="mt-1"><strong class="text-slate-100">Ultimo envio:</strong> {{ customer.last_reminder_at || '-' }}</p>
                        </div>
                        <div class="app-subsection sm:col-span-2">
                            <p class="text-xs uppercase tracking-[0.18em] text-cyan-100/70">Notas</p>
                            <p class="mt-2"><strong class="text-slate-100">Contacto:</strong> {{ customer.reminder_notes || '-' }}</p>
                            <p class="mt-1"><strong class="text-slate-100">Generales:</strong> {{ customer.notes || '-' }}</p>
                        </div>
                    </div>
                </AppPanel>

                <AppPanel id="register-payment" title="Acciones rapidas" subtitle="Registra cobros y envia recordatorios sin salir de la ficha.">
                    <div class="mt-0 grid gap-3">
                        <form class="grid gap-3 rounded-xl border border-cyan-100/15 bg-slate-950/35 p-4" @submit.prevent="submitPayment">
                            <p class="text-sm font-semibold text-slate-100">Registrar pago</p>
                            <input v-model="paymentForm.amount" type="number" min="0" step="0.01" class="rounded-xl bg-slate-950/35 text-sm text-slate-100" placeholder="Monto" />
                            <p v-if="paymentForm.errors.amount" class="text-xs text-rose-300">{{ paymentForm.errors.amount }}</p>
                            <input v-model="paymentForm.paid_at" type="datetime-local" class="rounded-xl bg-slate-950/35 text-sm text-slate-100" />
                            <p v-if="paymentForm.errors.paid_at" class="text-xs text-rose-300">{{ paymentForm.errors.paid_at }}</p>
                            <select v-model="paymentForm.payment_method" class="rounded-xl bg-slate-950/35 text-sm text-slate-100">
                                <option value="cash">Efectivo</option>
                                <option value="transfer">Transferencia</option>
                            </select>
                            <textarea v-model="paymentForm.description" rows="2" class="rounded-xl bg-slate-950/35 text-sm text-slate-100" placeholder="Descripcion opcional" />
                            <p class="text-xs text-slate-400">El pago se aplica automaticamente a las ventas pendientes mas antiguas.</p>
                            <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500" :disabled="paymentForm.processing">
                                Guardar pago
                            </button>
                        </form>

                        <a
                            :href="route('customers.reminders.whatsapp', customer.id)"
                            target="_blank"
                            rel="noreferrer"
                            class="inline-flex items-center justify-center rounded-xl border border-cyan-100/25 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/70"
                            :class="{ 'pointer-events-none opacity-50': !canWhatsapp }"
                        >
                            Enviar recordatorio por WhatsApp
                        </a>

                        <button type="button" class="rounded-xl border border-cyan-100/25 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/70 disabled:opacity-50" :disabled="!canEmail || emailForm.processing" @click="sendEmailReminder">
                            Enviar recordatorio por email
                        </button>
                        <p v-if="!remindersEnabled" class="text-xs text-slate-400">Los recordatorios estan deshabilitados para este cliente.</p>
                    </div>
                </AppPanel>
            </section>

            <section id="current-account" class="grid gap-4 xl:grid-cols-[minmax(0,1.35fr)_minmax(22rem,0.8fr)]">
                <article class="min-w-0 rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 backdrop-blur">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-base font-semibold text-slate-100">Cuenta Corriente</h3>
                        <p class="text-xs text-slate-400">Historial de movimientos</p>
                    </div>

                    <div class="mt-4 grid gap-3 md:hidden">
                        <article v-for="movement in movements.data" :key="movement.id" class="rounded-xl border border-cyan-100/20 bg-slate-950/35 p-4 text-sm text-slate-300">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-semibold text-slate-100">{{ movement.created_at }}</p>
                                    <p class="mt-1 text-xs text-slate-400">{{ movement.description || '-' }}</p>
                                </div>
                                <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold" :class="movementTypeClass(movement.type)">
                                    {{ movementTypeLabel(movement.type) }}
                                </span>
                            </div>

                            <div class="mt-3 grid gap-2 text-xs text-slate-400">
                                <p>Venta: <span class="text-slate-200">{{ movementSaleLabel(movement) }}</span></p>
                                <p>Monto: <span class="text-slate-100">{{ money(movement.amount) }}</span></p>
                                <p>Saldo: <span class="text-slate-100">{{ money(movement.balance_after) }}</span></p>
                                <p>Usuario: <span class="text-slate-200">{{ movement.creator || '-' }}</span></p>
                            </div>
                        </article>
                    </div>

                    <div class="mt-4 hidden overflow-x-auto rounded-xl border border-cyan-100/20 app-table-wrap md:block">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-950/35">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium text-slate-300/80">Fecha</th>
                                    <th class="px-3 py-2 text-left font-medium text-slate-300/80">Tipo</th>
                                    <th class="px-3 py-2 text-left font-medium text-slate-300/80">Descripcion</th>
                                    <th class="px-3 py-2 text-left font-medium text-slate-300/80">Venta</th>
                                    <th class="px-3 py-2 text-left font-medium text-slate-300/80">Monto</th>
                                    <th class="px-3 py-2 text-left font-medium text-slate-300/80">Saldo</th>
                                    <th class="px-3 py-2 text-left font-medium text-slate-300/80">Usuario</th>
                                </tr>
                            </thead>
                            <tbody v-if="movements.data.length" class="divide-y divide-slate-100">
                                <tr v-for="movement in movements.data" :key="movement.id">
                                    <td class="px-3 py-2">{{ movement.created_at }}</td>
                                    <td class="px-3 py-2">
                                        <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold" :class="movementTypeClass(movement.type)">
                                            {{ movementTypeLabel(movement.type) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2">{{ movement.description || '-' }}</td>
                                    <td class="px-3 py-2">{{ movementSaleLabel(movement) }}</td>
                                    <td class="px-3 py-2">{{ money(movement.amount) }}</td>
                                    <td class="px-3 py-2">{{ money(movement.balance_after) }}</td>
                                    <td class="px-3 py-2">{{ movement.creator || '-' }}</td>
                                </tr>
                            </tbody>
                            <tbody v-else>
                                <tr>
                                    <td colspan="7" class="px-3 py-6 text-center text-slate-400">No hay movimientos registrados.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="movements.links?.length" class="mt-4 flex flex-wrap gap-2">
                        <component
                            :is="link.url ? Link : 'span'"
                            v-for="(link, index) in movements.links"
                            :key="index"
                            :href="link.url || undefined"
                            class="rounded-lg border px-3 py-1 text-xs"
                            :class="link.active ? 'border-cyan-300/50 bg-cyan-400/15 text-cyan-100' : 'border-cyan-100/25 text-slate-300'"
                            v-html="link.label"
                        />
                    </div>
                </article>

                <div class="grid min-w-0 gap-4">
                    <article class="min-w-0 rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 backdrop-blur">
                        <h3 class="text-base font-semibold text-slate-100">Ultimas ventas asociadas</h3>
                        <ul v-if="recent_sales.length" class="mt-3 space-y-2 text-sm">
                            <li v-for="sale in recent_sales" :key="sale.id" class="min-w-0 overflow-hidden rounded-lg border border-cyan-100/20 bg-slate-950/40 px-3 py-3">
                                <div class="flex min-w-0 flex-col gap-2 xl:flex-row xl:items-start xl:justify-between">
                                    <p class="min-w-0 break-words font-medium text-slate-100">{{ sale.sale_number }}</p>
                                    <span class="rounded-full bg-cyan-400/15 px-2 py-1 text-[11px] font-semibold text-cyan-100">
                                        {{ paymentStatusLabel(sale.payment_status) }}
                                    </span>
                                </div>
                                <p class="mt-1 text-xs text-slate-400">{{ sale.sold_at }}</p>
                                <div class="mt-3 grid gap-2 sm:grid-cols-3">
                                    <div class="rounded-lg border border-cyan-100/10 bg-slate-950/45 px-3 py-2">
                                        <p class="text-[11px] uppercase tracking-[0.14em] text-slate-400">Total</p>
                                        <p class="mt-1 font-semibold text-slate-100">{{ money(sale.total) }}</p>
                                    </div>
                                    <div class="rounded-lg border border-cyan-100/10 bg-slate-950/45 px-3 py-2">
                                        <p class="text-[11px] uppercase tracking-[0.14em] text-slate-400">Pagado</p>
                                        <p class="mt-1 font-semibold text-slate-100">{{ money(sale.paid_amount) }}</p>
                                    </div>
                                    <div class="rounded-lg border border-cyan-100/10 bg-slate-950/45 px-3 py-2">
                                        <p class="text-[11px] uppercase tracking-[0.14em] text-slate-400">Pendiente</p>
                                        <p class="mt-1 font-semibold" :class="Number(sale.pending_amount) > 0 ? 'text-amber-100' : 'text-emerald-100'">{{ money(sale.pending_amount) }}</p>
                                    </div>
                                </div>
                            </li>
                        </ul>
                        <p v-else class="mt-3 text-sm text-slate-400">No hay ventas asociadas todavia.</p>
                    </article>

                    <article class="min-w-0 rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 backdrop-blur">
                        <h3 class="text-base font-semibold text-slate-100">Recordatorios recientes</h3>
                        <ul v-if="recent_reminders.length" class="mt-3 space-y-2 text-sm">
                            <li v-for="reminder in recent_reminders" :key="reminder.id" class="min-w-0 overflow-hidden rounded-lg border border-cyan-100/20 bg-slate-950/40 px-3 py-3">
                                <div class="flex min-w-0 flex-col gap-2 xl:flex-row xl:items-start xl:justify-between">
                                    <p class="min-w-0 break-words font-medium text-slate-100">{{ reminder.channel }}</p>
                                    <span class="rounded-full bg-slate-700/80 px-2 py-1 text-[11px] font-semibold text-slate-100">
                                        {{ reminder.status }}
                                    </span>
                                </div>
                                <p class="mt-1 text-xs text-slate-400">{{ reminder.sent_at || '-' }}</p>
                                <div class="mt-3 rounded-lg border border-cyan-100/10 bg-slate-950/45 px-3 py-2">
                                    <p class="text-[11px] uppercase tracking-[0.14em] text-slate-400">Destino / contenido</p>
                                    <p class="mt-1 break-all font-mono text-[11px] leading-relaxed text-slate-300">{{ reminder.target || '-' }}</p>
                                </div>
                            </li>
                        </ul>
                        <p v-else class="mt-3 text-sm text-slate-400">No hay recordatorios registrados.</p>
                    </article>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
