<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    products: { type: Array, default: () => [] },
});

const state = reactive({
    search: '',
    quantity: 1,
    highlightedIndex: 0,
    activeProductId: null,
    helperMessage: 'Busca por nombre, codigo de barras o SKU. Presiona Enter para agregar.',
});

const searchInput = ref(null);
const quantityInput = ref(null);

const nowLocalDateTime = () => {
    const date = new Date();
    const offsetMinutes = date.getTimezoneOffset();
    const localDate = new Date(date.getTime() - (offsetMinutes * 60000));

    return localDate.toISOString().slice(0, 16);
};

const form = useForm({
    sold_at: nowLocalDateTime(),
    discount: 0,
    notes: '',
    items: [],
});

const normalize = (value) => String(value || '').trim().toLowerCase();

const filteredProducts = computed(() => {
    const term = normalize(state.search);

    if (term === '') {
        return props.products.slice(0, 20);
    }

    return props.products
        .filter((product) => (
            normalize(product.name).includes(term)
            || normalize(product.barcode).includes(term)
            || normalize(product.sku).includes(term)
        ))
        .slice(0, 30);
});

const activeProduct = computed(() => {
    if (state.activeProductId !== null) {
        const direct = filteredProducts.value.find((product) => product.id === state.activeProductId);
        if (direct) return direct;
    }

    return filteredProducts.value[state.highlightedIndex] || null;
});

const findExactCodeMatch = (term) => {
    const normalized = normalize(term);
    if (normalized === '') return null;

    return props.products.find((product) => (
        normalize(product.barcode) === normalized || normalize(product.sku) === normalized
    )) || null;
};

const syncSelection = () => {
    if (!filteredProducts.value.length) {
        state.highlightedIndex = 0;
        state.activeProductId = null;
        return;
    }

    if (!filteredProducts.value.find((product) => product.id === state.activeProductId)) {
        state.highlightedIndex = 0;
        state.activeProductId = filteredProducts.value[0].id;
        return;
    }

    const activeIndex = filteredProducts.value.findIndex((product) => product.id === state.activeProductId);
    state.highlightedIndex = activeIndex >= 0 ? activeIndex : 0;
};

watch(() => state.search, () => {
    syncSelection();
});

watch(filteredProducts, () => {
    syncSelection();
});

const addProductToCart = (product, source = 'manual') => {
    if (!product) return;

    const qty = Number(state.quantity || 0);
    if (qty <= 0) {
        state.helperMessage = 'La cantidad debe ser mayor a 0.';
        return;
    }

    const existing = form.items.find((item) => item.product_id === product.id);

    if (existing) {
        existing.quantity = Number((Number(existing.quantity) + qty).toFixed(3));
    } else {
        form.items.push({
            product_id: product.id,
            product_name: product.name,
            quantity: Number(qty.toFixed(3)),
            unit_price: Number(product.sale_price),
        });
    }

    state.quantity = 1;
    state.search = '';
    state.activeProductId = null;
    state.highlightedIndex = 0;
    state.helperMessage = source === 'scanner'
        ? `Producto agregado por codigo: ${product.name}`
        : `Producto agregado: ${product.name}`;

    nextTick(() => {
        searchInput.value?.focus();
    });
};

const handleSearchEnter = () => {
    const exact = findExactCodeMatch(state.search);

    if (exact) {
        addProductToCart(exact, 'scanner');
        return;
    }

    addProductToCart(activeProduct.value, 'manual');
};

const handleSearchKeydown = (event) => {
    if (event.key === 'ArrowDown') {
        event.preventDefault();

        if (!filteredProducts.value.length) return;

        state.highlightedIndex = (state.highlightedIndex + 1) % filteredProducts.value.length;
        state.activeProductId = filteredProducts.value[state.highlightedIndex].id;
        return;
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();

        if (!filteredProducts.value.length) return;

        state.highlightedIndex = (state.highlightedIndex - 1 + filteredProducts.value.length) % filteredProducts.value.length;
        state.activeProductId = filteredProducts.value[state.highlightedIndex].id;
        return;
    }

    if (event.key === 'Enter') {
        event.preventDefault();
        handleSearchEnter();
        return;
    }

    if (event.key === 'Escape') {
        event.preventDefault();
        state.search = '';
        state.helperMessage = 'Busqueda limpiada.';
    }
};

