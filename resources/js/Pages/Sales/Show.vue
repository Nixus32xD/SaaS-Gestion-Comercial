<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    sale: { type: Object, required: true },
    fiscal: { type: Object, required: true },
    auto_back: { type: Boolean, default: false },
    advanced_sale_settings_enabled: { type: Boolean, default: false },
});

const money = (value) => new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    minimumFractionDigits: 2,
}).format(Number(value) || 0);

const paymentMethodLabel = computed(() => (
    props.sale.payment_method === 'transfer' ? 'Transferencia' : 'Efectivo'
));

const fiscalDocument = computed(() => props.fiscal?.document || null);

const fiscalStatusLabel = computed(() => {
    const status = fiscalDocument.value?.fiscal_status;

    if (status === 'authorized') return 'Autorizado';
    if (status === 'rejected') return 'Rechazado';
    if (status === 'uncertain') return 'Incierto';
    if (status === 'processing') return 'En proceso';
    if (status === 'error') return 'Error';

    return 'Sin emitir';
});

const fiscalBadgeClass = computed(() => {
    const status = fiscalDocument.value?.fiscal_status;

    if (status === 'authorized') return 'border-emerald-200/45 bg-emerald-400/15 text-emerald-100';
    if (status === 'rejected' || status === 'error') return 'border-rose-200/45 bg-rose-400/15 text-rose-100';
    if (status === 'uncertain' || status === 'processing') return 'border-amber-200/45 bg-amber-400/15 text-amber-100';

    return 'border-slate-200/20 bg-slate-950/35 text-slate-300';
});

const fiscalVoucherLabel = computed(() => {
    const document = fiscalDocument.value;

    if (!document?.fiscal_point_of_sale || !document?.fiscal_number) {
        return null;
    }

    const typeLabel = Number(document.fiscal_cbte_type) === 11 ? 'Factura C' : `Comprobante ${document.fiscal_cbte_type}`;
    const pointOfSale = String(document.fiscal_point_of_sale).padStart(5, '0');
    const number = String(document.fiscal_number).padStart(8, '0');

    return `${typeLabel} ${pointOfSale}-${number}`;
});

const fiscalAuthorizationType = computed(() => (
    fiscalDocument.value?.authorization_type || (fiscalDocument.value?.fiscal_cae ? 'CAE' : null)
));

const fiscalAuthorizationCode = computed(() => (
    fiscalDocument.value?.authorization_code || fiscalDocument.value?.fiscal_cae || null
));

const fiscalAuthorizationExpiresAt = computed(() => (
    fiscalDocument.value?.authorization_expires_at || fiscalDocument.value?.fiscal_cae_expires_at || null
));

const fiscalIssueLabel = computed(() => (
    fiscalDocument.value ? 'Reintentar factura' : 'Emitir factura'
));

const fiscalActionMessage = computed(() => {
    const action = fiscalDocument.value?.fiscal_error_action;

    if (action === 'conciliar') return 'Conciliar antes de reintentar.';
    if (action === 'revisar_configuracion') return 'Revisar certificado, CUIT, clave privada y servicio habilitado en ARCA.';
    if (action === 'revisar_datos') return 'Revisar datos del comprobante antes de emitir nuevamente.';
    if (action === 'reintentar') return 'Se puede reintentar cuando los datos ya fueron corregidos.';

    return null;
});

const fiscalObservations = computed(() => {
    const observations = fiscalDocument.value?.fiscal_observations || [];

    return Array.isArray(observations) ? observations : [observations];
});

const fiscalObservationText = (observation) => {
    if (typeof observation === 'string') {
        return observation;
    }

    if (observation && typeof observation === 'object') {
        return [observation.code, observation.message, observation.description]
            .filter(Boolean)
            .join(' - ');
    }

    return String(observation || '');
};

const issueFiscalDocument = () => {
    if (props.fiscal.production_confirmation_required) {
        const confirmed = window.confirm('Vas a emitir un comprobante fiscal en produccion. Esta accion puede impactar en ARCA. Continuar?');

        if (!confirmed) return;
    } else if (!window.confirm('Emitir comprobante fiscal para esta venta?')) {
        return;
    }

    router.post(route('sales.fiscal-documents.store', props.sale.id), {}, {
        preserveScroll: true,
    });
};

const reconcileFiscalDocument = () => {
    if (!fiscalDocument.value?.id) return;

    router.post(route('sales.fiscal-documents.reconcile', {
        sale: props.sale.id,
        saleFiscalDocument: fiscalDocument.value.id,
    }), {}, {
        preserveScroll: true,
    });
};

