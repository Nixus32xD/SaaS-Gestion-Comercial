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
                    <h2 class="text-2xl font-bold leading-tight text-slate-100">Compras</h2>
                    <p class="mt-1 text-sm text-slate-300/80">Gestion de proveedores y ordenes de reposicion.</p>
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
                    class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 backdrop-blur p-5 shadow-sm"
                >
                    <p class="text-xs uppercase tracking-wider text-slate-300/80">{{ item.label }}</p>
                    <p class="mt-2 text-3xl font-bold text-slate-100">{{ item.value }}</p>
                </article>
            </section>

            <section class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 backdrop-blur p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-base font-semibold text-slate-100">Ordenes de compra recientes</h3>
                    <div class="flex gap-2">
                        <input
                            type="text"
                            class="rounded-xl border-cyan-100/25 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="Buscar por proveedor o numero"
                        />
                        <button class="rounded-xl border border-cyan-100/25 bg-slate-900/50 px-3 py-2 text-sm font-semibold text-slate-100 hover:bg-slate-800/70">
                            Filtrar
                        </button>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <span
                        v-for="status in statuses"
                        :key="status.key"
                        class="rounded-full border border-cyan-100/25 bg-slate-950/35 px-3 py-1 text-xs font-medium text-slate-300"
                    >
                        {{ status.name }}
                    </span>
                </div>

                <div class="mt-5 overflow-x-auto rounded-xl border border-cyan-100/20 app-table-wrap">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-950/35">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Nro Orden</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Proveedor</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Estado</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Total</th>
                            </tr>
                        </thead>
                        <tbody v-if="recentPurchases.length" class="divide-y divide-slate-700/40 bg-slate-950/30">
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