const selectProduct = (product) => {
    state.activeProductId = product.id;
    const index = filteredProducts.value.findIndex((item) => item.id === product.id);
    state.highlightedIndex = index >= 0 ? index : 0;
};

const removeItem = (index) => {
    form.items.splice(index, 1);
};

const subtotal = computed(() => form.items.reduce((acc, item) => acc + (Number(item.quantity) * Number(item.unit_price)), 0));
const total = computed(() => Math.max(0, subtotal.value - Number(form.discount || 0)));

const money = (value) => new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    minimumFractionDigits: 2,
}).format(Number(value) || 0);

const submit = () => {
    form.post(route('sales.store'));
};

const handleGlobalShortcuts = (event) => {
    if (event.key === 'F2') {
        event.preventDefault();
        searchInput.value?.focus();
        return;
    }

    if (event.key === 'F4') {
        event.preventDefault();
        quantityInput.value?.focus();
        return;
    }

    if (event.altKey && event.key.toLowerCase() === 'a') {
        event.preventDefault();
        addProductToCart(activeProduct.value, 'manual');
        return;
    }

    if (event.ctrlKey && event.key === 'Enter') {
        event.preventDefault();
        if (form.items.length && !form.processing) {
            submit();
        }
    }
};

onMounted(() => {
    syncSelection();
    window.addEventListener('keydown', handleGlobalShortcuts);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleGlobalShortcuts);
});
</script>

