<script setup>
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    businesses: {
        type: Array,
        default: () => [],
    },
});

const search = ref('');

const filteredBusinesses = computed(() => {
    const term = search.value.trim().toLowerCase();

    if (term === '') {
        return props.businesses;
    }

    return props.businesses.filter((business) => (
        (business.name || '').toLowerCase().includes(term)
        || (business.slug || '').toLowerCase().includes(term)
        || (business.email || '').toLowerCase().includes(term)
    ));
});
</script>

<template>
    <Head title="Comercios" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold text-slate-900">Comercios</h2>
                    <p class="mt-1 text-sm text-slate-500">Alta, edicion y estado de comercios.</p>
                </div>
                <Link
                    :href="route('admin.businesses.create')"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                >
                    Nuevo comercio
                </Link>
            </div>
        </template>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-base font-semibold text-slate-900">Listado</h3>
                <input
                    v-model="search"
                    type="text"
                    class="rounded-xl border-slate-300 text-sm"
                    placeholder="Buscar por nombre, slug o email"
                >
            </div>

            <div class="mt-4 overflow-hidden rounded-xl border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-slate-500">Comercio</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-500">Admin inicial</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-500">Estado</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-500">Productos</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-500">Proveedores</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-500"></th>
                        </tr>
                    </thead>
                    <tbody v-if="filteredBusinesses.length" class="divide-y divide-slate-100">
                        <tr v-for="business in filteredBusinesses" :key="business.id">
                            <td class="px-3 py-2">
                                <p class="font-semibold text-slate-900">{{ business.name }}</p>
                                <p class="text-xs text-slate-500">{{ business.slug }}</p>
                                <p class="text-xs text-slate-500">{{ business.email || '-' }}</p>
                            </td>
                            <td class="px-3 py-2">
                                <p class="font-medium text-slate-800">{{ business.admin_user?.name || '-' }}</p>
                                <p class="text-xs text-slate-500">{{ business.admin_user?.email || '-' }}</p>
                            </td>
                            <td class="px-3 py-2">
                                <span
                                    class="rounded-full px-2 py-1 text-xs font-semibold"
                                    :class="business.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'"
                                >
                                    {{ business.is_active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="px-3 py-2">{{ business.products_count }}</td>
                            <td class="px-3 py-2">{{ business.suppliers_count }}</td>
                            <td class="px-3 py-2 text-right">
                                <Link
                                    :href="route('admin.businesses.edit', business.id)"
                                    class="rounded-lg border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                >
                                    Editar
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                    <tbody v-else>
                        <tr>
                            <td colspan="6" class="px-3 py-6 text-center text-slate-400">No se encontraron comercios.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
