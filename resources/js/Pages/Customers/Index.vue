<script setup>
import AppPanel from '@/Components/AppPanel.vue';
import MetricCard from '@/Components/MetricCard.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const props = defineProps({
    customers: { type: Object, required: true },
    filters: { type: Object, default: () => ({ search: '' }) },
});

const state = reactive({
    search: props.filters.search || '',
});

const money = (value) => new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    minimumFractionDigits: 2,
}).format(Number(value) || 0);

const filter = () => {
    router.get(route('customers.index'), { search: state.search }, { preserveState: true, replace: true });
};

const clearFilters = () => {
    state.search = '';
    filter();
};

const debtBadgeLabel = (value) => {
    if (value === 'high') return 'Deuda alta';
    if (value === 'due') return 'Con deuda';
    return 'Sin deuda';
};

const debtBadgeClass = (value) => {
    if (value === 'high') return 'bg-rose-500/20 text-rose-100';
    if (value === 'due') return 'bg-amber-400/20 text-amber-100';
    return 'bg-emerald-500/20 text-emerald-100';
};

const visibleCustomers = computed(() => props.customers.data || []);
const currentDebtTotal = computed(() => visibleCustomers.value.reduce((carry, customer) => carry + (Number(customer.current_balance) || 0), 0));
const debtSummary = computed(() => ({
    high: visibleCustomers.value.filter((customer) => customer.debt_badge === 'high').length,
    due: visibleCustomers.value.filter((customer) => customer.debt_badge === 'due').length,
    clear: visibleCustomers.value.filter((customer) => customer.debt_badge === 'clear').length,
}));
</script>

