<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    suppliers: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
    global_catalog: { type: Object, default: () => ({ enabled: false }) },
});

const buildNewProductState = () => ({
    global_product_id: '',
    category_id: '',
    name: '',
    barcode: '',
    sku: '',
    unit_type: 'unit',
    weight_unit: 'kg',
    sale_price: 0,
    min_stock: 0,
    expiry_alert_days: 15,
});

const state = reactive({
    mode: 'existing',
    search: '',
    quantity: 1,
    unit_cost: 0,
    expires_at: '',
    highlightedIndex: 0,
    activeProductId: null,
    helperMessage: 'Busca por nombre, codigo de barras o SKU. Enter agrega el producto.',
    new_product: buildNewProductState(),
    lookup: {
        status: 'idle',
        localProduct: null,
        globalProduct: null,
        conflict: null,
    },
});

const searchInput = ref(null);
const quantityInput = ref(null);
const unitCostInput = ref(null);
const newNameInput = ref(null);
const isLookingUpCatalog = ref(false);

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

const calculateShelfLifeDays = (expiresAtValue) => {
    if (!expiresAtValue) return null;

    const expiresAt = new Date(`${expiresAtValue}T00:00:00`);
    if (Number.isNaN(expiresAt.getTime())) return null;

    const purchasedAt = form.purchased_at ? new Date(form.purchased_at) : new Date();
    if (Number.isNaN(purchasedAt.getTime())) return null;

    purchasedAt.setHours(0, 0, 0, 0);

    const diffDays = Math.ceil((expiresAt.getTime() - purchasedAt.getTime()) / 86400000);
    return diffDays > 0 ? diffDays : 1;
};

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

const measurementMeta = (product) => ({
    quantityLabel: product?.quantity_label || (product?.unit_type === 'weight' ? (product?.weight_unit === 'g' ? 'g' : 'kg') : 'un'),
    priceLabel: product?.price_label || (product?.unit_type === 'weight' ? (product?.weight_unit === 'g' ? 'por 100 g' : 'por kg') : 'por unidad'),
    quantityStep: product?.quantity_step || (product?.unit_type === 'weight' && product?.weight_unit === 'kg' ? '0.001' : '1'),
    quantityMin: product?.quantity_min || (product?.unit_type === 'weight' && product?.weight_unit === 'kg' ? '0.001' : '1'),
    isGrams: product?.unit_type === 'weight' && product?.weight_unit === 'g',
});

const activeMeasurement = computed(() => {
    if (state.mode === 'new') {
        return measurementMeta(state.new_product);
    }

    return measurementMeta(activeProduct.value);
});

const itemMeta = (item) => {
    if (item.product_id) {
        return measurementMeta(props.products.find((product) => product.id === Number(item.product_id)));
    }

    return measurementMeta(item.product);
};

const lineSubtotal = (item) => {
    const meta = itemMeta(item);

    if (meta.isGrams) {
        return Number((((Number(item.quantity) * Number(item.unit_cost)) / 100)).toFixed(2));
    }

    return Number((Number(item.quantity) * Number(item.unit_cost)).toFixed(2));
};

const total = computed(() => form.items.reduce((acc, item) => acc + lineSubtotal(item), 0));

const money = (value) => new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    minimumFractionDigits: 2,
}).format(Number(value) || 0);

const resetNewProductLookup = () => {
    state.lookup.status = 'idle';
    state.lookup.localProduct = null;
    state.lookup.globalProduct = null;
    state.lookup.conflict = null;
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
    const expiresAt = state.expires_at || null;

    const existingLine = form.items.find((item) => (
        item.product_id === product.id
        && item.product === null
        && Number(item.unit_cost) === unitCost
        && (item.expires_at || null) === expiresAt
    ));

    if (existingLine) {
        existingLine.quantity = Number((Number(existingLine.quantity) + quantity).toFixed(3));
    } else {
        form.items.push({
            product_id: product.id,
            quantity: Number(quantity.toFixed(3)),
            unit_cost: unitCost,
            expires_at: expiresAt,
            product: null,
        });
    }

    state.quantity = 1;
    state.unit_cost = unitCost;
    state.search = '';
    state.activeProductId = null;
    state.highlightedIndex = 0;
    state.expires_at = '';
    state.helperMessage = source === 'scanner'
        ? `Producto agregado por codigo: ${product.name}`
        : `Producto agregado: ${product.name}`;

    nextTick(() => {
        searchInput.value?.focus();
    });
};

