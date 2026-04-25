<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    customers: { type: Object, required: true },
    filters: { type: Object, required: true },
});

const state = reactive({
    search: props.filters.search || '',
    minimum_balance: props.filters.minimum_balance || '',
    last_movement_from: props.filters.last_movement_from || '',
    last_debt_from: props.filters.last_debt_from || '',
    preferred_reminder_channel: props.filters.preferred_reminder_channel || '',
    has_phone: props.filters.has_phone || '',
    has_email: props.filters.has_email || '',
});

const money = (value) => new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    minimumFractionDigits: 2,
}).format(Number(value) || 0);

const filter = () => {
    router.get(route('customers.debtors'), { ...state }, {
        preserveState: true,
        replace: true,
    });
};

const clearFilters = () => {
    state.search = '';
    state.minimum_balance = '';
    state.last_movement_from = '';
    state.last_debt_from = '';
    state.preferred_reminder_channel = '';
    state.has_phone = '';
    state.has_email = '';
    filter();
};

const copySummary = async (summary) => {
    await navigator.clipboard.writeText(summary);
};

const sendEmailReminder = (customerId) => {
    router.post(route('customers.reminders.email', customerId), {}, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Clientes con deuda" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-slate-100">Clientes con deuda</h2>
                    <p class="mt-1 text-sm text-slate-300/80">Vista operativa para cobranza y seguimiento.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link :href="route('customers.index')" class="rounded-lg border border-cyan-100/25 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/70">
                        Todos los clientes
                    </Link>
                </div>
            </div>
        </template>

        <div class="grid gap-6">
            <section class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 backdrop-blur p-5 shadow-sm">
                <div class="grid gap-3 xl:grid-cols-[minmax(0,1.4fr)_10rem_10rem_10rem_10rem_10rem_10rem_auto]">
                    <input v-model="state.search" type="text" class="rounded-xl border-cyan-100/25 text-sm" placeholder="Buscar cliente, telefono o email" @keyup.enter="filter">
                    <input v-model="state.minimum_balance" type="number" min="0" step="0.01" class="rounded-xl border-cyan-100/25 text-sm" placeholder="Saldo minimo">
                    <input v-model="state.last_movement_from" type="date" class="rounded-xl border-cyan-100/25 text-sm">
                    <input v-model="state.last_debt_from" type="date" class="rounded-xl border-cyan-100/25 text-sm">
                    <select v-model="state.preferred_reminder_channel" class="rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100">
                        <option value="">Canal</option>
                        <option value="whatsapp">WhatsApp</option>
                        <option value="email">Email</option>
                        <option value="none">Ninguno</option>
                    </select>
                    <select v-model="state.has_phone" class="rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100">
                        <option value="">Telefono</option>
                        <option value="yes">Con telefono</option>
                        <option value="no">Sin telefono</option>
                    </select>
                    <select v-model="state.has_email" class="rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100">
                        <option value="">Email</option>
                        <option value="yes">Con email</option>
                        <option value="no">Sin email</option>
                    </select>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" class="rounded-lg border border-cyan-100/25 px-3 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/70" @click="filter">Buscar</button>
                        <button type="button" class="rounded-lg border border-rose-300/35 px-3 py-2 text-sm font-semibold text-rose-100 hover:bg-rose-400/20" @click="clearFilters">Limpiar</button>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 backdrop-blur p-5 shadow-sm">
                <div class="grid gap-3 md:hidden">
                    <article v-for="customer in customers.data" :key="customer.id" class="rounded-xl border border-cyan-100/20 bg-slate-950/35 p-4 text-sm text-slate-300">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-semibold text-slate-100">{{ customer.name }}</p>
                                <p class="mt-1 text-xs text-slate-400">{{ customer.preferred_reminder_channel }}</p>
                            </div>
                            <p class="text-sm font-semibold text-slate-100">{{ money(customer.current_balance) }}</p>
                        </div>

                        <div class="mt-3 grid gap-2 text-xs text-slate-400">
                            <p>Telefono: <span class="text-slate-200">{{ customer.phone || '-' }}</span></p>
                            <p>Email: <span class="text-slate-200">{{ customer.email || '-' }}</span></p>
                            <p>Ultimo movimiento: <span class="text-slate-200">{{ customer.last_movement_at || '-' }}</span></p>
                            <p>Ultima venta fiada: <span class="text-slate-200">{{ customer.last_open_sale_at || '-' }}</span></p>
                            <p>Ultimo recordatorio: <span class="text-slate-200">{{ customer.last_reminder_sent_at || '-' }}</span></p>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-2">
                            <Link :href="route('customers.show', customer.id)" class="rounded-lg border border-cyan-100/25 px-3 py-1 text-xs font-semibold text-slate-300 hover:bg-slate-800/70">
                                Ver
                            </Link>
                            <Link :href="`${route('customers.show', customer.id)}#register-payment`" class="rounded-lg border border-cyan-100/25 px-3 py-1 text-xs font-semibold text-slate-300 hover:bg-slate-800/70">
                                Registrar pago
                            </Link>
                            <a
                                :href="route('customers.reminders.whatsapp', customer.id)"
                                target="_blank"
                                rel="noreferrer"
                                class="rounded-lg border border-cyan-100/25 px-3 py-1 text-xs font-semibold text-slate-300 hover:bg-slate-800/70"
                                :class="{ 'pointer-events-none opacity-50': !customer.phone || !customer.allow_reminders || customer.preferred_reminder_channel === 'none' }"
                            >
                                WhatsApp
                            </a>
                            <button
                                type="button"
                                class="rounded-lg border border-cyan-100/25 px-3 py-1 text-xs font-semibold text-slate-300 hover:bg-slate-800/70 disabled:opacity-50"
                                :disabled="!customer.email || !customer.allow_reminders || customer.preferred_reminder_channel === 'none'"
                                @click="sendEmailReminder(customer.id)"
                            >
                                Email
                            </button>
                            <button type="button" class="rounded-lg border border-cyan-100/25 px-3 py-1 text-xs font-semibold text-slate-300 hover:bg-slate-800/70" @click="copySummary(customer.summary_copy)">
                                Copiar resumen
                            </button>
                        </div>
                    </article>
                </div>

                <div class="hidden overflow-x-auto rounded-xl border border-cyan-100/20 app-table-wrap md:block">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-950/35">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Cliente</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Telefono</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Email</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Saldo</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Ultimo movimiento</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Ultima venta fiada</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Ultimo recordatorio</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80"></th>
                            </tr>
                        </thead>
                        <tbody v-if="customers.data.length" class="divide-y divide-slate-100">
                            <tr v-for="customer in customers.data" :key="customer.id">
                                <td class="px-3 py-2">
                                    <p class="font-semibold text-slate-100">{{ customer.name }}</p>
                                    <p class="mt-1 text-xs text-slate-400">{{ customer.preferred_reminder_channel }}</p>
                                </td>
                                <td class="px-3 py-2">{{ customer.phone || '-' }}</td>
                                <td class="px-3 py-2">{{ customer.email || '-' }}</td>
                                <td class="px-3 py-2">{{ money(customer.current_balance) }}</td>
                                <td class="px-3 py-2">{{ customer.last_movement_at || '-' }}</td>
                                <td class="px-3 py-2">{{ customer.last_open_sale_at || '-' }}</td>
                                <td class="px-3 py-2">{{ customer.last_reminder_sent_at || '-' }}</td>
                                <td class="px-3 py-2 text-right">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <Link :href="route('customers.show', customer.id)" class="rounded-lg border border-cyan-100/25 px-3 py-1 text-xs font-semibold text-slate-300 hover:bg-slate-800/70">
                                            Ver
                                        </Link>
                                        <Link :href="`${route('customers.show', customer.id)}#register-payment`" class="rounded-lg border border-cyan-100/25 px-3 py-1 text-xs font-semibold text-slate-300 hover:bg-slate-800/70">
                                            Registrar pago
                                        </Link>
                                        <a
                                            :href="route('customers.reminders.whatsapp', customer.id)"
                                            target="_blank"
                                            rel="noreferrer"
                                            class="rounded-lg border border-cyan-100/25 px-3 py-1 text-xs font-semibold text-slate-300 hover:bg-slate-800/70"
                                            :class="{ 'pointer-events-none opacity-50': !customer.phone || !customer.allow_reminders || customer.preferred_reminder_channel === 'none' }"
                                        >
                                            WhatsApp
                                        </a>
                                        <button
                                            type="button"
                                            class="rounded-lg border border-cyan-100/25 px-3 py-1 text-xs font-semibold text-slate-300 hover:bg-slate-800/70 disabled:opacity-50"
                                            :disabled="!customer.email || !customer.allow_reminders || customer.preferred_reminder_channel === 'none'"
                                            @click="sendEmailReminder(customer.id)"
                                        >
                                            Email
                                        </button>
                                        <button type="button" class="rounded-lg border border-cyan-100/25 px-3 py-1 text-xs font-semibold text-slate-300 hover:bg-slate-800/70" @click="copySummary(customer.summary_copy)">
                                            Copiar resumen
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                        <tbody v-else>
                            <tr>
                                <td colspan="8" class="px-3 py-6 text-center text-slate-400">No hay clientes con deuda para los filtros actuales.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="customers.links?.length" class="mt-4 flex flex-wrap gap-2">
                    <component
                        :is="link.url ? Link : 'span'"
                        v-for="(link, index) in customers.links"
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
