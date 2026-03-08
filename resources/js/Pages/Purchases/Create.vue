<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    suppliers: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
});

const state = reactive({
    mode: 'existing',
    search: '',
    quantity: 1,
    unit_cost: 0,
    highlightedIndex: 0,
    activeProductId: null,
    helperMessage: 'Busca por nombre, codigo de barras o SKU. Enter agrega el producto.',
    new_product: {
        name: '',
        barcode: '',
        sku: '',
        unit_type: 'unit',
        sale_price: 0,
        min_stock: 0,
    },
});

const searchInput = ref(null);
const quantityInput = ref(null);
const unitCostInput = ref(null);
const newNameInput = ref(null);

const nowLocalDateTime = () => {
    const date = new Date();
    const offsetMinutes = date.getTimezoneOffset();
    const localDate = new Date(date.getTime() - (offsetMinutes * 60000));

    return localDate.toISOString().slice(0, 16);
};

const form = useForm({
    supplier_id: '',
    purchased_at: nowLocalDateTime(),
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
        const directMatch = filteredProducts.value.find((product) => product.id === state.activeProductId);
        if (directMatch) return directMatch;
    }

    return filteredProducts.value[state.highlightedIndex] || null;
});

const total = computed(() => form.items.reduce((acc, item) => acc + (Number(item.quantity) * Number(item.unit_cost)), 0));

const money = (value) => new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    minimumFractionDigits: 2,
}).format(Number(value) || 0);

const syncSelection = () => {
    if (!filteredProducts.value.length) {
        state.highlightedIndex = 0;
        state.activeProductId = null;
        return;
    }

    if (!filteredProducts.value.find((product) => product.id === state.activeProductId)) {
        state.highlightedIndex = 0;
        state.activeProductId = filteredProducts.value[0].id;
        setDefaultCost(filteredProducts.value[0]);
        return;
    }

    const activeIndex = filteredProducts.value.findIndex((product) => product.id === state.activeProductId);
    state.highlightedIndex = activeIndex >= 0 ? activeIndex : 0;
};

const setDefaultCost = (product) => {
    if (!product) return;

    state.unit_cost = Number(product.cost_price || 0);
};

const selectProduct = (product) => {
    state.activeProductId = product.id;
    const index = filteredProducts.value.findIndex((item) => item.id === product.id);
    state.highlightedIndex = index >= 0 ? index : 0;
    setDefaultCost(product);
};

const findExactCodeMatch = (term) => {
    const normalized = normalize(term);
    if (normalized === '') return null;

    return props.products.find((product) => (
        normalize(product.barcode) === normalized || normalize(product.sku) === normalized
    )) || null;
};

