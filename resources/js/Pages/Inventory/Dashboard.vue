<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    summary: {
        type: Array,
        default: () => [],
    },
    alerts: {
        type: Array,
        default: () => [],
    },
    movements: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <Head title="Stock" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold leading-tight text-slate-900">Stock</h2>
                    <p class="mt-1 text-sm text-slate-500">Monitoreo de inventario por sucursal.</p>
                </div>
                <button class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Nuevo ajuste
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

            <section class="grid gap-6 lg:grid-cols-2">
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="text-base font-semibold text-slate-900">Alertas de stock</h3>
                    <ul v-if="alerts.length" class="mt-3 space-y-2 text-sm text-slate-600">
                        <li v-for="alert in alerts" :key="alert.id" class="flex items-start gap-2">
                            <span class="mt-1 block h-2 w-2 rounded-full bg-rose-500" />
                            <span>{{ alert.product }} ({{ alert.branch }}) - minimo {{ alert.minimum }}</span>
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-sm text-slate-500">
                        Sin alertas por el momento.
                    </p>
                </article>

                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="text-base font-semibold text-slate-900">Ultimos movimientos</h3>
                    <ul v-if="movements.length" class="mt-3 space-y-2 text-sm text-slate-600">
                        <li v-for="movement in movements" :key="movement.id" class="flex items-start gap-2">
                            <span class="mt-1 block h-2 w-2 rounded-full bg-indigo-500" />
                            <span>{{ movement.type }} - {{ movement.product }} ({{ movement.quantity }})</span>
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-sm text-slate-500">
                        Todavia no hay movimientos registrados.
                    </p>
                </article>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
