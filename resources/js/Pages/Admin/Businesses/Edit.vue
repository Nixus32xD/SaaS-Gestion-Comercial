<script setup>
import { computed, ref, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    business: {
        type: Object,
        required: true,
    },
    sales_settings: {
        type: Object,
        required: true,
    },
    commercial_catalog: {
        type: Object,
        required: true,
    },
    billing: {
        type: Object,
        required: true,
    },
    fiscal_catalog: {
        type: Object,
        default: () => ({ document_types: [], voucher_types: [], authorization_modes: [], environments: [], fiscal_conditions: [] }),
    },
    branches: {
        type: Array,
        default: () => [],
    },
    fiscal_identities: {
        type: Array,
        default: () => [],
    },
    fiscal_identity_point_of_sale_options: {
        type: Object,
        default: () => ({}),
    },
});

const makeSector = () => ({
    id: null,
    name: '',
    description: '',
    is_active: true,
});

const makePaymentDestination = () => ({
    id: null,
    name: '',
    account_holder: '',
    reference: '',
    account_number: '',
    is_active: true,
});

const form = useForm({
    name: props.business.name,
    slug: props.business.slug || '',
    owner_name: props.business.owner_name || '',
    email: props.business.email || '',
    phone: props.business.phone || '',
    address: props.business.address || '',
    is_active: Boolean(props.business.is_active),
});

const billingForm = useForm({
    implementation_plan_code: props.billing.implementation?.plan_code || '',
    implementation_amount: props.billing.implementation?.amount ?? '',
    maintenance_plan_code: props.billing.maintenance?.plan_code || '',
    maintenance_amount: props.billing.maintenance?.amount ?? '',
    maintenance_started_at: props.billing.maintenance?.started_at || '',
    maintenance_ends_at: props.billing.maintenance?.ends_at || '',
    subscription_grace_days: props.billing.maintenance?.grace_days ?? 7,
    subscription_notes: props.billing.subscription_notes || '',
});

const salesSettingsForm = useForm({
    advanced_sale_settings_enabled: Boolean(props.sales_settings.advanced_sale_settings_enabled),
    global_product_catalog_enabled: Boolean(props.sales_settings.global_product_catalog_enabled),
    fiscal_enabled: Boolean(props.sales_settings.fiscal_enabled),
    fiscal_external_business_id: props.sales_settings.fiscal_external_business_id || '',
    fiscal_environment: props.sales_settings.fiscal_environment || 'testing',
    fiscal_cuit: props.sales_settings.fiscal_cuit || '',
    fiscal_condition: props.sales_settings.fiscal_condition || 'monotributo',
    fiscal_point_of_sale: props.sales_settings.fiscal_point_of_sale ?? 2,
    fiscal_document_type: props.sales_settings.fiscal_document_type || 'invoice_c',
    fiscal_cbte_type: props.sales_settings.fiscal_cbte_type ?? 11,
    fiscal_concept: props.sales_settings.fiscal_concept ?? 1,
    fiscal_authorization_mode: props.sales_settings.fiscal_authorization_mode || 'cae',
    fiscal_caea_code: props.sales_settings.fiscal_caea_code || '',
    fiscal_caea_period: props.sales_settings.fiscal_caea_period || '',
    fiscal_caea_order: props.sales_settings.fiscal_caea_order || '',
    fiscal_caea_from: props.sales_settings.fiscal_caea_from || '',
    fiscal_caea_to: props.sales_settings.fiscal_caea_to || '',
    fiscal_caea_due_date: props.sales_settings.fiscal_caea_due_date || '',
    fiscal_caea_report_deadline: props.sales_settings.fiscal_caea_report_deadline || '',
    fiscal_activities: props.sales_settings.fiscal_activities || '',
    mercadopago_enabled: Boolean(props.sales_settings.mercadopago?.is_enabled),
    mercadopago_environment: props.sales_settings.mercadopago?.environment || 'testing',
    mercadopago_public_key: '',
    mercadopago_access_token: '',
    mercadopago_webhook_secret: '',
    mercadopago_point_terminal_id: props.sales_settings.mercadopago?.point_terminal_id || '',
    mercadopago_point_store_id: props.sales_settings.mercadopago?.point_store_id || '',
    mercadopago_point_pos_id: props.sales_settings.mercadopago?.point_pos_id || '',
    mercadopago_point_external_store_id: props.sales_settings.mercadopago?.point_external_store_id || '',
    mercadopago_point_external_pos_id: props.sales_settings.mercadopago?.point_external_pos_id || '',
    mercadopago_point_expiration_time: props.sales_settings.mercadopago?.point_expiration_time || 'PT15M',
    mercadopago_point_print_on_terminal: props.sales_settings.mercadopago?.point_print_on_terminal || 'no_ticket',
    sale_sectors: (props.sales_settings.sale_sectors || []).map((sector) => ({
        id: sector.id,
        name: sector.name || '',
        description: sector.description || '',
        is_active: Boolean(sector.is_active),
    })),
    payment_destinations: (props.sales_settings.payment_destinations || []).map((destination) => ({
        id: destination.id,
        name: destination.name || '',
        account_holder: destination.account_holder || '',
        reference: destination.reference || '',
        account_number: destination.account_number || '',
        is_active: Boolean(destination.is_active),
    })),
});

const paymentForm = useForm({
    type: 'maintenance',
    plan_code: '',
    amount: '',
    paid_at: props.billing.payment_defaults?.today || '',
    coverage_ends_at: '',
    notes: '',
});

const branchForm = useForm({
    name: '',
    code: '',
    address: '',
    phone: '',
    email: '',
    is_active: true,
});
const branchFiscalForm = useForm({
    is_enabled: false,
    fiscal_identity_id: '',
    fiscal_identity: {
        external_fiscal_id: '',
        environment: 'testing',
        cuit: '',
        fiscal_condition: 'monotributo',
        legal_name: '',
        fiscal_activities: [],
    },
    fiscal_point_of_sale: 2,
    fiscal_document_type: 'invoice_c',
    fiscal_cbte_type: 11,
    fiscal_concept: 1,
    fiscal_authorization_mode: 'cae',
    fiscal_caea_code: '',
    fiscal_caea_period: '',
    fiscal_caea_order: '',
    fiscal_caea_from: '',
    fiscal_caea_to: '',
    fiscal_caea_due_date: '',
    fiscal_caea_report_deadline: '',
    fiscal_activities: '',
});
const branchCommercialForm = useForm({
    advanced_sale_settings_enabled: false,
    sale_sectors: [],
    payment_destinations: [],
});
const editingBranch = ref(null);
const showingBranchForm = ref(false);
const fiscalBranch = ref(null);
const showingBranchFiscalForm = ref(false);
const commercialBranch = ref(null);
const showingBranchCommercialForm = ref(false);

const implementationPlans = computed(() => props.commercial_catalog?.implementation_plans || []);
const maintenancePlans = computed(() => props.commercial_catalog?.maintenance_plans || []);
const availablePaymentPlans = computed(() => (
    paymentForm.type === 'maintenance' ? maintenancePlans.value : implementationPlans.value
));

const fiscalConceptOptions = [
    { value: 1, label: 'Productos' },
    { value: 2, label: 'Servicios' },
    { value: 3, label: 'Productos y servicios' },
];

const fiscalDocumentTypeOptions = computed(() => props.fiscal_catalog?.document_types || []);
const fiscalVoucherTypeOptions = computed(() => props.fiscal_catalog?.voucher_types || []);
const fiscalAuthorizationModeOptions = computed(() => props.fiscal_catalog?.authorization_modes || []);
const fiscalEnvironmentOptions = computed(() => props.fiscal_catalog?.environments || []);
const fiscalConditionOptions = computed(() => props.fiscal_catalog?.fiscal_conditions || []);
const fiscalIdentities = computed(() => props.fiscal_identities || []);
const selectedFiscalIdentity = computed(() => fiscalIdentities.value.find(
    (identity) => Number(identity.id) === Number(branchFiscalForm.fiscal_identity_id)
) || null);
const branchFiscalPointOfSaleOptions = computed(() => {
    const identityId = branchFiscalForm.fiscal_identity_id;
    return identityId ? props.fiscal_identity_point_of_sale_options?.[identityId]?.options || [] : [];
});
const branchFiscalPointOfSaleMessage = computed(() => {
    const identityId = branchFiscalForm.fiscal_identity_id;
    return identityId ? props.fiscal_identity_point_of_sale_options?.[identityId]?.message || null : null;
});
const fiscalPointOfSaleOptions = computed(() => props.sales_settings.fiscal_point_of_sale_options?.options || []);
const fiscalPointOfSaleMessage = computed(() => props.sales_settings.fiscal_point_of_sale_options?.message || null);
const hasFiscalPointOfSaleOptions = computed(() => fiscalPointOfSaleOptions.value.length > 0);
const mercadoPagoSettings = computed(() => props.sales_settings.mercadopago || {});

watch(() => salesSettingsForm.fiscal_document_type, (documentType) => {
    const option = fiscalDocumentTypeOptions.value.find((item) => item.value === documentType);

    if (option?.default_cbte_type) {
        salesSettingsForm.fiscal_cbte_type = Number(option.default_cbte_type);
    }
});

