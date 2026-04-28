<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, reactive } from 'vue';

const PRODUCT_FILTER_STORAGE_KEY = 'products.index.filters';

const filterCards = [
    {
        key: 'no_price',
        title: 'Sin precio',
        description: 'Precio de venta en 0 o sin valor cargado.',
    },
    {
        key: 'no_cost',
        title: 'Sin costo',
        description: 'Costo en 0 o sin referencia de compra.',
    },
    {
        key: 'no_stock',
        title: 'Sin stock',
        description: 'Productos agotados o con stock cero/negativo.',
    },
    {
        key: 'with_stock',
        title: 'Con stock',
        description: 'Solo productos con disponibilidad positiva.',
    },
    {
        key: 'low_stock',
        title: 'Stock bajo',
        description: 'Productos en o por debajo del stock minimo.',
    },
    {
        key: 'expired_batches',
        title: 'Lotes vencidos',
        description: 'Con stock en lotes ya vencidos para revisar urgente.',
    },
    {
        key: 'upcoming_batches',
        title: 'Por vencer',
        description: 'Lotes dentro de la ventana de alerta del producto.',
    },
    {
        key: 'valid_batches',
        title: 'Lotes vigentes',
        description: 'Con stock en lotes fuera del rango de alerta.',
    },
    {
        key: 'no_expiration_batches',
        title: 'Sin vencimiento',
        description: 'Lotes cargados sin fecha de vencimiento.',
    },
];

const booleanFilterKeys = filterCards.map((filterCard) => filterCard.key);
const reviewBadgeKeys = ['expired_batches', 'upcoming_batches', 'valid_batches', 'no_expiration_batches'];

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
            expired_batches: false,
            upcoming_batches: false,
            valid_batches: false,
            no_expiration_batches: false,
        }),
    },
});

const toBoolean = (value) => value === true || value === 1 || value === '1' || value === 'true';

const normalizeFilters = (filters = {}) => ({
    search: typeof filters.search === 'string' ? filters.search : '',
    category_id: filters.category_id ? String(filters.category_id) : '',
    ...Object.fromEntries(booleanFilterKeys.map((key) => [key, toBoolean(filters[key])])),
});

const hasMeaningfulFilters = (filters = {}) => {
    const normalizedFilters = normalizeFilters(filters);

    return normalizedFilters.search.trim() !== ''
        || normalizedFilters.category_id !== ''
        || booleanFilterKeys.some((key) => normalizedFilters[key]);
};

const state = reactive(normalizeFilters(props.filters));

const moneyFormatter = new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    minimumFractionDigits: 2,
});

const badgeClassByType = {
    no_price: 'rounded-full border border-amber-300/35 bg-amber-400/15 px-2 py-0.5 text-[11px] font-semibold text-amber-100',
    no_cost: 'rounded-full border border-fuchsia-300/35 bg-fuchsia-400/15 px-2 py-0.5 text-[11px] font-semibold text-fuchsia-100',
    no_stock: 'rounded-full border border-rose-300/40 bg-rose-400/15 px-2 py-0.5 text-[11px] font-semibold text-rose-100',
    low_stock: 'rounded-full border border-amber-300/40 bg-amber-400/15 px-2 py-0.5 text-[11px] font-semibold text-amber-100',
    expired_batches: 'rounded-full border border-rose-300/40 bg-rose-400/15 px-2 py-0.5 text-[11px] font-semibold text-rose-100',
    upcoming_batches: 'rounded-full border border-amber-300/40 bg-amber-400/15 px-2 py-0.5 text-[11px] font-semibold text-amber-100',
    valid_batches: 'rounded-full border border-emerald-300/35 bg-emerald-400/15 px-2 py-0.5 text-[11px] font-semibold text-emerald-100',
    no_expiration_batches: 'rounded-full border border-sky-300/35 bg-sky-400/15 px-2 py-0.5 text-[11px] font-semibold text-sky-100',
};

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

    filterCards.forEach((filterCard) => {
        if (filters[filterCard.key]) {
            badges.push({ key: filterCard.key, label: filterCard.title });
        }
    });

    return badges;
});

