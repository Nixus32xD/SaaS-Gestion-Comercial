<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, reactive } from 'vue';

const PRODUCT_FILTER_STORAGE_KEY = 'products.index.filters';

const props = defineProps({
    products: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
    filters: {
        type: Object,
        default: () => ({
            search: '',
            category_id: '',
            no_price: false,
            no_cost: false,
            no_stock: false,
            with_stock: false,
            low_stock: false,
        }),
    },
});

const toBoolean = (value) => value === true || value === 1 || value === '1' || value === 'true';

const normalizeFilters = (filters = {}) => ({
    search: typeof filters.search === 'string' ? filters.search : '',
    category_id: filters.category_id ? String(filters.category_id) : '',
    no_price: toBoolean(filters.no_price),
    no_cost: toBoolean(filters.no_cost),
    no_stock: toBoolean(filters.no_stock),
    with_stock: toBoolean(filters.with_stock),
    low_stock: toBoolean(filters.low_stock),
});

const hasMeaningfulFilters = (filters = {}) => {
    const normalizedFilters = normalizeFilters(filters);

    return normalizedFilters.search.trim() !== ''
        || normalizedFilters.category_id !== ''
        || normalizedFilters.no_price
        || normalizedFilters.no_cost
        || normalizedFilters.no_stock
        || normalizedFilters.with_stock
        || normalizedFilters.low_stock;
};

const state = reactive(normalizeFilters(props.filters));

const moneyFormatter = new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    minimumFractionDigits: 2,
});

const money = (value) => moneyFormatter.format(Number(value) || 0);
const filterCardClass = (enabled) => (enabled
    ? 'border-cyan-300/40 bg-cyan-400/10'
    : 'border-cyan-100/15 bg-slate-950/35');

const currentFilters = computed(() => normalizeFilters(props.filters));
const selectedCategoryLabel = computed(() => props.categories.find(
    (category) => String(category.id) === currentFilters.value.category_id,
)?.name || '');
const hasAppliedFilters = computed(() => hasMeaningfulFilters(currentFilters.value));
const activeFilters = computed(() => {
    const filters = currentFilters.value;
    const badges = [];

    if (filters.search.trim() !== '') {
        badges.push({ key: 'search', label: `Busqueda: ${filters.search.trim()}` });
    }

    if (filters.category_id !== '') {
        badges.push({
            key: 'category_id',
            label: `Categoria: ${selectedCategoryLabel.value || `#${filters.category_id}`}`,
        });
    }

    if (filters.no_price) badges.push({ key: 'no_price', label: 'Sin precio' });
    if (filters.no_cost) badges.push({ key: 'no_cost', label: 'Sin costo' });
    if (filters.no_stock) badges.push({ key: 'no_stock', label: 'Sin stock' });
    if (filters.with_stock) badges.push({ key: 'with_stock', label: 'Con stock' });
    if (filters.low_stock) badges.push({ key: 'low_stock', label: 'Stock bajo' });

    return badges;
});

const buildParams = () => {
    const params = {};
    const search = state.search.trim();

    if (search !== '') params.search = search;
    if (state.category_id) params.category_id = state.category_id;
    if (state.no_price) params.no_price = 1;
    if (state.no_cost) params.no_cost = 1;
    if (state.no_stock) params.no_stock = 1;
    if (state.with_stock) params.with_stock = 1;
    if (state.low_stock) params.low_stock = 1;

    return params;
};

const persistAppliedFilters = (params) => {
    if (typeof window === 'undefined') return;

    if (Object.keys(params).length === 0) {
        window.localStorage.removeItem(PRODUCT_FILTER_STORAGE_KEY);
        return;
    }

    window.localStorage.setItem(PRODUCT_FILTER_STORAGE_KEY, JSON.stringify(params));
};

const filter = () => {
    const params = buildParams();

    persistAppliedFilters(params);

    router.get(route('products.index'), params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['filters', 'products'],
    });
};

const clearFilters = () => {
    Object.assign(state, normalizeFilters());
    persistAppliedFilters({});

    router.get(route('products.index'), {}, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['filters', 'products'],
    });
};

const toggleBooleanFilter = (key) => {
    if (key === 'no_stock' && state.no_stock) {
        state.with_stock = false;
    }

    if (key === 'with_stock' && state.with_stock) {
        state.no_stock = false;
    }

    filter();
};

const clearSingleFilter = (key) => {
    if (key === 'search' || key === 'category_id') {
        state[key] = '';
    } else {
        state[key] = false;
    }

    filter();
};