const submit = () => {
    form.put(route('admin.businesses.update', props.business.id));
};

const submitBilling = () => {
    billingForm.put(route('admin.businesses.billing.update', props.business.id));
};

const submitSalesSettings = () => {
    salesSettingsForm.put(route('admin.businesses.sales-settings.update', props.business.id));
};

const submitPayment = () => {
    paymentForm.post(route('admin.businesses.payments.store', props.business.id));
};

const resetBranchForm = () => {
    editingBranch.value = null;
    branchForm.reset();
    branchForm.is_active = true;
    branchForm.clearErrors();
};

const startBranchCreation = () => {
    resetBranchForm();
    showingBranchForm.value = true;
};

const startBranchEdition = (branch) => {
    editingBranch.value = branch;
    branchForm.name = branch.name || '';
    branchForm.code = branch.code || '';
    branchForm.address = branch.address || '';
    branchForm.phone = branch.phone || '';
    branchForm.email = branch.email || '';
    branchForm.is_active = Boolean(branch.is_active);
    branchForm.clearErrors();
    showingBranchForm.value = true;
};

const cancelBranchEdition = () => {
    resetBranchForm();
    showingBranchForm.value = false;
};

const submitBranch = () => {
    if (editingBranch.value) {
        branchForm.put(route('admin.businesses.branches.update', [props.business.id, editingBranch.value.id]));
        return;
    }

    branchForm.post(route('admin.businesses.branches.store', props.business.id));
};

const configureBranchFiscal = (branch) => {
    const settings = branch.fiscal_setting || props.sales_settings;
    const identity = settings.fiscal_identity || null;

    fiscalBranch.value = branch;
    branchFiscalForm.is_enabled = Boolean(settings.is_enabled ?? settings.fiscal_enabled);
    branchFiscalForm.fiscal_identity_id = settings.fiscal_identity_id || '';
    branchFiscalForm.fiscal_identity = {
        external_fiscal_id: identity?.external_fiscal_id || settings.fiscal_external_business_id || '',
        environment: identity?.environment || settings.fiscal_environment || 'testing',
        cuit: identity?.cuit || settings.fiscal_cuit || '',
        fiscal_condition: identity?.fiscal_condition || settings.fiscal_condition || 'monotributo',
        legal_name: identity?.legal_name || props.business.name || '',
        fiscal_activities: identity?.fiscal_activities || settings.fiscal_activities || '',
    };
    branchFiscalForm.fiscal_point_of_sale = settings.fiscal_point_of_sale ?? 2;
    branchFiscalForm.fiscal_document_type = settings.fiscal_document_type || 'invoice_c';
    branchFiscalForm.fiscal_cbte_type = settings.fiscal_cbte_type ?? 11;
    branchFiscalForm.fiscal_concept = settings.fiscal_concept ?? 1;
    branchFiscalForm.fiscal_authorization_mode = settings.fiscal_authorization_mode || 'cae';
    branchFiscalForm.fiscal_caea_code = settings.fiscal_caea_code || '';
    branchFiscalForm.fiscal_caea_period = settings.fiscal_caea_period || '';
    branchFiscalForm.fiscal_caea_order = settings.fiscal_caea_order || '';
    branchFiscalForm.fiscal_caea_from = settings.fiscal_caea_from || '';
    branchFiscalForm.fiscal_caea_to = settings.fiscal_caea_to || '';
    branchFiscalForm.fiscal_caea_due_date = settings.fiscal_caea_due_date || '';
    branchFiscalForm.fiscal_caea_report_deadline = settings.fiscal_caea_report_deadline || '';
    branchFiscalForm.fiscal_activities = settings.fiscal_activities || '';
    branchFiscalForm.clearErrors();
    showingBranchFiscalForm.value = true;
};

const createFiscalIdentity = () => {
    branchFiscalForm.fiscal_identity_id = '';
    branchFiscalForm.fiscal_identity = {
        external_fiscal_id: '',
        environment: 'testing',
        cuit: '',
        fiscal_condition: 'monotributo',
        legal_name: props.business.name || '',
        fiscal_activities: '',
    };
};

const cancelBranchFiscalEdition = () => {
    fiscalBranch.value = null;
    branchFiscalForm.reset();
    branchFiscalForm.clearErrors();
    showingBranchFiscalForm.value = false;
};

const submitBranchFiscal = () => {
    if (!fiscalBranch.value) return;

    branchFiscalForm.put(route('admin.businesses.branches.fiscal-settings.update', [props.business.id, fiscalBranch.value.id]));
};

const retryFiscalIdentitySync = (identity) => {
    router.post(route('admin.businesses.fiscal-identities.sync', [props.business.id, identity.id]));
};

const configureBranchCommercial = (branch) => {
    const settings = branch.commercial_setting || {};
    commercialBranch.value = branch;
    branchCommercialForm.advanced_sale_settings_enabled = Boolean(settings.advanced_sale_settings_enabled);
    branchCommercialForm.sale_sectors = (settings.sale_sectors || []).map((sector) => ({
        id: sector.id || null,
        name: sector.name || '',
        description: sector.description || '',
        is_active: Boolean(sector.is_active),
    }));
    branchCommercialForm.payment_destinations = (settings.payment_destinations || []).map((destination) => ({
        id: destination.id || null,
        name: destination.name || '',
        account_holder: destination.account_holder || '',
        reference: destination.reference || '',
        account_number: destination.account_number || '',
        is_active: Boolean(destination.is_active),
    }));
    branchCommercialForm.clearErrors();
    showingBranchCommercialForm.value = true;
};

const cancelBranchCommercialEdition = () => {
    commercialBranch.value = null;
    branchCommercialForm.reset();
    branchCommercialForm.clearErrors();
    showingBranchCommercialForm.value = false;
};

const submitBranchCommercial = () => {
    if (!commercialBranch.value) return;
    branchCommercialForm.put(route('admin.businesses.branches.commercial-settings.update', [props.business.id, commercialBranch.value.id]));
};

const addBranchSector = () => branchCommercialForm.sale_sectors.push(makeSector());
const addBranchPaymentDestination = () => branchCommercialForm.payment_destinations.push(makePaymentDestination());

const removeBranchSector = (index) => {
    const sector = branchCommercialForm.sale_sectors[index];
    if (sector?.id) { sector.is_active = false; return; }
    branchCommercialForm.sale_sectors.splice(index, 1);
};

const removeBranchPaymentDestination = (index) => {
    const destination = branchCommercialForm.payment_destinations[index];
    if (destination?.id) { destination.is_active = false; return; }
    branchCommercialForm.payment_destinations.splice(index, 1);
};

const addSector = () => {
    salesSettingsForm.sale_sectors.push(makeSector());
};

const addPaymentDestination = () => {
    salesSettingsForm.payment_destinations.push(makePaymentDestination());
};

const removeSector = (index) => {
    const sector = salesSettingsForm.sale_sectors[index];

    if (sector?.id) {
        salesSettingsForm.sale_sectors[index].is_active = false;
        return;
    }

    salesSettingsForm.sale_sectors.splice(index, 1);
};

const removePaymentDestination = (index) => {
    const destination = salesSettingsForm.payment_destinations[index];

    if (destination?.id) {
        salesSettingsForm.payment_destinations[index].is_active = false;
        return;
    }

    salesSettingsForm.payment_destinations.splice(index, 1);
};

const syncPaymentDefaults = (type) => {
    if (type === 'implementation') {
        paymentForm.plan_code = props.billing.payment_defaults?.implementation_plan_code || '';
        paymentForm.amount = props.billing.payment_defaults?.implementation_amount ?? '';
        paymentForm.coverage_ends_at = '';
        return;
    }

    paymentForm.plan_code = props.billing.payment_defaults?.maintenance_plan_code || '';
    paymentForm.amount = props.billing.payment_defaults?.maintenance_amount ?? '';
    paymentForm.coverage_ends_at = props.billing.payment_defaults?.maintenance_coverage_end || '';
};

watch(() => paymentForm.type, syncPaymentDefaults, { immediate: true });

const statusBadgeClass = (tone) => {
    if (tone === 'emerald') return 'bg-emerald-100 text-emerald-700';
    if (tone === 'amber') return 'bg-amber-100 text-amber-700';
    if (tone === 'rose') return 'bg-rose-100 text-rose-700';

    return 'bg-slate-200 text-slate-700';
};

const planLabel = (plan) => {
    const priceLabel = plan.priceLabel ? `${plan.priceLabel} ` : '';
    const priceSuffix = plan.priceSuffix ? ` ${plan.priceSuffix}` : '';

    return `${plan.title}${plan.price ? ` - ${priceLabel}${plan.price}${priceSuffix}` : ''}`;
};

const sectionNavigation = [
    { id: 'datos-generales', label: 'Datos generales' },
    { id: 'resumen-comercial', label: 'Resumen' },
    { id: 'abonos', label: 'Planes y abonos' },
    { id: 'configuracion-operativa', label: 'Configuración' },
    { id: 'sucursales', label: 'Sucursales' },
    { id: 'cobros', label: 'Registrar pago' },
    { id: 'historial-pagos', label: 'Historial' },
];

