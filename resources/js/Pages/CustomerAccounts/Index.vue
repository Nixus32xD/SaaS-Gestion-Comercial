<script setup>
import AppPanel from '@/Components/AppPanel.vue';
import MetricCard from '@/Components/MetricCard.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const props = defineProps({
    customers: { type: Object, required: true },
    filters: { type: Object, required: true },
    summary: { type: Object, required: true },
});

const state = reactive({
    search: props.filters.search || '',
    only_with_balance: props.filters.only_with_balance ?? true,
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
    sort: props.filters.sort || 'balance_desc',
});

const money = (value) => new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    minimumFractionDigits: 2,
}).format(Number(value) || 0);

const applyFilters = () => {
    router.get(route('customer-accounts.index'), {
        search: state.search || undefined,
        only_with_balance: state.only_with_balance ? 1 : 0,
        date_from: state.date_from || undefined,
        date_to: state.date_to || undefined,
        sort: state.sort,
    }, {
        preserveState: true,
        replace: true,
    });
};

const clearFilters = () => {
    state.search = '';
    state.only_with_balance = true;
    state.date_from = '';
    state.date_to = '';
    state.sort = 'balance_desc';
    applyFilters();
};

const sortLabel = (value) => {
    if (value === 'name') return 'Nombre';
    if (value === 'last_activity') return 'Ultima actividad';
    return 'Mayor deuda';
};

const activeFilters = computed(() => {
    const filters = [];

    if (state.search) filters.push({ key: 'search', label: `Busqueda: ${state.search}` });
    if (state.only_with_balance) filters.push({ key: 'only_with_balance', label: 'Solo con deuda' });
    if (state.date_from) filters.push({ key: 'date_from', label: `Desde ${state.date_from}` });
    if (state.date_to) filters.push({ key: 'date_to', label: `Hasta ${state.date_to}` });
    if (state.sort !== 'balance_desc') filters.push({ key: 'sort', label: `Orden: ${sortLabel(state.sort)}` });

    return filters;
});

const clearSingleFilter = (key) => {
    if (key === 'only_with_balance') {
        state.only_with_balance = false;
    } else if (key === 'sort') {
        state.sort = 'balance_desc';
    } else {
        state[key] = '';
    }

    applyFilters();
};
</script>