const redirectSeconds = ref(5);
const showAutoBackMessage = computed(() => props.auto_back === true);

let redirectTimeout = null;
let countdownInterval = null;

onMounted(() => {
    if (!showAutoBackMessage.value) return;

    countdownInterval = window.setInterval(() => {
        if (redirectSeconds.value > 1) {
            redirectSeconds.value -= 1;
        }
    }, 1000);

    redirectTimeout = window.setTimeout(() => {
        router.visit(route('sales.create'));
    }, redirectSeconds.value * 1000);
});

onBeforeUnmount(() => {
    if (redirectTimeout !== null) {
        window.clearTimeout(redirectTimeout);
    }

    if (countdownInterval !== null) {
        window.clearInterval(countdownInterval);
    }
});
</script>

<template>
    <Head :title="sale.sale_number || `Venta #${sale.id}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold text-slate-100">Detalle de venta</h2>
                    <p class="mt-1 text-sm text-slate-300/80">{{ sale.sale_number || `Venta #${sale.id}` }} - {{ sale.sold_at }}</p>
                </div>
                <Link :href="route('sales.index')" class="text-sm font-semibold text-slate-300 hover:text-slate-100">Volver</Link>
            </div>
        </template>

        <div class="grid gap-6">
            <section class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 backdrop-blur p-5 shadow-sm">
                <p v-if="showAutoBackMessage" class="mb-3 rounded-lg border border-emerald-200/45 bg-emerald-400/15 px-3 py-2 text-sm text-emerald-100">
                    Venta registrada. Volviendo a nueva venta en {{ redirectSeconds }}s...
                </p>

                <div class="grid gap-2 text-sm text-slate-300">
                    <p>Vendedor: <strong>{{ sale.user || '-' }}</strong></p>
                    <p v-if="advanced_sale_settings_enabled">Sector / punto de venta: <strong>{{ sale.sale_sector || '-' }}</strong></p>
                    <p v-if="advanced_sale_settings_enabled">Cuenta de cobro / destino: <strong>{{ sale.payment_destination || '-' }}</strong></p>
                    <p>Medio de pago: <strong>{{ paymentMethodLabel }}</strong></p>
                    <p>Subtotal: <strong>{{ money(sale.subtotal) }}</strong></p>
                    <p>Descuento: <strong>{{ money(sale.discount) }}</strong></p>
                    <p>Total: <strong>{{ money(sale.total) }}</strong></p>
                    <p v-if="sale.payment_method === 'cash'">Recibido: <strong>{{ money(sale.amount_received) }}</strong></p>
                    <p v-if="sale.payment_method === 'cash'">Vuelto: <strong>{{ money(sale.change_amount) }}</strong></p>
                    <p>Notas: <strong>{{ sale.notes || '-' }}</strong></p>
                </div>
            </section>

            <section v-if="fiscal.enabled" class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 backdrop-blur p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-slate-100">Facturacion fiscal</h3>
                        <p class="mt-1 text-sm text-slate-300/80">Estado: <strong>{{ fiscalStatusLabel }}</strong></p>
                    </div>
                    <span class="rounded-full border px-3 py-1 text-xs font-semibold" :class="fiscalBadgeClass">
                        {{ fiscalStatusLabel }}
                    </span>
                </div>

                <div class="mt-4 grid gap-4">
                    <div v-if="fiscalDocument" class="grid gap-2 text-sm text-slate-300">
                        <p v-if="fiscalVoucherLabel">Comprobante: <strong>{{ fiscalVoucherLabel }}</strong></p>
                        <p>Intento: <strong>{{ fiscalDocument.attempt_number }}</strong></p>
                        <p v-if="fiscalAuthorizationType">Tipo de autorizacion: <strong>{{ fiscalAuthorizationType }}</strong></p>
                        <p v-if="fiscalAuthorizationCode">Codigo de autorizacion: <strong>{{ fiscalAuthorizationCode }}</strong></p>
                        <p v-if="fiscalAuthorizationExpiresAt">Vencimiento: <strong>{{ fiscalAuthorizationExpiresAt }}</strong></p>
                        <p v-if="fiscalDocument.caea_period">Periodo CAEA: <strong>{{ fiscalDocument.caea_period }}</strong></p>
                        <p v-if="fiscalDocument.caea_order">Orden CAEA: <strong>{{ fiscalDocument.caea_order }}</strong></p>
                        <p v-if="fiscalDocument.caea_report_status">Reporte CAEA: <strong>{{ fiscalDocument.caea_report_status }}</strong></p>
                        <p v-if="fiscalDocument.caea_reported_at">Informado: <strong>{{ fiscalDocument.caea_reported_at }}</strong></p>
                        <p v-if="fiscalDocument.fiscal_idempotency_key">Idempotencia: <strong>{{ fiscalDocument.fiscal_idempotency_key }}</strong></p>
                        <p v-if="fiscalDocument.fiscal_error_code">Codigo: <strong>{{ fiscalDocument.fiscal_error_code }}</strong></p>
                        <p v-if="fiscalDocument.fiscal_error_message">Error: <strong>{{ fiscalDocument.fiscal_error_message }}</strong></p>
                        <p v-if="fiscalActionMessage">Accion sugerida: <strong>{{ fiscalActionMessage }}</strong></p>
                        <p v-if="fiscalDocument.fiscal_technical_message && fiscalDocument.fiscal_technical_message !== fiscalDocument.fiscal_error_message">
                            Detalle tecnico: <strong>{{ fiscalDocument.fiscal_technical_message }}</strong>
                        </p>
                        <div v-if="fiscalObservations.length" class="rounded-xl border border-amber-200/25 bg-amber-400/10 p-3 text-amber-50">
                            <p class="font-semibold">Observaciones</p>
                            <ul class="mt-2 list-disc space-y-1 pl-5">
                                <li v-for="(observation, index) in fiscalObservations" :key="index">
                                    {{ fiscalObservationText(observation) }}
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div v-else class="rounded-xl border border-slate-200/15 bg-slate-950/35 p-4 text-sm text-slate-300">
                        Esta venta todavia no tiene comprobante fiscal asociado.
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <button
                            v-if="fiscal.can_issue"
                            type="button"
                            class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500"
                            @click="issueFiscalDocument"
                        >
                            {{ fiscalIssueLabel }}
                        </button>

                        <button
                            v-if="fiscal.can_reconcile"
                            type="button"
                            class="rounded-lg border border-amber-200/45 px-4 py-2 text-sm font-semibold text-amber-100 hover:bg-amber-400/10"
                            @click="reconcileFiscalDocument"
                        >
                            Conciliar
                        </button>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 backdrop-blur p-5 shadow-sm">
                <h3 class="text-base font-semibold text-slate-100">Items</h3>
                <div class="mt-4 grid gap-3 md:hidden">
                    <article v-for="item in sale.items" :key="item.id" class="rounded-xl border border-cyan-100/20 bg-slate-950/35 p-4 text-sm text-slate-300">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-semibold text-slate-100">{{ item.product_name }}</p>
                            <span v-if="item.is_manual" class="rounded-full bg-amber-300/15 px-2 py-0.5 text-[11px] font-semibold text-amber-100">Sin stock</span>
                        </div>
                        <p class="mt-1 text-xs text-slate-400">
                            <template v-if="item.is_manual">
                                Monto fijo
                            </template>
                            <template v-else>
                                {{ item.quantity }} {{ item.quantity_label }}
                            </template>
                        </p>
                        <p class="mt-1 text-xs text-slate-400">
                            {{ money(item.unit_price) }}
                            <span v-if="item.price_label">{{ item.price_label }}</span>
                        </p>
                        <p class="mt-3 text-sm">Subtotal: <strong class="text-slate-100">{{ money(item.subtotal) }}</strong></p>
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
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="item in sale.items" :key="item.id">
                                <td class="px-3 py-2 font-semibold text-slate-100">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span>{{ item.product_name }}</span>
                                        <span v-if="item.is_manual" class="rounded-full bg-amber-300/15 px-2 py-0.5 text-[11px] font-semibold text-amber-100">Sin stock</span>
                                    </div>
                                </td>
                                <td class="px-3 py-2">
                                    <template v-if="item.is_manual">
                                        <span class="text-xs text-slate-400">Monto fijo</span>
                                    </template>
                                    <template v-else>
                                        {{ item.quantity }} <span class="text-xs text-slate-400">{{ item.quantity_label }}</span>
                                    </template>
                                </td>
                                <td class="px-3 py-2">
                                    {{ money(item.unit_price) }}
                                    <span v-if="item.price_label" class="text-xs text-slate-400">{{ item.price_label }}</span>
                                </td>
                                <td class="px-3 py-2">{{ money(item.subtotal) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