const lookupNewProductCatalog = async () => {
    const barcode = String(state.new_product.barcode || '').trim();
    const name = String(state.new_product.name || '').trim();

    if (barcode === '' && name === '') {
        resetNewProductLookup();
        return;
    }

    isLookingUpCatalog.value = true;

    try {
        const { data } = await window.axios.get(route('products.catalog.lookup'), {
            params: { barcode, name },
        });

        state.new_product.global_product_id = '';
        state.lookup.localProduct = data?.local_product || null;
        state.lookup.globalProduct = data?.global_product || null;
        state.lookup.conflict = data?.conflict || null;

        if (state.lookup.localProduct) {
            state.lookup.status = 'found_local';
            return;
        }

        if (state.lookup.conflict) {
            state.lookup.status = 'conflict';
            return;
        }

        if (state.lookup.globalProduct) {
            state.lookup.status = 'found_global';
            return;
        }

        state.lookup.status = 'not_found';
    } catch (error) {
        resetNewProductLookup();
        state.lookup.status = 'error';
    } finally {
        isLookingUpCatalog.value = false;
    }
};

const applyGlobalProductToPurchase = () => {
    if (!state.lookup.globalProduct) return;

    state.new_product.global_product_id = state.lookup.globalProduct.id;
    state.new_product.name = state.lookup.globalProduct.name || state.new_product.name;

    if (String(state.new_product.barcode || '').trim() === '' && state.lookup.globalProduct.barcode) {
        state.new_product.barcode = state.lookup.globalProduct.barcode;
    }

    if (state.lookup.globalProduct.suggested_category?.id) {
        state.new_product.category_id = state.lookup.globalProduct.suggested_category.id;
    }

    state.lookup.status = 'applied';
    state.helperMessage = `Catalogo global aplicado a ${state.new_product.name}.`;
};

