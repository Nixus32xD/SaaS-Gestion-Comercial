<script setup>
import { computed, reactive } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    units: {
        type: Array,
        default: () => [],
    },
    products: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();

const state = reactive({
    search: '',
});

const form = useForm({
    name: '',
    sku: '',
    unit: props.units[0] ?? 'unidad',
    base_price: '',
    minimum: 0,
});

const filteredProducts = computed(() => {
    const term = state.search.trim().toLowerCase();

    if (term === '') {
        return props.products;
    }

    return props.products.filter((product) => (
        product.name.toLowerCase().includes(term)
        || (product.sku ?? '').toLowerCase().includes(term)
    ));
});

const formatMoney = (value) => new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    minimumFractionDigits: 2,
}).format(Number(value) || 0);

const submit = () => {
    form.post(route('products.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('name', 'sku', 'base_price', 'minimum');
            form.unit = props.units[0] ?? 'unidad';
        },
    });
};
</script>

<template>
    <Head title="Productos" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold leading-tight text-slate-900">Productos</h2>
                    <p class="mt-1 text-sm text-slate-500">Alta de productos y base para POS, compras y stock.</p>
                </div>
            </div>
        </template>

        <div class="grid gap-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-base font-semibold text-slate-900">Nuevo producto</h3>
                <form class="mt-4 grid gap-3 md:grid-cols-5" @submit.prevent="submit">
                    <input
                        v-model="form.name"
                        type="text"
                        class="rounded-xl border-slate-300 text-sm md:col-span-2"
                        placeholder="Nombre del producto"
                    >
                    <input
                        v-model="form.sku"
                        type="text"
                        class="rounded-xl border-slate-300 text-sm"
                        placeholder="SKU / Codigo"
                    >
                    <select v-model="form.unit" class="rounded-xl border-slate-300 text-sm">
                        <option v-for="unit in units" :key="unit" :value="unit">
                            {{ unit }}
                        </option>
                    </select>
                    <input
                        v-model.number="form.base_price"
                        type="number"
                        min="0"
                        step="0.01"
                        class="rounded-xl border-slate-300 text-sm"
                        placeholder="Precio base"
                    >
                    <input
                        v-model.number="form.minimum"
                        type="number"
                        min="0"
                        step="0.001"
                        class="rounded-xl border-slate-300 text-sm"
                        placeholder="Stock minimo"
                    >
                    <button
                        type="submit"
                        class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-600 disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        Guardar producto
                    </button>
                </form>

                <p
                    v-if="page.props.flash?.success || page.props.flash?.error"
                    class="mt-3 rounded-lg px-3 py-2 text-sm"
                    :class="page.props.flash?.error ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700'"
                >
                    {{ page.props.flash?.error ?? page.props.flash?.success }}
                </p>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-base font-semibold text-slate-900">Catalogo del comercio</h3>
                    <input
                        v-model="state.search"
                        type="text"
                        class="rounded-xl border-slate-300 text-sm"
                        placeholder="Buscar por nombre o SKU"
                    >
                </div>

                <div class="mt-4 overflow-hidden rounded-xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Nombre</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">SKU</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Unidad</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Precio</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Stock</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Minimo</th>
                            </tr>
                        </thead>
                        <tbody v-if="filteredProducts.length" class="divide-y divide-slate-100 bg-white">
                            <tr v-for="product in filteredProducts" :key="product.id">
                                <td class="px-3 py-2 font-medium text-slate-800">{{ product.name }}</td>
                                <td class="px-3 py-2">{{ product.sku || '-' }}</td>
                                <td class="px-3 py-2">{{ product.unit }}</td>
                                <td class="px-3 py-2">{{ formatMoney(product.base_price) }}</td>
                                <td class="px-3 py-2">{{ product.stock }}</td>
                                <td class="px-3 py-2">{{ product.minimum }}</td>
                            </tr>
                        </tbody>
                        <tbody v-else>
                            <tr>
                                <td colspan="6" class="px-3 py-6 text-center text-slate-400">
                                    No hay productos cargados con ese filtro.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
