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
                    <h2 class="text-2xl font-bold leading-tight text-slate-100">Dashboard Operativo</h2>
                    <p class="mt-1 text-sm text-slate-300">Control de POS, compras y stock en una sola vista.</p>
                </div>
                <div class="flex gap-2">
                    <Link
                        :href="route('pos.index')"
                        class="rounded-lg bg-cyan-300 px-4 py-2 text-sm font-semibold text-slate-900 shadow hover:bg-cyan-200"
                    >
                        Abrir POS
                    </Link>
                    <Link
                        :href="route('inventory.index')"
                        class="rounded-lg border border-cyan-200/35 bg-slate-900/50 px-4 py-2 text-sm font-semibold text-cyan-100 hover:bg-slate-800/70"
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
                    class="app-section"
                >
                    <p class="text-xs uppercase tracking-wider text-slate-500">{{ kpi.label }}</p>
                    <p class="mt-2 text-3xl font-bold text-slate-900">{{ kpi.value }}</p>
                </article>
            </section>

            <section class="rounded-2xl border border-cyan-100/20 bg-gradient-to-r from-[#0b3a75] via-[#0f2550] to-[#0b5f8f] p-6 text-white shadow-[0_20px_40px_rgba(8,47,73,0.35)]">
                <p class="text-sm text-cyan-100">Vista rapida</p>
                <h3 class="mt-1 text-2xl font-bold">Todo listo para operar</h3>
                <p class="mt-2 max-w-2xl text-sm text-cyan-100">
                    Usa los modulos para abrir caja, vender, cargar compras y controlar niveles de inventario.
                </p>
            </section>

            <section class="grid gap-4 lg:grid-cols-3">
                <article
                    v-for="module in modules"
                    :key="module.title"
                    class="app-section group flex flex-col justify-between transition hover:-translate-y-0.5 hover:shadow-[0_22px_40px_rgba(8,47,73,0.35)]"
                >
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">{{ module.title }}</h3>
                        <p class="mt-2 text-sm text-slate-600">{{ module.description }}</p>
                    </div>
                    <Link
                        :href="route(module.route)"
                        class="mt-5 inline-flex w-fit rounded-lg bg-cyan-300 px-4 py-2 text-sm font-semibold text-slate-900 transition group-hover:bg-cyan-200"
                    >
                        {{ module.action }}
                    </Link>
                </article>
            </section>

            <section class="grid gap-4 lg:grid-cols-3">
                <article
                    v-for="queue in workQueue"
                    :key="queue.title"
                    class="app-section"
                >
                    <h3 class="text-base font-semibold text-slate-900">{{ queue.title }}</h3>
                    <ul class="mt-3 space-y-2">
                        <li
                            v-for="task in queue.items"
                            :key="task"
                            class="flex items-start gap-2 text-sm text-slate-600"
                        >
                            <span class="mt-1 block h-2 w-2 rounded-full bg-cyan-300" />
                            <span>{{ task }}</span>
                        </li>
                    </ul>
                </article>
            </section>

            <section class="app-section">
                <p class="text-sm font-medium text-slate-700">
                    Comercio: {{ $page.props.business?.name ?? 'No definido' }}
                </p>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
