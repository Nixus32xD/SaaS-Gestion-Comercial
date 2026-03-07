<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    summary: {
        type: Array,
        default: () => [],
    },
    statuses: {
        type: Array,
        default: () => [],
    },
    recentPurchases: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <Head title="Compras" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold leading-tight text-slate-900">Compras</h2>
                    <p class="mt-1 text-sm text-slate-500">Gestion de proveedores y ordenes de reposicion.</p>
                </div>
                <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500">
                    Nueva orden
                </button>
            </div>
        </template>

        <div class="grid gap-6">
            <section class="grid gap-4 md:grid-cols-3">
                <article
                    v-for="item in summary"
                    :key="item.label"
                    class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <p class="text-xs uppercase tracking-wider text-slate-500">{{ item.label }}</p>
                    <p class="mt-2 text-3xl font-bold text-slate-900">{{ item.value }}</p>
                </article>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-base font-semibold text-slate-900">Ordenes de compra recientes</h3>
                    <div class="flex gap-2">
                        <input
                            type="text"
                            class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="Buscar por proveedor o numero"
                        />
                        <button class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            Filtrar
                        </button>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <span
                        v-for="status in statuses"
                        :key="status.key"
                        class="rounded-full border border-slate-300 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-600"
                    >
                        {{ status.name }}
                    </span>
                </div>

                <div class="mt-5 overflow-hidden rounded-xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Nro Orden</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Proveedor</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Estado</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Total</th>
                            </tr>
                        </thead>
                        <tbody v-if="recentPurchases.length" class="divide-y divide-slate-100 bg-white">
                            <tr v-for="purchase in recentPurchases" :key="purchase.id">
                                <td class="px-3 py-2">{{ purchase.number }}</td>
                                <td class="px-3 py-2">{{ purchase.supplier }}</td>
                                <td class="px-3 py-2">{{ purchase.status }}</td>
                                <td class="px-3 py-2">{{ purchase.total }}</td>
                            </tr>
                        </tbody>
                        <tbody v-else>
                            <tr>
                                <td colspan="4" class="px-3 py-6 text-center text-slate-400">
                                    Aun no hay ordenes de compra cargadas.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
