<script setup>
import AppPanel from '@/Components/AppPanel.vue';
import SaleCart from '@/Components/Sales/SaleCart.vue';
import { useSaleDraft } from '@/Composables/useSaleDraft';
import StatusBadge from '@/Components/StatusBadge.vue';
import { paymentMethodLabel, paymentMethodOptions, paymentMethodRequiresDestination } from '@/Support/paymentMethods';
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    products: { type: Array, default: () => [] },
    customers: { type: Array, default: () => [] },
    advanced_sale_settings: { type: Object, default: () => ({ enabled: false, sale_sectors: [], payment_destinations: [] }) },
    fiscal: { type: Object, default: () => ({ enabled: false, issuer_condition: 'monotributo', receiver_iva_conditions: [] }) },
    quick_sale_options: { type: Array, default: () => [] },
    can_manage_quick_sale_options: { type: Boolean, default: false },
    vat_options: { type: Object, default: () => ({ treatments: [], rates: [], defaults: { treatment: 'gravado', rate: 21 } }) },
    mercadopago_point: { type: Object, default: () => ({ enabled: false }) },
    receipt_feature_available: { type: Boolean, default: false },
});

const defaultVatTreatment = props.vat_options?.defaults?.treatment || 'gravado';
const defaultVatRate = Number(props.vat_options?.defaults?.rate || 21);

const state = reactive({
    search: '',
    quantity: 1,
    manualItemName: '',
    manualItemAmount: '',
    manualItemVatTreatment: defaultVatTreatment,
    manualItemVatRate: defaultVatRate,
    highlightedIndex: 0,
    activeProductId: null,
    helperMessage: 'Busca por nombre, codigo de barras o SKU. Presiona Enter para agregar.',
});

const searchInput = ref(null);
const quantityInput = ref(null);
const manualAmountInput = ref(null);
const knownProducts = ref([...(props.products || [])]);
const searchResults = ref([...(props.products || [])]);
const isLoadingProducts = ref(false);
const isResolvingSearch = ref(false);
let searchTimer = null;
let lastSearchRequestId = 0;
let searchMutationVersion = 0;
let lastHandledSearchMutationVersion = -1;

const nowLocalDateTime = () => {
    const date = new Date();
    const offsetMinutes = date.getTimezoneOffset();
    const localDate = new Date(date.getTime() - (offsetMinutes * 60000));

    return localDate.toISOString().slice(0, 16);
};

const form = useForm({
    customer_id: null,
    fiscal_customer: {
        with_data: false,
        name: '',
        document_type: 'CUIT',
        document_number: '',
        iva_condition: 'consumidor_final',
        address: '',
    },
    payment_status: 'paid',
    payment_provider: 'manual',
    payment_method: 'cash',
    sale_sector_id: null,
    payment_destination_id: null,
    amount_received: '',
    paid_amount: '',
    sold_at: nowLocalDateTime(),
    discount: 0,
    notes: '',
    receipt: null,
    items: [],
});

const quickOptionForm = useForm({
    name: '',
    description: '',
    default_amount: '',
    vat_treatment: defaultVatTreatment,
    vat_rate: defaultVatRate,
    is_active: true,
});

const paymentStatusOptions = [
    { value: 'paid', label: 'Pagada' },
    { value: 'partial', label: 'Parcial' },
    { value: 'pending', label: 'Fiado' },
];
const pointPaymentMethodValues = ['debit_card', 'credit_card', 'qr'];

const normalize = (value) => String(value || '').trim().toLowerCase();

const filteredProducts = computed(() => searchResults.value);
const quickSaleOptions = computed(() => props.quick_sale_options || []);
const customQuickSaleOptions = computed(() => quickSaleOptions.value.filter((option) => !option.is_default && option.id));
const vatTreatmentOptions = computed(() => props.vat_options?.treatments || []);
const vatRateOptions = computed(() => props.vat_options?.rates || []);

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

const vatLabel = (treatment, rate) => {
    if (treatment === 'exento') return 'IVA exento';
    if (treatment === 'no_gravado') return 'No gravado';

    return `IVA ${Number(rate || defaultVatRate).toLocaleString('es-AR')}%`;
};

const normalizeVatRate = (treatment, rate) => (
    treatment === 'gravado'
        ? Number(rate || defaultVatRate)
        : 0
);

const resetManualVat = () => {
    state.manualItemVatTreatment = defaultVatTreatment;
    state.manualItemVatRate = defaultVatRate;
};

const resetQuickOptionForm = () => {
    quickOptionForm.reset();
    quickOptionForm.name = '';
    quickOptionForm.description = '';
    quickOptionForm.default_amount = '';
    quickOptionForm.vat_treatment = defaultVatTreatment;
    quickOptionForm.vat_rate = defaultVatRate;
    quickOptionForm.is_active = true;
};

const storeQuickOption = () => {
    quickOptionForm
        .transform((data) => ({
            ...data,
            description: String(data.description || '').trim() || null,
            default_amount: data.default_amount === '' ? null : data.default_amount,
            vat_rate: data.vat_treatment === 'gravado' ? data.vat_rate : 0,
        }))
        .post(route('sales.quick-options.store'), {
            preserveScroll: true,
            onSuccess: () => {
                resetQuickOptionForm();
            },
        });
};

const deleteQuickOption = (option) => {
    if (!option?.id) return;

    router.delete(route('sales.quick-options.destroy', option.id), {
        preserveScroll: true,
    });
};

