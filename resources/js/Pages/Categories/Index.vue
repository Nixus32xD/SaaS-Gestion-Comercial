<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    categories: { type: Object, required: true },
    filters: { type: Object, default: () => ({ search: '' }) },
});

const state = reactive({
    search: props.filters.search || '',
});

const filter = () => {
    router.get(route('categories.index'), { search: state.search }, { preserveState: true, replace: true });
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

        <section class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 backdrop-blur p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <input
                    v-model="state.search"
                    type="text"
                    class="rounded-xl border-cyan-100/25 text-sm"
                    placeholder="Buscar por nombre"
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
                                <span class="rounded border px-2 py-0.5 text-xs font-semibold" :class="category.is_active ? 'border-emerald-300/45 bg-emerald-400/15 text-emerald-100' : 'border-slate-200/30 bg-slate-400/10 text-slate-300'">
                                    {{ category.is_active ? 'Activa' : 'Inactiva' }}
                                </span>
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
        </section>
    </AuthenticatedLayout>
</template>
