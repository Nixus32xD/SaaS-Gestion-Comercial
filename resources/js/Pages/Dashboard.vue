<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    kpis: {
        type: Array,
        default: () => [],
    },
    modules: {
        type: Array,
        default: () => [],
    },
    workQueue: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <Head title="Dashboard Operativo" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold leading-tight text-slate-900">Dashboard Operativo</h2>
                    <p class="mt-1 text-sm text-slate-500">Control de POS, compras y stock en una sola vista.</p>
                </div>
                <div class="flex gap-2">
                    <Link
                        :href="route('pos.index')"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500"
                    >
                        Abrir POS
                    </Link>
                    <Link
                        :href="route('inventory.index')"
                        class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        Revisar Stock
                    </Link>
                </div>
            </div>
        </template>

        <div class="grid gap-6">
            <section class="grid gap-4 md:grid-cols-3">
                <article
                    v-for="kpi in kpis"
                    :key="kpi.label"
                    class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <p class="text-xs uppercase tracking-wider text-slate-500">{{ kpi.label }}</p>
                    <p class="mt-2 text-3xl font-bold text-slate-900">{{ kpi.value }}</p>
                </article>
            </section>

            <section class="rounded-2xl bg-gradient-to-r from-indigo-600 via-indigo-500 to-sky-500 p-6 text-white shadow">
                <p class="text-sm text-indigo-100">Vista rapida</p>
                <h3 class="mt-1 text-2xl font-bold">Todo listo para operar</h3>
                <p class="mt-2 max-w-2xl text-sm text-indigo-100">
                    Usa los modulos para abrir caja, vender, cargar compras y controlar niveles de inventario.
                </p>
            </section>

            <section class="grid gap-4 lg:grid-cols-3">
                <article
                    v-for="module in modules"
                    :key="module.title"
                    class="group flex flex-col justify-between rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                >
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">{{ module.title }}</h3>
                        <p class="mt-2 text-sm text-slate-600">{{ module.description }}</p>
                    </div>
                    <Link
                        :href="route(module.route)"
                        class="mt-5 inline-flex w-fit rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition group-hover:bg-indigo-600"
                    >
                        {{ module.action }}
                    </Link>
                </article>
            </section>

            <section class="grid gap-4 lg:grid-cols-3">
                <article
                    v-for="queue in workQueue"
                    :key="queue.title"
                    class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <h3 class="text-base font-semibold text-slate-900">{{ queue.title }}</h3>
                    <ul class="mt-3 space-y-2">
                        <li
                            v-for="task in queue.items"
                            :key="task"
                            class="flex items-start gap-2 text-sm text-slate-600"
                        >
                            <span class="mt-1 block h-2 w-2 rounded-full bg-indigo-500" />
                            <span>{{ task }}</span>
                        </li>
                    </ul>
                </article>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-700">
                    Comercio: {{ $page.props.tenant?.name ?? 'No definido' }} -
                    Sucursal: {{ $page.props.branch?.name ?? 'No definida' }}
                </p>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
