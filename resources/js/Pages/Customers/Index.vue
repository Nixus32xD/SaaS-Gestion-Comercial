<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

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

        <section class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 backdrop-blur p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <input
                    v-model="state.search"
                    type="text"
                    class="rounded-xl border-cyan-100/25 text-sm"
                    placeholder="Buscar por nombre, telefono o email"
                    @keyup.enter="filter"
                >
                <button type="button" class="rounded-lg border border-cyan-100/25 px-3 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/70" @click="filter">
                    Buscar
                </button>
            </div>

            <div class="mt-4 overflow-x-auto rounded-xl border border-cyan-100/20 app-table-wrap">
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
                                <p class="mt-1 text-xs text-slate-400">Abiertos: {{ customer.open_sales_count }}</p>
                            </td>
                            <td class="px-3 py-2">{{ customer.phone || '-' }}</td>
                            <td class="px-3 py-2">{{ customer.email || '-' }}</td>
                            <td class="px-3 py-2">{{ money(customer.current_balance) }}</td>
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
        </section>
    </AuthenticatedLayout>
</template>
