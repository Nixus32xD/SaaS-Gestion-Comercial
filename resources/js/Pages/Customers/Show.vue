<script setup>
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
            <section class="grid gap-4 lg:grid-cols-4">
                <article class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 backdrop-blur">
                    <p class="text-xs uppercase tracking-wider text-cyan-100/70">Saldo actual</p>
                    <p class="mt-2 text-3xl font-bold text-slate-100">{{ money(customer.current_balance) }}</p>
                </article>
                <article class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 backdrop-blur">
                    <p class="text-xs uppercase tracking-wider text-cyan-100/70">Deuda historica</p>
                    <p class="mt-2 text-3xl font-bold text-slate-100">{{ money(customer.debt_total) }}</p>
                </article>
                <article class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 backdrop-blur">
                    <p class="text-xs uppercase tracking-wider text-cyan-100/70">Total pagado</p>
                    <p class="mt-2 text-3xl font-bold text-slate-100">{{ money(customer.paid_total) }}</p>
                </article>
                <article class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 backdrop-blur">
                    <p class="text-xs uppercase tracking-wider text-cyan-100/70">Comprobantes abiertos</p>
                    <p class="mt-2 text-3xl font-bold text-slate-100">{{ customer.open_sales_count }}</p>
                </article>
            </section>

            <section class="grid gap-4 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]">
                <article class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 backdrop-blur">
                    <h3 class="text-base font-semibold text-slate-100">Datos del cliente</h3>
                    <div class="mt-3 grid gap-2 text-sm text-slate-300">
                        <p>Telefono: <strong>{{ customer.phone || '-' }}</strong></p>
                        <p>Email: <strong>{{ customer.email || '-' }}</strong></p>
                        <p>Direccion: <strong>{{ customer.address || '-' }}</strong></p>
                        <p>Canal preferido: <strong>{{ customer.preferred_reminder_channel }}</strong></p>
                        <p>Recordatorios habilitados: <strong>{{ customer.allow_reminders ? 'Si' : 'No' }}</strong></p>
                        <p>Ultimo recordatorio: <strong>{{ customer.last_reminder_at || '-' }}</strong></p>
                        <p>Notas de contacto: <strong>{{ customer.reminder_notes || '-' }}</strong></p>
                        <p>Notas generales: <strong>{{ customer.notes || '-' }}</strong></p>
                    </div>
                </article>

                <article id="register-payment" class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 backdrop-blur">
                    <h3 class="text-base font-semibold text-slate-100">Acciones</h3>
                    <div class="mt-4 grid gap-3">
                        <form class="grid gap-3 rounded-xl border border-cyan-100/15 bg-slate-950/35 p-4" @submit.prevent="submitPayment">
                            <p class="text-sm font-semibold text-slate-100">Registrar pago</p>
                            <input v-model="paymentForm.amount" type="number" min="0" step="0.01" class="rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100" placeholder="Monto" />
                            <p v-if="paymentForm.errors.amount" class="text-xs text-rose-300">{{ paymentForm.errors.amount }}</p>
                            <input v-model="paymentForm.paid_at" type="datetime-local" class="rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100" />
                            <p v-if="paymentForm.errors.paid_at" class="text-xs text-rose-300">{{ paymentForm.errors.paid_at }}</p>
                            <select v-model="paymentForm.payment_method" class="rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100">
                                <option value="cash">Efectivo</option>
                                <option value="transfer">Transferencia</option>
                            </select>
                            <p v-if="paymentForm.errors.payment_method" class="text-xs text-rose-300">{{ paymentForm.errors.payment_method }}</p>
                            <textarea v-model="paymentForm.description" rows="2" class="rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100" placeholder="Descripcion opcional" />
                            <p v-if="paymentForm.errors.description" class="text-xs text-rose-300">{{ paymentForm.errors.description }}</p>
                            <p class="text-xs text-slate-400">El pago se aplica automaticamente a las ventas pendientes mas antiguas.</p>
                            <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-600" :disabled="paymentForm.processing">
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
                        <p v-if="emailForm.errors.email" class="text-xs text-rose-300">{{ emailForm.errors.email }}</p>
                        <p v-if="emailForm.errors.allow_reminders" class="text-xs text-rose-300">{{ emailForm.errors.allow_reminders }}</p>
                        <p v-if="!remindersEnabled" class="text-xs text-slate-400">
                            Los recordatorios estan deshabilitados para este cliente.
                        </p>
                    </div>
                </article>
            </section>

            <section id="current-account" class="grid gap-4 xl:grid-cols-[minmax(0,1.4fr)_minmax(0,0.6fr)]">
                <article class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 backdrop-blur">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-base font-semibold text-slate-100">Cuenta Corriente</h3>
                        <p class="text-xs text-slate-400">Historial de movimientos</p>
                    </div>

                    <div class="mt-4 overflow-x-auto rounded-xl border border-cyan-100/20 app-table-wrap">
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

                <div class="grid gap-4">
                    <article class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 backdrop-blur">
                        <h3 class="text-base font-semibold text-slate-100">Ultimas ventas asociadas</h3>
                        <ul v-if="recent_sales.length" class="mt-3 space-y-2 text-sm">
                            <li v-for="sale in recent_sales" :key="sale.id" class="rounded-lg border border-cyan-100/20 bg-slate-950/40 px-3 py-2">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="font-medium text-slate-100">{{ sale.sale_number }}</p>
                                    <span class="rounded-full bg-cyan-400/15 px-2 py-1 text-[11px] font-semibold text-cyan-100">
                                        {{ paymentStatusLabel(sale.payment_status) }}
                                    </span>
                                </div>
                                <p class="mt-1 text-xs text-slate-400">{{ sale.sold_at }}</p>
                                <p class="mt-2 text-xs text-slate-300">Total {{ money(sale.total) }} · Pagado {{ money(sale.paid_amount) }} · Pendiente {{ money(sale.pending_amount) }}</p>
                            </li>
                        </ul>
                        <p v-else class="mt-3 text-sm text-slate-400">No hay ventas asociadas todavia.</p>
                    </article>

                    <article class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 backdrop-blur">
                        <h3 class="text-base font-semibold text-slate-100">Recordatorios recientes</h3>
                        <ul v-if="recent_reminders.length" class="mt-3 space-y-2 text-sm">
                            <li v-for="reminder in recent_reminders" :key="reminder.id" class="rounded-lg border border-cyan-100/20 bg-slate-950/40 px-3 py-2">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="font-medium text-slate-100">{{ reminder.channel }}</p>
                                    <span class="rounded-full bg-slate-700/80 px-2 py-1 text-[11px] font-semibold text-slate-100">
                                        {{ reminder.status }}
                                    </span>
                                </div>
                                <p class="mt-1 text-xs text-slate-400">{{ reminder.sent_at || '-' }}</p>
                                <p class="mt-2 text-xs text-slate-300">{{ reminder.target || '-' }}</p>
                            </li>
                        </ul>
                        <p v-else class="mt-3 text-sm text-slate-400">No hay recordatorios registrados.</p>
                    </article>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