<template>
    <Head title="Nueva venta" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold text-slate-900">Nueva venta</h2>
                    <p class="mt-1 text-sm text-slate-500">Carga rapida por nombre o lector de codigo.</p>
                </div>
                <Link :href="route('sales.index')" class="text-sm font-semibold text-slate-600 hover:text-slate-900">Volver</Link>
            </div>
        </template>

        <form class="grid gap-6" @submit.prevent="submit">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-3 text-xs text-indigo-800">
                    <p class="font-semibold">Atajos</p>
                    <p>F2: foco en buscador | F4: foco en cantidad | Alt+A: agregar producto | Ctrl+Enter: confirmar venta | Esc: limpiar busqueda</p>
                </div>

                <div class="mt-4 grid gap-3 md:grid-cols-4">
                    <div class="md:col-span-2">
                        <label for="product-search" class="mb-1 block text-sm font-medium text-slate-700">Producto (nombre, barcode o SKU)</label>
                        <input
                            id="product-search"
                            ref="searchInput"
                            v-model="state.search"
                            type="text"
                            class="w-full rounded-xl border-slate-300 text-sm"
                            placeholder="Escanear codigo o escribir nombre y Enter"
                            aria-controls="product-results"
                            aria-autocomplete="list"
                            role="combobox"
                            :aria-expanded="filteredProducts.length ? 'true' : 'false'"
                            @keydown="handleSearchKeydown"
                        >
                    </div>
                    <div>
                        <label for="product-qty" class="mb-1 block text-sm font-medium text-slate-700">Cantidad</label>
                        <input
                            id="product-qty"
                            ref="quantityInput"
                            v-model.number="state.quantity"
                            type="number"
                            min="0.001"
                            step="0.001"
                            class="w-full rounded-xl border-slate-300 text-sm"
                        >
                    </div>
                    <div class="flex items-end">
                        <button
                            type="button"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            @click="addProductToCart(activeProduct, 'manual')"
                        >
                            Agregar
                        </button>
                    </div>
                </div>

                <p class="mt-2 text-xs text-slate-500" aria-live="polite">{{ state.helperMessage }}</p>

                <div id="product-results" class="mt-3 max-h-64 overflow-auto rounded-xl border border-slate-200" role="listbox" aria-label="Resultados de productos">
                    <ul v-if="filteredProducts.length" class="divide-y divide-slate-100 text-sm">
                        <li v-for="(product, index) in filteredProducts" :key="product.id">
                            <button
                                type="button"
                                class="flex w-full items-center justify-between px-3 py-2 text-left hover:bg-slate-50"
                                :class="product.id === activeProductId || index === state.highlightedIndex ? 'bg-indigo-50' : ''"
                                role="option"
                                :aria-selected="product.id === activeProductId || index === state.highlightedIndex ? 'true' : 'false'"
                                @click="selectProduct(product)"
                                @dblclick="addProductToCart(product, 'manual')"
                            >
                                <span>
                                    <span class="font-semibold text-slate-900">{{ product.name }}</span>
                                    <span class="ml-2 text-xs text-slate-500">{{ product.barcode || product.sku || 'sin codigo' }}</span>
                                </span>
                                <span class="text-xs text-slate-600">stock {{ product.stock }} - {{ money(product.sale_price) }}</span>
                            </button>
                        </li>
                    </ul>
                    <p v-else class="px-3 py-4 text-sm text-slate-400">Sin resultados para la busqueda actual.</p>
                </div>

                <div class="mt-4 overflow-hidden rounded-xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Producto</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Cantidad</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Precio</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Subtotal</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-500"></th>
                            </tr>
                        </thead>
                        <tbody v-if="form.items.length" class="divide-y divide-slate-100">
                            <tr v-for="(item, index) in form.items" :key="`${item.product_id}-${index}`">
                                <td class="px-3 py-2 font-semibold text-slate-900">{{ item.product_name }}</td>
                                <td class="px-3 py-2">{{ item.quantity }}</td>
                                <td class="px-3 py-2">{{ money(item.unit_price) }}</td>
                                <td class="px-3 py-2">{{ money(Number(item.quantity) * Number(item.unit_price)) }}</td>
                                <td class="px-3 py-2 text-right">
                                    <button type="button" class="rounded-lg border border-rose-300 px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50" @click="removeItem(index)">Quitar</button>
                                </td>
                            </tr>
                        </tbody>
                        <tbody v-else>
                            <tr>
                                <td colspan="5" class="px-3 py-5 text-center text-slate-400">Agrega productos para continuar.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="grid gap-3 md:grid-cols-3">
                    <div>
                        <label for="sold-at" class="mb-1 block text-sm font-medium text-slate-700">Fecha y hora</label>
                        <input id="sold-at" v-model="form.sold_at" type="datetime-local" class="w-full rounded-xl border-slate-300 text-sm" />
                    </div>
                    <div>
                        <label for="discount" class="mb-1 block text-sm font-medium text-slate-700">Descuento</label>
                        <input id="discount" v-model.number="form.discount" type="number" min="0" step="0.01" class="w-full rounded-xl border-slate-300 text-sm" placeholder="0.00" />
                    </div>
                    <div>
                        <label for="notes" class="mb-1 block text-sm font-medium text-slate-700">Notas</label>
                        <input id="notes" v-model="form.notes" type="text" class="w-full rounded-xl border-slate-300 text-sm" placeholder="Observaciones" />
                    </div>
                </div>
                <div class="mt-4 rounded-xl bg-slate-50 p-3 text-sm text-slate-700">
                    <p>Subtotal: <strong>{{ money(subtotal) }}</strong></p>
                    <p>Total: <strong>{{ money(total) }}</strong></p>
                </div>
                <div class="mt-4 flex justify-end">
                    <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-600" :disabled="form.processing || !form.items.length">
                        Confirmar venta
                    </button>
                </div>
            </section>
        </form>
    </AuthenticatedLayout>
</template>
