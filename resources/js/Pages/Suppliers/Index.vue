<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    suppliers: { type: Object, required: true },
    filters: { type: Object, default: () => ({ search: '' }) },
});

const state = reactive({ search: props.filters.search || '' });

const filter = () => {
    router.get(route('suppliers.index'), { search: state.search }, { preserveState: true, replace: true });
};
</script>

<template>
    <Head title="Proveedores" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold text-slate-100">Proveedores</h2>
                    <p class="mt-1 text-sm text-slate-300/80">Gestion de proveedores del comercio.</p>
                </div>
                <Link :href="route('suppliers.create')" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                    Nuevo proveedor
                </Link>
            </div>
        </template>

        <section class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 backdrop-blur p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <input
                    v-model="state.search"
                    type="text"
                    class="rounded-xl border-cyan-100/25 text-sm"
                    placeholder="Buscar por nombre, contacto, telefono o email"
                    @keyup.enter="filter"
                >
                <button type="button" class="rounded-lg border border-cyan-100/25 px-3 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/70" @click="filter">Buscar</button>
            </div>

            <div class="mt-4 overflow-x-auto rounded-xl border border-cyan-100/20 app-table-wrap">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-950/35">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-slate-300/80">Nombre</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-300/80">Contacto</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-300/80">Telefono</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-300/80">Email</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-300/80"></th>
                        </tr>
                    </thead>
                    <tbody v-if="suppliers.data.length" class="divide-y divide-slate-100">
                        <tr v-for="supplier in suppliers.data" :key="supplier.id">
                            <td class="px-3 py-2 font-semibold text-slate-100">{{ supplier.name }}</td>
                            <td class="px-3 py-2">{{ supplier.contact_name || '-' }}</td>
                            <td class="px-3 py-2">{{ supplier.phone || '-' }}</td>
                            <td class="px-3 py-2">{{ supplier.email || '-' }}</td>
                            <td class="px-3 py-2 text-right">
                                <Link :href="route('suppliers.edit', supplier.id)" class="rounded-lg border border-cyan-100/25 px-3 py-1 text-xs font-semibold text-slate-300 hover:bg-slate-800/70">Editar</Link>
                            </td>
                        </tr>
                    </tbody>
                    <tbody v-else>
                        <tr>
                            <td colspan="5" class="px-3 py-6 text-center text-slate-400">No hay proveedores.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="suppliers.links?.length" class="mt-4 flex flex-wrap gap-2">
                <component
                    :is="link.url ? Link : 'span'"
                    v-for="(link, index) in suppliers.links"
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

