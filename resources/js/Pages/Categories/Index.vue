<script setup>
import AppPanel from '@/Components/AppPanel.vue';
import MetricCard from '@/Components/MetricCard.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const props = defineProps({
    categories: { type: Object, required: true },
    filters: { type: Object, default: () => ({ search: '' }) },
});

const state = reactive({
    search: props.filters.search || '',
});

const visibleCategories = computed(() => props.categories.data || []);
const activeCount = computed(() => visibleCategories.value.filter((category) => category.is_active).length);
const inactiveCount = computed(() => visibleCategories.value.filter((category) => !category.is_active).length);
const productsCovered = computed(() => visibleCategories.value.reduce((carry, category) => carry + (Number(category.products_count) || 0), 0));

const filter = () => {
    router.get(route('categories.index'), { search: state.search }, { preserveState: true, replace: true });
};

const clearFilters = () => {
    state.search = '';
    filter();
};
</script>

<template>
    <Head title="Categorias" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold text-slate-100">Categorias</h2>
                    <p class="mt-1 text-sm text-slate-300/80">Organiza el catalogo para encontrar productos mas rapido.</p>
                </div>
                <Link :href="route('categories.create')" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                    Nueva categoria
                </Link>
            </div>
        </template>

        <div class="grid gap-6">
            <section class="app-kpi-grid">
                <MetricCard label="Categorias visibles" :value="categories.total" hint="Resultado total con el filtro actual." />
                <MetricCard label="Activas" :value="activeCount" :hint="`${inactiveCount} inactivas en esta vista.`" :tone="activeCount > 0 ? 'success' : 'default'" />
                <MetricCard label="Productos cubiertos" :value="productsCovered" hint="Cantidad de productos asociados en la pagina actual." />
            </section>

            <AppPanel title="Busqueda de categorias" subtitle="Encuentra rapido la categoria correcta y ve si sigue activa para venta o compra.">
                <template #actions>
                    <button type="button" class="rounded-lg border border-rose-300/35 px-3 py-2 text-sm font-semibold text-rose-100 hover:bg-rose-400/20" @click="clearFilters">
                        Limpiar
                    </button>
                </template>

                <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_auto]">
                    <input
                        v-model="state.search"
                        type="text"
                        class="rounded-xl text-sm"
                        placeholder="Buscar por nombre"
                        @keyup.enter="filter"
                    >
                    <button type="button" class="rounded-lg border border-cyan-100/25 px-3 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/70" @click="filter">
                        Buscar
                    </button>
                </div>

                <div v-if="state.search" class="mt-4 app-chip-row">
                    <StatusBadge tone="info" :label="`Busqueda: ${state.search}`" />
                </div>
            </AppPanel>

            <AppPanel title="Listado de categorias" subtitle="Detecta rapido si la categoria esta activa y cuanta parte del catalogo depende de ella.">
                <div class="grid gap-3 md:hidden">
                    <article v-for="category in categories.data" :key="category.id" class="rounded-xl border border-cyan-100/20 bg-slate-950/35 p-4 text-sm text-slate-300">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-semibold text-slate-100">{{ category.name }}</p>
                                <p class="mt-1 text-xs text-slate-400">{{ category.description || 'Sin descripcion' }}</p>
                            </div>
                            <Link :href="route('categories.edit', category.id)" class="shrink-0 rounded-lg border border-cyan-100/25 px-3 py-1 text-xs font-semibold text-slate-300 hover:bg-slate-800/70">
                                Editar
                            </Link>
                        </div>

                        <div class="mt-3 app-chip-row">
                            <StatusBadge :tone="category.is_active ? 'success' : 'neutral'" :label="category.is_active ? 'Activa' : 'Inactiva'" size="sm" />
                            <StatusBadge tone="info" size="sm" :label="`${category.products_count} productos`" />
                        </div>
                    </article>
                </div>

                <div class="hidden overflow-x-auto rounded-xl border border-cyan-100/20 app-table-wrap md:block">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-950/35">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Categoria</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Estado</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Productos</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80"></th>
                            </tr>
                        </thead>
                        <tbody v-if="categories.data.length" class="divide-y divide-slate-100">
                            <tr v-for="category in categories.data" :key="category.id">
                                <td class="px-3 py-2">
                                    <p class="font-semibold text-slate-100">{{ category.name }}</p>
                                    <p class="text-xs text-slate-300/80">{{ category.description || 'Sin descripcion' }}</p>
                                </td>
                                <td class="px-3 py-2">
                                    <StatusBadge :tone="category.is_active ? 'success' : 'neutral'" :label="category.is_active ? 'Activa' : 'Inactiva'" size="sm" />
                                </td>
                                <td class="px-3 py-2 text-slate-300">{{ category.products_count }}</td>
                                <td class="px-3 py-2 text-right">
                                    <Link :href="route('categories.edit', category.id)" class="rounded-lg border border-cyan-100/25 px-3 py-1 text-xs font-semibold text-slate-300 hover:bg-slate-800/70">
                                        Editar
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                        <tbody v-else>
                            <tr>
                                <td colspan="4" class="px-3 py-6 text-center text-slate-400">No hay categorias cargadas.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="categories.links?.length" class="mt-4 flex flex-wrap gap-2">
                    <component
                        :is="link.url ? Link : 'span'"
                        v-for="(link, index) in categories.links"
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
