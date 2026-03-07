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
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Stock</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:px-8">
                <section class="grid gap-4 md:grid-cols-3">
                    <article
                        v-for="item in summary"
                        :key="item.label"
                        class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm"
                    >
                        <p class="text-xs uppercase tracking-wide text-gray-500">{{ item.label }}</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900">{{ item.value }}</p>
                    </article>
                </section>

                <section class="grid gap-6 lg:grid-cols-2">
                    <article class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                        <h3 class="text-base font-semibold text-gray-900">Alertas de stock</h3>
                        <ul v-if="alerts.length" class="mt-3 space-y-2 text-sm text-gray-600">
                            <li v-for="alert in alerts" :key="alert.id">
                                • {{ alert.product }} ({{ alert.branch }}) - mínimo {{ alert.minimum }}
                            </li>
                        </ul>
                        <p v-else class="mt-3 text-sm text-gray-500">
                            Sin alertas por el momento.
                        </p>
                    </article>

                    <article class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                        <h3 class="text-base font-semibold text-gray-900">Últimos movimientos</h3>
                        <ul v-if="movements.length" class="mt-3 space-y-2 text-sm text-gray-600">
                            <li v-for="movement in movements" :key="movement.id">
                                • {{ movement.type }} - {{ movement.product }} ({{ movement.quantity }})
                            </li>
                        </ul>
                        <p v-else class="mt-3 text-sm text-gray-500">
                            Todavía no hay movimientos registrados.
                        </p>
                    </article>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