const switchToExistingFromLookup = () => {
    if (!state.lookup.localProduct) return;

    setMode('existing');
    state.search = state.lookup.localProduct.barcode || state.lookup.localProduct.name;
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

    const expiresAt = state.expires_at || null;
    const shelfLifeDays = calculateShelfLifeDays(expiresAt);

    form.items.push({
        product_id: null,
        quantity: Number(quantity.toFixed(3)),
        unit_cost: Number(unitCost.toFixed(2)),
        expires_at: expiresAt || null,
        product: {
            global_product_id: state.new_product.global_product_id || null,
            category_id: state.new_product.category_id || null,
            name,
            barcode: String(state.new_product.barcode || '').trim() || null,
            sku: String(state.new_product.sku || '').trim() || null,
            unit_type: state.new_product.unit_type,
            sale_price: Number(Number(state.new_product.sale_price || 0).toFixed(2)),
            min_stock: Number(Number(state.new_product.min_stock || 0).toFixed(3)),
            weight_unit: state.new_product.unit_type === 'weight' ? state.new_product.weight_unit : null,
            shelf_life_days: shelfLifeDays,
            expiry_alert_days: Number(state.new_product.expiry_alert_days || 15),
        },
    });

    state.new_product = buildNewProductState();
    resetNewProductLookup();
    state.quantity = 1;
    state.unit_cost = 0;
    state.expires_at = '';
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

const handleNewBarcodeKeydown = (event) => {
    if (event.key !== 'Enter') return;

    event.preventDefault();
    void lookupNewProductCatalog();
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

    state.helperMessage = 'Modo producto nuevo. Carga nombre y datos basicos o busca en el catalogo global.';
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
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold text-slate-100">Nueva compra</h2>
                    <p class="mt-1 text-sm text-slate-300/80">Carga rapida por lector de codigo o busqueda manual.</p>
                </div>
                <Link :href="route('purchases.index')" class="text-sm font-semibold text-slate-300 hover:text-slate-100">Volver</Link>
            </div>
        </template>

        <form class="grid gap-6" @submit.prevent="submit">
            <section class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 backdrop-blur p-5 shadow-sm">
                <div class="rounded-xl border border-cyan-200/35 bg-cyan-300/15 p-3 text-xs text-cyan-100">
                    <p class="font-semibold">Atajos</p>
                    <p class="leading-relaxed">F2: buscador | F4: cantidad | F6: costo | Alt+A: agregar item | Alt+N: producto nuevo | Alt+E: producto existente | Ctrl+Enter: confirmar compra | Esc: limpiar busqueda</p>
                </div>

                <div class="mt-4 grid gap-3 lg:grid-cols-3">
                    <div>
                        <label for="supplier_id" class="mb-1 block text-sm font-medium text-slate-300">Proveedor</label>
                        <select id="supplier_id" v-model="form.supplier_id" class="w-full rounded-xl border-cyan-100/25 text-sm">
                            <option value="">Sin proveedor</option>
                            <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">{{ supplier.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="purchased_at" class="mb-1 block text-sm font-medium text-slate-300">Fecha y hora</label>
                        <input id="purchased_at" v-model="form.purchased_at" type="datetime-local" class="w-full rounded-xl border-cyan-100/25 text-sm" />
                    </div>
                    <div>
                        <label for="purchase_notes" class="mb-1 block text-sm font-medium text-slate-300">Notas</label>
                        <input id="purchase_notes" v-model="form.notes" type="text" class="w-full rounded-xl border-cyan-100/25 text-sm" placeholder="Observaciones" />
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 backdrop-blur p-5 shadow-sm">
                <div class="flex flex-wrap items-center gap-4">
                    <label class="inline-flex w-full items-center gap-2 text-sm text-slate-300 sm:w-auto">
                        <input :checked="state.mode === 'existing'" type="radio" class="border-cyan-100/25" @change="setMode('existing')">
                        Usar producto existente
                    </label>
                    <label class="inline-flex w-full items-center gap-2 text-sm text-slate-300 sm:w-auto">
                        <input :checked="state.mode === 'new'" type="radio" class="border-cyan-100/25" @change="setMode('new')">
                        Crear producto nuevo
                    </label>
                </div>

                <div v-if="state.mode === 'existing'" class="mt-4">
                    <label for="product-search" class="mb-1 block text-sm font-medium text-slate-300">Producto (nombre, barcode o SKU)</label>
                    <input
                        id="product-search"
                        ref="searchInput"
                        v-model="state.search"
                        type="text"
                        class="w-full rounded-xl border-cyan-100/25 text-sm"
                        placeholder="Escanear codigo y Enter o buscar por nombre"
                        aria-controls="purchase-product-results"
                        aria-autocomplete="list"
                        role="combobox"
                        :aria-expanded="filteredProducts.length ? 'true' : 'false'"
                        @keydown="handleSearchKeydown"
                    >

                    <div id="purchase-product-results" class="mt-3 max-h-64 overflow-auto rounded-xl border border-cyan-100/20" role="listbox" aria-label="Resultados de productos">
                        <ul v-if="filteredProducts.length" class="divide-y divide-slate-100 text-sm">
                            <li v-for="(product, index) in filteredProducts" :key="product.id">
                                <button
                                    type="button"
                                    class="flex w-full flex-col items-start gap-1 px-3 py-2 text-left hover:bg-slate-800/70 sm:flex-row sm:items-center sm:justify-between"
                                    :class="product.id === state.activeProductId || index === state.highlightedIndex ? 'bg-cyan-400/15' : ''"
                                    role="option"
                                    :aria-selected="product.id === state.activeProductId || index === state.highlightedIndex ? 'true' : 'false'"
                                    @click="selectProduct(product)"
                                    @dblclick="addExistingProduct(product, 'manual')"
                                >
                                    <span class="min-w-0">
                                        <span class="font-semibold text-slate-100">{{ product.name }}</span>
                                        <span class="ml-2 text-xs text-slate-300/80">{{ product.barcode || product.sku || 'sin codigo' }}</span>
                                    </span>
                                    <span class="text-xs text-slate-300">stock {{ product.stock }} {{ product.quantity_label }} - costo {{ money(product.cost_price) }} {{ product.price_label }}</span>
                                </button>
                            </li>
                        </ul>
                        <p v-else class="px-3 py-4 text-sm text-slate-400">Sin resultados para la busqueda actual.</p>
                    </div>
                </div>

                <div v-else class="mt-4 grid gap-4">
                    <section v-if="global_catalog.enabled" class="rounded-2xl border border-cyan-100/20 bg-slate-950/35 p-4">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-100">Lookup en catalogo global</h3>
                                <p class="mt-1 text-xs text-slate-300/80">Si el producto no existe localmente, puedes buscarlo en el catalogo global y reutilizar nombre y categoria.</p>
                            </div>
                            <button
                                type="button"
                                class="rounded-xl border border-cyan-100/25 px-3 py-2 text-sm font-semibold text-slate-200 hover:bg-slate-800/70 disabled:opacity-60"
                                :disabled="isLookingUpCatalog"
                                @click="lookupNewProductCatalog"
                            >
                                {{ isLookingUpCatalog ? 'Buscando...' : 'Buscar en catalogo' }}
                            </button>
                        </div>

                        <div v-if="state.lookup.status === 'found_local'" class="mt-4 rounded-xl border border-amber-200/35 bg-amber-300/10 p-4 text-sm text-amber-100">
                            <p class="font-semibold">Ese producto ya existe en este comercio.</p>
                            <p class="mt-1">Puedes volver al modo de producto existente para registrar la compra sobre el item local.</p>
                            <button type="button" class="mt-3 rounded-lg border border-amber-100/35 px-3 py-2 text-xs font-semibold text-amber-50 hover:bg-amber-100/10" @click="switchToExistingFromLookup">
                                Ir a producto existente
                            </button>
                        </div>

                        <div v-else-if="state.lookup.status === 'found_global'" class="mt-4 rounded-xl border border-emerald-200/35 bg-emerald-300/10 p-4 text-sm text-emerald-100">
                            <p class="font-semibold">Producto encontrado en el catalogo global.</p>
                            <p class="mt-2">Nombre: {{ state.lookup.globalProduct.name }}</p>
                            <p class="mt-1">Barcode: {{ state.lookup.globalProduct.barcode || 'Sin barcode' }}</p>
                            <p class="mt-1">Categoria global: {{ state.lookup.globalProduct.category?.name || 'Sin categoria' }}</p>
                            <p class="mt-1">Categoria sugerida local: {{ state.lookup.globalProduct.suggested_category?.name || 'Sin coincidencia segura' }}</p>
                            <button type="button" class="mt-3 rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-500" @click="applyGlobalProductToPurchase">
                                Usar producto del catalogo global
                            </button>
                        </div>

                        <div v-else-if="state.lookup.status === 'applied'" class="mt-4 rounded-xl border border-cyan-200/35 bg-cyan-300/10 p-4 text-sm text-cyan-100">
                            Se aplico el catalogo global al nuevo producto. El costo, stock y vencimiento siguen siendo del lote y del comercio actual.
                        </div>

                        <div v-else-if="state.lookup.status === 'conflict'" class="mt-4 rounded-xl border border-amber-200/35 bg-amber-300/10 p-4 text-sm text-amber-100">
                            {{ state.lookup.conflict }}
                        </div>

                        <div v-else-if="state.lookup.status === 'not_found'" class="mt-4 rounded-xl border border-slate-100/15 bg-slate-900/30 p-4 text-sm text-slate-300">
                            No hubo coincidencia segura en el catalogo global. Puedes seguir con la carga manual.
                        </div>

                        <div v-else-if="state.lookup.status === 'error'" class="mt-4 rounded-xl border border-rose-200/35 bg-rose-300/10 p-4 text-sm text-rose-100">
                            No se pudo consultar el catalogo global en este momento.
                        </div>
                    </section>

                    <div class="grid gap-3 lg:grid-cols-3">
                        <div>
                            <label for="new_product_name" class="mb-1 block text-sm font-medium text-slate-300">Nombre del producto</label>
                            <input id="new_product_name" ref="newNameInput" v-model="state.new_product.name" type="text" class="w-full rounded-xl border-cyan-100/25 text-sm" placeholder="Ej: Yerba 1kg" />
                        </div>
                        <div>
                            <label for="new_product_barcode" class="mb-1 block text-sm font-medium text-slate-300">Codigo de barras</label>
                            <input id="new_product_barcode" v-model="state.new_product.barcode" type="text" class="w-full rounded-xl border-cyan-100/25 text-sm" placeholder="Opcional" @keydown="handleNewBarcodeKeydown" />
                        </div>
                        <div>
                            <label for="new_product_sku" class="mb-1 block text-sm font-medium text-slate-300">SKU</label>
                            <input id="new_product_sku" v-model="state.new_product.sku" type="text" class="w-full rounded-xl border-cyan-100/25 text-sm" placeholder="Opcional" />
                        </div>
                        <div>
                            <label for="new_product_category_id" class="mb-1 block text-sm font-medium text-slate-300">Categoria</label>
                            <select id="new_product_category_id" v-model="state.new_product.category_id" class="w-full rounded-xl border-cyan-100/25 text-sm">
                                <option value="">Sin categoria</option>
                                <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label for="new_product_unit_type" class="mb-1 block text-sm font-medium text-slate-300">Tipo de unidad</label>
                            <select id="new_product_unit_type" v-model="state.new_product.unit_type" class="w-full rounded-xl border-cyan-100/25 text-sm">
                                <option value="unit">Unidad</option>
                                <option value="weight">Peso</option>
                            </select>
                        </div>
                        <div v-if="state.new_product.unit_type === 'weight'">
                            <label for="new_product_weight_unit" class="mb-1 block text-sm font-medium text-slate-300">Gestion por peso</label>
                            <select id="new_product_weight_unit" v-model="state.new_product.weight_unit" class="w-full rounded-xl border-cyan-100/25 text-sm">
                                <option value="kg">Kilos</option>
                                <option value="g">Gramos</option>
                            </select>
                        </div>
                        <div>
                            <label for="new_product_sale_price" class="mb-1 block text-sm font-medium text-slate-300">
                                {{ state.new_product.unit_type === 'weight' ? (state.new_product.weight_unit === 'g' ? 'Precio de venta sugerido por 100 g' : 'Precio de venta sugerido por kg') : 'Precio de venta sugerido' }}
                            </label>
                            <input id="new_product_sale_price" v-model.number="state.new_product.sale_price" type="number" min="0" step="0.01" class="w-full rounded-xl border-cyan-100/25 text-sm" placeholder="0.00" />
                        </div>
                        <div>
                            <label for="new_product_min_stock" class="mb-1 block text-sm font-medium text-slate-300">
                                {{ state.new_product.unit_type === 'weight' ? `Stock minimo (${state.new_product.weight_unit === 'g' ? 'g' : 'kg'})` : 'Stock minimo' }}
                            </label>
                            <input id="new_product_min_stock" v-model.number="state.new_product.min_stock" type="number" min="0" :step="state.new_product.unit_type === 'weight' && state.new_product.weight_unit === 'kg' ? '0.001' : '1'" class="w-full rounded-xl border-cyan-100/25 text-sm" placeholder="0" />
                        </div>
                        <div>
                            <label for="new_product_expiry_alert_days" class="mb-1 block text-sm font-medium text-slate-300">Alerta vencimiento (dias)</label>
                            <input id="new_product_expiry_alert_days" v-model.number="state.new_product.expiry_alert_days" type="number" min="1" step="1" class="w-full rounded-xl border-cyan-100/25 text-sm" />
                        </div>
                    </div>
                </div>

                <div class="mt-4 grid gap-3 lg:grid-cols-4">
                    <div>
                        <label for="purchase_quantity" class="mb-1 block text-sm font-medium text-slate-300">Cantidad <span class="text-xs text-slate-400">({{ activeMeasurement.quantityLabel }})</span></label>
                        <input id="purchase_quantity" ref="quantityInput" v-model.number="state.quantity" type="number" :min="activeMeasurement.quantityMin" :step="activeMeasurement.quantityStep" class="w-full rounded-xl border-cyan-100/25 text-sm" />
                    </div>
                    <div>
                        <label for="purchase_unit_cost" class="mb-1 block text-sm font-medium text-slate-300">Costo unitario <span class="text-xs text-slate-400">{{ activeMeasurement.priceLabel }}</span></label>
                        <input id="purchase_unit_cost" ref="unitCostInput" v-model.number="state.unit_cost" type="number" min="0" step="0.01" class="w-full rounded-xl border-cyan-100/25 text-sm" />
                    </div>
                    <div>
                        <label for="purchase_expires_at" class="mb-1 block text-sm font-medium text-slate-300">Vencimiento del lote</label>
                        <input id="purchase_expires_at" v-model="state.expires_at" type="date" class="w-full rounded-xl border-cyan-100/25 text-sm" />
                    </div>
                    <div class="flex items-end">
                        <button type="button" class="w-full rounded-xl border border-cyan-100/25 px-3 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/70" @click="addCurrentItem">
                            Agregar item
                        </button>
                    </div>
                </div>

                <p class="mt-2 text-xs text-slate-300/80" aria-live="polite">{{ state.helperMessage }}</p>

                <div v-if="form.items.length" class="mt-4 grid gap-3 md:hidden">
                    <article v-for="(item, index) in form.items" :key="`${item.product_id || 'new'}-${index}`" class="rounded-xl border border-cyan-100/20 bg-slate-950/35 p-4 text-sm text-slate-300">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-slate-100">{{ itemLabel(item) }}</p>
                                <p class="mt-1 text-xs text-slate-400">
                                    {{ item.quantity }} {{ itemMeta(item).quantityLabel }}
                                    · {{ money(item.unit_cost) }} {{ itemMeta(item).priceLabel }}
                                </p>
                                <p class="mt-1 text-xs text-slate-400">Vence: {{ item.expires_at || '-' }}</p>
                            </div>
                            <button type="button" class="shrink-0 rounded-lg border border-rose-300/45 px-2 py-1 text-xs font-semibold text-rose-100 hover:bg-rose-400/20" @click="removeItem(index)">Quitar</button>
                        </div>
                        <p class="mt-3 text-sm">Subtotal: <strong class="text-slate-100">{{ money(lineSubtotal(item)) }}</strong></p>
                    </article>
                </div>

                <div class="mt-4 hidden overflow-x-auto rounded-xl border border-cyan-100/20 app-table-wrap md:block">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-950/35">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Producto</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Cantidad</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Costo</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Vencimiento</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Subtotal</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80"></th>
                            </tr>
                        </thead>
                        <tbody v-if="form.items.length" class="divide-y divide-slate-100">
                            <tr v-for="(item, index) in form.items" :key="`${item.product_id || 'new'}-${index}`">
                                <td class="px-3 py-2 font-semibold text-slate-100">{{ itemLabel(item) }}</td>
                                <td class="px-3 py-2">{{ item.quantity }} <span class="text-xs text-slate-400">{{ itemMeta(item).quantityLabel }}</span></td>
                                <td class="px-3 py-2">{{ money(item.unit_cost) }} <span class="text-xs text-slate-400">{{ itemMeta(item).priceLabel }}</span></td>
                                <td class="px-3 py-2">{{ item.expires_at || '-' }}</td>
                                <td class="px-3 py-2">{{ money(lineSubtotal(item)) }}</td>
                                <td class="px-3 py-2 text-right">
                                    <button type="button" class="rounded-lg border border-rose-300/45 px-2 py-1 text-xs font-semibold text-rose-100 hover:bg-rose-400/20" @click="removeItem(index)">Quitar</button>
                                </td>
                            </tr>
                        </tbody>
                        <tbody v-else>
                            <tr>
                                <td colspan="6" class="px-3 py-5 text-center text-slate-400">Agrega items para registrar la compra.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 backdrop-blur p-5 shadow-sm">
                <p class="text-sm text-slate-300">Total: <strong>{{ money(total) }}</strong></p>
                <div class="mt-4 flex justify-end">
                    <button type="submit" class="w-full rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-600 sm:w-auto" :disabled="form.processing || !form.items.length">
                        Confirmar compra
                    </button>
                </div>
            </section>
        </form>
    </AuthenticatedLayout>
</template>