const buildParams = () => {
    const params = {};
    const search = state.search.trim();

    if (search !== '') params.search = search;
    if (state.category_id) params.category_id = state.category_id;

    booleanFilterKeys.forEach((key) => {
        if (state[key]) {
            params[key] = 1;
        }
    });

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

const pluralize = (count, singular, plural) => (Number(count) === 1 ? singular : plural);

const productStatusBadges = (product) => {
    const badges = [];

    if (Number(product.sale_price) <= 0) {
        badges.push({ key: 'no_price', label: 'Sin precio', class: badgeClassByType.no_price });
    }

    if (Number(product.cost_price) <= 0) {
        badges.push({ key: 'no_cost', label: 'Sin costo', class: badgeClassByType.no_cost });
    }

    if (Number(product.stock) <= 0) {
        badges.push({ key: 'no_stock', label: 'Sin stock', class: badgeClassByType.no_stock });
    } else if (product.has_low_stock) {
        badges.push({ key: 'low_stock', label: 'Stock bajo', class: badgeClassByType.low_stock });
    }

    if (Number(product.expired_batches_count) > 0) {
        badges.push({
            key: 'expired_batches',
            label: `${product.expired_batches_count} ${pluralize(product.expired_batches_count, 'lote vencido', 'lotes vencidos')}`,
            class: badgeClassByType.expired_batches,
        });
    }

    if (Number(product.upcoming_batches_count) > 0) {
        badges.push({
            key: 'upcoming_batches',
            label: `${product.upcoming_batches_count} ${pluralize(product.upcoming_batches_count, 'lote por vencer', 'lotes por vencer')}`,
            class: badgeClassByType.upcoming_batches,
        });
    }

    if (Number(product.valid_batches_count) > 0) {
        badges.push({
            key: 'valid_batches',
            label: `${product.valid_batches_count} ${pluralize(product.valid_batches_count, 'lote vigente', 'lotes vigentes')}`,
            class: badgeClassByType.valid_batches,
        });
    }

    if (Number(product.no_expiration_batches_count) > 0) {
        badges.push({
            key: 'no_expiration_batches',
            label: `${product.no_expiration_batches_count} ${pluralize(product.no_expiration_batches_count, 'lote sin vencimiento', 'lotes sin vencimiento')}`,
            class: badgeClassByType.no_expiration_batches,
        });
    }

    return badges;
};

const pricingBadges = (product) => productStatusBadges(product).filter((badge) => ['no_price', 'no_cost'].includes(badge.key));
const reviewBadges = (product) => productStatusBadges(product).filter((badge) => reviewBadgeKeys.includes(badge.key));

const productRowClass = (product) => {
    if (Number(product.expired_batches_count) > 0) {
        return 'bg-rose-400/10';
    }

    if (Number(product.upcoming_batches_count) > 0) {
        return 'bg-amber-400/10';
    }

    if (product.has_low_stock) {
        return 'bg-rose-400/5';
    }

    return '';
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
                        <p class="mt-1 text-xs text-slate-400">Combina estados de precio, costo, stock y vencimientos para detectar productos que necesitan revision operativa.</p>
                    </div>
                    <p class="text-sm text-slate-300">
                        <span class="font-semibold text-slate-100">{{ products.total }}</span>
                        {{ hasAppliedFilters ? 'resultados filtrados' : 'productos encontrados' }}
                    </p>
                </div>

                <div class="mt-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                    <label
                        v-for="filterCard in filterCards"
                        :key="filterCard.key"
                        class="flex items-start gap-3 rounded-xl border px-4 py-3 text-sm text-slate-200 transition"
                        :class="filterCardClass(state[filterCard.key])"
                    >
                        <input
                            v-model="state[filterCard.key]"
                            type="checkbox"
                            class="mt-1 rounded border-cyan-100/25 bg-slate-900 text-cyan-400 focus:ring-cyan-400/50"
                            @change="toggleBooleanFilter(filterCard.key)"
                        >
                        <span>
                            <span class="block font-semibold text-slate-100">{{ filterCard.title }}</span>
                            <span class="mt-1 block text-xs text-slate-400">{{ filterCard.description }}</span>
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
                        <span
                            v-for="badge in productStatusBadges(product)"
                            :key="badge.key"
                            :class="badge.class"
                        >
                            {{ badge.label }}
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
                            <th class="px-3 py-2 text-left font-medium text-slate-300/80">Revision</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-300/80">Venta</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-300/80"></th>
                        </tr>
                    </thead>
                    <tbody v-if="products.data.length" class="divide-y divide-slate-100">
                        <tr v-for="product in products.data" :key="product.id" :class="productRowClass(product)">
                            <td class="px-3 py-2">
                                <p class="font-semibold text-slate-100">{{ product.name }}</p>
                                <p class="text-xs text-cyan-100/80">{{ product.category || 'Sin categoria' }}</p>
                                <p class="text-xs text-slate-300/80">{{ product.supplier || 'Sin proveedor' }}</p>
                                <div class="mt-2 flex flex-wrap gap-1">
                                    <span
                                        v-for="badge in pricingBadges(product)"
                                        :key="badge.key"
                                        :class="badge.class"
                                    >
                                        {{ badge.label }}
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
                            <td class="px-3 py-2">
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="badge in reviewBadges(product)"
                                        :key="badge.key"
                                        :class="badge.class"
                                    >
                                        {{ badge.label }}
                                    </span>
                                    <span
                                        v-if="reviewBadges(product).length === 0"
                                        class="rounded-full border border-slate-300/15 bg-slate-900/35 px-2 py-0.5 text-[11px] font-semibold text-slate-400"
                                    >
                                        Sin lotes revisables
                                    </span>
                                </div>
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
                            <td colspan="7" class="px-3 py-6 text-center text-slate-400">
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