<template>
    <Head title="Clientes" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-slate-100">Clientes</h2>
                    <p class="mt-1 text-sm text-slate-300/80">Base de clientes del comercio y saldo actual de cuenta corriente.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link :href="route('customer-accounts.index')" class="rounded-lg border border-cyan-100/25 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/70">
                        Cuenta corriente
                    </Link>
                    <Link :href="route('customers.create')" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                        Nuevo cliente
                    </Link>
                </div>
            </div>
        </template>

        <div class="grid gap-6">
            <section class="app-kpi-grid">
                <MetricCard label="Clientes visibles" :value="customers.total" hint="Resultado total con los filtros actuales." />
                <MetricCard label="Saldo pendiente visible" :value="money(currentDebtTotal)" :hint="`${debtSummary.high} con deuda alta y ${debtSummary.due} con saldo pendiente.`" :tone="debtSummary.high > 0 ? 'warning' : 'default'" />
                <MetricCard label="Con deuda alta" :value="debtSummary.high" hint="Conviene priorizar seguimiento y cobranza." :tone="debtSummary.high > 0 ? 'danger' : 'success'" />
                <MetricCard label="Al dia" :value="debtSummary.clear" hint="Clientes sin saldo pendiente visible." :tone="debtSummary.clear > 0 ? 'success' : 'default'" />
            </section>

            <AppPanel title="Busqueda y seguimiento" subtitle="Prioriza clientes con deuda y detecta rapido quien necesita accion hoy.">
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
                        placeholder="Buscar por nombre, telefono o email"
                        @keyup.enter="filter"
                    >
                    <button type="button" class="rounded-lg border border-cyan-100/25 px-3 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/70" @click="filter">
                        Buscar
                    </button>
                </div>

                <div v-if="state.search" class="mt-4 app-chip-row">
                    <StatusBadge tone="info" :label="`Busqueda: ${state.search}`" />
                </div>

                <div class="mt-4 grid gap-3 md:hidden">
                    <article v-for="customer in customers.data" :key="customer.id" class="rounded-xl border border-cyan-100/20 bg-slate-950/35 p-4 text-sm text-slate-300">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-semibold text-slate-100">{{ customer.name }}</p>
                                <p class="mt-1 text-xs text-slate-400">{{ customer.phone || 'Sin telefono' }}</p>
                                <p class="text-xs text-slate-400">{{ customer.email || 'Sin email' }}</p>
                            </div>
                            <div class="flex flex-wrap justify-end gap-2">
                                <Link :href="route('customers.show', customer.id)" class="rounded-lg border border-cyan-100/25 px-3 py-1 text-xs font-semibold text-slate-300 hover:bg-slate-800/70">
                                    Ver
                                </Link>
                            </div>
                        </div>

                        <div class="mt-3 app-chip-row">
                            <StatusBadge :tone="customer.debt_badge === 'high' ? 'danger' : (customer.debt_badge === 'due' ? 'warning' : 'success')" :label="debtBadgeLabel(customer.debt_badge)" size="sm" />
                            <StatusBadge tone="neutral" :label="`${customer.open_sales_count} abiertos`" size="sm" />
                        </div>

                        <p class="mt-3 text-sm">Saldo actual: <strong class="text-slate-100">{{ money(customer.current_balance) }}</strong></p>

                        <div class="mt-3 flex flex-wrap gap-2">
                            <Link :href="route('customer-accounts.show', customer.id)" class="rounded-lg border border-cyan-100/25 px-3 py-1 text-xs font-semibold text-slate-300 hover:bg-slate-800/70">
                                Cuenta corriente
                            </Link>
                            <Link :href="route('customers.edit', customer.id)" class="rounded-lg border border-cyan-100/25 px-3 py-1 text-xs font-semibold text-slate-300 hover:bg-slate-800/70">
                                Editar
                            </Link>
                        </div>
                    </article>
                </div>

                <div class="mt-4 hidden overflow-x-auto rounded-xl border border-cyan-100/20 app-table-wrap md:block">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-950/35">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-slate-300/80">Cliente</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-300/80">Telefono</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-300/80">Email</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-300/80">Saldo</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-300/80">Estado</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-300/80"></th>
                        </tr>
                    </thead>
                    <tbody v-if="customers.data.length" class="divide-y divide-slate-100">
                        <tr v-for="customer in customers.data" :key="customer.id">
                            <td class="px-3 py-2">
                                <p class="font-semibold text-slate-100">{{ customer.name }}</p>
                                <div class="mt-2 app-chip-row">
                                    <StatusBadge :tone="customer.debt_badge === 'high' ? 'danger' : (customer.debt_badge === 'due' ? 'warning' : 'success')" :label="debtBadgeLabel(customer.debt_badge)" size="sm" />
                                    <StatusBadge tone="neutral" :label="`${customer.open_sales_count} abiertos`" size="sm" />
                                </div>
                            </td>
                            <td class="px-3 py-2">{{ customer.phone || '-' }}</td>
                            <td class="px-3 py-2">{{ customer.email || '-' }}</td>
                            <td class="px-3 py-2 font-semibold text-slate-100">{{ money(customer.current_balance) }}</td>
                            <td class="px-3 py-2">
                                <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold" :class="debtBadgeClass(customer.debt_badge)">
                                    {{ debtBadgeLabel(customer.debt_badge) }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-right">
                                <div class="flex justify-end gap-2">
                                    <Link :href="route('customer-accounts.show', customer.id)" class="rounded-lg border border-cyan-100/25 px-3 py-1 text-xs font-semibold text-slate-300 hover:bg-slate-800/70">
                                        Cuenta corriente
                                    </Link>
                                    <Link :href="route('customers.show', customer.id)" class="rounded-lg border border-cyan-100/25 px-3 py-1 text-xs font-semibold text-slate-300 hover:bg-slate-800/70">
                                        Ver
                                    </Link>
                                    <Link :href="route('customers.edit', customer.id)" class="rounded-lg border border-cyan-100/25 px-3 py-1 text-xs font-semibold text-slate-300 hover:bg-slate-800/70">
                                        Editar
                                    </Link>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                    <tbody v-else>
                        <tr>
                            <td colspan="6" class="px-3 py-6 text-center text-slate-400">No hay clientes registrados.</td>
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
            </AppPanel>
        </div>
    </AuthenticatedLayout>
</template>