const fetchProducts = async (term = state.search) => {
    const normalizedTerm = String(term || '').trim();

    if (normalizedTerm === '') {
        searchResults.value = knownProducts.value.slice(0, 20);
        syncSelection();
        return searchResults.value;
    }

    const requestId = ++lastSearchRequestId;
    isLoadingProducts.value = true;

    try {
        const { data } = await window.axios.get(route('sales.products.search'), {
            params: { search: normalizedTerm },
        });

        if (requestId !== lastSearchRequestId) {
            return searchResults.value;
        }

        searchResults.value = Array.isArray(data?.products) ? data.products : [];
        searchResults.value.forEach((product) => {
            if (!knownProducts.value.find((entry) => entry.id === product.id)) {
                knownProducts.value.push(product);
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
    searchMutationVersion += 1;

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

watch(() => form.payment_status, (value) => {
    if (form.payment_provider === 'mercadopago_point' && value !== 'paid') {
        form.payment_provider = 'manual';
    }

    if (value === 'pending') {
        form.payment_method = null;
        form.amount_received = '';
        form.paid_amount = '';
    } else if (!form.payment_method) {
        form.payment_method = 'cash';
    }

    if (value !== 'partial') {
        form.paid_amount = '';
    }
});

watch(() => form.payment_provider, (value) => {
    if (value === 'mercadopago_point') {
        form.payment_status = 'paid';
        form.amount_received = '';
        form.paid_amount = '';

        if (!pointPaymentMethodValues.includes(form.payment_method)) {
            form.payment_method = 'debit_card';
        }

        return;
    }

    if (!form.payment_method) {
        form.payment_method = 'cash';
    }
});

watch(() => form.payment_method, (value) => {
    if (form.payment_provider === 'mercadopago_point' && !pointPaymentMethodValues.includes(value)) {
        form.payment_method = 'debit_card';

        return;
    }

    if (value !== 'cash') {
        form.amount_received = '';
    }
});

watch(() => form.fiscal_customer.iva_condition, (value) => {
    if (['responsable_inscripto', 'monotributo', 'exento'].includes(value)) {
        form.fiscal_customer.document_type = 'CUIT';
    }
});

watch(() => state.manualItemVatTreatment, (value) => {
    if (value !== 'gravado') {
        state.manualItemVatRate = 0;
        return;
    }

    if (Number(state.manualItemVatRate || 0) <= 0) {
        state.manualItemVatRate = defaultVatRate;
    }
});

watch(() => quickOptionForm.vat_treatment, (value) => {
    if (value !== 'gravado') {
        quickOptionForm.vat_rate = 0;
        return;
    }

    if (Number(quickOptionForm.vat_rate || 0) <= 0) {
        quickOptionForm.vat_rate = defaultVatRate;
    }
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
            is_manual: false,
            quantity: Number(qty.toFixed(3)),
            unit_price: Number(product.sale_price),
            unit_type: product.unit_type,
            weight_unit: product.weight_unit,
            vat_treatment: product.vat_treatment || 'gravado',
            vat_rate: Number(product.vat_rate ?? 21),
            vat_label: product.vat_label || `IVA ${Number(product.vat_rate ?? 21).toLocaleString('es-AR')}%`,
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

const applyManualPreset = (option) => {
    const label = typeof option === 'string' ? option : option?.name;

    state.manualItemName = label || '';
    state.manualItemAmount = Number(option?.default_amount || 0) > 0
        ? Number(option.default_amount).toFixed(2)
        : '';
    state.manualItemVatTreatment = option?.vat_treatment || defaultVatTreatment;
    state.manualItemVatRate = normalizeVatRate(state.manualItemVatTreatment, option?.vat_rate ?? defaultVatRate);
    state.helperMessage = option?.description || `Concepto rapido seleccionado: ${label}`;

    nextTick(() => {
        manualAmountInput.value?.focus();
    });
};

const addManualItem = () => {
    const detail = String(state.manualItemName || '').trim();
    const amount = Number(state.manualItemAmount || 0);

    if (detail === '') {
        state.helperMessage = 'Escribe un detalle para la venta manual.';
        return;
    }

    if (amount <= 0) {
        state.helperMessage = 'El monto manual debe ser mayor a 0.';
        return;
    }

    const vatTreatment = state.manualItemVatTreatment || defaultVatTreatment;
    const vatRate = normalizeVatRate(vatTreatment, state.manualItemVatRate);

    form.items.push({
        product_id: null,
        product_name: detail,
        is_manual: true,
        quantity: 1,
        unit_price: Number(amount.toFixed(2)),
        vat_treatment: vatTreatment,
        vat_rate: vatRate,
        vat_label: vatLabel(vatTreatment, vatRate),
        quantity_label: 'sin stock',
        price_label: '',
    });

    state.manualItemName = '';
    state.manualItemAmount = '';
    resetManualVat();
    state.helperMessage = `Item manual agregado: ${detail}`;

    nextTick(() => {
        searchInput.value?.focus();
    });
};

const handleSearchEnter = async () => {
    const searchTerm = String(state.search || '').trim();
    if (searchTerm === '') return;

    if (isResolvingSearch.value || searchMutationVersion === lastHandledSearchMutationVersion) {
        return;
    }

    lastHandledSearchMutationVersion = searchMutationVersion;

    if (searchTimer !== null) {
        window.clearTimeout(searchTimer);
        searchTimer = null;
    }

    const exactKnown = findExactCodeMatch(searchTerm, knownProducts.value);

    if (exactKnown) {
        addProductToCart(exactKnown, 'scanner');
        return;
    }

    const activeProductIdAtSubmit = state.activeProductId;
    const highlightedIndexAtSubmit = state.highlightedIndex;
    isResolvingSearch.value = true;

    const results = await fetchProducts(searchTerm);
    const exact = findExactCodeMatch(searchTerm, results);

    try {
        if (exact) {
            addProductToCart(exact, 'scanner');
            return;
        }

        const fallback = results.find((product) => product.id === activeProductIdAtSubmit)
            || results[highlightedIndexAtSubmit]
            || null;

        addProductToCart(fallback, 'manual');
    } finally {
        isResolvingSearch.value = false;
    }
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

const getProductMeta = (product) => {
    if (product?.is_manual) {
        return {
            quantityLabel: 'sin stock',
            priceLabel: '',
            quantityStep: '1',
            quantityMin: '1',
            isGrams: false,
        };
    }

    return {
        quantityLabel: product?.quantity_label || 'un',
        priceLabel: product?.price_label || 'por unidad',
        quantityStep: product?.quantity_step || '1',
        quantityMin: product?.quantity_min || '1',
        isGrams: product?.unit_type === 'weight' && product?.weight_unit === 'g',
    };
};

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
const customerOptions = computed(() => props.customers || []);
const selectedCustomer = computed(() => customerOptions.value.find((customer) => customer.id === form.customer_id) || null);
const fiscalEnabled = computed(() => Boolean(props.fiscal?.enabled));
const fiscalReceiverIvaOptions = computed(() => props.fiscal?.receiver_iva_conditions || []);
const fiscalCustomerWithData = computed(() => Boolean(form.fiscal_customer?.with_data));
const fiscalCustomerRequiresCuit = computed(() => (
    fiscalCustomerWithData.value
    && ['responsable_inscripto', 'monotributo', 'exento'].includes(form.fiscal_customer.iva_condition)
));
const fiscalCustomerReady = computed(() => {
    if (!fiscalCustomerWithData.value) return true;

    const documentNumber = String(form.fiscal_customer.document_number || '').replace(/\D+/g, '');

    return String(form.fiscal_customer.name || '').trim() !== ''
        && ['CUIT', 'DNI'].includes(form.fiscal_customer.document_type)
        && String(form.fiscal_customer.iva_condition || '').trim() !== ''
        && documentNumber !== ''
        && (!fiscalCustomerRequiresCuit.value || (form.fiscal_customer.document_type === 'CUIT' && documentNumber.length === 11));
});
const isPendingSale = computed(() => form.payment_status === 'pending');
const isPartialSale = computed(() => form.payment_status === 'partial');
const requiresImmediatePayment = computed(() => form.payment_status !== 'pending');
const isCashPayment = computed(() => form.payment_method === 'cash');
const usesMercadoPagoPoint = computed(() => form.payment_provider === 'mercadopago_point');
const canUseMercadoPagoPoint = computed(() => Boolean(props.mercadopago_point?.enabled));
const pointPaymentMethodOptions = computed(() => (
    paymentMethodOptions.filter((option) => pointPaymentMethodValues.includes(option.value))
));
const activePaymentMethodOptions = computed(() => (
    usesMercadoPagoPoint.value ? pointPaymentMethodOptions.value : paymentMethodOptions
));
const selectedPaymentMethod = computed(() => (
    paymentMethodOptions.find((option) => option.value === form.payment_method) || null
));
const selectedPaymentMethodLabel = computed(() => selectedPaymentMethod.value?.label || paymentMethodLabel(form.payment_method));
const advancedSaleSettingsEnabled = computed(() => Boolean(props.advanced_sale_settings?.enabled));
const saleSectorOptions = computed(() => props.advanced_sale_settings?.sale_sectors || []);
const paymentDestinationOptions = computed(() => props.advanced_sale_settings?.payment_destinations || []);
const paidAmount = computed(() => (
    usesMercadoPagoPoint.value
        ? 0
        : form.payment_status === 'paid'
        ? total.value
        : Math.max(0, Number(form.paid_amount || 0))
));
const pendingAmount = computed(() => Math.max(0, Number((total.value - paidAmount.value).toFixed(2))));
const amountReceived = computed(() => Number(form.amount_received || 0));
const expectedCashCollection = computed(() => (
    isCashPayment.value && requiresImmediatePayment.value ? paidAmount.value : 0
));
const remaining = computed(() => (
    isCashPayment.value && requiresImmediatePayment.value
        ? Math.max(0, Number((expectedCashCollection.value - amountReceived.value).toFixed(2)))
        : 0
));
const changeAmount = computed(() => (
    isCashPayment.value && requiresImmediatePayment.value
        ? Math.max(0, Number((amountReceived.value - expectedCashCollection.value).toFixed(2)))
        : 0
));
const requiresPaymentDestination = computed(() => (
    advancedSaleSettingsEnabled.value
    && requiresImmediatePayment.value
    && (paidAmount.value > 0 || usesMercadoPagoPoint.value)
    && paymentMethodRequiresDestination(form.payment_method)
));
const canSubmit = computed(() => (
    form.items.length > 0
    && !form.processing
    && (!advancedSaleSettingsEnabled.value || form.sale_sector_id)
    && (!requiresPaymentDestination.value || form.payment_destination_id)
    && (!requiresImmediatePayment.value || form.payment_method)
    && (!isPendingSale.value || form.customer_id)
    && (!isPartialSale.value || (form.customer_id && paidAmount.value > 0 && pendingAmount.value > 0))
    && (!isCashPayment.value || remaining.value === 0)
    && (!usesMercadoPagoPoint.value || canUseMercadoPagoPoint.value)
    && (!fiscalEnabled.value || fiscalCustomerReady.value)
));
const itemErrorMessages = computed(() => Array.from(new Set(
    Object.entries(form.errors || {})
        .filter(([key]) => key === 'items' || key.startsWith('items.'))
        .map(([, message]) => message)
        .filter(Boolean),
)));
const cartItemsCount = computed(() => form.items.length);
const manualItemsCount = computed(() => form.items.filter((item) => item.is_manual).length);
const cartUnitsCount = computed(() => form.items.reduce((carry, item) => carry + (Number(item.quantity) || 0), 0));
const saleStatusLabel = computed(() => (
    usesMercadoPagoPoint.value
        ? 'A cobrar Point'
        : form.payment_status === 'paid'
        ? 'Pagado completo'
        : (form.payment_status === 'partial' ? 'Pago parcial' : 'Fiado')
));
const paymentStatusTone = computed(() => (
    usesMercadoPagoPoint.value
        ? 'warning'
        : form.payment_status === 'paid'
        ? 'success'
        : (form.payment_status === 'partial' ? 'warning' : 'danger')
));
const submitButtonLabel = computed(() => (
    usesMercadoPagoPoint.value ? 'Enviar a Point' : 'Confirmar venta'
));
const readyMessage = computed(() => (
    usesMercadoPagoPoint.value ? 'Lista para enviar a Point.' : 'Lista para confirmar.'
));
const mercadoPagoPointUnavailableMessage = computed(() => {
    if (canUseMercadoPagoPoint.value) return '';

    const missing = [];

    if (!props.mercadopago_point?.access_token_configured) missing.push('access token');
    if (!props.mercadopago_point?.terminal_configured) missing.push('terminal Point');

    return missing.length
        ? `Falta configurar ${missing.join(' y ')}.`
        : 'Mercado Pago Point no esta configurado.';
});
const activeProductStock = computed(() => (
    activeProduct.value ? getDisplayedStock(activeProduct.value) : null
));
const summaryWarnings = computed(() => {
    const warnings = [];

    if (!form.items.length) {
        warnings.push('Agrega al menos un item para poder confirmar la venta.');
    }

    if (advancedSaleSettingsEnabled.value && !form.sale_sector_id) {
        warnings.push('Falta seleccionar el sector o punto de venta.');
    }

    if (requiresPaymentDestination.value && !form.payment_destination_id) {
        warnings.push('Falta indicar el destino de cobro donde entra el dinero.');
    }

    if ((isPendingSale.value || isPartialSale.value) && !form.customer_id) {
        warnings.push('Las ventas fiadas o parciales requieren cliente asociado.');
    }

    if (usesMercadoPagoPoint.value && !canUseMercadoPagoPoint.value) {
        warnings.push(mercadoPagoPointUnavailableMessage.value);
    }

    if (fiscalEnabled.value && fiscalCustomerWithData.value && !fiscalCustomerReady.value) {
        warnings.push('Completa los datos fiscales del receptor o desactiva la solicitud con datos.');
    }

    if (isPartialSale.value && (paidAmount.value <= 0 || pendingAmount.value <= 0)) {
        warnings.push('El pago parcial debe dejar una parte cobrada y otra pendiente.');
    }

    if (isCashPayment.value && requiresImmediatePayment.value && remaining.value > 0) {
        warnings.push('El monto recibido en efectivo aun no cubre el cobro inicial.');
    }

    return warnings;
});
const summaryTone = computed(() => {
    if (summaryWarnings.value.length >= 2) return 'danger';
    if (summaryWarnings.value.length > 0) return 'warning';
    return 'success';
});
const saleDraftStorageKey = 'saas-gestion-comercial:sales-create-draft';
const receiptFileName = computed(() => form.receipt?.name || '');
const hasFiscalCustomerErrors = computed(() => (
    Object.keys(form.errors || {}).some((key) => key.startsWith('fiscal_customer.'))
));
const saleDetailsOpen = computed(() => (
    fiscalCustomerWithData.value
    || hasFiscalCustomerErrors.value
    || Boolean(form.errors.customer_id || form.errors.receipt || form.errors.sold_at || form.errors.discount || form.errors.notes)
    || Boolean(receiptFileName.value)
    || String(form.notes || '').trim() !== ''
    || Number(form.discount || 0) > 0
));

const {
    applyCustomerFromQuery,
    buildSnapshot: buildDraftSnapshot,
    clear: clearDraft,
    persist: persistDraft,
    restore: restoreDraft,
} = useSaleDraft({
    form,
    state,
    customerOptions,
    storageKey: saleDraftStorageKey,
});

watch(() => buildDraftSnapshot(), () => {
    persistDraft();
}, { deep: true });

watch(total, (value) => {
    if (form.payment_status === 'partial' && Number(form.paid_amount || 0) >= value) {
        form.paid_amount = value > 0 ? Math.max(value - 1, 0).toFixed(2) : '';
    }

    if (form.payment_status === 'paid' && isCashPayment.value && (form.amount_received === '' || Number(form.amount_received || 0) < value)) {
        form.amount_received = value > 0 ? value.toFixed(2) : '';
    }
});

watch(requiresPaymentDestination, (value) => {
    if (!value) {
        form.payment_destination_id = null;
    }
});

const moneyFormatter = new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    minimumFractionDigits: 2,
});

const money = (value) => moneyFormatter.format(Number(value) || 0);
const selectedSaleSectorName = computed(() => (
    saleSectorOptions.value.find((item) => String(item.id) === String(form.sale_sector_id))?.name || '-'
));
const selectedPaymentDestination = computed(() => (
    paymentDestinationOptions.value.find((item) => String(item.id) === String(form.payment_destination_id)) || null
));
const selectedPaymentDestinationName = computed(() => {
    if (!requiresPaymentDestination.value) {
        return requiresImmediatePayment.value ? 'No aplica' : 'Sin cobro inicial';
    }

    return selectedPaymentDestination.value?.name || '-';
});

const applyQuickAmount = (mode, amount = 0) => {
    if (!isCashPayment.value || !requiresImmediatePayment.value) return;

    if (mode === 'exact') {
        form.amount_received = expectedCashCollection.value.toFixed(2);
        return;
    }

    if (mode === 'clear') {
        form.amount_received = '';
        return;
    }

    const nextAmount = Number((amountReceived.value + amount).toFixed(2));
    form.amount_received = nextAmount.toFixed(2);
};

const setReceipt = (event) => {
    const [receipt] = event.target?.files || [];

    form.receipt = receipt || null;
};

const submit = () => {
    form
        .transform((data) => ({
            ...data,
            customer_id: data.customer_id,
            fiscal_customer: fiscalEnabled.value && data.fiscal_customer?.with_data
                ? {
                    ...data.fiscal_customer,
                    document_number: String(data.fiscal_customer.document_number || '').replace(/\D+/g, ''),
                }
                : null,
            payment_status: data.payment_status,
            payment_provider: usesMercadoPagoPoint.value ? 'mercadopago_point' : 'manual',
            payment_method: requiresImmediatePayment.value ? data.payment_method : null,
            sale_sector_id: advancedSaleSettingsEnabled.value ? data.sale_sector_id : null,
            payment_destination_id: requiresPaymentDestination.value ? data.payment_destination_id : null,
            amount_received: isCashPayment.value && requiresImmediatePayment.value ? data.amount_received : null,
            paid_amount: data.payment_status === 'partial' ? data.paid_amount : null,
            receipt: data.receipt,
            items: data.items.map((item) => ({
                product_id: item.product_id,
                product_name: item.product_id === null ? item.product_name : null,
                quantity: item.quantity,
                unit_price: item.unit_price,
                vat_treatment: item.vat_treatment || null,
                vat_rate: item.vat_rate ?? null,
            })),
        }))
        .post(route('sales.store'), {
            forceFormData: true,
            onSuccess: () => {
                clearDraft();
            },
        });
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
    restoreDraft();
    applyCustomerFromQuery();
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
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <h2 class="text-2xl font-bold text-slate-100">Nueva venta</h2>
                    <p class="mt-1 text-sm text-slate-300/80">Mostrador</p>
                </div>
                <div class="flex w-full sm:w-auto sm:justify-end">
                    <Link :href="route('sales.index')" class="inline-flex w-full items-center justify-center rounded-xl border border-cyan-100/20 px-4 py-2 text-sm font-semibold text-slate-300 transition hover:bg-slate-800/60 hover:text-slate-100 sm:w-auto">Volver</Link>
                </div>
            </div>
        </template>

        <form class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_24rem]" @submit.prevent>
            <div class="grid self-start gap-6">
            <AppPanel title="Venta" subtitle="" padding="sm">
                <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_8rem_9rem]">
                    <div>
                        <label for="product-search" class="mb-1 block text-sm font-medium text-slate-300">Producto</label>
                        <input
                            id="product-search"
                            ref="searchInput"
                            v-model="state.search"
                            type="text"
                            class="h-11 w-full rounded-xl border-cyan-100/25 text-base"
                            placeholder="Escanear o buscar"
                            aria-controls="product-results"
                            aria-autocomplete="list"
                            role="combobox"
                            :aria-expanded="filteredProducts.length ? 'true' : 'false'"
                            :disabled="isResolvingSearch"
                            @keydown="handleSearchKeydown"
                        >
                    </div>
                    <div>
                        <label for="product-qty" class="mb-1 block text-sm font-medium text-slate-300">Cant. <span class="text-xs text-slate-400">({{ activeProductMeta.quantityLabel }})</span></label>
                        <input
                            id="product-qty"
                            ref="quantityInput"
                            v-model.number="state.quantity"
                            type="number"
                            :min="activeProductMeta.quantityMin"
                            :step="activeProductMeta.quantityStep"
                            class="h-11 w-full rounded-xl border-cyan-100/25 text-base"
                        >
                    </div>
                    <div class="flex items-end">
                        <button
                            type="button"
                            class="h-11 w-full rounded-xl bg-cyan-600 px-3 text-sm font-semibold text-white hover:bg-cyan-500 disabled:opacity-60"
                            :disabled="isResolvingSearch"
                            @click="addProductToCart(activeProduct, 'manual')"
                        >
                            Agregar
                        </button>
                    </div>
                </div>

                <p class="mt-2 text-xs text-slate-300/80" aria-live="polite">{{ state.helperMessage }}</p>

                <div class="mt-3 app-chip-row">
                    <StatusBadge v-if="activeProduct" tone="info" size="sm" :label="activeProduct.name" />
                    <StatusBadge v-if="activeProductStock" :tone="Number(activeProductStock.value) > 0 ? 'success' : 'danger'" size="sm" :label="`Stock ${activeProductStock.value} ${activeProductStock.label}`" />
                    <StatusBadge v-if="activeProduct" tone="neutral" size="sm" :label="money(activeProduct.sale_price)" />
                    <StatusBadge v-if="manualItemsCount" tone="warning" size="sm" :label="`${manualItemsCount} manuales en el carrito`" />
                </div>

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

                <div class="mt-4 rounded-xl border border-amber-200/20 bg-amber-300/10 p-4">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-amber-100">Venta rapida sin stock</h3>
                        </div>
                        <div class="flex flex-wrap gap-2 sm:justify-end">
                            <button
                                v-for="option in quickSaleOptions"
                                :key="option.key || option.id || option.name"
                                type="button"
                                class="inline-flex items-center gap-2 rounded-lg border border-amber-100/25 px-3 py-1.5 text-xs font-semibold text-amber-100 hover:bg-amber-200/10"
                                :title="option.description || option.name"
                                @click="applyManualPreset(option)"
                            >
                                <span>{{ option.name }}</span>
                                <span v-if="Number(option.default_amount || 0) > 0" class="text-amber-50/70">{{ money(option.default_amount) }}</span>
                            </button>
                        </div>
                    </div>

                    <div class="mt-3 grid gap-3 xl:grid-cols-[minmax(0,1fr)_11rem_15rem_auto]">
                        <div>
                            <label for="manual-item-name" class="mb-1 block text-sm font-medium text-amber-50/90">Detalle</label>
                            <input
                                id="manual-item-name"
                                v-model="state.manualItemName"
                                type="text"
                                class="w-full rounded-xl border-amber-100/25 bg-slate-950/35 text-sm text-slate-100"
                                placeholder="Ej. Material fraccionado"
                                @keydown.enter.prevent="addManualItem"
                            >
                        </div>
                        <div>
                            <label for="manual-item-amount" class="mb-1 block text-sm font-medium text-amber-50/90">Monto</label>
                            <input
                                id="manual-item-amount"
                                ref="manualAmountInput"
                                v-model="state.manualItemAmount"
                                type="number"
                                min="0"
                                step="0.01"
                                class="w-full rounded-xl border-amber-100/25 bg-slate-950/35 text-sm text-slate-100"
                                placeholder="0.00"
                                @keydown.enter.prevent="addManualItem"
                            >
                        </div>
                        <div>
                            <label for="manual-item-vat-treatment" class="mb-1 block text-sm font-medium text-amber-50/90">IVA</label>
                            <div class="grid grid-cols-2 gap-2">
                                <select
                                    id="manual-item-vat-treatment"
                                    v-model="state.manualItemVatTreatment"
                                    class="w-full rounded-xl border-amber-100/25 bg-slate-950/35 text-sm text-slate-100"
                                >
                                    <option v-for="option in vatTreatmentOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                                <select
                                    v-if="state.manualItemVatTreatment === 'gravado'"
                                    v-model.number="state.manualItemVatRate"
                                    class="w-full rounded-xl border-amber-100/25 bg-slate-950/35 text-sm text-slate-100"
                                >
                                    <option v-for="option in vatRateOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                                <span v-else class="rounded-xl border border-amber-100/25 px-3 py-2 text-xs font-semibold text-amber-50/80">{{ vatLabel(state.manualItemVatTreatment, state.manualItemVatRate) }}</span>
                            </div>
                        </div>
                        <div class="flex items-end">
                            <button
                                type="button"
                                class="w-full rounded-xl border border-amber-100/25 px-3 py-2 text-sm font-semibold text-amber-100 hover:bg-amber-200/10"
                                @click="addManualItem"
                            >
                                Agregar monto
                            </button>
                        </div>
                    </div>

                    <details v-if="can_manage_quick_sale_options" class="mt-4 border-t border-amber-100/20 pt-3">
                        <summary class="cursor-pointer text-sm font-semibold text-amber-100">
                            Configurar opciones rapidas
                        </summary>

                        <div class="mt-3 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h4 class="text-sm font-semibold text-amber-100">Opciones rapidas del comercio</h4>
                                <p class="text-xs text-amber-50/70">Agrega accesos propios para ferreteria, lubricentro, servicios o venta suelta.</p>
                            </div>
                            <StatusBadge v-if="customQuickSaleOptions.length" tone="warning" size="sm" :label="`${customQuickSaleOptions.length} propias`" />
                        </div>

                        <div class="mt-3 grid gap-3 xl:grid-cols-[minmax(0,1fr)_10rem_15rem_auto]">
                            <div>
                                <label for="quick-option-name" class="mb-1 block text-xs font-medium text-amber-50/90">Nombre</label>
                                <input
                                    id="quick-option-name"
                                    v-model="quickOptionForm.name"
                                    type="text"
                                    class="w-full rounded-xl border-amber-100/25 bg-slate-950/35 text-sm text-slate-100"
                                    placeholder="Ej. Tornilleria suelta"
                                >
                                <p v-if="quickOptionForm.errors.name" class="mt-1 text-xs text-rose-300">{{ quickOptionForm.errors.name }}</p>
                            </div>
                            <div>
                                <label for="quick-option-amount" class="mb-1 block text-xs font-medium text-amber-50/90">Monto sugerido</label>
                                <input
                                    id="quick-option-amount"
                                    v-model="quickOptionForm.default_amount"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="w-full rounded-xl border-amber-100/25 bg-slate-950/35 text-sm text-slate-100"
                                    placeholder="Opcional"
                                >
                                <p v-if="quickOptionForm.errors.default_amount" class="mt-1 text-xs text-rose-300">{{ quickOptionForm.errors.default_amount }}</p>
                            </div>
                            <div>
                                <label for="quick-option-vat-treatment" class="mb-1 block text-xs font-medium text-amber-50/90">IVA</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <select
                                        id="quick-option-vat-treatment"
                                        v-model="quickOptionForm.vat_treatment"
                                        class="w-full rounded-xl border-amber-100/25 bg-slate-950/35 text-sm text-slate-100"
                                    >
                                        <option v-for="option in vatTreatmentOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                    </select>
                                    <select
                                        v-if="quickOptionForm.vat_treatment === 'gravado'"
                                        v-model.number="quickOptionForm.vat_rate"
                                        class="w-full rounded-xl border-amber-100/25 bg-slate-950/35 text-sm text-slate-100"
                                    >
                                        <option v-for="option in vatRateOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                    </select>
                                    <span v-else class="rounded-xl border border-amber-100/25 px-3 py-2 text-xs font-semibold text-amber-50/80">{{ vatLabel(quickOptionForm.vat_treatment, quickOptionForm.vat_rate) }}</span>
                                </div>
                                <p v-if="quickOptionForm.errors.vat_treatment" class="mt-1 text-xs text-rose-300">{{ quickOptionForm.errors.vat_treatment }}</p>
                                <p v-if="quickOptionForm.errors.vat_rate" class="mt-1 text-xs text-rose-300">{{ quickOptionForm.errors.vat_rate }}</p>
                            </div>
                            <div class="flex items-end">
                                <button
                                    type="button"
                                    class="w-full rounded-xl bg-amber-200 px-3 py-2 text-sm font-semibold text-slate-950 hover:bg-amber-100 disabled:opacity-60"
                                    :disabled="quickOptionForm.processing"
                                    @click="storeQuickOption"
                                >
                                    Guardar
                                </button>
                            </div>
                        </div>

                        <div class="mt-3">
                            <label for="quick-option-description" class="mb-1 block text-xs font-medium text-amber-50/90">Descripcion opcional</label>
                            <input
                                id="quick-option-description"
                                v-model="quickOptionForm.description"
                                type="text"
                                class="w-full rounded-xl border-amber-100/25 bg-slate-950/35 text-sm text-slate-100"
                                placeholder="Ej. Para ventas por metro, litro, unidad suelta o mano de obra."
                            >
                            <p v-if="quickOptionForm.errors.description" class="mt-1 text-xs text-rose-300">{{ quickOptionForm.errors.description }}</p>
                        </div>

                        <div v-if="customQuickSaleOptions.length" class="mt-3 flex flex-wrap gap-2">
                            <span v-for="option in customQuickSaleOptions" :key="`manage-${option.id}`" class="inline-flex items-center gap-2 rounded-lg border border-amber-100/20 px-3 py-1.5 text-xs font-semibold text-amber-50/90">
                                {{ option.name }}
                                <button type="button" class="text-rose-200 hover:text-rose-100" @click="deleteQuickOption(option)">Quitar</button>
                            </span>
                        </div>
                    </details>
                </div>

                <SaleCart :items="form.items" :errors="itemErrorMessages" :line-subtotal="getLineSubtotal" @remove="removeItem" />
            </AppPanel>

            <details class="rounded-2xl border border-cyan-100/15 bg-slate-950/30 p-4 text-slate-100" :open="saleDetailsOpen">
                <summary class="cursor-pointer text-sm font-semibold text-slate-200">
                    Mas datos de venta
                </summary>
                <div class="mt-4">
                <div v-if="advancedSaleSettingsEnabled" class="hidden mb-4 rounded-2xl border border-cyan-200/20 bg-slate-950/35 p-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <h3 class="text-base font-semibold text-slate-100">Contexto de venta</h3>
                            <p class="mt-1 text-sm text-slate-300/80">Selecciona el sector y el destino de cobro configurados para este comercio.</p>
                        </div>
                        <span class="inline-flex w-fit rounded-full bg-cyan-400/15 px-3 py-1 text-xs font-semibold text-cyan-100">Exclusivo por comercio</span>
                    </div>

                    <div class="mt-4 grid gap-3 md:grid-cols-2">
                        <div>
                            <label for="sale-sector" class="mb-1 block text-sm font-medium text-slate-300">Sector / punto de venta</label>
                            <select
                                id="sale-sector"
                                v-model="form.sale_sector_id"
                                class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100"
                            >
                                <option :value="null">Seleccionar sector</option>
                                <option v-for="sector in saleSectorOptions" :key="sector.id" :value="sector.id">
                                    {{ sector.name }}
                                </option>
                            </select>
                            <p v-if="form.errors.sale_sector_id" class="mt-1 text-xs text-rose-300">
                                {{ form.errors.sale_sector_id }}
                            </p>
                        </div>

                        <div>
                            <label for="payment-destination" class="mb-1 block text-sm font-medium text-slate-300">Destino de cobro</label>
                            <select
                                id="payment-destination"
                                v-model="form.payment_destination_id"
                                :disabled="!requiresPaymentDestination"
                                class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100"
                            >
                                <option :value="null">{{ requiresPaymentDestination ? 'Seleccionar destino' : (isCashPayment && requiresImmediatePayment ? 'No aplica en efectivo' : 'Sin cobro inicial') }}</option>
                                <option v-for="destination in paymentDestinationOptions" :key="destination.id" :value="destination.id">
                                    {{ destination.name }}
                                </option>
                            </select>
                            <p v-if="!requiresPaymentDestination" class="mt-1 text-xs text-slate-400">
                                {{ isCashPayment && requiresImmediatePayment
                                    ? 'En pagos en efectivo no se asigna destino de cobro.'
                                    : 'El destino de cobro se pide cuando entra dinero por transferencia, QR o tarjeta.' }}
                            </p>
                            <div v-if="requiresPaymentDestination && selectedPaymentDestination" class="mt-2 rounded-lg border border-cyan-100/15 bg-slate-900/45 px-3 py-2 text-xs text-slate-300">
                                <p class="font-semibold text-slate-100">{{ selectedPaymentDestination.name }}</p>
                                <p v-if="selectedPaymentDestination.account_holder" class="mt-1">Titular: {{ selectedPaymentDestination.account_holder }}</p>
                                <p v-if="selectedPaymentDestination.reference" class="mt-1">Alias / referencia: {{ selectedPaymentDestination.reference }}</p>
                                <p v-if="selectedPaymentDestination.account_number" class="mt-1">Dato de cobro: {{ selectedPaymentDestination.account_number }}</p>
                            </div>
                            <p v-if="form.errors.payment_destination_id" class="mt-1 text-xs text-rose-300">
                                {{ form.errors.payment_destination_id }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="mb-4 grid gap-4 lg:grid-cols-[minmax(0,1.15fr)_minmax(0,0.85fr)]">
                    <article class="hidden rounded-2xl border border-cyan-100/20 bg-slate-950/35 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="text-base font-semibold text-slate-100">Cliente</h3>
                                <p class="mt-1 text-sm text-slate-300/80">Para ventas fiadas o parciales, el cliente es obligatorio.</p>
                            </div>
                            <Link :href="route('customers.create', { return_to: 'sales.create' })" class="inline-flex items-center rounded-lg border border-cyan-100/25 px-3 py-2 text-xs font-semibold text-slate-300 hover:bg-slate-800/70">
                                Nuevo cliente
                            </Link>
                        </div>

                        <div class="mt-4 grid gap-3 md:grid-cols-[minmax(0,1fr)_auto]">
                            <div>
                                <label for="sale-customer" class="mb-1 block text-sm font-medium text-slate-300">Cliente asociado</label>
                                <select
                                    id="sale-customer"
                                    v-model="form.customer_id"
                                    class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100"
                                >
                                    <option :value="null">Consumidor final / sin cliente</option>
                                    <option v-for="customer in customerOptions" :key="customer.id" :value="customer.id">
                                        {{ customer.name }}
                                    </option>
                                </select>
                                <p v-if="form.errors.customer_id" class="mt-1 text-xs text-rose-300">
                                    {{ form.errors.customer_id }}
                                </p>
                            </div>
                            <div v-if="selectedCustomer" class="rounded-xl border border-cyan-100/15 bg-slate-900/45 px-4 py-3 text-sm text-slate-300">
                                <p class="font-semibold text-slate-100">{{ selectedCustomer.name }}</p>
                                <p class="mt-1 text-xs text-slate-400">{{ selectedCustomer.phone || 'Sin telefono' }} · {{ selectedCustomer.email || 'Sin email' }}</p>
                                <p class="mt-2 text-xs">Saldo actual: <strong class="text-slate-100">{{ money(selectedCustomer.current_balance) }}</strong></p>
                            </div>
                        </div>

                        <div v-if="fiscalEnabled" class="mt-4 rounded-xl border border-cyan-100/15 bg-slate-900/45 p-4">
                            <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-100">
                                <input v-model="form.fiscal_customer.with_data" type="checkbox" class="rounded border-cyan-100/25 bg-slate-950/35 text-indigo-500 focus:ring-indigo-500">
                                Cliente solicita factura con datos
                            </label>
                            <p class="mt-2 text-xs text-slate-400">Sin datos fiscales, el receptor queda como consumidor final. La API fiscal define el comprobante automaticamente.</p>

                            <div v-if="form.fiscal_customer.with_data" class="mt-4 grid gap-3 md:grid-cols-2">
                                <div class="space-y-1 md:col-span-2">
                                    <label class="text-sm font-medium text-slate-300">Nombre / razon social</label>
                                    <input v-model="form.fiscal_customer.name" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Cliente o razon social" />
                                    <p v-if="form.errors['fiscal_customer.name']" class="text-xs text-rose-300">{{ form.errors['fiscal_customer.name'] }}</p>
                                </div>

                                <div class="space-y-1">
                                    <label class="text-sm font-medium text-slate-300">Tipo de documento</label>
                                    <select v-model="form.fiscal_customer.document_type" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100">
                                        <option value="CUIT">CUIT</option>
                                        <option value="DNI">DNI</option>
                                    </select>
                                    <p v-if="form.errors['fiscal_customer.document_type']" class="text-xs text-rose-300">{{ form.errors['fiscal_customer.document_type'] }}</p>
                                </div>

                                <div class="space-y-1">
                                    <label class="text-sm font-medium text-slate-300">Numero</label>
                                    <input v-model="form.fiscal_customer.document_number" type="text" inputmode="numeric" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Sin guiones" />
                                    <p v-if="form.errors['fiscal_customer.document_number']" class="text-xs text-rose-300">{{ form.errors['fiscal_customer.document_number'] }}</p>
                                </div>

                                <div class="space-y-1">
                                    <label class="text-sm font-medium text-slate-300">Condicion frente al IVA</label>
                                    <select v-model="form.fiscal_customer.iva_condition" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100">
                                        <option v-for="option in fiscalReceiverIvaOptions" :key="option.value" :value="option.value">
                                            {{ option.label }}
                                        </option>
                                    </select>
                                    <p v-if="form.errors['fiscal_customer.iva_condition']" class="text-xs text-rose-300">{{ form.errors['fiscal_customer.iva_condition'] }}</p>
                                </div>

                                <div class="space-y-1">
                                    <label class="text-sm font-medium text-slate-300">Domicilio fiscal</label>
                                    <input v-model="form.fiscal_customer.address" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Direccion" />
                                    <p v-if="form.errors['fiscal_customer.address']" class="text-xs text-rose-300">{{ form.errors['fiscal_customer.address'] }}</p>
                                </div>
                            </div>
                        </div>
                    </article>

                    <article class="rounded-2xl border border-cyan-100/20 bg-slate-950/35 p-4">
                        <h3 class="text-base font-semibold text-slate-100">Condicion de pago</h3>
                        <div class="mt-4 grid gap-3">
                            <label class="rounded-xl border border-cyan-100/15 bg-slate-900/45 p-3 text-sm text-slate-300">
                                <input v-model="form.payment_status" type="radio" class="mr-2" value="paid">
                                Pagado completo
                            </label>
                            <label class="rounded-xl border border-cyan-100/15 bg-slate-900/45 p-3 text-sm text-slate-300">
                                <input v-model="form.payment_status" type="radio" class="mr-2" value="partial">
                                Pago parcial
                            </label>
                            <label class="rounded-xl border border-cyan-100/15 bg-slate-900/45 p-3 text-sm text-slate-300">
                                <input v-model="form.payment_status" type="radio" class="mr-2" value="pending">
                                Fiado total
                            </label>
                        </div>
                    </article>
                </div>

                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <div>
                        <label for="sold-at" class="mb-1 block text-sm font-medium text-slate-300">Fecha y hora</label>
                        <input id="sold-at" v-model="form.sold_at" type="datetime-local" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 sale-calendar-input" />
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

                <div v-if="receipt_feature_available" class="mt-4 rounded-2xl border border-cyan-100/20 bg-slate-950/35 p-4">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <h3 class="text-base font-semibold text-slate-100">Comprobante adjunto</h3>
                            <p class="mt-1 text-sm text-slate-300/80">Opcional. Sirve para guardar un ticket, transferencia o foto del respaldo de la venta.</p>
                        </div>
                        <span class="inline-flex w-fit rounded-full bg-cyan-400/15 px-3 py-1 text-xs font-semibold text-cyan-100">PDF o imagen</span>
                    </div>

                    <div class="mt-4 grid gap-3 md:grid-cols-[minmax(0,1fr)_auto]">
                        <div>
                            <label for="sale-receipt" class="mb-1 block text-sm font-medium text-slate-300">Archivo</label>
                            <input
                                id="sale-receipt"
                                type="file"
                                accept="application/pdf,image/jpeg,image/png,image/webp"
                                class="w-full rounded-xl border border-cyan-100/25 bg-slate-950/35 px-3 py-2 text-sm text-slate-100 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-800 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-slate-100"
                                @change="setReceipt"
                            >
                            <p class="mt-2 text-xs text-slate-400">Formatos aceptados: PDF, JPG, PNG o WEBP. Maximo 5 MB.</p>
                            <p v-if="receiptFileName" class="mt-2 text-xs text-cyan-100">Seleccionado: {{ receiptFileName }}</p>
                            <p v-if="form.errors.receipt" class="mt-2 text-xs text-rose-300">{{ form.errors.receipt }}</p>
                        </div>
                        <div class="rounded-xl border border-cyan-100/15 bg-slate-900/45 px-4 py-3 text-sm text-slate-300">
                            <p class="font-semibold text-slate-100">{{ receiptFileName || 'Sin comprobante cargado' }}</p>
                            <p class="mt-2 text-xs text-slate-400">El archivo acompana la venta al verla o imprimirla. No se guarda dentro del borrador local.</p>
                        </div>
                    </div>
                </div>

                <div class="hidden mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
                    <div>
                        <template v-if="requiresImmediatePayment">
                            <label for="payment-method" class="mb-1 block text-sm font-medium text-slate-300">Medio de pago del cobro inicial</label>
                            <select
                                id="payment-method"
                                v-model="form.payment_method"
                                class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100"
                            >
                                <option v-for="option in activePaymentMethodOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                            <p v-if="form.errors.payment_method" class="mt-1 text-xs text-rose-300">
                                {{ form.errors.payment_method }}
                            </p>

                            <div v-if="isPartialSale" class="mt-4 rounded-xl border border-cyan-100/20 bg-slate-950/35 p-4 text-sm text-slate-300">
                                <label for="paid-amount" class="mb-1 block text-sm font-medium text-slate-300">Monto abonado ahora</label>
                                <input
                                    id="paid-amount"
                                    v-model="form.paid_amount"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100"
                                    placeholder="0.00"
                                >
                                <p class="mt-2 text-xs text-slate-400">El resto quedara en cuenta corriente del cliente.</p>
                                <p v-if="form.errors.paid_amount" class="mt-1 text-xs text-rose-300">
                                    {{ form.errors.paid_amount }}
                                </p>
                            </div>

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
                                        Faltan {{ money(remaining) }} para completar el cobro inicial.
                                    </p>
                                    <p v-else class="text-xs font-medium text-emerald-300">
                                        Vuelto calculado: {{ money(changeAmount) }}
                                    </p>
                                </div>
                            </div>

                            <p v-else-if="usesMercadoPagoPoint" class="mt-4 rounded-xl border border-cyan-100/20 bg-slate-950/35 px-4 py-3 text-sm text-slate-300">
                                Se enviara una orden a la terminal Point por {{ money(total) }}.
                            </p>

                            <p v-else class="mt-4 rounded-xl border border-cyan-100/20 bg-slate-950/35 px-4 py-3 text-sm text-slate-300">
                                {{ selectedPaymentMethodLabel }} se registra como cobro manual confirmado en mostrador. No hace falta calcular vuelto.
                            </p>
                        </template>

                        <p v-else class="rounded-xl border border-amber-200/25 bg-amber-300/10 px-4 py-3 text-sm text-amber-50">
                            La venta quedara fiada en su totalidad. El cliente es obligatorio y el saldo pendiente se registrara en cuenta corriente.
                        </p>
                    </div>

                    <div class="rounded-xl bg-slate-950/35 p-4 text-sm text-slate-300">
                        <p v-if="advancedSaleSettingsEnabled">Sector: <strong>{{ selectedSaleSectorName }}</strong></p>
                        <p v-if="advancedSaleSettingsEnabled">Destino: <strong>{{ selectedPaymentDestinationName }}</strong></p>
                        <p>Subtotal: <strong>{{ money(subtotal) }}</strong></p>
                        <p>Descuento: <strong>{{ money(form.discount) }}</strong></p>
                        <p class="mt-2 text-base text-slate-100">Total: <strong>{{ money(total) }}</strong></p>
                        <p class="mt-2">Estado: <strong>{{ saleStatusLabel }}</strong></p>
                        <p>Canal: <strong>{{ usesMercadoPagoPoint ? 'Mercado Pago Point' : 'Manual' }}</strong></p>
                        <p v-if="requiresImmediatePayment">Medio: <strong>{{ selectedPaymentMethodLabel }}</strong></p>
                        <p v-if="selectedCustomer">Cliente: <strong>{{ selectedCustomer.name }}</strong></p>
                        <template v-if="requiresImmediatePayment">
                            <p class="mt-2">Abonado ahora: <strong>{{ money(paidAmount) }}</strong></p>
                            <p>Pendiente: <strong>{{ money(pendingAmount) }}</strong></p>
                        </template>
                        <template v-if="isCashPayment && requiresImmediatePayment">
                            <p class="mt-2">Recibido: <strong>{{ money(amountReceived) }}</strong></p>
                            <p>Vuelto: <strong>{{ money(changeAmount) }}</strong></p>
                        </template>
                        <p v-else-if="isPendingSale" class="mt-2 text-amber-100">Se genera deuda por {{ money(total) }}.</p>
                    </div>
                </div>
                <div class="mt-4 flex justify-end xl:hidden">
                    <button type="button" class="w-full rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-600 disabled:opacity-50 sm:w-auto" :disabled="!canSubmit" @click="submitIfReady">
                        {{ submitButtonLabel }}
                    </button>
                </div>
                </div>
            </details>
            </div>

            <aside class="app-sticky-column">
                <AppPanel title="Cobro" :tone="summaryTone" subtitle="">
                    <div class="app-chip-row">
                        <StatusBadge :tone="paymentStatusTone" :label="saleStatusLabel" />
                        <StatusBadge tone="info" :label="`${cartItemsCount} items`" />
                        <StatusBadge v-if="manualItemsCount" tone="warning" :label="`${manualItemsCount} manuales`" />
                    </div>

                    <div class="mt-4 space-y-4">
                        <div>
                            <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Canal</label>
                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    type="button"
                                    class="h-10 rounded-lg border px-2 text-xs font-semibold transition"
                                    :class="!usesMercadoPagoPoint ? 'border-cyan-300/60 bg-cyan-400/20 text-cyan-50' : 'border-cyan-100/15 bg-slate-950/35 text-slate-300 hover:bg-slate-800/70'"
                                    @click="form.payment_provider = 'manual'"
                                >
                                    Manual
                                </button>
                                <button
                                    type="button"
                                    class="h-10 rounded-lg border px-2 text-xs font-semibold transition"
                                    :class="usesMercadoPagoPoint ? 'border-emerald-300/60 bg-emerald-400/20 text-emerald-50' : 'border-cyan-100/15 bg-slate-950/35 text-slate-300 hover:bg-slate-800/70'"
                                    @click="form.payment_provider = 'mercadopago_point'"
                                >
                                    Point
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Estado</label>
                            <div class="grid grid-cols-3 gap-2">
                                <button
                                    v-for="option in paymentStatusOptions"
                                    :key="option.value"
                                    type="button"
                                    class="h-10 rounded-lg border px-2 text-xs font-semibold transition"
                                    :class="form.payment_status === option.value ? 'border-cyan-300/60 bg-cyan-400/20 text-cyan-50' : 'border-cyan-100/15 bg-slate-950/35 text-slate-300 hover:bg-slate-800/70'"
                                    :disabled="usesMercadoPagoPoint && option.value !== 'paid'"
                                    @click="form.payment_status = option.value"
                                >
                                    {{ option.label }}
                                </button>
                            </div>
                        </div>

                        <div>
                            <div class="mb-1 flex items-center justify-between gap-2">
                                <label for="sale-customer-sidebar" class="text-sm font-medium text-slate-300">Cliente</label>
                                <Link :href="route('customers.create', { return_to: 'sales.create' })" class="text-xs font-semibold text-cyan-100 hover:text-cyan-50">
                                    Nuevo
                                </Link>
                            </div>
                            <select
                                id="sale-customer-sidebar"
                                v-model="form.customer_id"
                                class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100"
                            >
                                <option :value="null">Consumidor final</option>
                                <option v-for="customer in customerOptions" :key="customer.id" :value="customer.id">
                                    {{ customer.name }}
                                </option>
                            </select>
                            <p v-if="form.errors.customer_id" class="mt-1 text-xs text-rose-300">
                                {{ form.errors.customer_id }}
                            </p>
                            <p v-if="selectedCustomer" class="mt-1 text-xs text-slate-400">
                                Saldo: {{ money(selectedCustomer.current_balance) }}
                            </p>
                        </div>

                        <div v-if="advancedSaleSettingsEnabled" class="grid gap-3">
                            <div>
                                <label for="sale-sector-sidebar" class="mb-1 block text-sm font-medium text-slate-300">Sector</label>
                                <select
                                    id="sale-sector-sidebar"
                                    v-model="form.sale_sector_id"
                                    class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100"
                                >
                                    <option :value="null">Seleccionar</option>
                                    <option v-for="sector in saleSectorOptions" :key="sector.id" :value="sector.id">
                                        {{ sector.name }}
                                    </option>
                                </select>
                                <p v-if="form.errors.sale_sector_id" class="mt-1 text-xs text-rose-300">
                                    {{ form.errors.sale_sector_id }}
                                </p>
                            </div>

                            <div>
                                <label for="payment-destination-sidebar" class="mb-1 block text-sm font-medium text-slate-300">Destino</label>
                                <select
                                    id="payment-destination-sidebar"
                                    v-model="form.payment_destination_id"
                                    :disabled="!requiresPaymentDestination"
                                    class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100"
                                >
                                    <option :value="null">{{ requiresPaymentDestination ? 'Seleccionar' : 'No aplica' }}</option>
                                    <option v-for="destination in paymentDestinationOptions" :key="destination.id" :value="destination.id">
                                        {{ destination.name }}
                                    </option>
                                </select>
                                <p v-if="form.errors.payment_destination_id" class="mt-1 text-xs text-rose-300">
                                    {{ form.errors.payment_destination_id }}
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                            <div>
                                <label for="discount-sidebar" class="mb-1 block text-sm font-medium text-slate-300">Descuento</label>
                                <input
                                    id="discount-sidebar"
                                    v-model.number="form.discount"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100"
                                    placeholder="0.00"
                                >
                            </div>

                            <div v-if="requiresImmediatePayment">
                                <label class="mb-2 block text-sm font-medium text-slate-300">Medio</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <button
                                        v-for="option in activePaymentMethodOptions"
                                        :key="option.value"
                                        type="button"
                                        class="min-h-10 rounded-lg border px-2 py-2 text-xs font-semibold transition"
                                        :class="form.payment_method === option.value ? 'border-emerald-300/60 bg-emerald-400/20 text-emerald-50' : 'border-cyan-100/15 bg-slate-950/35 text-slate-300 hover:bg-slate-800/70'"
                                        @click="form.payment_method = option.value"
                                    >
                                        {{ option.label }}
                                    </button>
                                </div>
                                <p v-if="form.errors.payment_method" class="mt-1 text-xs text-rose-300">
                                    {{ form.errors.payment_method }}
                                </p>
                                <p v-if="usesMercadoPagoPoint && !canUseMercadoPagoPoint" class="mt-2 text-xs text-amber-100">
                                    {{ mercadoPagoPointUnavailableMessage }}
                                </p>
                            </div>
                        </div>

                        <div v-if="isPartialSale" class="grid gap-2">
                            <label for="paid-amount-sidebar" class="text-sm font-medium text-slate-300">Abonado ahora</label>
                            <input
                                id="paid-amount-sidebar"
                                v-model="form.paid_amount"
                                type="number"
                                min="0"
                                step="0.01"
                                class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100"
                                placeholder="0.00"
                            >
                            <p v-if="form.errors.paid_amount" class="text-xs text-rose-300">
                                {{ form.errors.paid_amount }}
                            </p>
                        </div>

                        <div v-if="isCashPayment && requiresImmediatePayment" class="grid gap-3">
                            <div>
                                <label for="amount-received-sidebar" class="mb-1 block text-sm font-medium text-slate-300">Recibido</label>
                                <input
                                    id="amount-received-sidebar"
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
                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                <button type="button" class="rounded-lg border border-cyan-100/25 px-2 py-2 text-xs font-semibold text-slate-300 hover:bg-slate-800/70" @click="applyQuickAmount('exact')">Exacto</button>
                                <button type="button" class="rounded-lg border border-cyan-100/25 px-2 py-2 text-xs font-semibold text-slate-300 hover:bg-slate-800/70" @click="applyQuickAmount('add', 500)">+500</button>
                                <button type="button" class="rounded-lg border border-cyan-100/25 px-2 py-2 text-xs font-semibold text-slate-300 hover:bg-slate-800/70" @click="applyQuickAmount('add', 1000)">+1000</button>
                                <button type="button" class="rounded-lg border border-rose-300/45 px-2 py-2 text-xs font-semibold text-rose-100 hover:bg-rose-400/20" @click="applyQuickAmount('clear')">Borrar</button>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 space-y-3 text-sm text-slate-300">
                        <div class="flex items-center justify-between gap-3">
                            <span>Cliente</span>
                            <span class="text-right font-semibold text-slate-100">{{ selectedCustomer?.name || 'Consumidor final' }}</span>
                        </div>
                        <div v-if="fiscalEnabled" class="flex items-center justify-between gap-3">
                            <span>Factura</span>
                            <span class="text-right font-semibold text-slate-100">{{ form.fiscal_customer.with_data ? 'Con datos fiscales' : 'Consumidor final' }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span>Cantidad cargada</span>
                            <span class="font-semibold text-slate-100">{{ cartUnitsCount }}</span>
                        </div>
                        <div v-if="receipt_feature_available" class="flex items-center justify-between gap-3">
                            <span>Comprobante</span>
                            <span class="text-right font-semibold text-slate-100">{{ receiptFileName || 'Sin adjunto' }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span>Subtotal</span>
                            <span class="font-semibold text-slate-100">{{ money(subtotal) }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span>Descuento</span>
                            <span class="font-semibold text-slate-100">{{ money(form.discount) }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3 rounded-xl border border-cyan-300/20 bg-cyan-400/10 px-3 py-2">
                            <span>Total</span>
                            <span class="text-xl font-bold text-slate-100">{{ money(total) }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span>{{ usesMercadoPagoPoint ? 'A enviar a Point' : 'Abonado ahora' }}</span>
                            <span class="font-semibold text-slate-100">{{ money(usesMercadoPagoPoint ? total : paidAmount) }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span>Pendiente</span>
                            <span :class="pendingAmount > 0 ? 'text-amber-100' : 'text-emerald-100'" class="font-semibold">{{ money(pendingAmount) }}</span>
                        </div>
                        <div v-if="isCashPayment && requiresImmediatePayment" class="flex items-center justify-between gap-3">
                            <span>Vuelto</span>
                            <span class="font-semibold text-slate-100">{{ money(changeAmount) }}</span>
                        </div>
                    </div>

                    <div v-if="summaryWarnings.length" class="mt-4 rounded-xl border border-rose-300/25 bg-rose-400/10 p-3 text-sm text-rose-100">
                        <p v-for="warning in summaryWarnings" :key="warning">{{ warning }}</p>
                    </div>
                    <div v-else class="mt-4 rounded-xl border border-emerald-300/25 bg-emerald-400/10 p-3 text-sm text-emerald-100">
                        {{ readyMessage }}
                    </div>

                    <template #footer>
                        <div class="grid gap-3">
                            <button type="button" class="inline-flex w-full items-center justify-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-600 disabled:opacity-50" :disabled="!canSubmit" @click="submitIfReady">
                                {{ submitButtonLabel }}
                            </button>
                            <Link :href="route('sales.index')" class="inline-flex w-full items-center justify-center rounded-xl border border-cyan-100/25 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/70">
                                Volver al listado
                            </Link>
                        </div>
                    </template>
                </AppPanel>
            </aside>
        </form>
    </AuthenticatedLayout>
</template>
