<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

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
            <section class="grid gap-4 md:grid-cols-3">
                <article class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 backdrop-blur">
                    <p class="text-xs uppercase tracking-wider text-cyan-100/70">Clientes visibles</p>
                    <p class="mt-2 text-3xl font-bold text-slate-100">{{ summary.customers_count }}</p>
                </article>
                <article class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 backdrop-blur">
                    <p class="text-xs uppercase tracking-wider text-cyan-100/70">Deuda total</p>
                    <p class="mt-2 text-3xl font-bold text-slate-100">{{ money(summary.total_debt) }}</p>
                </article>
                <article class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 backdrop-blur">
                    <p class="text-xs uppercase tracking-wider text-cyan-100/70">Orden actual</p>
                    <p class="mt-2 text-3xl font-bold text-slate-100">{{ sortLabel(state.sort) }}</p>
                </article>
            </section>

            <section class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-sm backdrop-blur">
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
            </section>

            <section class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-sm backdrop-blur">
                <div class="overflow-x-auto rounded-xl border border-cyan-100/20 app-table-wrap">
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
                                    <p class="mt-1 text-xs text-slate-400">{{ customer.email || 'Sin email' }}</p>
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
            </section>
        </div>
    </AuthenticatedLayout>
</template>
