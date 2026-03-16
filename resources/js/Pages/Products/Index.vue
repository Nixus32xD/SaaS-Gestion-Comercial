<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    products: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ search: '', category_id: '' }) },
});

const state = reactive({
    search: props.filters.search || '',
    category_id: props.filters.category_id || '',
});

const money = (value) => new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    minimumFractionDigits: 2,
}).format(Number(value) || 0);

const filter = () => {
    router.get(route('products.index'), {
        search: state.search,
        category_id: state.category_id || undefined,
    }, { preserveState: true, replace: true });
};
</script>

<template>
    <Head title="Productos" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold text-slate-100">Productos</h2>
                    <p class="mt-1 text-sm text-slate-300/80">Catalogo de productos por comercio.</p>
                </div>
                <Link :href="route('products.create')" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                    Nuevo producto
                </Link>
            </div>
        </template>

        <section class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 backdrop-blur p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <input
                    v-model="state.search"
                    type="text"
                    class="rounded-xl border-cyan-100/25 text-sm"
                    placeholder="Buscar por nombre, barcode o sku"
                    @keyup.enter="filter"
                >
                <select v-model="state.category_id" class="rounded-xl border-cyan-100/25 text-sm" @change="filter">
                    <option value="">Todas las categorias</option>
                    <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                </select>
                <button type="button" class="rounded-lg border border-cyan-100/25 px-3 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/70" @click="filter">
                    Buscar
                </button>
            </div>

            <div class="mt-4 overflow-x-auto rounded-xl border border-cyan-100/20 app-table-wrap">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-950/35">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-slate-300/80">Producto</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-300/80">Codigo</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-300/80">Tipo</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-300/80">Stock</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-300/80">Venta</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-300/80"></th>
                        </tr>
                    </thead>
                    <tbody v-if="products.data.length" class="divide-y divide-slate-100">
                        <tr v-for="product in products.data" :key="product.id" :class="product.has_low_stock ? 'bg-rose-400/10' : ''">
                            <td class="px-3 py-2">
                                <p class="font-semibold text-slate-100">{{ product.name }}</p>
                                <p class="text-xs text-cyan-100/80">{{ product.category || 'Sin categoria' }}</p>
                                <p class="text-xs text-slate-300/80">{{ product.supplier || 'Sin proveedor' }}</p>
                            </td>
                            <td class="px-3 py-2 text-slate-300">{{ product.barcode || product.sku || '-' }}</td>
                            <td class="px-3 py-2">{{ product.type_label }}</td>
                            <td class="px-3 py-2">
                                {{ product.stock }} {{ product.quantity_label }}
                                <span v-if="product.has_low_stock" class="ml-1 rounded border border-rose-300/40 bg-rose-400/15 px-1.5 py-0.5 text-xs font-semibold text-rose-100">Bajo</span>
                            </td>
                            <td class="px-3 py-2">{{ money(product.sale_price) }} <span class="text-xs text-slate-400">{{ product.price_label }}</span></td>
                            <td class="px-3 py-2 text-right">
                                <Link :href="route('products.edit', product.id)" class="rounded-lg border border-cyan-100/25 px-3 py-1 text-xs font-semibold text-slate-300 hover:bg-slate-800/70">
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
                    :class="link.active ? 'border-cyan-300/50 bg-cyan-400/15 text-cyan-100' : 'border-cyan-100/25 text-slate-300'"
                    v-html="link.label"
                />
            </div>
        </section>
    </AuthenticatedLayout>
</template>