const addExistingProduct = (product, source = 'manual') => {
    if (!product) {
        state.helperMessage = 'Selecciona un producto para agregar.';
        return;
    }

    const quantity = Number(state.quantity || 0);
    if (quantity <= 0) {
        state.helperMessage = 'La cantidad debe ser mayor a 0.';
        return;
    }

    let unitCost = Number(state.unit_cost || 0);
    if (unitCost < 0) {
        state.helperMessage = 'El costo no puede ser negativo.';
        return;
    }

    if (unitCost === 0 && Number(product.cost_price || 0) > 0) {
        unitCost = Number(product.cost_price);
    }

    unitCost = Number(unitCost.toFixed(2));

    const existingLine = form.items.find((item) => (
        item.product_id === product.id
        && item.product === null
        && Number(item.unit_cost) === unitCost
    ));

    if (existingLine) {
        existingLine.quantity = Number((Number(existingLine.quantity) + quantity).toFixed(3));
    } else {
        form.items.push({
            product_id: product.id,
            quantity: Number(quantity.toFixed(3)),
            unit_cost: unitCost,
            product: null,
        });
    }

    state.quantity = 1;
    state.unit_cost = unitCost;
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

const addNewProductItem = () => {
    const name = String(state.new_product.name || '').trim();
    if (name === '') {
        state.helperMessage = 'El producto nuevo necesita un nombre.';
        return;
    }

    const quantity = Number(state.quantity || 0);
    if (quantity <= 0) {
        state.helperMessage = 'La cantidad debe ser mayor a 0.';
        return;
    }

    const unitCost = Number(state.unit_cost || 0);
    if (unitCost < 0) {
        state.helperMessage = 'El costo no puede ser negativo.';
        return;
    }

    form.items.push({
        product_id: null,
        quantity: Number(quantity.toFixed(3)),
        unit_cost: Number(unitCost.toFixed(2)),
        product: {
            name,
            barcode: String(state.new_product.barcode || '').trim() || null,
            sku: String(state.new_product.sku || '').trim() || null,
            unit_type: state.new_product.unit_type,
            sale_price: Number(Number(state.new_product.sale_price || 0).toFixed(2)),
            min_stock: Number(Number(state.new_product.min_stock || 0).toFixed(3)),
        },
    });

    state.new_product = {
        name: '',
        barcode: '',
        sku: '',
        unit_type: 'unit',
        sale_price: 0,
        min_stock: 0,
    };
    state.quantity = 1;
    state.unit_cost = 0;
    state.helperMessage = `Producto nuevo agregado: ${name}`;

    nextTick(() => {
        newNameInput.value?.focus();
    });
};

const addCurrentItem = () => {
    if (state.mode === 'existing') {
        addExistingProduct(activeProduct.value, 'manual');
        return;
    }

    addNewProductItem();
};

const handleSearchEnter = () => {
    if (state.mode !== 'existing') return;

    const exactCodeMatch = findExactCodeMatch(state.search);

    if (exactCodeMatch) {
        setDefaultCost(exactCodeMatch);
        addExistingProduct(exactCodeMatch, 'scanner');
        return;
    }

    if (activeProduct.value) {
        addExistingProduct(activeProduct.value, 'manual');
        return;
    }

    state.helperMessage = 'Sin coincidencias. Usa Alt+N para cargar un producto nuevo.';
};

const handleSearchKeydown = (event) => {
    if (event.key === 'ArrowDown') {
        event.preventDefault();

        if (!filteredProducts.value.length) return;

        state.highlightedIndex = (state.highlightedIndex + 1) % filteredProducts.value.length;
        state.activeProductId = filteredProducts.value[state.highlightedIndex].id;
        setDefaultCost(filteredProducts.value[state.highlightedIndex]);
        return;
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();

        if (!filteredProducts.value.length) return;

        state.highlightedIndex = (state.highlightedIndex - 1 + filteredProducts.value.length) % filteredProducts.value.length;
        state.activeProductId = filteredProducts.value[state.highlightedIndex].id;
        setDefaultCost(filteredProducts.value[state.highlightedIndex]);
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

const removeItem = (index) => {
    form.items.splice(index, 1);
};

const itemLabel = (item) => {
    if (item.product_id) {
        return props.products.find((product) => product.id === Number(item.product_id))?.name || `Producto #${item.product_id}`;
    }

    return item.product?.name || 'Producto nuevo';
};

const submit = () => {
    form.post(route('purchases.store'));
};

const setMode = (mode) => {
    state.mode = mode;

    if (mode === 'existing') {
        state.helperMessage = 'Modo producto existente. Escanea o busca por nombre.';
        nextTick(() => searchInput.value?.focus());
        return;
    }

    state.helperMessage = 'Modo producto nuevo. Carga nombre y datos basicos.';
    nextTick(() => newNameInput.value?.focus());
};

const handleGlobalShortcuts = (event) => {
    if (event.key === 'F2') {
        event.preventDefault();
        setMode('existing');
        searchInput.value?.focus();
        return;
    }

    if (event.key === 'F4') {
        event.preventDefault();
        quantityInput.value?.focus();
        return;
    }

    if (event.key === 'F6') {
        event.preventDefault();
        unitCostInput.value?.focus();
        return;
    }

    if (event.altKey && event.key.toLowerCase() === 'a') {
        event.preventDefault();
        addCurrentItem();
        return;
    }

    if (event.altKey && event.key.toLowerCase() === 'n') {
        event.preventDefault();
        setMode('new');
        return;
    }

    if (event.altKey && event.key.toLowerCase() === 'e') {
        event.preventDefault();
        setMode('existing');
        return;
    }

    if (event.ctrlKey && event.key === 'Enter') {
        event.preventDefault();
        if (form.items.length && !form.processing) {
            submit();
        }
    }
};

watch(() => state.search, () => {
    syncSelection();
});

watch(filteredProducts, () => {
    syncSelection();
});

watch(() => state.mode, (mode) => {
    if (mode === 'existing') {
        syncSelection();
    }
});

onMounted(() => {
    syncSelection();
    window.addEventListener('keydown', handleGlobalShortcuts);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleGlobalShortcuts);
});
</script>

<template>
    <Head title="Nueva compra" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold text-slate-900">Nueva compra</h2>
                    <p class="mt-1 text-sm text-slate-500">Carga rapida por lector de codigo o busqueda manual.</p>
                </div>
                <Link :href="route('purchases.index')" class="text-sm font-semibold text-slate-600 hover:text-slate-900">Volver</Link>
            </div>
        </template>

        <form class="grid gap-6" @submit.prevent="submit">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="rounded-xl border border-cyan-200/35 bg-cyan-300/15 p-3 text-xs text-cyan-100">
                    <p class="font-semibold">Atajos</p>
                    <p>F2: buscador | F4: cantidad | F6: costo | Alt+A: agregar item | Alt+N: producto nuevo | Alt+E: producto existente | Ctrl+Enter: confirmar compra | Esc: limpiar busqueda</p>
                </div>

                <div class="mt-4 grid gap-3 md:grid-cols-3">
                    <div>
                        <label for="supplier_id" class="mb-1 block text-sm font-medium text-slate-700">Proveedor</label>
                        <select id="supplier_id" v-model="form.supplier_id" class="w-full rounded-xl border-slate-300 text-sm">
                            <option value="">Sin proveedor</option>
                            <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">{{ supplier.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="purchased_at" class="mb-1 block text-sm font-medium text-slate-700">Fecha y hora</label>
                        <input id="purchased_at" v-model="form.purchased_at" type="datetime-local" class="w-full rounded-xl border-slate-300 text-sm" />
                    </div>
                    <div>
                        <label for="purchase_notes" class="mb-1 block text-sm font-medium text-slate-700">Notas</label>
                        <input id="purchase_notes" v-model="form.notes" type="text" class="w-full rounded-xl border-slate-300 text-sm" placeholder="Observaciones" />
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center gap-4">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input :checked="state.mode === 'existing'" type="radio" class="border-slate-300" @change="setMode('existing')">
                        Usar producto existente
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input :checked="state.mode === 'new'" type="radio" class="border-slate-300" @change="setMode('new')">
                        Crear producto nuevo
                    </label>
                </div>

                <div v-if="state.mode === 'existing'" class="mt-4">
                    <label for="product-search" class="mb-1 block text-sm font-medium text-slate-700">Producto (nombre, barcode o SKU)</label>
                    <input
                        id="product-search"
                        ref="searchInput"
                        v-model="state.search"
                        type="text"
                        class="w-full rounded-xl border-slate-300 text-sm"
                        placeholder="Escanear codigo y Enter o buscar por nombre"
                        aria-controls="purchase-product-results"
                        aria-autocomplete="list"
                        role="combobox"
                        :aria-expanded="filteredProducts.length ? 'true' : 'false'"
                        @keydown="handleSearchKeydown"
                    >

                    <div id="purchase-product-results" class="mt-3 max-h-64 overflow-auto rounded-xl border border-slate-200" role="listbox" aria-label="Resultados de productos">
                        <ul v-if="filteredProducts.length" class="divide-y divide-slate-100 text-sm">
                            <li v-for="(product, index) in filteredProducts" :key="product.id">
                                <button
                                    type="button"
                                    class="flex w-full items-center justify-between px-3 py-2 text-left hover:bg-slate-50"
                                    :class="product.id === state.activeProductId || index === state.highlightedIndex ? 'bg-indigo-50' : ''"
                                    role="option"
                                    :aria-selected="product.id === state.activeProductId || index === state.highlightedIndex ? 'true' : 'false'"
                                    @click="selectProduct(product)"
                                    @dblclick="addExistingProduct(product, 'manual')"
                                >
                                    <span>
                                        <span class="font-semibold text-slate-900">{{ product.name }}</span>
                                        <span class="ml-2 text-xs text-slate-500">{{ product.barcode || product.sku || 'sin codigo' }}</span>
                                    </span>
                                    <span class="text-xs text-slate-600">stock {{ product.stock }} - costo {{ money(product.cost_price) }}</span>
                                </button>
                            </li>
                        </ul>
                        <p v-else class="px-3 py-4 text-sm text-slate-400">Sin resultados para la busqueda actual.</p>
                    </div>
                </div>

                <div v-else class="mt-4 grid gap-3 md:grid-cols-3">
                    <div>
                        <label for="new_product_name" class="mb-1 block text-sm font-medium text-slate-700">Nombre del producto</label>
                        <input id="new_product_name" ref="newNameInput" v-model="state.new_product.name" type="text" class="w-full rounded-xl border-slate-300 text-sm" placeholder="Ej: Yerba 1kg" />
                    </div>
                    <div>
                        <label for="new_product_barcode" class="mb-1 block text-sm font-medium text-slate-700">Codigo de barras</label>
                        <input id="new_product_barcode" v-model="state.new_product.barcode" type="text" class="w-full rounded-xl border-slate-300 text-sm" placeholder="Opcional" />
                    </div>
                    <div>
                        <label for="new_product_sku" class="mb-1 block text-sm font-medium text-slate-700">SKU</label>
                        <input id="new_product_sku" v-model="state.new_product.sku" type="text" class="w-full rounded-xl border-slate-300 text-sm" placeholder="Opcional" />
                    </div>
                    <div>
                        <label for="new_product_unit_type" class="mb-1 block text-sm font-medium text-slate-700">Tipo de unidad</label>
                        <select id="new_product_unit_type" v-model="state.new_product.unit_type" class="w-full rounded-xl border-slate-300 text-sm">
                            <option value="unit">Unidad</option>
                            <option value="weight">Peso</option>
                        </select>
                    </div>
                    <div>
                        <label for="new_product_sale_price" class="mb-1 block text-sm font-medium text-slate-700">Precio de venta sugerido</label>
                        <input id="new_product_sale_price" v-model.number="state.new_product.sale_price" type="number" min="0" step="0.01" class="w-full rounded-xl border-slate-300 text-sm" placeholder="0.00" />
                    </div>
                    <div>
                        <label for="new_product_min_stock" class="mb-1 block text-sm font-medium text-slate-700">Stock minimo</label>
                        <input id="new_product_min_stock" v-model.number="state.new_product.min_stock" type="number" min="0" step="0.001" class="w-full rounded-xl border-slate-300 text-sm" placeholder="0.000" />
                    </div>
                </div>

                <div class="mt-4 grid gap-3 md:grid-cols-3">
                    <div>
                        <label for="purchase_quantity" class="mb-1 block text-sm font-medium text-slate-700">Cantidad</label>
                        <input id="purchase_quantity" ref="quantityInput" v-model.number="state.quantity" type="number" min="0.001" step="0.001" class="w-full rounded-xl border-slate-300 text-sm" />
                    </div>
                    <div>
                        <label for="purchase_unit_cost" class="mb-1 block text-sm font-medium text-slate-700">Costo unitario</label>
                        <input id="purchase_unit_cost" ref="unitCostInput" v-model.number="state.unit_cost" type="number" min="0" step="0.01" class="w-full rounded-xl border-slate-300 text-sm" />
                    </div>
                    <div class="flex items-end">
                        <button type="button" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="addCurrentItem">
                            Agregar item
                        </button>
                    </div>
                </div>

                <p class="mt-2 text-xs text-slate-500" aria-live="polite">{{ state.helperMessage }}</p>

                <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200 app-table-wrap">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Producto</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Cantidad</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Costo</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Subtotal</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-500"></th>
                            </tr>
                        </thead>
                        <tbody v-if="form.items.length" class="divide-y divide-slate-100">
                            <tr v-for="(item, index) in form.items" :key="`${item.product_id || 'new'}-${index}`">
                                <td class="px-3 py-2 font-semibold text-slate-900">{{ itemLabel(item) }}</td>
                                <td class="px-3 py-2">{{ item.quantity }}</td>
                                <td class="px-3 py-2">{{ money(item.unit_cost) }}</td>
                                <td class="px-3 py-2">{{ money(Number(item.quantity) * Number(item.unit_cost)) }}</td>
                                <td class="px-3 py-2 text-right">
                                    <button type="button" class="rounded-lg border border-rose-300 px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50" @click="removeItem(index)">Quitar</button>
                                </td>
                            </tr>
                        </tbody>
                        <tbody v-else>
                            <tr>
                                <td colspan="5" class="px-3 py-5 text-center text-slate-400">Agrega items para registrar la compra.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-slate-700">Total: <strong>{{ money(total) }}</strong></p>
                <div class="mt-4 flex justify-end">
                    <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-600" :disabled="form.processing || !form.items.length">
                        Confirmar compra
                    </button>
                </div>
            </section>
        </form>
    </AuthenticatedLayout>
</template>