const openSection = (sectionId) => {
    const section = document.getElementById(sectionId);

    if (!section) return;

    if (section.tagName === 'DETAILS') {
        section.open = true;
    }

    requestAnimationFrame(() => section.scrollIntoView({ behavior: 'smooth', block: 'start' }));
};
</script>

<template>
    <Head title="Editar comercio" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold text-slate-100">Editar comercio</h2>
                    <p class="mt-1 text-sm text-slate-300/80">Actualiza datos generales, abonos y configuraciones del negocio.</p>
                </div>
                <Link
                    :href="route('admin.businesses.index')"
                    class="rounded-lg border border-cyan-100/25 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/70"
                >
                    Volver
                </Link>
            </div>
        </template>

        <nav aria-label="Secciones de edición" class="mb-6 flex gap-2 overflow-x-auto rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-3 shadow-sm backdrop-blur">
            <button
                v-for="section in sectionNavigation"
                :key="section.id"
                type="button"
                class="shrink-0 rounded-lg border border-cyan-100/15 px-3 py-2 text-sm font-medium text-slate-300 hover:bg-slate-800/70 hover:text-slate-100"
                @click="openSection(section.id)"
            >
                {{ section.label }}
            </button>
        </nav>

        <div class="grid gap-6">
            <details id="resumen-comercial" class="order-2 rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-sm backdrop-blur">
                <summary class="cursor-pointer list-none [&::-webkit-details-marker]:hidden">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-100">Resumen de implementacion y mantenimiento</h3>
                            <p class="mt-1 text-sm text-slate-300/80">Estado actual del alta y del abono del comercio.</p>
                        </div>
                        <span class="rounded-full border border-cyan-100/20 px-3 py-1 text-xs font-semibold text-cyan-100">Ver / ocultar</span>
                    </div>
                </summary>

                <section class="mt-5 grid gap-4 border-t border-cyan-100/15 pt-5 xl:grid-cols-2">
                <article class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-sm backdrop-blur">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs uppercase tracking-[0.25em] text-cyan-100/70">Implementacion</p>
                            <h3 class="mt-2 text-lg font-semibold text-slate-100">{{ billing.implementation.plan_title || 'Sin plan cargado' }}</h3>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="statusBadgeClass(billing.implementation.status === 'paid' ? 'emerald' : billing.implementation.status === 'partial' ? 'amber' : 'slate')">
                            {{ billing.implementation.status_label }}
                        </span>
                    </div>
                    <div class="mt-4 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-xl border border-cyan-100/15 bg-slate-950/35 p-3">
                            <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Monto pactado</p>
                            <p class="mt-2 text-lg font-semibold text-slate-100">{{ billing.implementation.amount_label || '-' }}</p>
                        </div>
                        <div class="rounded-xl border border-cyan-100/15 bg-slate-950/35 p-3">
                            <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Pagado</p>
                            <p class="mt-2 text-lg font-semibold text-slate-100">{{ billing.implementation.paid_amount_label || '-' }}</p>
                        </div>
                        <div class="rounded-xl border border-cyan-100/15 bg-slate-950/35 p-3">
                            <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Saldo</p>
                            <p class="mt-2 text-lg font-semibold text-slate-100">{{ billing.implementation.balance_label || '-' }}</p>
                        </div>
                    </div>
                </article>

                <article class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-sm backdrop-blur">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs uppercase tracking-[0.25em] text-cyan-100/70">Mantenimiento</p>
                            <h3 class="mt-2 text-lg font-semibold text-slate-100">{{ billing.maintenance.plan_title || 'Sin plan cargado' }}</h3>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="statusBadgeClass(billing.maintenance.tone)">
                            {{ billing.maintenance.status_label }}
                        </span>
                    </div>
                    <div class="mt-4 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-xl border border-cyan-100/15 bg-slate-950/35 p-3">
                            <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Monto mensual</p>
                            <p class="mt-2 text-lg font-semibold text-slate-100">{{ billing.maintenance.amount_label || '-' }}</p>
                        </div>
                        <div class="rounded-xl border border-cyan-100/15 bg-slate-950/35 p-3">
                            <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Vence</p>
                            <p class="mt-2 text-lg font-semibold text-slate-100">{{ billing.maintenance.ends_at_label || '-' }}</p>
                        </div>
                        <div class="rounded-xl border border-cyan-100/15 bg-slate-950/35 p-3">
                            <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Gracia</p>
                            <p class="mt-2 text-lg font-semibold text-slate-100">{{ billing.maintenance.grace_ends_at_label || '-' }}</p>
                        </div>
                    </div>
                    <p class="mt-4 text-sm text-slate-300/80">{{ billing.maintenance.status_message }}</p>
                </article>
                </section>
            </details>

            <details id="abonos" class="order-3 rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-sm backdrop-blur">
                <summary class="cursor-pointer list-none [&::-webkit-details-marker]:hidden">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-100">Planes y abonos</h3>
                        <p class="mt-1 text-sm text-slate-300/80">Define lo pactado con el cliente y la ventana de gracia del comercio.</p>
                    </div>
                        <span class="rounded-full border border-cyan-100/20 px-3 py-1 text-xs font-semibold text-cyan-100">Ver / ocultar</span>
                    </div>
                </summary>

                <form class="mt-5 border-t border-cyan-100/15 pt-5" @submit.prevent="submitBilling">

                <div class="mt-5 grid gap-6 xl:grid-cols-2">
                    <section class="rounded-2xl border border-cyan-100/20 bg-slate-950/35 p-4">
                        <h4 class="text-base font-semibold text-slate-100">Implementacion inicial</h4>
                        <p class="mt-1 text-xs text-slate-400">Express, Esencial o Plus, con posibilidad de ajustar el precio acordado.</p>

                        <div class="mt-4 grid gap-3">
                            <div class="space-y-1">
                                <label class="text-sm font-medium text-slate-300">Plan inicial</label>
                                <select v-model="billingForm.implementation_plan_code" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100">
                                    <option value="">Sin definir</option>
                                    <option v-for="plan in implementationPlans" :key="plan.code" :value="plan.code">{{ planLabel(plan) }}</option>
                                </select>
                            </div>

                            <div class="space-y-1">
                                <label class="text-sm font-medium text-slate-300">Monto pactado</label>
                                <input v-model="billingForm.implementation_amount" type="number" min="0" step="0.01" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100" placeholder="Ej. 150000" />
                            </div>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-cyan-100/20 bg-slate-950/35 p-4">
                        <h4 class="text-base font-semibold text-slate-100">Mantenimiento mensual</h4>
                        <p class="mt-1 text-xs text-slate-400">Este plan define el abono mensual y el vencimiento operativo del comercio.</p>

                        <div class="mt-4 grid gap-3">
                            <div class="space-y-1">
                                <label class="text-sm font-medium text-slate-300">Plan mensual</label>
                                <select v-model="billingForm.maintenance_plan_code" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100">
                                    <option value="">Sin definir</option>
                                    <option v-for="plan in maintenancePlans" :key="plan.code" :value="plan.code">{{ planLabel(plan) }}</option>
                                </select>
                            </div>

                            <div class="grid gap-3 md:grid-cols-2">
                                <div class="space-y-1">
                                    <label class="text-sm font-medium text-slate-300">Monto mensual pactado</label>
                                    <input v-model="billingForm.maintenance_amount" type="number" min="0" step="0.01" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100" placeholder="Ej. 25000" />
                                </div>
                                <div class="space-y-1">
                                    <label class="text-sm font-medium text-slate-300">Dias de gracia</label>
                                    <input v-model="billingForm.subscription_grace_days" type="number" min="0" max="30" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100" />
                                </div>
                            </div>

                            <div class="grid gap-3 md:grid-cols-2">
                                <div class="space-y-1">
                                    <label class="text-sm font-medium text-slate-300">Inicio del mantenimiento</label>
                                    <input v-model="billingForm.maintenance_started_at" type="date" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100" />
                                </div>
                                <div class="space-y-1">
                                    <label class="text-sm font-medium text-slate-300">Vence el</label>
                                    <input v-model="billingForm.maintenance_ends_at" type="date" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100" />
                                </div>
                            </div>

                            <div class="space-y-1">
                                <label class="text-sm font-medium text-slate-300">Notas internas</label>
                                <textarea v-model="billingForm.subscription_notes" rows="3" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Ej. Cliente referido, precio promocional, paga por transferencia..." />
                            </div>
                        </div>
                    </section>
                </div>

                <div class="mt-5 flex justify-end">
                    <button
                        type="submit"
                        class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500 disabled:opacity-50"
                        :disabled="billingForm.processing"
                    >
                        Guardar planes y abonos
                    </button>
                </div>
                </form>
            </details>

            <details id="datos-generales" open class="order-1 rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-sm backdrop-blur">
                <summary class="cursor-pointer list-none [&::-webkit-details-marker]:hidden">
                    <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-100">Datos generales</h3>
                        <p class="mt-1 text-sm text-slate-300/80">Configuracion base del comercio.</p>
                    </div>
                        <span class="rounded-full border border-cyan-100/20 px-3 py-1 text-xs font-semibold text-cyan-100">Ver / ocultar</span>
                    </div>
                </summary>

                <form class="mt-4 border-t border-cyan-100/15 pt-4" @submit.prevent="submit">

                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-slate-300">Nombre</label>
                        <input v-model="form.name" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Nombre del comercio" />
                    </div>
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-slate-300">Slug</label>
                        <input v-model="form.slug" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Slug (opcional)" />
                    </div>
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-slate-300">Responsable</label>
                        <input v-model="form.owner_name" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Nombre del responsable" />
                    </div>
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-slate-300">Email</label>
                        <input v-model="form.email" type="email" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Email del comercio" />
                    </div>
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-slate-300">Telefono</label>
                        <input v-model="form.phone" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Telefono de contacto" />
                    </div>
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-slate-300">Direccion</label>
                        <input v-model="form.address" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Direccion del comercio" />
                    </div>
                </div>

                <label class="mt-4 inline-flex items-center gap-2 text-sm text-slate-300">
                    <input v-model="form.is_active" type="checkbox" class="rounded border-cyan-100/25 bg-slate-950/35 text-indigo-500 focus:ring-indigo-500">
                    Comercio activo
                </label>

                <div class="mt-5 flex justify-end">
                    <button
                        type="submit"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        Guardar datos generales
                    </button>
                </div>
                </form>
            </details>

            <details id="cobros" class="order-6 rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-sm backdrop-blur">
                <summary class="cursor-pointer list-none [&::-webkit-details-marker]:hidden">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-100">Registrar pago manual</h3>
                        <p class="mt-1 text-sm text-slate-300/80">Cada pago queda asentado y, si es mantenimiento, actualiza la cobertura del comercio.</p>
                    </div>
                        <span class="rounded-full border border-cyan-100/20 px-3 py-1 text-xs font-semibold text-cyan-100">Ver / ocultar</span>
                    </div>
                </summary>

                <form class="mt-5 border-t border-cyan-100/15 pt-5" @submit.prevent="submitPayment">

                <div class="mt-5 grid gap-3 xl:grid-cols-2">
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-slate-300">Concepto</label>
                        <select v-model="paymentForm.type" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100">
                            <option value="maintenance">Mantenimiento mensual</option>
                            <option value="implementation">Implementacion inicial</option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium text-slate-300">Plan asociado</label>
                        <select v-model="paymentForm.plan_code" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100">
                            <option value="">Sin plan</option>
                            <option v-for="plan in availablePaymentPlans" :key="plan.code" :value="plan.code">{{ planLabel(plan) }}</option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium text-slate-300">Monto pagado</label>
                        <input v-model="paymentForm.amount" type="number" min="0" step="0.01" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100" placeholder="Ej. 25000" />
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium text-slate-300">Fecha de pago</label>
                        <input v-model="paymentForm.paid_at" type="date" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100" />
                    </div>

                    <div v-if="paymentForm.type === 'maintenance'" class="space-y-1 xl:col-span-2">
                        <label class="text-sm font-medium text-slate-300">Mantenimiento cubierto hasta</label>
                        <input v-model="paymentForm.coverage_ends_at" type="date" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100" />
                        <p class="text-xs text-slate-400">Sugerencia actual: {{ billing.maintenance.recommended_coverage_end_label }}. Despues de esa fecha corre la gracia de {{ billing.maintenance.grace_days }} dias.</p>
                    </div>

                    <div class="space-y-1 xl:col-span-2">
                        <label class="text-sm font-medium text-slate-300">Observaciones</label>
                        <textarea v-model="paymentForm.notes" rows="3" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Ej. Pago por transferencia, promo de lanzamiento, pago parcial..." />
                    </div>
                </div>

                <div class="mt-5 flex justify-end">
                    <button
                        type="submit"
                        class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-500 disabled:opacity-50"
                        :disabled="paymentForm.processing"
                    >
                        Registrar pago
                    </button>
                </div>
                </form>
            </details>

            <details id="historial-pagos" class="order-7 rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-sm backdrop-blur">
                <summary class="cursor-pointer list-none [&::-webkit-details-marker]:hidden">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-100">Historial de pagos</h3>
                            <p class="mt-1 text-sm text-slate-300/80">Ultimos movimientos registrados manualmente para este comercio.</p>
                        </div>
                        <span class="rounded-full border border-cyan-100/20 px-3 py-1 text-xs font-semibold text-cyan-100">Ver / ocultar</span>
                    </div>
                </summary>

                <div class="mt-5 overflow-x-auto rounded-xl border border-cyan-100/20 app-table-wrap border-t border-cyan-100/15 pt-5">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-950/35">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Fecha</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Concepto</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Plan</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Monto</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Cubre hasta</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Registrado por</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Notas</th>
                            </tr>
                        </thead>
                        <tbody v-if="billing.payment_history?.length" class="divide-y divide-slate-100">
                            <tr v-for="payment in billing.payment_history" :key="payment.id">
                                <td class="px-3 py-2 text-slate-200">{{ payment.paid_at_label }}</td>
                                <td class="px-3 py-2 text-slate-200">{{ payment.type_label }}</td>
                                <td class="px-3 py-2 text-slate-200">{{ payment.plan_title || '-' }}</td>
                                <td class="px-3 py-2 text-slate-200">{{ payment.amount_label }}</td>
                                <td class="px-3 py-2 text-slate-200">{{ payment.coverage_ends_at_label || '-' }}</td>
                                <td class="px-3 py-2 text-slate-200">{{ payment.recorded_by || '-' }}</td>
                                <td class="px-3 py-2 text-slate-300/80">{{ payment.notes || '-' }}</td>
                            </tr>
                        </tbody>
                        <tbody v-else>
                            <tr>
                                <td colspan="7" class="px-3 py-6 text-center text-slate-400">Todavia no hay pagos registrados para este comercio.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </details>

            <details id="configuracion-operativa" class="order-4 rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-sm backdrop-blur">
                <summary class="cursor-pointer list-none [&::-webkit-details-marker]:hidden">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-100">Credenciales y compatibilidad del comercio</h3>
                        <p class="mt-1 text-sm text-slate-300/80">El catálogo global y las credenciales compartidas se gestionan aquí. ARCA, terminal Point y sectores/destinos de cobro se configuran en cada sucursal.</p>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="inline-flex items-center gap-2 rounded-xl border border-cyan-100/20 bg-slate-950/35 px-3 py-2 text-sm text-slate-200">
                            <input v-model="salesSettingsForm.advanced_sale_settings_enabled" type="checkbox" class="rounded border-cyan-100/25 bg-slate-950/35 text-indigo-500 focus:ring-indigo-500">
                            Fallback de ventas avanzadas
                        </label>
                        <label class="inline-flex items-center gap-2 rounded-xl border border-cyan-100/20 bg-slate-950/35 px-3 py-2 text-sm text-slate-200">
                            <input v-model="salesSettingsForm.global_product_catalog_enabled" type="checkbox" class="rounded border-cyan-100/25 bg-slate-950/35 text-indigo-500 focus:ring-indigo-500">
                            Habilitar catalogo global de productos
                        </label>
                        <label class="inline-flex items-center gap-2 rounded-xl border border-cyan-100/20 bg-slate-950/35 px-3 py-2 text-sm text-slate-200">
                            <input v-model="salesSettingsForm.fiscal_enabled" type="checkbox" class="rounded border-cyan-100/25 bg-slate-950/35 text-indigo-500 focus:ring-indigo-500">
                            Fallback de facturación electrónica
                        </label>
                        <label class="inline-flex items-center gap-2 rounded-xl border border-cyan-100/20 bg-slate-950/35 px-3 py-2 text-sm text-slate-200">
                            <input v-model="salesSettingsForm.mercadopago_enabled" type="checkbox" class="rounded border-cyan-100/25 bg-slate-950/35 text-indigo-500 focus:ring-indigo-500">
                            Habilitar credenciales de Mercado Pago
                        </label>
                    </div>
                        <span class="rounded-full border border-cyan-100/20 px-3 py-1 text-xs font-semibold text-cyan-100">Ver / ocultar</span>
                    </div>
                </summary>

                <form class="mt-5 border-t border-cyan-100/15 pt-5" @submit.prevent="submitSalesSettings">

                <p v-if="salesSettingsForm.errors.sale_sectors" class="mt-3 text-sm text-rose-300">{{ salesSettingsForm.errors.sale_sectors }}</p>
                <p v-if="salesSettingsForm.errors.payment_destinations" class="mt-2 text-sm text-rose-300">{{ salesSettingsForm.errors.payment_destinations }}</p>
                <p v-if="salesSettingsForm.errors.fiscal_external_business_id" class="mt-2 text-sm text-rose-300">{{ salesSettingsForm.errors.fiscal_external_business_id }}</p>
                <p v-if="salesSettingsForm.errors.fiscal_cuit" class="mt-2 text-sm text-rose-300">{{ salesSettingsForm.errors.fiscal_cuit }}</p>
                <p v-if="salesSettingsForm.errors.fiscal_condition" class="mt-2 text-sm text-rose-300">{{ salesSettingsForm.errors.fiscal_condition }}</p>
                <p v-if="salesSettingsForm.errors.fiscal_point_of_sale" class="mt-2 text-sm text-rose-300">{{ salesSettingsForm.errors.fiscal_point_of_sale }}</p>
                <p v-if="salesSettingsForm.errors.fiscal_document_type" class="mt-2 text-sm text-rose-300">{{ salesSettingsForm.errors.fiscal_document_type }}</p>
                <p v-if="salesSettingsForm.errors.fiscal_cbte_type" class="mt-2 text-sm text-rose-300">{{ salesSettingsForm.errors.fiscal_cbte_type }}</p>
                <p v-if="salesSettingsForm.errors.fiscal_authorization_mode" class="mt-2 text-sm text-rose-300">{{ salesSettingsForm.errors.fiscal_authorization_mode }}</p>
                <p v-if="salesSettingsForm.errors.fiscal_caea_code" class="mt-2 text-sm text-rose-300">{{ salesSettingsForm.errors.fiscal_caea_code }}</p>
                <p v-if="salesSettingsForm.errors.fiscal_caea_period" class="mt-2 text-sm text-rose-300">{{ salesSettingsForm.errors.fiscal_caea_period }}</p>
                <p v-if="salesSettingsForm.errors.fiscal_activities" class="mt-2 text-sm text-rose-300">{{ salesSettingsForm.errors.fiscal_activities }}</p>
                <p v-if="salesSettingsForm.errors.mercadopago_access_token" class="mt-2 text-sm text-rose-300">{{ salesSettingsForm.errors.mercadopago_access_token }}</p>
                <p v-if="salesSettingsForm.errors.mercadopago_point_terminal_id" class="mt-2 text-sm text-rose-300">{{ salesSettingsForm.errors.mercadopago_point_terminal_id }}</p>
                <p v-if="salesSettingsForm.errors.mercadopago_point_expiration_time" class="mt-2 text-sm text-rose-300">{{ salesSettingsForm.errors.mercadopago_point_expiration_time }}</p>

                <section class="mt-5 rounded-2xl border border-cyan-100/20 bg-slate-950/35 p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h4 class="text-base font-semibold text-slate-100">Mercado Pago Point</h4>
                            <p class="mt-1 text-xs text-slate-400">Credenciales compartidas de la cuenta; la terminal se define por sucursal.</p>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-300">Ambiente</label>
                            <select v-model="salesSettingsForm.mercadopago_environment" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100">
                                <option value="testing">Prueba</option>
                                <option value="production">Produccion</option>
                            </select>
                        </div>

                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-300">Terminal ID</label>
                            <input v-model="salesSettingsForm.mercadopago_point_terminal_id" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="NEWLAND_N950__..." />
                        </div>

                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-300">Access Token</label>
                            <input v-model="salesSettingsForm.mercadopago_access_token" type="password" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" :placeholder="mercadoPagoSettings.access_token_configured ? 'Ya configurado' : 'APP_USR-...'" autocomplete="new-password" />
                        </div>

                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-300">Public Key</label>
                            <input v-model="salesSettingsForm.mercadopago_public_key" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" :placeholder="mercadoPagoSettings.public_key_configured ? 'Ya configurada' : 'APP_USR-...'" autocomplete="off" />
                        </div>

                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-300">Clave secreta webhook</label>
                            <input v-model="salesSettingsForm.mercadopago_webhook_secret" type="password" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" :placeholder="mercadoPagoSettings.webhook_secret_configured ? 'Ya configurada' : 'Clave de Mercado Pago'" autocomplete="new-password" />
                        </div>

                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-300">Expiracion</label>
                            <input v-model="salesSettingsForm.mercadopago_point_expiration_time" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="PT15M" />
                        </div>

                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-300">Impresion</label>
                            <select v-model="salesSettingsForm.mercadopago_point_print_on_terminal" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100">
                                <option value="no_ticket">Sin ticket</option>
                                <option value="seller_ticket">Ticket vendedor</option>
                            </select>
                        </div>

                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-300">Store ID</label>
                            <input v-model="salesSettingsForm.mercadopago_point_store_id" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="86244114" />
                        </div>

                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-300">POS ID</label>
                            <input v-model="salesSettingsForm.mercadopago_point_pos_id" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="136820601" />
                        </div>

                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-300">External Store ID</label>
                            <input v-model="salesSettingsForm.mercadopago_point_external_store_id" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="SUC001" />
                        </div>

                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-300">External POS ID</label>
                            <input v-model="salesSettingsForm.mercadopago_point_external_pos_id" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="CAJA001" />
                        </div>

                        <div class="space-y-1 md:col-span-2">
                            <label class="text-sm font-medium text-slate-300">Webhook</label>
                            <input :value="mercadoPagoSettings.webhook_url" type="text" readonly class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100" />
                        </div>
                    </div>
                </section>

                <section class="mt-5 rounded-2xl border border-cyan-100/20 bg-slate-950/35 p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h4 class="text-base font-semibold text-slate-100">Facturacion fiscal externa</h4>
                            <p class="mt-1 text-xs text-slate-400">La API fiscal determina automaticamente Factura A/B/C segun el comercio y el receptor.</p>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-300">ID externo del comercio</label>
                            <input v-model="salesSettingsForm.fiscal_external_business_id" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Ej. empresa-demo-prod" />
                        </div>

                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-300">Ambiente fiscal</label>
                            <select v-model="salesSettingsForm.fiscal_environment" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100">
                                <option v-for="option in fiscalEnvironmentOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>

                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-300">CUIT fiscal</label>
                            <input v-model="salesSettingsForm.fiscal_cuit" type="text" inputmode="numeric" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="20-12345678-6" />
                        </div>

                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-300">Condicion fiscal del comercio</label>
                            <select v-model="salesSettingsForm.fiscal_condition" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100">
                                <option v-for="option in fiscalConditionOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>

                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-300">Punto de venta</label>
                            <select
                                v-if="hasFiscalPointOfSaleOptions"
                                v-model.number="salesSettingsForm.fiscal_point_of_sale"
                                class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100"
                            >
                                <option
                                    v-for="option in fiscalPointOfSaleOptions"
                                    :key="option.value"
                                    :value="option.value"
                                    :disabled="!option.selectable"
                                >
                                    {{ option.label }}{{ option.disabled_reason ? ` - ${option.disabled_reason}` : '' }}
                                </option>
                            </select>
                            <input
                                v-else
                                v-model="salesSettingsForm.fiscal_point_of_sale"
                                type="number"
                                min="1"
                                class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100"
                            />
                            <p v-if="fiscalPointOfSaleMessage" class="text-xs text-amber-100">{{ fiscalPointOfSaleMessage }}</p>
                        </div>

                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-300">Tipo interno legacy</label>
                            <select v-model="salesSettingsForm.fiscal_document_type" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100">
                                <option v-for="option in fiscalDocumentTypeOptions" :key="option.value" :value="option.value">
                                    {{ option.label }} ({{ option.value }})
                                </option>
                            </select>
                        </div>

                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-300">Cbte legacy/admin</label>
                            <select v-model.number="salesSettingsForm.fiscal_cbte_type" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100">
                                <option v-for="option in fiscalVoucherTypeOptions" :key="option.value" :value="option.value">
                                    {{ option.label }} - codigo {{ option.value }}
                                </option>
                            </select>
                        </div>

                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-300">Concepto</label>
                            <select v-model="salesSettingsForm.fiscal_concept" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100">
                                <option v-for="option in fiscalConceptOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                        </div>

                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-300">Modo autorizacion</label>
                            <select v-model="salesSettingsForm.fiscal_authorization_mode" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100">
                                <option v-for="option in fiscalAuthorizationModeOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>

                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-300">Actividades</label>
                            <input v-model="salesSettingsForm.fiscal_activities" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="492140" />
                        </div>
                    </div>

                    <div v-if="salesSettingsForm.fiscal_authorization_mode === 'caea'" class="mt-4 grid gap-4 md:grid-cols-4">
                        <div class="space-y-1 md:col-span-2">
                            <label class="text-sm font-medium text-slate-300">CAEA vigente</label>
                            <input v-model="salesSettingsForm.fiscal_caea_code" type="text" maxlength="14" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="14 digitos" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-300">Periodo</label>
                            <input v-model="salesSettingsForm.fiscal_caea_period" type="text" maxlength="6" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="AAAAMM" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-300">Orden</label>
                            <select v-model="salesSettingsForm.fiscal_caea_order" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100">
                                <option value="">-</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-300">Desde</label>
                            <input v-model="salesSettingsForm.fiscal_caea_from" type="date" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-300">Hasta</label>
                            <input v-model="salesSettingsForm.fiscal_caea_to" type="date" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-300">Vencimiento</label>
                            <input v-model="salesSettingsForm.fiscal_caea_due_date" type="date" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-300">Tope informe</label>
                            <input v-model="salesSettingsForm.fiscal_caea_report_deadline" type="date" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100" />
                        </div>
                    </div>
                </section>

                <div class="mt-5 grid gap-6 xl:grid-cols-2">
                    <section class="rounded-2xl border border-cyan-100/20 bg-slate-950/35 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h4 class="text-base font-semibold text-slate-100">Sectores / puntos de venta</h4>
                                <p class="mt-1 text-xs text-slate-400">Ejemplo: Mostrador, Taller, Deposito.</p>
                            </div>
                            <button type="button" class="rounded-lg border border-cyan-100/25 px-3 py-2 text-xs font-semibold text-slate-300 hover:bg-slate-800/70" @click="addSector">
                                Agregar sector
                            </button>
                        </div>

                        <div class="mt-4 space-y-3">
                            <article v-for="(sector, index) in salesSettingsForm.sale_sectors" :key="`sector-${sector.id ?? `new-${index}`}`" class="rounded-xl border border-cyan-100/15 bg-slate-900/45 p-4">
                                <div class="grid gap-3">
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="text-sm font-semibold text-slate-100">Sector {{ index + 1 }}</p>
                                        <div class="flex items-center gap-3">
                                            <label class="inline-flex items-center gap-2 text-xs text-slate-300">
                                                <input v-model="sector.is_active" type="checkbox" class="rounded border-cyan-100/25 bg-slate-950/35 text-indigo-500 focus:ring-indigo-500">
                                                Activo
                                            </label>
                                            <button type="button" class="text-xs font-semibold text-rose-200 hover:text-rose-100" @click="removeSector(index)">
                                                {{ sector.id ? 'Desactivar' : 'Quitar' }}
                                            </button>
                                        </div>
                                    </div>

                                    <div class="space-y-1">
                                        <label class="text-sm font-medium text-slate-300">Nombre</label>
                                        <input v-model="sector.name" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Nombre del sector" />
                                    </div>

                                    <div class="space-y-1">
                                        <label class="text-sm font-medium text-slate-300">Descripcion</label>
                                        <input v-model="sector.description" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Descripcion opcional" />
                                    </div>
                                </div>
                            </article>

                            <p v-if="!salesSettingsForm.sale_sectors.length" class="rounded-xl border border-dashed border-cyan-100/20 px-4 py-5 text-sm text-slate-400">
                                Todavia no hay sectores configurados.
                            </p>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-cyan-100/20 bg-slate-950/35 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h4 class="text-base font-semibold text-slate-100">Destinos de cobro</h4>
                                <p class="mt-1 text-xs text-slate-400">Caja, banco, QR, Mercado Pago, terminal posnet o referencia equivalente.</p>
                            </div>
                            <button type="button" class="rounded-lg border border-cyan-100/25 px-3 py-2 text-xs font-semibold text-slate-300 hover:bg-slate-800/70" @click="addPaymentDestination">
                                Agregar destino
                            </button>
                        </div>

                        <div class="mt-4 space-y-3">
                            <article v-for="(destination, index) in salesSettingsForm.payment_destinations" :key="`destination-${destination.id ?? `new-${index}`}`" class="rounded-xl border border-cyan-100/15 bg-slate-900/45 p-4">
                                <div class="grid gap-3">
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="text-sm font-semibold text-slate-100">Destino {{ index + 1 }}</p>
                                        <div class="flex items-center gap-3">
                                            <label class="inline-flex items-center gap-2 text-xs text-slate-300">
                                                <input v-model="destination.is_active" type="checkbox" class="rounded border-cyan-100/25 bg-slate-950/35 text-indigo-500 focus:ring-indigo-500">
                                                Activa
                                            </label>
                                            <button type="button" class="text-xs font-semibold text-rose-200 hover:text-rose-100" @click="removePaymentDestination(index)">
                                                {{ destination.id ? 'Desactivar' : 'Quitar' }}
                                            </button>
                                        </div>
                                    </div>

                                    <div class="space-y-1">
                                        <label class="text-sm font-medium text-slate-300">Nombre identificatorio</label>
                                        <input v-model="destination.name" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Ej. Mercado Pago Mostrador" />
                                    </div>

                                    <div class="grid gap-3 md:grid-cols-2">
                                        <div class="space-y-1">
                                            <label class="text-sm font-medium text-slate-300">Titular o descripcion</label>
                                            <input v-model="destination.account_holder" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Titular" />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-sm font-medium text-slate-300">Alias / referencia</label>
                                            <input v-model="destination.reference" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Alias o referencia" />
                                        </div>
                                    </div>

                                    <div class="space-y-1">
                                        <label class="text-sm font-medium text-slate-300">Numero de cuenta o dato equivalente</label>
                                        <input v-model="destination.account_number" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="CBU, CVU, terminal, link, etc." />
                                    </div>
                                </div>
                            </article>

                            <p v-if="!salesSettingsForm.payment_destinations.length" class="rounded-xl border border-dashed border-cyan-100/20 px-4 py-5 text-sm text-slate-400">
                                Todavia no hay destinos configurados.
                            </p>
                        </div>
                    </section>
                </div>

                <div class="mt-5 flex justify-end">
                    <button
                        type="submit"
                        class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-500 disabled:opacity-50"
                        :disabled="salesSettingsForm.processing"
                    >
                        Guardar funciones por comercio
                    </button>
                </div>
                </form>
            </details>

            <details id="sucursales" class="order-5 rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-sm backdrop-blur">
                <summary class="cursor-pointer list-none [&::-webkit-details-marker]:hidden">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs uppercase tracking-[0.25em] text-cyan-100/70">Operación</p>
                        <h3 class="mt-2 text-lg font-semibold text-slate-100">Sucursales del comercio</h3>
                        <p class="mt-1 text-sm text-slate-300/80">Crea, edita o desactiva sucursales. Nunca se eliminan datos operativos.</p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-500"
                        @click="startBranchCreation"
                    >
                        Agregar sucursal
                    </button>
                        <span class="rounded-full border border-cyan-100/20 px-3 py-1 text-xs font-semibold text-cyan-100">Ver / ocultar</span>
                    </div>
                </summary>

                <div class="mt-5 border-t border-cyan-100/15 pt-5">

                <form v-if="showingBranchForm" class="mt-5 rounded-2xl border border-cyan-100/20 bg-slate-950/35 p-4" @submit.prevent="submitBranch">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h4 class="text-base font-semibold text-slate-100">{{ editingBranch ? 'Editar sucursal' : 'Nueva sucursal' }}</h4>
                            <p class="mt-1 text-xs text-slate-400">El código identifica a la sucursal dentro de este comercio.</p>
                        </div>
                        <span v-if="editingBranch?.is_default" class="rounded-full bg-cyan-300/15 px-3 py-1 text-xs font-semibold text-cyan-100">Sucursal principal</span>
                    </div>

                    <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-300">Nombre</label>
                            <input v-model="branchForm.name" type="text" maxlength="150" required class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100" placeholder="Ej. Sucursal Centro" />
                            <p v-if="branchForm.errors.name" class="text-xs text-rose-200">{{ branchForm.errors.name }}</p>
                        </div>
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-300">Código</label>
                            <input v-model="branchForm.code" type="text" maxlength="64" required class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100" placeholder="centro" />
                            <p v-if="branchForm.errors.code" class="text-xs text-rose-200">{{ branchForm.errors.code }}</p>
                        </div>
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-300">Teléfono</label>
                            <input v-model="branchForm.phone" type="text" maxlength="80" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100" placeholder="Opcional" />
                            <p v-if="branchForm.errors.phone" class="text-xs text-rose-200">{{ branchForm.errors.phone }}</p>
                        </div>
                        <div class="space-y-1 md:col-span-2">
                            <label class="text-sm font-medium text-slate-300">Dirección</label>
                            <input v-model="branchForm.address" type="text" maxlength="255" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100" placeholder="Opcional" />
                            <p v-if="branchForm.errors.address" class="text-xs text-rose-200">{{ branchForm.errors.address }}</p>
                        </div>
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-300">Email</label>
                            <input v-model="branchForm.email" type="email" maxlength="255" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100" placeholder="Opcional" />
                            <p v-if="branchForm.errors.email" class="text-xs text-rose-200">{{ branchForm.errors.email }}</p>
                        </div>
                    </div>

                    <label class="mt-4 inline-flex items-center gap-2 text-sm text-slate-300">
                        <input v-model="branchForm.is_active" type="checkbox" :disabled="Boolean(editingBranch?.is_default)" class="rounded border-cyan-100/25 bg-slate-950/35 text-indigo-500 focus:ring-indigo-500" />
                        Sucursal activa
                    </label>
                    <p v-if="editingBranch?.is_default" class="mt-1 text-xs text-slate-400">La sucursal principal no se puede desactivar.</p>
                    <p v-if="branchForm.errors.is_active" class="mt-1 text-xs text-rose-200">{{ branchForm.errors.is_active }}</p>

                    <div class="mt-5 flex justify-end gap-3">
                        <button type="button" class="rounded-lg border border-cyan-100/25 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/70" @click="cancelBranchEdition">
                            Cancelar
                        </button>
                        <button type="submit" class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-500 disabled:opacity-50" :disabled="branchForm.processing">
                            {{ editingBranch ? 'Guardar sucursal' : 'Crear sucursal' }}
                        </button>
                    </div>
                </form>

                <div class="mt-5 overflow-x-auto rounded-xl border border-cyan-100/15">
                    <table class="min-w-full divide-y divide-cyan-100/15 text-left text-sm">
                        <thead class="bg-slate-950/35 text-xs uppercase tracking-wider text-slate-400">
                            <tr>
                                <th class="px-4 py-3 font-medium">Sucursal</th>
                                <th class="px-4 py-3 font-medium">Contacto</th>
                                <th class="px-4 py-3 font-medium">Estado</th>
                                <th class="px-4 py-3 font-medium"><span class="sr-only">Acciones</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-cyan-100/10 text-slate-200">
                            <tr v-for="branch in branches" :key="branch.id">
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-semibold text-slate-100">{{ branch.name }}</span>
                                        <span v-if="branch.is_default" class="rounded-full bg-cyan-300/15 px-2 py-0.5 text-xs font-semibold text-cyan-100">Principal</span>
                                    </div>
                                    <p class="mt-1 text-xs text-slate-400">{{ branch.code }}{{ branch.address ? ` · ${branch.address}` : '' }}</p>
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-300">
                                    <p>{{ branch.phone || '-' }}</p>
                                    <p class="mt-1">{{ branch.email || '-' }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="branch.is_active ? 'bg-emerald-400/15 text-emerald-100' : 'bg-slate-400/15 text-slate-300'">
                                        {{ branch.is_active ? 'Activa' : 'Inactiva' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button type="button" class="rounded-lg border border-emerald-100/30 px-3 py-1.5 text-xs font-semibold text-emerald-100 hover:bg-emerald-400/10" @click="configureBranchCommercial(branch)">
                                            Ventas
                                        </button>
                                        <button type="button" class="rounded-lg border border-amber-100/30 px-3 py-1.5 text-xs font-semibold text-amber-100 hover:bg-amber-400/10" @click="configureBranchFiscal(branch)">
                                            ARCA
                                        </button>
                                        <button type="button" class="rounded-lg border border-cyan-100/25 px-3 py-1.5 text-xs font-semibold text-slate-200 hover:bg-slate-800/70" @click="startBranchEdition(branch)">
                                            Editar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!branches.length">
                                <td colspan="4" class="px-4 py-6 text-center text-sm text-slate-400">Este comercio todavía no tiene sucursales.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <form v-if="showingBranchFiscalForm" class="mt-5 rounded-2xl border border-amber-100/25 bg-slate-950/35 p-4" @submit.prevent="submitBranchFiscal">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-xs uppercase tracking-[0.25em] text-amber-100/70">API ARCA</p>
                            <h4 class="mt-2 text-base font-semibold text-slate-100">Perfil fiscal: {{ fiscalBranch?.name }}</h4>
                            <p class="mt-1 text-xs text-slate-400">Esta configuración se usa únicamente al facturar ventas de esta sucursal.</p>
                        </div>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-200">
                            <input v-model="branchFiscalForm.is_enabled" type="checkbox" class="rounded border-amber-100/25 bg-slate-950/35 text-amber-500 focus:ring-amber-500" />
                            Habilitar facturación ARCA
                        </label>
                    </div>

                    <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <div class="space-y-1 xl:col-span-2">
                            <label class="text-sm font-medium text-slate-300">Identidad fiscal</label>
                            <select v-model="branchFiscalForm.fiscal_identity_id" class="w-full rounded-xl border-amber-100/25 bg-slate-950/35 text-sm text-slate-100">
                                <option value="">Crear nueva identidad fiscal</option>
                                <option v-for="identity in fiscalIdentities" :key="identity.id" :value="identity.id">
                                    {{ identity.legal_name || identity.external_fiscal_id }} — CUIT {{ identity.cuit }} ({{ identity.environment }}, {{ identity.sync_status === 'synced' ? 'sincronizada' : identity.sync_status === 'pending' ? 'pendiente' : 'con error' }})
                                </option>
                            </select>
                            <p v-if="selectedFiscalIdentity" class="text-xs text-slate-400">Compartida por: {{ selectedFiscalIdentity.branch_names?.join(', ') || 'ninguna otra sucursal' }}.</p>
                            <div v-if="selectedFiscalIdentity && selectedFiscalIdentity.sync_status !== 'synced'" class="mt-2 flex items-center gap-2 text-xs text-amber-100">
                                <span>{{ selectedFiscalIdentity.sync_error || 'La identidad todavía no está sincronizada con ARCA y no puede emitir.' }}</span>
                                <button type="button" class="font-semibold underline" @click="retryFiscalIdentitySync(selectedFiscalIdentity)">Reintentar sincronización</button>
                            </div>
                            <p v-if="branchFiscalForm.errors.fiscal_identity_id || branchFiscalForm.errors.fiscal_identity" class="text-xs text-rose-200">{{ branchFiscalForm.errors.fiscal_identity_id || branchFiscalForm.errors.fiscal_identity }}</p>
                        </div>
                        <div class="flex items-end">
                            <button type="button" class="w-full rounded-lg border border-amber-100/25 px-3 py-2 text-sm font-semibold text-amber-100 hover:bg-amber-400/10" @click="createFiscalIdentity">Crear nueva</button>
                        </div>
                        <template v-if="!branchFiscalForm.fiscal_identity_id">
                            <div class="space-y-1">
                                <label class="text-sm font-medium text-slate-300">Nombre o razón social</label>
                                <input v-model="branchFiscalForm.fiscal_identity.legal_name" type="text" class="w-full rounded-xl border-amber-100/25 bg-slate-950/35 text-sm text-slate-100" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-sm font-medium text-slate-300">ID fiscal en API ARCA</label>
                                <input v-model="branchFiscalForm.fiscal_identity.external_fiscal_id" type="text" class="w-full rounded-xl border-amber-100/25 bg-slate-950/35 text-sm text-slate-100" placeholder="Ej. fiscal-nicolas" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-sm font-medium text-slate-300">CUIT emisor</label>
                            <input v-model="branchFiscalForm.fiscal_identity.cuit" type="text" inputmode="numeric" maxlength="11" class="w-full rounded-xl border-amber-100/25 bg-slate-950/35 text-sm text-slate-100" />
                            <p v-if="branchFiscalForm.errors['fiscal_identity.cuit']" class="text-xs text-rose-200">{{ branchFiscalForm.errors['fiscal_identity.cuit'] }}</p>
                            </div>
                            <div class="space-y-1">
                                <label class="text-sm font-medium text-slate-300">Ambiente</label>
                                <select v-model="branchFiscalForm.fiscal_identity.environment" class="w-full rounded-xl border-amber-100/25 bg-slate-950/35 text-sm text-slate-100"><option v-for="option in fiscalEnvironmentOptions" :key="option.value" :value="option.value">{{ option.label }}</option></select>
                            </div>
                            <div class="space-y-1">
                                <label class="text-sm font-medium text-slate-300">Condición fiscal</label>
                                <select v-model="branchFiscalForm.fiscal_identity.fiscal_condition" class="w-full rounded-xl border-amber-100/25 bg-slate-950/35 text-sm text-slate-100"><option v-for="option in fiscalConditionOptions" :key="option.value" :value="option.value">{{ option.label }}</option></select>
                            </div>
                        </template>
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-300">Punto de venta de esta sucursal</label>
                            <select v-if="branchFiscalPointOfSaleOptions.length" v-model.number="branchFiscalForm.fiscal_point_of_sale" class="w-full rounded-xl border-amber-100/25 bg-slate-950/35 text-sm text-slate-100"><option v-for="option in branchFiscalPointOfSaleOptions" :key="option.value" :value="option.value" :disabled="!option.selectable">{{ option.label }}{{ option.disabled_reason ? ` — ${option.disabled_reason}` : '' }}</option></select>
                            <input v-else v-model.number="branchFiscalForm.fiscal_point_of_sale" type="number" min="1" max="99998" class="w-full rounded-xl border-amber-100/25 bg-slate-950/35 text-sm text-slate-100" />
                            <p v-if="branchFiscalPointOfSaleMessage" class="text-xs text-amber-100/80">{{ branchFiscalPointOfSaleMessage }} Se conserva el ingreso manual administrativo.</p>
                            <p v-if="branchFiscalForm.errors.fiscal_point_of_sale" class="text-xs text-rose-200">{{ branchFiscalForm.errors.fiscal_point_of_sale }}</p>
                        </div>
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-300">Tipo de comprobante</label>
                            <select v-model="branchFiscalForm.fiscal_document_type" class="w-full rounded-xl border-amber-100/25 bg-slate-950/35 text-sm text-slate-100">
                                <option v-for="option in fiscalDocumentTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-300">Código de comprobante</label>
                            <select v-model.number="branchFiscalForm.fiscal_cbte_type" class="w-full rounded-xl border-amber-100/25 bg-slate-950/35 text-sm text-slate-100">
                                <option v-for="option in fiscalVoucherTypeOptions" :key="option.value" :value="option.value">{{ option.label }} - {{ option.value }}</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-300">Concepto</label>
                            <select v-model.number="branchFiscalForm.fiscal_concept" class="w-full rounded-xl border-amber-100/25 bg-slate-950/35 text-sm text-slate-100">
                                <option v-for="option in fiscalConceptOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-300">Autorización</label>
                            <select v-model="branchFiscalForm.fiscal_authorization_mode" class="w-full rounded-xl border-amber-100/25 bg-slate-950/35 text-sm text-slate-100">
                                <option v-for="option in fiscalAuthorizationModeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                        </div>
                        <div v-if="!branchFiscalForm.fiscal_identity_id" class="space-y-1 xl:col-span-2">
                            <label class="text-sm font-medium text-slate-300">Actividades ARCA</label>
                            <input v-model="branchFiscalForm.fiscal_identity.fiscal_activities" type="text" class="w-full rounded-xl border-amber-100/25 bg-slate-950/35 text-sm text-slate-100" placeholder="Ej. 492140, 471120" />
                        </div>
                    </div>

                    <div v-if="branchFiscalForm.fiscal_authorization_mode === 'caea'" class="mt-4 grid gap-4 md:grid-cols-4">
                        <div class="space-y-1 md:col-span-2">
                            <label class="text-sm font-medium text-slate-300">CAEA vigente</label>
                            <input v-model="branchFiscalForm.fiscal_caea_code" type="text" maxlength="14" class="w-full rounded-xl border-amber-100/25 bg-slate-950/35 text-sm text-slate-100" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-300">Período</label>
                            <input v-model="branchFiscalForm.fiscal_caea_period" type="text" maxlength="6" class="w-full rounded-xl border-amber-100/25 bg-slate-950/35 text-sm text-slate-100" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-300">Orden</label>
                            <select v-model="branchFiscalForm.fiscal_caea_order" class="w-full rounded-xl border-amber-100/25 bg-slate-950/35 text-sm text-slate-100"><option value="">-</option><option value="1">1</option><option value="2">2</option></select>
                        </div>
                        <div class="space-y-1"><label class="text-sm font-medium text-slate-300">Desde</label><input v-model="branchFiscalForm.fiscal_caea_from" type="date" class="w-full rounded-xl border-amber-100/25 bg-slate-950/35 text-sm text-slate-100" /></div>
                        <div class="space-y-1"><label class="text-sm font-medium text-slate-300">Hasta</label><input v-model="branchFiscalForm.fiscal_caea_to" type="date" class="w-full rounded-xl border-amber-100/25 bg-slate-950/35 text-sm text-slate-100" /></div>
                        <div class="space-y-1"><label class="text-sm font-medium text-slate-300">Vencimiento</label><input v-model="branchFiscalForm.fiscal_caea_due_date" type="date" class="w-full rounded-xl border-amber-100/25 bg-slate-950/35 text-sm text-slate-100" /></div>
                        <div class="space-y-1"><label class="text-sm font-medium text-slate-300">Tope informe</label><input v-model="branchFiscalForm.fiscal_caea_report_deadline" type="date" class="w-full rounded-xl border-amber-100/25 bg-slate-950/35 text-sm text-slate-100" /></div>
                    </div>

                    <p v-if="branchFiscalForm.errors.is_enabled" class="mt-3 text-xs text-rose-200">{{ branchFiscalForm.errors.is_enabled }}</p>
                    <div class="mt-5 flex justify-end gap-3">
                        <button type="button" class="rounded-lg border border-cyan-100/25 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/70" @click="cancelBranchFiscalEdition">Cancelar</button>
                        <button type="submit" class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-amber-400 disabled:opacity-50" :disabled="branchFiscalForm.processing">Guardar configuración ARCA</button>
                    </div>
                </form>

                <form v-if="showingBranchCommercialForm" class="mt-5 rounded-2xl border border-emerald-100/25 bg-slate-950/35 p-4" @submit.prevent="submitBranchCommercial">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-xs uppercase tracking-[0.25em] text-emerald-100/70">Operación de ventas</p>
                            <h4 class="mt-2 text-base font-semibold text-slate-100">Sectores y cobros: {{ commercialBranch?.name }}</h4>
                            <p class="mt-1 text-xs text-slate-400">Sólo se usan y validan al vender desde esta sucursal.</p>
                        </div>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-200"><input v-model="branchCommercialForm.advanced_sale_settings_enabled" type="checkbox" class="rounded border-emerald-100/25 bg-slate-950/35 text-emerald-500 focus:ring-emerald-500" /> Habilitar sectores y destinos de cobro</label>
                    </div>
                    <p v-if="branchCommercialForm.errors.sale_sectors" class="mt-3 text-sm text-rose-200">{{ branchCommercialForm.errors.sale_sectors }}</p>
                    <p v-if="branchCommercialForm.errors.payment_destinations" class="mt-2 text-sm text-rose-200">{{ branchCommercialForm.errors.payment_destinations }}</p>

                    <div class="mt-5 grid gap-5 xl:grid-cols-2">
                        <section class="rounded-xl border border-emerald-100/15 bg-slate-900/45 p-4">
                            <div class="flex items-center justify-between gap-3"><div><h5 class="font-semibold text-slate-100">Sectores / puntos de venta</h5><p class="mt-1 text-xs text-slate-400">Mostrador, taller, depósito, etc.</p></div><button type="button" class="rounded-lg border border-emerald-100/25 px-3 py-2 text-xs font-semibold text-emerald-100 hover:bg-emerald-400/10" @click="addBranchSector">Agregar</button></div>
                            <div class="mt-4 space-y-3">
                                <article v-for="(sector, index) in branchCommercialForm.sale_sectors" :key="`branch-sector-${sector.id ?? index}`" class="rounded-lg border border-emerald-100/10 p-3">
                                    <div class="flex justify-end"><button type="button" class="text-xs text-rose-200 hover:text-rose-100" @click="removeBranchSector(index)">{{ sector.id ? 'Desactivar' : 'Quitar' }}</button></div>
                                    <div class="mt-2 grid gap-3"><input v-model="sector.name" type="text" maxlength="255" class="w-full rounded-xl border-emerald-100/25 bg-slate-950/35 text-sm text-slate-100" placeholder="Nombre" /><input v-model="sector.description" type="text" maxlength="255" class="w-full rounded-xl border-emerald-100/25 bg-slate-950/35 text-sm text-slate-100" placeholder="Descripción opcional" /></div>
                                    <label class="mt-3 inline-flex items-center gap-2 text-xs text-slate-300"><input v-model="sector.is_active" type="checkbox" class="rounded border-emerald-100/25 bg-slate-950/35 text-emerald-500" /> Activo</label>
                                </article>
                            </div>
                        </section>
                        <section class="rounded-xl border border-emerald-100/15 bg-slate-900/45 p-4">
                            <div class="flex items-center justify-between gap-3"><div><h5 class="font-semibold text-slate-100">Destinos de cobro</h5><p class="mt-1 text-xs text-slate-400">Cuentas, alias o cajas de esta sucursal.</p></div><button type="button" class="rounded-lg border border-emerald-100/25 px-3 py-2 text-xs font-semibold text-emerald-100 hover:bg-emerald-400/10" @click="addBranchPaymentDestination">Agregar</button></div>
                            <div class="mt-4 space-y-3">
                                <article v-for="(destination, index) in branchCommercialForm.payment_destinations" :key="`branch-destination-${destination.id ?? index}`" class="rounded-lg border border-emerald-100/10 p-3">
                                    <div class="flex justify-end"><button type="button" class="text-xs text-rose-200 hover:text-rose-100" @click="removeBranchPaymentDestination(index)">{{ destination.id ? 'Desactivar' : 'Quitar' }}</button></div>
                                    <div class="mt-2 grid gap-3"><input v-model="destination.name" type="text" maxlength="255" class="w-full rounded-xl border-emerald-100/25 bg-slate-950/35 text-sm text-slate-100" placeholder="Nombre" /><input v-model="destination.account_holder" type="text" maxlength="255" class="w-full rounded-xl border-emerald-100/25 bg-slate-950/35 text-sm text-slate-100" placeholder="Titular" /><input v-model="destination.reference" type="text" maxlength="255" class="w-full rounded-xl border-emerald-100/25 bg-slate-950/35 text-sm text-slate-100" placeholder="Alias / referencia" /><input v-model="destination.account_number" type="text" maxlength="255" class="w-full rounded-xl border-emerald-100/25 bg-slate-950/35 text-sm text-slate-100" placeholder="Cuenta / CBU / CVU" /></div>
                                    <label class="mt-3 inline-flex items-center gap-2 text-xs text-slate-300"><input v-model="destination.is_active" type="checkbox" class="rounded border-emerald-100/25 bg-slate-950/35 text-emerald-500" /> Activo</label>
                                </article>
                            </div>
                        </section>
                    </div>
                    <div class="mt-5 flex justify-end gap-3"><button type="button" class="rounded-lg border border-cyan-100/25 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/70" @click="cancelBranchCommercialEdition">Cancelar</button><button type="submit" class="rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-emerald-400 disabled:opacity-50" :disabled="branchCommercialForm.processing">Guardar configuración de ventas</button></div>
                </form>
                </div>
            </details>
        </div>
    </AuthenticatedLayout>
</template>