<template>
    <Head title="Cuenta corriente" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-slate-100">Cuenta corriente</h2>
                    <p class="mt-1 text-sm text-slate-300/80">Saldo vivo por cliente, filtrado para seguimiento diario.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link :href="route('customers.index')" class="rounded-lg border border-cyan-100/25 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/70">
                        Ver clientes
                    </Link>
                </div>
            </div>
        </template>

        <div class="grid gap-6">
            <section class="app-kpi-grid">
                <MetricCard label="Clientes visibles" :value="summary.customers_count" hint="Cantidad total segun filtros actuales." />
                <MetricCard label="Deuda total" :value="money(summary.total_debt)" hint="Saldo vivo a seguir en cuenta corriente." :tone="summary.total_debt > 0 ? 'warning' : 'success'" />
                <MetricCard label="Orden actual" :value="sortLabel(state.sort)" hint="Criterio de prioridad aplicado al listado." />
                <MetricCard label="Seguimiento activo" :value="activeFilters.length" hint="Filtros visibles para no perder contexto." tone="accent" />
            </section>

            <AppPanel title="Filtros de seguimiento" subtitle="Usa esta vista para priorizar cobranza, detectar clientes inactivos y revisar saldos con criterio comercial.">
                <div class="grid gap-3 lg:grid-cols-[minmax(0,1.5fr)_12rem_12rem_12rem_auto]">
                    <input
                        v-model="state.search"
                        type="text"
                        class="rounded-xl border-cyan-100/25 text-sm"
                        placeholder="Buscar cliente, telefono o email"
                        @keyup.enter="applyFilters"
                    >
                    <input v-model="state.date_from" type="date" class="rounded-xl border-cyan-100/25 text-sm">
                    <input v-model="state.date_to" type="date" class="rounded-xl border-cyan-100/25 text-sm">
                    <select v-model="state.sort" class="rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100">
                        <option value="balance_desc">Mayor deuda</option>
                        <option value="name">Nombre</option>
                        <option value="last_activity">Ultima actividad</option>
                    </select>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" class="rounded-lg border border-cyan-100/25 px-3 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/70" @click="applyFilters">
                            Buscar
                        </button>
                        <button type="button" class="rounded-lg border border-rose-300/35 px-3 py-2 text-sm font-semibold text-rose-100 hover:bg-rose-400/20" @click="clearFilters">
                            Limpiar
                        </button>
                    </div>
                </div>

                <label class="mt-4 inline-flex items-center gap-3 text-sm text-slate-300">
                    <input v-model="state.only_with_balance" type="checkbox" class="rounded border-cyan-200/30 text-cyan-400 focus:ring-cyan-400/50">
                    Solo clientes con deuda
                </label>
                <div v-if="activeFilters.length" class="mt-4 app-chip-row">
                    <button
                        v-for="filter in activeFilters"
                        :key="filter.key"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-full border border-cyan-300/30 bg-cyan-400/10 px-3 py-1 text-xs font-semibold text-cyan-50 hover:bg-cyan-400/20"
                        @click="clearSingleFilter(filter.key)"
                    >
                        {{ filter.label }}
                        <span aria-hidden="true">x</span>
                    </button>
                </div>
            </AppPanel>

            <AppPanel title="Clientes para seguimiento" subtitle="La idea es ver en segundos quien debe, cuanto debe y que accion conviene tomar.">
                <div class="grid gap-3 md:hidden">
                    <article v-for="customer in customers.data" :key="customer.id" class="rounded-xl border border-cyan-100/20 bg-slate-950/35 p-4 text-sm text-slate-300">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-semibold text-slate-100">{{ customer.name }}</p>
                                <p class="mt-1 text-xs text-slate-400">{{ customer.phone || '-' }}</p>
                                <p class="text-xs text-slate-400">{{ customer.email || 'Sin email' }}</p>
                            </div>
                            <Link :href="route('customer-accounts.show', customer.id)" class="shrink-0 rounded-lg border border-cyan-100/25 px-3 py-1 text-xs font-semibold text-slate-300 hover:bg-slate-800/70">
                                Ver detalle
                            </Link>
                        </div>

                        <div class="mt-3 app-chip-row">
                            <StatusBadge :tone="Number(customer.current_balance) > 0 ? 'warning' : 'success'" :label="Number(customer.current_balance) > 0 ? 'Con deuda' : 'Al dia'" size="sm" />
                            <StatusBadge tone="neutral" :label="`${customer.open_sales_count} abiertas`" size="sm" />
                        </div>

                        <div class="mt-3 grid gap-2 text-xs text-slate-400">
                            <p>Saldo actual: <span class="text-slate-100">{{ money(customer.current_balance) }}</span></p>
                            <p>Ultima actividad: <span class="text-slate-200">{{ customer.last_activity_at || '-' }}</span></p>
                        </div>
                    </article>
                </div>

                <div class="hidden overflow-x-auto rounded-xl border border-cyan-100/20 app-table-wrap md:block">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-950/35">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Cliente</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Telefono</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Saldo actual</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Ultima actividad</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Ventas abiertas</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80"></th>
                            </tr>
                        </thead>
                        <tbody v-if="customers.data.length" class="divide-y divide-slate-100">
                            <tr v-for="customer in customers.data" :key="customer.id">
                                <td class="px-3 py-2">
                                    <p class="font-semibold text-slate-100">{{ customer.name }}</p>
                                    <div class="mt-2 app-chip-row">
                                        <StatusBadge :tone="Number(customer.current_balance) > 0 ? 'warning' : 'success'" :label="Number(customer.current_balance) > 0 ? 'Con deuda' : 'Al dia'" size="sm" />
                                        <StatusBadge tone="neutral" :label="`${customer.open_sales_count} abiertas`" size="sm" />
                                    </div>
                                    <p class="mt-2 text-xs text-slate-400">{{ customer.email || 'Sin email' }}</p>
                                </td>
                                <td class="px-3 py-2">{{ customer.phone || '-' }}</td>
                                <td class="px-3 py-2 font-semibold text-slate-100">{{ money(customer.current_balance) }}</td>
                                <td class="px-3 py-2">{{ customer.last_activity_at || '-' }}</td>
                                <td class="px-3 py-2">{{ customer.open_sales_count }}</td>
                                <td class="px-3 py-2 text-right">
                                    <Link :href="route('customer-accounts.show', customer.id)" class="rounded-lg border border-cyan-100/25 px-3 py-1 text-xs font-semibold text-slate-300 hover:bg-slate-800/70">
                                        Ver detalle
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                        <tbody v-else>
                            <tr>
                                <td colspan="6" class="px-3 py-6 text-center text-slate-400">No hay clientes para los filtros seleccionados.</td>
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
