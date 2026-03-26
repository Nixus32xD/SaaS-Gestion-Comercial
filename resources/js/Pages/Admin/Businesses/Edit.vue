<script setup>
import { computed, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

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

const implementationPlans = computed(() => props.commercial_catalog?.implementation_plans || []);
const maintenancePlans = computed(() => props.commercial_catalog?.maintenance_plans || []);
const availablePaymentPlans = computed(() => (
    paymentForm.type === 'maintenance' ? maintenancePlans.value : implementationPlans.value
));

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

        <div class="grid gap-6">
            <section class="grid gap-4 xl:grid-cols-2">
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

            <form class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-sm backdrop-blur" @submit.prevent="submitBilling">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-100">Planes y abonos</h3>
                        <p class="mt-1 text-sm text-slate-300/80">Define lo pactado con el cliente y la ventana de gracia del comercio.</p>
                    </div>
                </div>

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

            <form class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-sm backdrop-blur" @submit.prevent="submit">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-100">Datos generales</h3>
                        <p class="mt-1 text-sm text-slate-300/80">Configuracion base del comercio.</p>
                    </div>
                </div>

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

            <form class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-sm backdrop-blur" @submit.prevent="submitPayment">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-100">Registrar pago manual</h3>
                        <p class="mt-1 text-sm text-slate-300/80">Cada pago queda asentado y, si es mantenimiento, actualiza la cobertura del comercio.</p>
                    </div>
                </div>

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

            <section class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-sm backdrop-blur">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-100">Historial de pagos</h3>
                        <p class="mt-1 text-sm text-slate-300/80">Ultimos movimientos registrados manualmente para este comercio.</p>
                    </div>
                </div>

                <div class="mt-4 overflow-x-auto rounded-xl border border-cyan-100/20 app-table-wrap">
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
            </section>

            <form class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-sm backdrop-blur" @submit.prevent="submitSalesSettings">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-100">Funciones por comercio</h3>
                        <p class="mt-1 text-sm text-slate-300/80">Configuracion exclusiva para ventas avanzadas y acceso al catalogo global.</p>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="inline-flex items-center gap-2 rounded-xl border border-cyan-100/20 bg-slate-950/35 px-3 py-2 text-sm text-slate-200">
                            <input v-model="salesSettingsForm.advanced_sale_settings_enabled" type="checkbox" class="rounded border-cyan-100/25 bg-slate-950/35 text-indigo-500 focus:ring-indigo-500">
                            Habilitar ventas avanzadas
                        </label>
                        <label class="inline-flex items-center gap-2 rounded-xl border border-cyan-100/20 bg-slate-950/35 px-3 py-2 text-sm text-slate-200">
                            <input v-model="salesSettingsForm.global_product_catalog_enabled" type="checkbox" class="rounded border-cyan-100/25 bg-slate-950/35 text-indigo-500 focus:ring-indigo-500">
                            Habilitar catalogo global de productos
                        </label>
                    </div>
                </div>

                <p v-if="salesSettingsForm.errors.sale_sectors" class="mt-3 text-sm text-rose-300">{{ salesSettingsForm.errors.sale_sectors }}</p>
                <p v-if="salesSettingsForm.errors.payment_destinations" class="mt-2 text-sm text-rose-300">{{ salesSettingsForm.errors.payment_destinations }}</p>

                <div class="mt-5 grid gap-6 xl:grid-cols-2">
                    <section class="rounded-2xl border border-cyan-100/20 bg-slate-950/35 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h4 class="text-base font-semibold text-slate-100">Sectores / puntos de venta</h4>
                                <p class="mt-1 text-xs text-slate-400">Ejemplo: Almacen, Viviendas.</p>
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
                                <h4 class="text-base font-semibold text-slate-100">Cuentas de cobro / destino</h4>
                                <p class="mt-1 text-xs text-slate-400">Mercado Pago, banco, alias o referencia equivalente.</p>
                            </div>
                            <button type="button" class="rounded-lg border border-cyan-100/25 px-3 py-2 text-xs font-semibold text-slate-300 hover:bg-slate-800/70" @click="addPaymentDestination">
                                Agregar cuenta
                            </button>
                        </div>

                        <div class="mt-4 space-y-3">
                            <article v-for="(destination, index) in salesSettingsForm.payment_destinations" :key="`destination-${destination.id ?? `new-${index}`}`" class="rounded-xl border border-cyan-100/15 bg-slate-900/45 p-4">
                                <div class="grid gap-3">
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="text-sm font-semibold text-slate-100">Cuenta {{ index + 1 }}</p>
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
                                        <input v-model="destination.name" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Ej. Mercado Pago Almacen" />
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
                                        <input v-model="destination.account_number" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="CBU, CVU, cuenta, link, etc." />
                                    </div>
                                </div>
                            </article>

                            <p v-if="!salesSettingsForm.payment_destinations.length" class="rounded-xl border border-dashed border-cyan-100/20 px-4 py-5 text-sm text-slate-400">
                                Todavia no hay cuentas configuradas.
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
        </div>
    </AuthenticatedLayout>
</template>