onMounted(() => {
    if (typeof window === 'undefined' || hasMeaningfulFilters(props.filters)) {
        return;
    }

    const storedFilters = window.localStorage.getItem(PRODUCT_FILTER_STORAGE_KEY);

    if (!storedFilters) {
        return;
    }

    try {
        const restoredFilters = normalizeFilters(JSON.parse(storedFilters));

        if (!hasMeaningfulFilters(restoredFilters)) {
            return;
        }

        Object.assign(state, restoredFilters);
        filter();
    } catch {
        window.localStorage.removeItem(PRODUCT_FILTER_STORAGE_KEY);
    }
});
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
            <div class="grid gap-3 xl:grid-cols-[minmax(0,1.5fr)_minmax(0,0.9fr)_auto]">
                <input
                    v-model="state.search"
                    type="text"
                    class="min-w-0 rounded-xl border-cyan-100/25 text-sm"
                    placeholder="Buscar por nombre, barcode o sku"
                    @keyup.enter="filter"
                >
                <select v-model="state.category_id" class="min-w-0 rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100" @change="filter">
                    <option value="">Todas las categorias</option>
                    <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                </select>
                <div class="grid grid-cols-2 gap-2 xl:flex xl:justify-end">
                    <button type="button" class="rounded-lg border border-cyan-100/25 px-3 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/70" @click="filter">
                        Buscar
                    </button>
                    <button type="button" class="rounded-lg border border-rose-300/35 px-3 py-2 text-sm font-semibold text-rose-100 hover:bg-rose-400/20" @click="clearFilters">
                        Limpiar
                    </button>
                </div>
            </div>

            <div class="mt-4 rounded-2xl border border-cyan-100/15 bg-slate-950/30 p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-100">Filtros avanzados</h3>
                        <p class="mt-1 text-xs text-slate-400">Combina estados de precio, costo y stock para detectar productos pendientes de revisar.</p>
                    </div>
                    <p class="text-sm text-slate-300">
                        <span class="font-semibold text-slate-100">{{ products.total }}</span>
                        {{ hasAppliedFilters ? 'resultados filtrados' : 'productos encontrados' }}
                    </p>
                </div>

                <div class="mt-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-5">
                    <label class="flex items-start gap-3 rounded-xl border px-4 py-3 text-sm text-slate-200 transition" :class="filterCardClass(state.no_price)">
                        <input
                            v-model="state.no_price"
                            type="checkbox"
                            class="mt-1 rounded border-cyan-100/25 bg-slate-900 text-cyan-400 focus:ring-cyan-400/50"
                            @change="toggleBooleanFilter('no_price')"
                        >
                        <span>
                            <span class="block font-semibold text-slate-100">Sin precio</span>
                            <span class="mt-1 block text-xs text-slate-400">Precio de venta en 0 o sin valor cargado.</span>
                        </span>
                    </label>

                    <label class="flex items-start gap-3 rounded-xl border px-4 py-3 text-sm text-slate-200 transition" :class="filterCardClass(state.no_cost)">
                        <input
                            v-model="state.no_cost"
                            type="checkbox"
                            class="mt-1 rounded border-cyan-100/25 bg-slate-900 text-cyan-400 focus:ring-cyan-400/50"
                            @change="toggleBooleanFilter('no_cost')"
                        >
                        <span>
                            <span class="block font-semibold text-slate-100">Sin costo</span>
                            <span class="mt-1 block text-xs text-slate-400">Costo en 0 o sin referencia de compra.</span>
                        </span>
                    </label>

                    <label class="flex items-start gap-3 rounded-xl border px-4 py-3 text-sm text-slate-200 transition" :class="filterCardClass(state.no_stock)">
                        <input
                            v-model="state.no_stock"
                            type="checkbox"
                            class="mt-1 rounded border-cyan-100/25 bg-slate-900 text-cyan-400 focus:ring-cyan-400/50"
                            @change="toggleBooleanFilter('no_stock')"
                        >
                        <span>
                            <span class="block font-semibold text-slate-100">Sin stock</span>
                            <span class="mt-1 block text-xs text-slate-400">Productos agotados o con stock cero/negativo.</span>
                        </span>
                    </label>

                    <label class="flex items-start gap-3 rounded-xl border px-4 py-3 text-sm text-slate-200 transition" :class="filterCardClass(state.with_stock)">
                        <input
                            v-model="state.with_stock"
                            type="checkbox"
                            class="mt-1 rounded border-cyan-100/25 bg-slate-900 text-cyan-400 focus:ring-cyan-400/50"
                            @change="toggleBooleanFilter('with_stock')"
                        >
                        <span>
                            <span class="block font-semibold text-slate-100">Con stock</span>
                            <span class="mt-1 block text-xs text-slate-400">Solo productos con disponibilidad positiva.</span>
                        </span>
                    </label>

                    <label class="flex items-start gap-3 rounded-xl border px-4 py-3 text-sm text-slate-200 transition" :class="filterCardClass(state.low_stock)">
                        <input
                            v-model="state.low_stock"
                            type="checkbox"
                            class="mt-1 rounded border-cyan-100/25 bg-slate-900 text-cyan-400 focus:ring-cyan-400/50"
                            @change="toggleBooleanFilter('low_stock')"
                        >
                        <span>
                            <span class="block font-semibold text-slate-100">Stock bajo</span>
                            <span class="mt-1 block text-xs text-slate-400">Productos en o por debajo del stock minimo.</span>
                        </span>
                    </label>
                </div>

                <div v-if="activeFilters.length" class="mt-4 flex flex-wrap items-center gap-2">
                    <span class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-100/70">Activos</span>
                    <button
                        v-for="activeFilter in activeFilters"
                        :key="activeFilter.key"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-full border border-cyan-300/30 bg-cyan-400/10 px-3 py-1 text-xs font-semibold text-cyan-50 transition hover:bg-cyan-400/20"
                        @click="clearSingleFilter(activeFilter.key)"
                    >
                        {{ activeFilter.label }}
                        <span aria-hidden="true" class="text-cyan-100/70">x</span>
                    </button>
                </div>
            </div>

            <div class="mt-4 grid gap-3 md:hidden">
                <article v-for="product in products.data" :key="product.id" class="rounded-xl border border-cyan-100/20 bg-slate-950/35 p-4 text-sm text-slate-300">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-semibold text-slate-100">{{ product.name }}</p>
                            <p class="mt-1 text-xs text-cyan-100/80">{{ product.category || 'Sin categoria' }}</p>
                            <p class="text-xs text-slate-400">{{ product.supplier || 'Sin proveedor' }}</p>
                        </div>
                        <Link :href="route('products.edit', product.id)" class="shrink-0 rounded-lg border border-cyan-100/25 px-3 py-1 text-xs font-semibold text-slate-300 hover:bg-slate-800/70">
                            Editar
                        </Link>
                    </div>

                    <div class="mt-3 grid gap-2 text-xs text-slate-400">
                        <p>Codigo: <span class="text-slate-200">{{ product.barcode || product.sku || '-' }}</span></p>
                        <p>Tipo: <span class="text-slate-200">{{ product.type_label }}</span></p>
                        <p>Stock: <span class="text-slate-200">{{ product.stock }} {{ product.quantity_label }}</span></p>
                        <p>Venta: <span class="text-slate-200">{{ money(product.sale_price) }} {{ product.price_label }}</span></p>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-1">
                        <span v-if="Number(product.sale_price) <= 0" class="rounded-full border border-amber-300/35 bg-amber-400/15 px-2 py-0.5 text-[11px] font-semibold text-amber-100">
                            Sin precio
                        </span>
                        <span v-if="Number(product.cost_price) <= 0" class="rounded-full border border-fuchsia-300/35 bg-fuchsia-400/15 px-2 py-0.5 text-[11px] font-semibold text-fuchsia-100">
                            Sin costo
                        </span>
                        <span v-if="Number(product.stock) <= 0" class="rounded-full border border-rose-300/40 bg-rose-400/15 px-2 py-0.5 text-[11px] font-semibold text-rose-100">
                            Sin stock
                        </span>
                        <span v-else-if="product.has_low_stock" class="rounded-full border border-amber-300/40 bg-amber-400/15 px-2 py-0.5 text-[11px] font-semibold text-amber-100">
                            Stock bajo
                        </span>
                    </div>
                </article>
            </div>

            <div class="mt-4 hidden overflow-x-auto rounded-xl border border-cyan-100/20 app-table-wrap md:block">
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
                                <div class="mt-2 flex flex-wrap gap-1">
                                    <span v-if="Number(product.sale_price) <= 0" class="rounded-full border border-amber-300/35 bg-amber-400/15 px-2 py-0.5 text-[11px] font-semibold text-amber-100">
                                        Sin precio
                                    </span>
                                    <span v-if="Number(product.cost_price) <= 0" class="rounded-full border border-fuchsia-300/35 bg-fuchsia-400/15 px-2 py-0.5 text-[11px] font-semibold text-fuchsia-100">
                                        Sin costo
                                    </span>
                                </div>
                            </td>
                            <td class="px-3 py-2 text-slate-300">{{ product.barcode || product.sku || '-' }}</td>
                            <td class="px-3 py-2">{{ product.type_label }}</td>
                            <td class="px-3 py-2">
                                {{ product.stock }} {{ product.quantity_label }}
                                <span v-if="Number(product.stock) <= 0" class="ml-1 rounded border border-rose-300/40 bg-rose-400/15 px-1.5 py-0.5 text-xs font-semibold text-rose-100">Sin stock</span>
                                <span v-else-if="product.has_low_stock" class="ml-1 rounded border border-amber-300/40 bg-amber-400/15 px-1.5 py-0.5 text-xs font-semibold text-amber-100">Bajo</span>
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
                            <td colspan="6" class="px-3 py-6 text-center text-slate-400">
                                {{ hasAppliedFilters ? 'No hay productos que coincidan con los filtros actuales.' : 'No hay productos cargados.' }}
                            </td>
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
