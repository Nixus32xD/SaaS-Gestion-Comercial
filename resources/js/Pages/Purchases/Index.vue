<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    purchases: { type: Object, required: true },
    filters: { type: Object, default: () => ({ search: '' }) },
});

const state = reactive({ search: props.filters.search || '' });

const filter = () => {
    router.get(route('purchases.index'), { search: state.search }, { preserveState: true, replace: true });
};

const money = (value) => new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    minimumFractionDigits: 2,
}).format(Number(value) || 0);
</script>

<template>
    <Head title="Compras" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold text-slate-900">Compras</h2>
                    <p class="mt-1 text-sm text-slate-500">Historial de compras y reposicion.</p>
                </div>
                <Link :href="route('purchases.create')" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Nueva compra</Link>
            </div>
        </template>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <input
                    v-model="state.search"
                    type="text"
                    class="rounded-xl border-slate-300 text-sm"
                    placeholder="Buscar por numero o notas"
                    @keyup.enter="filter"
                >
                <button type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="filter">Buscar</button>
            </div>

            <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200 app-table-wrap">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-slate-500">Numero</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-500">Fecha</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-500">Proveedor</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-500">Items</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-500">Total</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-500"></th>
                        </tr>
                    </thead>
                    <tbody v-if="purchases.data.length" class="divide-y divide-slate-100">
                        <tr v-for="purchase in purchases.data" :key="purchase.id">
                            <td class="px-3 py-2 font-semibold text-slate-900">{{ purchase.purchase_number || `COMPRA-${purchase.id}` }}</td>
                            <td class="px-3 py-2">{{ purchase.purchased_at || '-' }}</td>
                            <td class="px-3 py-2">{{ purchase.supplier || '-' }}</td>
                            <td class="px-3 py-2">{{ purchase.items_count }}</td>
                            <td class="px-3 py-2">{{ money(purchase.total) }}</td>
                            <td class="px-3 py-2 text-right">
                                <Link :href="route('purchases.show', purchase.id)" class="rounded-lg border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">Ver</Link>
                            </td>
                        </tr>
                    </tbody>
                    <tbody v-else>
                        <tr>
                            <td colspan="6" class="px-3 py-6 text-center text-slate-400">No hay compras registradas.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="purchases.links?.length" class="mt-4 flex flex-wrap gap-2">
                <component
                    :is="link.url ? Link : 'span'"
                    v-for="(link, index) in purchases.links"
                    :key="index"
                    :href="link.url || undefined"
                    class="rounded-lg border px-3 py-1 text-xs"
                    :class="link.active ? 'border-indigo-600 bg-indigo-50 text-indigo-700' : 'border-slate-300 text-slate-600'"
                    v-html="link.label"
                />
            </div>
        </section>
    </AuthenticatedLayout>
</template>
