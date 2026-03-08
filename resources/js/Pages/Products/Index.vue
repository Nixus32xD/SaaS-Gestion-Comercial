<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    products: { type: Object, required: true },
    filters: { type: Object, default: () => ({ search: '' }) },
});

const state = reactive({
    search: props.filters.search || '',
});

const money = (value) => new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    minimumFractionDigits: 2,
}).format(Number(value) || 0);

const filter = () => {
    router.get(route('products.index'), { search: state.search }, { preserveState: true, replace: true });
};
</script>

<template>
    <Head title="Productos" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold text-slate-900">Productos</h2>
                    <p class="mt-1 text-sm text-slate-500">Catalogo de productos por comercio.</p>
                </div>
                <Link :href="route('products.create')" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                    Nuevo producto
                </Link>
            </div>
        </template>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <input
                    v-model="state.search"
                    type="text"
                    class="rounded-xl border-slate-300 text-sm"
                    placeholder="Buscar por nombre, barcode o sku"
                    @keyup.enter="filter"
                >
                <button type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="filter">
                    Buscar
                </button>
            </div>

            <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200 app-table-wrap">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-slate-500">Producto</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-500">Codigo</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-500">Tipo</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-500">Stock</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-500">Venta</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-500"></th>
                        </tr>
                    </thead>
                    <tbody v-if="products.data.length" class="divide-y divide-slate-100">
                        <tr v-for="product in products.data" :key="product.id" :class="product.has_low_stock ? 'bg-rose-50/60' : ''">
                            <td class="px-3 py-2">
                                <p class="font-semibold text-slate-900">{{ product.name }}</p>
                                <p class="text-xs text-slate-500">{{ product.supplier || 'Sin proveedor' }}</p>
                            </td>
                            <td class="px-3 py-2 text-slate-700">{{ product.barcode || product.sku || '-' }}</td>
                            <td class="px-3 py-2">{{ product.unit_type === 'weight' ? 'Peso' : 'Unidad' }}</td>
                            <td class="px-3 py-2">
                                {{ product.stock }}
                                <span v-if="product.has_low_stock" class="ml-1 rounded bg-rose-100 px-1.5 py-0.5 text-xs font-semibold text-rose-700">Bajo</span>
                            </td>
                            <td class="px-3 py-2">{{ money(product.sale_price) }}</td>
                            <td class="px-3 py-2 text-right">
                                <Link :href="route('products.edit', product.id)" class="rounded-lg border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                    Editar
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                    <tbody v-else>
                        <tr>
                            <td colspan="6" class="px-3 py-6 text-center text-slate-400">No hay productos cargados.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="products.links?.length" class="mt-4 flex flex-wrap gap-2">
                <component
                    :is="link.url ? Link : 'span'"
                    v-for="(link, index) in products.links"
                    :key="index"
                    :href="link.url || undefined"
                    class="rounded-lg border px-3 py-1 text-xs"
                    :class="link.active ? 'border-indigo-600 bg-indigo-50 text-indigo-700' : 'border-slate-300 text-slate-600'"
                    v-html="link.label"
                />
            </div>
        </section>
    </AuthenticatedLayout>
</template>
