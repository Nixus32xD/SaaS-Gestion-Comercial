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
const searchResults = ref([...props.products]);
const isLoadingProducts = ref(false);
let searchTimer = null;
let lastSearchRequestId = 0;

const nowLocalDateTime = () => {
    const date = new Date();
    const offsetMinutes = date.getTimezoneOffset();
    const localDate = new Date(date.getTime() - (offsetMinutes * 60000));

    return localDate.toISOString().slice(0, 16);
};

const form = useForm({
    payment_method: 'cash',
    amount_received: '',
    sold_at: nowLocalDateTime(),
    discount: 0,
    notes: '',
    items: [],
});

const normalize = (value) => String(value || '').trim().toLowerCase();

const filteredProducts = computed(() => searchResults.value);

const activeProduct = computed(() => {
    if (state.activeProductId !== null) {
        const direct = filteredProducts.value.find((product) => product.id === state.activeProductId);
        if (direct) return direct;
    }

    return filteredProducts.value[state.highlightedIndex] || null;
});

const findExactCodeMatch = (term, products = searchResults.value) => {
    const normalized = normalize(term);
    if (normalized === '') return null;

    return products.find((product) => (
        normalize(product.barcode) === normalized || normalize(product.sku) === normalized
    )) || null;
};

const fetchProducts = async (term = state.search) => {
    const requestId = ++lastSearchRequestId;
    isLoadingProducts.value = true;

    try {
        const { data } = await window.axios.get(route('sales.products.search'), {
            params: { search: String(term || '').trim() },
        });

        if (requestId !== lastSearchRequestId) {
            return searchResults.value;
        }

        searchResults.value = Array.isArray(data?.products) ? data.products : [];
        searchResults.value.forEach((product) => {
            if (!props.products.find((entry) => entry.id === product.id)) {
                props.products.push(product);
            }
        });
        syncSelection();

        return searchResults.value;
    } catch (error) {
        if (requestId === lastSearchRequestId) {
            state.helperMessage = 'No se pudieron cargar productos en este momento.';
        }

        return searchResults.value;
    } finally {
        if (requestId === lastSearchRequestId) {
            isLoadingProducts.value = false;
        }
    }
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

watch(() => state.search, (value) => {
    if (searchTimer !== null) {
        window.clearTimeout(searchTimer);
    }

    searchTimer = window.setTimeout(() => {
        void fetchProducts(value);
    }, value.trim() === '' ? 0 : 180);
});

watch(filteredProducts, () => {
    syncSelection();
});

const addProductToCart = (product, source = 'manual') => {
    if (!product) {
        state.helperMessage = 'No hay productos disponibles para agregar con esa busqueda.';
        return;
    }

    const qty = Number(state.quantity || 0);
    if (qty <= 0) {
        state.helperMessage = 'La cantidad debe ser mayor a 0.';
        return;
    }

    const existing = form.items.find((item) => item.product_id === product.id);

    if (existing) {
        existing.quantity = Number((Number(existing.quantity) + qty).toFixed(3));
    } else {
        const meta = getProductMeta(product);

        form.items.push({
            product_id: product.id,
            product_name: product.name,
            quantity: Number(qty.toFixed(3)),
            unit_price: Number(product.sale_price),
            unit_type: product.unit_type,
            weight_unit: product.weight_unit,
            quantity_label: meta.quantityLabel,
            price_label: meta.priceLabel,
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

const handleSearchEnter = async () => {
    const results = await fetchProducts(state.search);
    const exact = findExactCodeMatch(state.search, results);

    if (exact) {
        addProductToCart(exact, 'scanner');
        return;
    }

    const fallback = results.find((product) => product.id === state.activeProductId)
        || results[state.highlightedIndex]
        || null;

    addProductToCart(fallback, 'manual');
};

const handleSearchKeydown = (event) => {
    if (event.ctrlKey && event.key === 'Enter') {
        event.preventDefault();
        submitIfReady();
        return;
    }

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
        void handleSearchEnter();
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

const getProductMeta = (product) => ({
    quantityLabel: product?.quantity_label || 'un',
    priceLabel: product?.price_label || 'por unidad',
    quantityStep: product?.quantity_step || '1',
    quantityMin: product?.quantity_min || '1',
    isGrams: product?.unit_type === 'weight' && product?.weight_unit === 'g',
});

const getDisplayedStock = (product) => {
    const meta = getProductMeta(product);

    if (meta.isGrams) {
        return {
            value: Number((Number(product?.stock || 0) / 1000).toFixed(3)),
            label: 'kg',
        };
    }

    return {
        value: Number(product?.stock || 0),
        label: meta.quantityLabel,
    };
};

const activeProductMeta = computed(() => getProductMeta(activeProduct.value));

const getLineSubtotal = (item) => {
    const meta = getProductMeta(item);

    if (meta.isGrams) {
        return Number((((Number(item.quantity) * Number(item.unit_price)) / 100)).toFixed(2));
    }

    return Number((Number(item.quantity) * Number(item.unit_price)).toFixed(2));
};

const subtotal = computed(() => form.items.reduce((acc, item) => acc + getLineSubtotal(item), 0));
const total = computed(() => Math.max(0, subtotal.value - Number(form.discount || 0)));
const isCashPayment = computed(() => form.payment_method === 'cash');
const amountReceived = computed(() => Number(form.amount_received || 0));
const remaining = computed(() => (
    isCashPayment.value
        ? Math.max(0, Number((total.value - amountReceived.value).toFixed(2)))
        : 0
));
const changeAmount = computed(() => (
    isCashPayment.value
        ? Math.max(0, Number((amountReceived.value - total.value).toFixed(2)))
        : 0
));
const canSubmit = computed(() => (
    form.items.length > 0
    && !form.processing
    && (!isCashPayment.value || remaining.value === 0)
));

const money = (value) => new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    minimumFractionDigits: 2,
}).format(Number(value) || 0);

const applyQuickAmount = (mode, amount = 0) => {
    if (!isCashPayment.value) return;

    if (mode === 'exact') {
        form.amount_received = total.value.toFixed(2);
        return;
    }

    if (mode === 'clear') {
        form.amount_received = '';
        return;
    }

    const nextAmount = Number((amountReceived.value + amount).toFixed(2));
    form.amount_received = nextAmount.toFixed(2);
};

const submit = () => {
    form
        .transform((data) => ({
            ...data,
            items: data.items.map((item) => ({
                product_id: item.product_id,
                quantity: item.quantity,
                unit_price: item.unit_price,
            })),
        }))
        .post(route('sales.store'));
};

const submitIfReady = () => {
    if (!canSubmit.value) return;
    submit();
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
        submitIfReady();
    }
};

onMounted(() => {
    syncSelection();
    window.addEventListener('keydown', handleGlobalShortcuts);
    nextTick(() => {
        searchInput.value?.focus();
    });
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleGlobalShortcuts);

    if (searchTimer !== null) {
        window.clearTimeout(searchTimer);
    }
});
</script>

<template>
    <Head title="Nueva venta" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold text-slate-100">Nueva venta</h2>
                    <p class="mt-1 text-sm text-slate-300/80">Carga rapida por nombre o lector de codigo.</p>
                </div>
                <Link :href="route('sales.index')" class="text-sm font-semibold text-slate-300 hover:text-slate-100">Volver</Link>
            </div>
        </template>

        <form class="grid gap-6" @submit.prevent>
            <section class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 backdrop-blur p-5 shadow-sm">
                <div class="rounded-xl border border-cyan-200/35 bg-cyan-300/15 p-3 text-xs text-cyan-100">
                    <p class="font-semibold">Atajos</p>
                    <p class="leading-relaxed">F2: foco en buscador | F4: foco en cantidad | Alt+A: agregar producto | Ctrl+Enter: confirmar venta | Esc: limpiar busqueda</p>
                </div>

                <div class="mt-4 grid gap-3 lg:grid-cols-4">
                    <div class="lg:col-span-2">
                        <label for="product-search" class="mb-1 block text-sm font-medium text-slate-300">Producto (nombre, barcode o SKU)</label>
                        <input
                            id="product-search"
                            ref="searchInput"
                            v-model="state.search"
                            type="text"
                            class="w-full rounded-xl border-cyan-100/25 text-sm"
                            placeholder="Escanear codigo o escribir nombre y Enter"
                            aria-controls="product-results"
                            aria-autocomplete="list"
                            role="combobox"
                            :aria-expanded="filteredProducts.length ? 'true' : 'false'"
                            @keydown="handleSearchKeydown"
                        >
                    </div>
                    <div>
                        <label for="product-qty" class="mb-1 block text-sm font-medium text-slate-300">Cantidad <span class="text-xs text-slate-400">({{ activeProductMeta.quantityLabel }})</span></label>
                        <input
                            id="product-qty"
                            ref="quantityInput"
                            v-model.number="state.quantity"
                            type="number"
                            :min="activeProductMeta.quantityMin"
                            :step="activeProductMeta.quantityStep"
                            class="w-full rounded-xl border-cyan-100/25 text-sm"
                        >
                    </div>
                    <div class="flex items-end">
                        <button
                            type="button"
                            class="w-full rounded-xl border border-cyan-100/25 px-3 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/70"
                            @click="addProductToCart(activeProduct, 'manual')"
                        >
                            Agregar
                        </button>
                    </div>
                </div>

                <p class="mt-2 text-xs text-slate-300/80" aria-live="polite">{{ state.helperMessage }}</p>

                <div id="product-results" class="mt-3 max-h-64 overflow-auto rounded-xl border border-cyan-100/20" role="listbox" aria-label="Resultados de productos">
                    <ul v-if="filteredProducts.length" class="divide-y divide-slate-100 text-sm">
                        <li v-for="(product, index) in filteredProducts" :key="product.id">
                            <button
                                type="button"
                                class="flex w-full flex-col items-start gap-1 px-3 py-2 text-left hover:bg-slate-800/70 sm:flex-row sm:items-center sm:justify-between"
                                :class="product.id === state.activeProductId || index === state.highlightedIndex ? 'bg-cyan-400/15' : ''"
                                role="option"
                                :aria-selected="product.id === state.activeProductId || index === state.highlightedIndex ? 'true' : 'false'"
                                @click="selectProduct(product)"
                                @dblclick="addProductToCart(product, 'manual')"
                            >
                                <span class="min-w-0">
                                    <span class="font-semibold text-slate-100">{{ product.name }}</span>
                                    <span class="ml-2 text-xs text-slate-300/80">{{ product.barcode || product.sku || 'sin codigo' }}</span>
                                </span>
                                <span class="text-xs text-slate-300">stock {{ getDisplayedStock(product).value }} {{ getDisplayedStock(product).label }} - {{ money(product.sale_price) }} {{ product.price_label }}</span>
                            </button>
                        </li>
                    </ul>
                    <p v-else class="px-3 py-4 text-sm text-slate-400">
                        {{ isLoadingProducts ? 'Buscando productos...' : 'Sin resultados para la busqueda actual.' }}
                    </p>
                </div>

                <div v-if="form.items.length" class="mt-4 grid gap-3 md:hidden">
                    <article v-for="(item, index) in form.items" :key="`${item.product_id}-${index}`" class="rounded-xl border border-cyan-100/20 bg-slate-950/35 p-4 text-sm text-slate-300">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-slate-100">{{ item.product_name }}</p>
                                <p class="mt-1 text-xs text-slate-400">
                                    {{ item.quantity }} {{ item.quantity_label }}
                                    - {{ money(item.unit_price) }} {{ item.price_label }}
                                </p>
                            </div>
                            <button type="button" class="shrink-0 rounded-lg border border-rose-300/45 px-2 py-1 text-xs font-semibold text-rose-100 hover:bg-rose-400/20" @click="removeItem(index)">Quitar</button>
                        </div>
                        <p class="mt-3 text-sm">Subtotal: <strong class="text-slate-100">{{ money(getLineSubtotal(item)) }}</strong></p>
                    </article>
                </div>

                <div class="mt-4 hidden overflow-x-auto rounded-xl border border-cyan-100/20 app-table-wrap md:block">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-950/35">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Producto</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Cantidad</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Precio</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Subtotal</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80"></th>
                            </tr>
                        </thead>
                        <tbody v-if="form.items.length" class="divide-y divide-slate-100">
                            <tr v-for="(item, index) in form.items" :key="`${item.product_id}-${index}`">
                                <td class="px-3 py-2 font-semibold text-slate-100">{{ item.product_name }}</td>
                                <td class="px-3 py-2">
                                    {{ item.quantity }}
                                    <span class="text-xs text-slate-400">{{ item.quantity_label }}</span>
                                </td>
                                <td class="px-3 py-2">
                                    {{ money(item.unit_price) }}
                                    <span class="text-xs text-slate-400">{{ item.price_label }}</span>
                                </td>
                                <td class="px-3 py-2">{{ money(getLineSubtotal(item)) }}</td>
                                <td class="px-3 py-2 text-right">
                                    <button type="button" class="rounded-lg border border-rose-300/45 px-2 py-1 text-xs font-semibold text-rose-100 hover:bg-rose-400/20" @click="removeItem(index)">Quitar</button>
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

            <section class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 backdrop-blur p-5 shadow-sm">
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <div>
                        <label for="sold-at" class="mb-1 block text-sm font-medium text-slate-300">Fecha y hora</label>
                        <input id="sold-at" v-model="form.sold_at" type="datetime-local" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100" />
                    </div>
                    <div>
                        <label for="discount" class="mb-1 block text-sm font-medium text-slate-300">Descuento</label>
                        <input id="discount" v-model.number="form.discount" type="number" min="0" step="0.01" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100" placeholder="0.00" />
                    </div>
                    <div>
                        <label for="notes" class="mb-1 block text-sm font-medium text-slate-300">Notas</label>
                        <input id="notes" v-model="form.notes" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100" placeholder="Observaciones" />
                    </div>
                </div>

                <div class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
                    <div>
                        <label for="payment-method" class="mb-1 block text-sm font-medium text-slate-300">Medio de pago</label>
                        <select
                            id="payment-method"
                            v-model="form.payment_method"
                            class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100"
                        >
                            <option value="cash">Efectivo</option>
                            <option value="transfer">Transferencia</option>
                        </select>

                        <div v-if="isCashPayment" class="mt-4 rounded-xl border border-cyan-100/20 bg-slate-950/35 p-4 text-sm text-slate-300">
                            <div class="grid gap-3">
                                <div>
                                    <label for="amount-received" class="mb-1 block text-sm font-medium text-slate-300">Monto recibido</label>
                                    <input
                                        id="amount-received"
                                        v-model="form.amount_received"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100"
                                        placeholder="0.00"
                                    >
                                    <p v-if="form.errors.amount_received" class="mt-1 text-xs text-rose-300">
                                        {{ form.errors.amount_received }}
                                    </p>
                                </div>
                                <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                                    <button type="button" class="rounded-lg border border-cyan-100/25 px-3 py-2 text-xs font-semibold text-slate-300 hover:bg-slate-800/70" @click="applyQuickAmount('exact')">Exacto</button>
                                    <button type="button" class="rounded-lg border border-cyan-100/25 px-3 py-2 text-xs font-semibold text-slate-300 hover:bg-slate-800/70" @click="applyQuickAmount('add', 100)">+100</button>
                                    <button type="button" class="rounded-lg border border-cyan-100/25 px-3 py-2 text-xs font-semibold text-slate-300 hover:bg-slate-800/70" @click="applyQuickAmount('add', 500)">+500</button>
                                    <button type="button" class="rounded-lg border border-cyan-100/25 px-3 py-2 text-xs font-semibold text-slate-300 hover:bg-slate-800/70" @click="applyQuickAmount('add', 1000)">+1000</button>
                                    <button type="button" class="rounded-lg border border-cyan-100/25 px-3 py-2 text-xs font-semibold text-slate-300 hover:bg-slate-800/70" @click="applyQuickAmount('add', 2000)">+2000</button>
                                    <button type="button" class="rounded-lg border border-cyan-100/25 px-3 py-2 text-xs font-semibold text-slate-300 hover:bg-slate-800/70" @click="applyQuickAmount('add', 5000)">+5000</button>
                                    <button type="button" class="rounded-lg border border-cyan-100/25 px-3 py-2 text-xs font-semibold text-slate-300 hover:bg-slate-800/70" @click="applyQuickAmount('add', 10000)">+10000</button>
                                    <button type="button" class="rounded-lg border border-rose-300/45 px-3 py-2 text-xs font-semibold text-rose-100 hover:bg-rose-400/20" @click="applyQuickAmount('clear')">Limpiar</button>
                                </div>
                                <p v-if="remaining > 0" class="text-xs font-medium text-amber-300">
                                    Faltan {{ money(remaining) }} para completar el cobro.
                                </p>
                                <p v-else class="text-xs font-medium text-emerald-300">
                                    Vuelto calculado: {{ money(changeAmount) }}
                                </p>
                            </div>
                        </div>

                        <p v-else class="mt-4 rounded-xl border border-cyan-100/20 bg-slate-950/35 px-4 py-3 text-sm text-slate-300">
                            En transferencia no hace falta calcular vuelto.
                        </p>
                    </div>

                    <div class="rounded-xl bg-slate-950/35 p-4 text-sm text-slate-300">
                        <p>Subtotal: <strong>{{ money(subtotal) }}</strong></p>
                        <p>Descuento: <strong>{{ money(form.discount) }}</strong></p>
                        <p class="mt-2 text-base text-slate-100">Total: <strong>{{ money(total) }}</strong></p>
                        <template v-if="isCashPayment">
                            <p class="mt-2">Recibido: <strong>{{ money(amountReceived) }}</strong></p>
                            <p>Vuelto: <strong>{{ money(changeAmount) }}</strong></p>
                        </template>
                    </div>
                </div>
                <div class="mt-4 flex justify-end">
                    <button type="button" class="w-full rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-600 disabled:opacity-50 sm:w-auto" :disabled="!canSubmit" @click="submitIfReady">
                        Confirmar venta
                    </button>
                </div>
            </section>
        </form>
    </AuthenticatedLayout>
</template>

