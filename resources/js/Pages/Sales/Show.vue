<script setup>
import AppPanel from '@/Components/AppPanel.vue';
import MetricCard from '@/Components/MetricCard.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { paymentMethodLabel as formatPaymentMethodLabel } from '@/Support/paymentMethods';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    sale: { type: Object, required: true },
    fiscal: { type: Object, required: true },
    mercadopago_point: { type: Object, default: () => ({}) },
    auto_back: { type: Boolean, default: false },
    advanced_sale_settings_enabled: { type: Boolean, default: false },
    receipt_feature_available: { type: Boolean, default: false },
});

const money = (value) => new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    minimumFractionDigits: 2,
}).format(Number(value) || 0);

const paymentMethodLabel = computed(() => formatPaymentMethodLabel(props.sale.payment_method));

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

const fiscalStatusTone = computed(() => {
    const status = fiscalDocument.value?.fiscal_status;

    if (status === 'authorized') return 'success';
    if (status === 'rejected' || status === 'error') return 'danger';
    if (status === 'uncertain' || status === 'processing') return 'warning';

    return 'neutral';
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
    if (action === 'revisar_configuracion') return 'Revisar token, CUIT y configuracion fiscal externa.';
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
        const confirmed = window.confirm('Vas a emitir un comprobante fiscal en produccion. Esta accion puede tener impacto fiscal. Continuar?');

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

const hasPendingMercadoPagoPointPayment = () => (props.sale.payments || []).some((payment) => (
    payment.provider === 'mercadopago' && payment.status === 'pending'
));

const paymentStatusLabel = computed(() => {
    if (props.sale.payment_status === 'pending' && hasPendingMercadoPagoPointPayment()) return 'Pendiente Point';
    if (props.sale.payment_status === 'partial') return 'Pago parcial';
    if (props.sale.payment_status === 'pending') return 'Fiada';
    return 'Pagada';
});

const paymentStatusTone = computed(() => {
    if (props.sale.payment_status === 'partial') return 'warning';
    if (props.sale.payment_status === 'pending') return 'danger';
    return 'success';
});

const itemsCount = computed(() => props.sale.items?.length || 0);
const manualItemsCount = computed(() => (props.sale.items || []).filter((item) => item.is_manual).length);
const pendingTone = computed(() => (Number(props.sale.pending_amount) > 0 ? 'warning' : 'success'));
const salePayments = computed(() => props.sale.payments || []);
const hasSalePendingAmount = computed(() => Number(props.sale.pending_amount) > 0);
const pendingMercadoPagoPayment = computed(() => salePayments.value.find((payment) => (
    payment.provider === 'mercadopago' && payment.status === 'pending'
)) || null);
const canCreateMercadoPagoPointOrder = computed(() => (
    props.mercadopago_point?.enabled === true
    && hasSalePendingAmount.value
    && !pendingMercadoPagoPayment.value
));
const mercadoPagoPointUnavailableMessage = computed(() => {
    if (props.mercadopago_point?.enabled) return '';

    const missing = [];

    if (!props.mercadopago_point?.access_token_configured) missing.push('access token');
    if (!props.mercadopago_point?.terminal_configured) missing.push('terminal Point');

    return missing.length
        ? `Falta configurar ${missing.join(' y ')}.`
        : 'Mercado Pago Point no esta configurado.';
});
const hasFiscalBreakdown = computed(() => (
    Number(props.sale.fiscal_vat_amount || 0) > 0
    || Number(props.sale.fiscal_exempt_amount || 0) > 0
    || Number(props.sale.fiscal_non_taxed_amount || 0) > 0
));
const redirectSeconds = ref(5);
const showAutoBackMessage = computed(() => props.auto_back === true);
const receiptInput = ref(null);
const receiptForm = useForm({ receipt: null });
const receiptFileName = computed(() => receiptForm.receipt?.name || '');
const mercadoPagoPointForm = useForm({ payment_method: 'debit_card' });
const syncingPaymentId = ref(null);
const mercadoPagoPointSyncError = ref('');

let redirectTimeout = null;
let countdownInterval = null;
let paymentPollInterval = null;

const setReceipt = (event) => {
    const [receipt] = event.target?.files || [];

    receiptForm.receipt = receipt || null;
};

const submitReceipt = () => {
    receiptForm.post(route('sales.receipt.store', props.sale.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            receiptForm.reset('receipt');

            if (receiptInput.value) {
                receiptInput.value.value = '';
            }
        },
    });
};

const createMercadoPagoPointOrder = (paymentMethod) => {
    mercadoPagoPointForm.payment_method = paymentMethod;
    mercadoPagoPointSyncError.value = '';

    mercadoPagoPointForm.post(route('sales.payments.mercadopago-point.store', props.sale.id), {
        preserveScroll: true,
    });
};

const syncMercadoPagoPointPayment = async (payment, options = {}) => {
    if (!payment?.can_sync || syncingPaymentId.value !== null) return;

    syncingPaymentId.value = payment.id;
    if (!options.silent) {
        mercadoPagoPointSyncError.value = '';
    }

    try {
        const response = await fetch(route('sales.payments.mercadopago-point.show', {
            sale: props.sale.id,
            payment: payment.id,
        }), {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
            },
        });

        if (!response.ok) {
            const payload = await response.json().catch(() => ({}));

            throw new Error(payload.message || 'No se pudo consultar Mercado Pago.');
        }

        router.reload({
            only: ['sale'],
            preserveScroll: true,
        });
    } catch (error) {
        if (!options.silent) {
            mercadoPagoPointSyncError.value = error.message || 'No se pudo actualizar el estado del pago.';
        }
    } finally {
        syncingPaymentId.value = null;
    }
};

const salePaymentProviderLabel = (provider) => {
    if (provider === 'mercadopago') return 'Mercado Pago';
    if (provider === 'manual') return 'Manual';
    if (provider === 'external') return 'Externo';

    return provider || '-';
};

const salePaymentStatusLabel = (status) => {
    if (status === 'approved') return 'Aprobado';
    if (status === 'pending') return 'Pendiente';
    if (status === 'rejected') return 'Rechazado';
    if (status === 'cancelled') return 'Cancelado';
    if (status === 'refunded') return 'Devuelto';

    return status || '-';
};

const salePaymentStatusTone = (status) => {
    if (status === 'approved') return 'success';
    if (status === 'pending') return 'warning';
    if (status === 'rejected') return 'danger';
    if (status === 'cancelled' || status === 'refunded') return 'neutral';

    return 'neutral';
};

const salePaymentDate = (payment) => (
    payment.approved_at
    || payment.rejected_at
    || payment.cancelled_at
    || payment.refunded_at
    || payment.requested_at
    || payment.created_at
    || '-'
);

onMounted(() => {
    paymentPollInterval = window.setInterval(() => {
        if (pendingMercadoPagoPayment.value) {
            void syncMercadoPagoPointPayment(pendingMercadoPagoPayment.value, { silent: true });
        }
    }, 5000);

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

    if (paymentPollInterval !== null) {
        window.clearInterval(paymentPollInterval);
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
                <div class="flex flex-wrap gap-2">
                    <a :href="sale.print_url" target="_blank" rel="noopener" class="rounded-lg border border-cyan-100/25 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/70">Imprimir</a>
                    <Link :href="route('sales.create')" class="rounded-lg border border-cyan-100/25 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/70">Nueva venta</Link>
                    <Link :href="route('sales.index')" class="rounded-lg border border-cyan-100/25 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/70">Volver</Link>
                </div>
            </div>
        </template>

        <div class="grid gap-6">
            <section class="app-kpi-grid">
                <MetricCard label="Total vendido" :value="money(sale.total)" hint="Importe final de la operacion." :tone="sale.total > 0 ? 'accent' : 'default'" />
                <MetricCard label="Saldo pendiente" :value="money(sale.pending_amount)" hint="Monto aun no cobrado en esta venta." :tone="pendingTone" />
                <MetricCard label="Items facturados" :value="itemsCount" :hint="`${manualItemsCount} items manuales sin stock.`" />
                <MetricCard label="Cobrado acumulado" :value="money(sale.paid_amount)" hint="Total registrado como cobrado hasta ahora." :tone="Number(sale.paid_amount) > 0 ? 'success' : 'default'" />
            </section>

            <AppPanel v-if="showAutoBackMessage" tone="success" title="Venta registrada" :subtitle="`Volviendo a nueva venta en ${redirectSeconds}s.`" />

            <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div class="grid gap-6">
                    <AppPanel title="Resumen de venta" :subtitle="`${paymentMethodLabel} - ${paymentStatusLabel}`">
                        <div class="mt-4 grid gap-3 md:grid-cols-2 text-sm text-slate-300">
                            <div class="app-subsection">
                                <p class="text-xs uppercase tracking-[0.18em] text-cyan-100/70">Importes</p>
                                <p class="mt-2">Subtotal: <strong class="text-slate-100">{{ money(sale.subtotal) }}</strong></p>
                                <p class="mt-1">Descuento: <strong class="text-slate-100">{{ money(sale.discount) }}</strong></p>
                                <p class="mt-1">Total: <strong class="text-slate-100">{{ money(sale.total) }}</strong></p>
                            </div>
                            <div v-if="hasFiscalBreakdown" class="app-subsection">
                                <p class="text-xs uppercase tracking-[0.18em] text-cyan-100/70">IVA fiscal</p>
                                <p class="mt-2">Neto gravado: <strong class="text-slate-100">{{ money(sale.fiscal_net_amount) }}</strong></p>
                                <p class="mt-1">IVA: <strong class="text-slate-100">{{ money(sale.fiscal_vat_amount) }}</strong></p>
                                <p v-if="Number(sale.fiscal_exempt_amount) > 0" class="mt-1">Exento: <strong class="text-slate-100">{{ money(sale.fiscal_exempt_amount) }}</strong></p>
                                <p v-if="Number(sale.fiscal_non_taxed_amount) > 0" class="mt-1">No gravado: <strong class="text-slate-100">{{ money(sale.fiscal_non_taxed_amount) }}</strong></p>
                            </div>
                            <div class="app-subsection">
                                <p class="text-xs uppercase tracking-[0.18em] text-cyan-100/70">Cobro</p>
                                <p class="mt-2">Pagado acumulado: <strong class="text-slate-100">{{ money(sale.paid_amount) }}</strong></p>
                                <p class="mt-1">Pendiente actual: <strong class="text-slate-100">{{ money(sale.pending_amount) }}</strong></p>
                                <p v-if="sale.payment_method === 'cash' && sale.amount_received > 0" class="mt-1">Recibido al momento: <strong class="text-slate-100">{{ money(sale.amount_received) }}</strong></p>
                                <p v-if="sale.payment_method === 'cash' && sale.amount_received > 0" class="mt-1">Vuelto inicial: <strong class="text-slate-100">{{ money(sale.change_amount) }}</strong></p>
                            </div>
                        </div>
                    </AppPanel>

                    <AppPanel title="Contexto de la operacion" subtitle="Datos que el negocio necesita identificar en segundos.">
                        <div class="grid gap-3 md:grid-cols-2 text-sm text-slate-300">
                            <div class="app-subsection">
                                <p class="app-section-title">Responsables</p>
                                <p class="mt-2">Vendedor: <strong class="text-slate-100">{{ sale.user || '-' }}</strong></p>
                                <p class="mt-1">Cliente: <strong class="text-slate-100">{{ sale.customer || 'Consumidor final' }}</strong></p>
                            </div>
                            <div class="app-subsection">
                                <p class="app-section-title">Circuito de venta</p>
                                <p v-if="advanced_sale_settings_enabled" class="mt-2">Sector / punto de venta: <strong class="text-slate-100">{{ sale.sale_sector || '-' }}</strong></p>
                                <p v-if="advanced_sale_settings_enabled" class="mt-1">Destino de cobro: <strong class="text-slate-100">{{ sale.payment_destination || '-' }}</strong></p>
                                <p class="mt-1">Fecha: <strong class="text-slate-100">{{ sale.sold_at || '-' }}</strong></p>
                            </div>
                        </div>

                        <div class="mt-4 app-subsection">
                            <p class="app-section-title">Notas</p>
                            <p class="mt-2 text-sm text-slate-300">{{ sale.notes || 'Sin notas operativas.' }}</p>
                        </div>
                    </AppPanel>

                    <AppPanel v-if="fiscal.enabled" title="Facturacion fiscal" :subtitle="`Estado: ${fiscalStatusLabel}`">
                        <template #actions>
                            <StatusBadge :tone="fiscalStatusTone" :label="fiscalStatusLabel" />
                        </template>

                        <div class="grid gap-4">
                            <div v-if="fiscalDocument" class="grid gap-2 text-sm text-slate-300">
                                <p v-if="fiscalVoucherLabel">Comprobante: <strong class="text-slate-100">{{ fiscalVoucherLabel }}</strong></p>
                                <p>Intento: <strong class="text-slate-100">{{ fiscalDocument.attempt_number }}</strong></p>
                                <p v-if="fiscalDocument.fiscal_cae">CAE: <strong class="text-slate-100">{{ fiscalDocument.fiscal_cae }}</strong></p>
                                <p v-if="fiscalDocument.fiscal_cae_expires_at">Vencimiento CAE: <strong class="text-slate-100">{{ fiscalDocument.fiscal_cae_expires_at }}</strong></p>
                                <p v-if="fiscalDocument.fiscal_idempotency_key">Idempotencia: <strong class="text-slate-100">{{ fiscalDocument.fiscal_idempotency_key }}</strong></p>
                                <p v-if="fiscalDocument.fiscal_error_code">Codigo: <strong class="text-slate-100">{{ fiscalDocument.fiscal_error_code }}</strong></p>
                                <p v-if="fiscalDocument.fiscal_error_message">Error: <strong class="text-slate-100">{{ fiscalDocument.fiscal_error_message }}</strong></p>
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

                                <a
                                    v-if="fiscalDocument?.pdf_url"
                                    :href="fiscalDocument.pdf_url"
                                    target="_blank"
                                    rel="noopener"
                                    class="rounded-lg border border-cyan-100/25 px-4 py-2 text-sm font-semibold text-cyan-100 hover:bg-slate-800/70"
                                >
                                    Descargar PDF
                                </a>
                            </div>
                        </div>
                    </AppPanel>

                    <AppPanel title="Items facturados" subtitle="Detalle de la venta con identificacion clara de items manuales y subtotales.">
                        <div class="grid gap-3 md:hidden">
                            <article v-for="item in sale.items" :key="item.id" class="rounded-xl border border-cyan-100/20 bg-slate-950/35 p-4 text-sm text-slate-300">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-semibold text-slate-100">{{ item.product_name }}</p>
                                    <StatusBadge v-if="item.is_manual" tone="warning" size="sm" label="Manual" />
                                </div>
                                <p class="mt-2 text-xs text-slate-400">
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
                                <p class="mt-1 text-xs text-slate-400">IVA: {{ item.vat_label || '-' }}</p>
                                <p class="mt-3 text-sm">Subtotal: <strong class="text-slate-100">{{ money(item.subtotal) }}</strong></p>
                            </article>
                        </div>

                        <div class="hidden overflow-x-auto rounded-xl border border-cyan-100/20 app-table-wrap md:block">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-950/35">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium text-slate-300/80">Producto</th>
                                        <th class="px-3 py-2 text-left font-medium text-slate-300/80">Cantidad</th>
                                        <th class="px-3 py-2 text-left font-medium text-slate-300/80">Precio</th>
                                        <th class="px-3 py-2 text-left font-medium text-slate-300/80">IVA</th>
                                        <th class="px-3 py-2 text-left font-medium text-slate-300/80">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="item in sale.items" :key="item.id">
                                        <td class="px-3 py-2 font-semibold text-slate-100">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span>{{ item.product_name }}</span>
                                                <StatusBadge v-if="item.is_manual" tone="warning" size="sm" label="Manual" />
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
                                        <td class="px-3 py-2 text-slate-300">{{ item.vat_label || '-' }}</td>
                                        <td class="px-3 py-2">{{ money(item.subtotal) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </AppPanel>
                </div>

                <aside class="app-sticky-column">
                    <AppPanel title="Lectura rapida" :tone="Number(sale.pending_amount) > 0 ? 'warning' : 'success'" subtitle="Resumen corto para caja, seguimiento o entrega.">
                        <div class="space-y-3 text-sm text-slate-300">
                            <div class="flex items-center justify-between gap-3">
                                <span>Estado</span>
                                <StatusBadge :tone="paymentStatusTone" :label="paymentStatusLabel" />
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <span>Cliente</span>
                                <span class="text-right font-semibold text-slate-100">{{ sale.customer || 'Consumidor final' }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <span>Total</span>
                                <span class="font-semibold text-slate-100">{{ money(sale.total) }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <span>Cobrado</span>
                                <span class="font-semibold text-slate-100">{{ money(sale.paid_amount) }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <span>Pendiente</span>
                                <span :class="Number(sale.pending_amount) > 0 ? 'text-amber-100' : 'text-emerald-100'" class="font-semibold">{{ money(sale.pending_amount) }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <span>Items</span>
                                <span class="font-semibold text-slate-100">{{ itemsCount }}</span>
                            </div>
                        </div>
                    </AppPanel>

                    <AppPanel title="Mercado Pago Point" subtitle="Enviar saldo pendiente a la terminal.">
                        <div class="space-y-4 text-sm text-slate-300">
                            <div v-if="!mercadopago_point.enabled" class="rounded-xl border border-amber-200/25 bg-amber-400/10 px-4 py-3 text-amber-50">
                                {{ mercadoPagoPointUnavailableMessage }}
                            </div>

                            <div v-else class="space-y-3">
                                <div class="grid gap-2 sm:grid-cols-3">
                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center rounded-xl bg-cyan-600 px-3 py-2 text-sm font-semibold text-white hover:bg-cyan-500 disabled:cursor-not-allowed disabled:opacity-50"
                                        :disabled="!canCreateMercadoPagoPointOrder || mercadoPagoPointForm.processing"
                                        @click="createMercadoPagoPointOrder('debit_card')"
                                    >
                                        Debito
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center rounded-xl border border-cyan-100/25 px-3 py-2 text-sm font-semibold text-cyan-100 hover:bg-slate-800/70 disabled:cursor-not-allowed disabled:opacity-50"
                                        :disabled="!canCreateMercadoPagoPointOrder || mercadoPagoPointForm.processing"
                                        @click="createMercadoPagoPointOrder('credit_card')"
                                    >
                                        Credito
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center rounded-xl border border-cyan-100/25 px-3 py-2 text-sm font-semibold text-cyan-100 hover:bg-slate-800/70 disabled:cursor-not-allowed disabled:opacity-50"
                                        :disabled="!canCreateMercadoPagoPointOrder || mercadoPagoPointForm.processing"
                                        @click="createMercadoPagoPointOrder('qr')"
                                    >
                                        QR
                                    </button>
                                </div>

                                <p v-if="pendingMercadoPagoPayment" class="text-xs text-amber-100">
                                    Ya hay una orden pendiente en Point.
                                </p>
                                <p v-else-if="!hasSalePendingAmount" class="text-xs text-emerald-100">
                                    La venta no tiene saldo pendiente.
                                </p>
                            </div>

                            <p v-if="mercadoPagoPointForm.errors.mercadopago_point || mercadoPagoPointForm.errors.payment || mercadoPagoPointForm.errors.payment_method" class="text-xs text-rose-300">
                                {{ mercadoPagoPointForm.errors.mercadopago_point || mercadoPagoPointForm.errors.payment || mercadoPagoPointForm.errors.payment_method }}
                            </p>
                            <p v-if="mercadoPagoPointSyncError" class="text-xs text-rose-300">{{ mercadoPagoPointSyncError }}</p>

                            <div class="border-t border-cyan-100/10 pt-4">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="font-semibold text-slate-100">Cobros</p>
                                    <span class="text-xs text-slate-400">{{ salePayments.length }}</span>
                                </div>

                                <div v-if="salePayments.length" class="mt-3 space-y-3">
                                    <article v-for="payment in salePayments" :key="payment.id" class="rounded-xl border border-cyan-100/15 bg-slate-950/35 px-4 py-3">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <p class="font-semibold text-slate-100">{{ money(payment.amount) }}</p>
                                                <p class="mt-1 text-xs text-slate-400">
                                                    {{ salePaymentProviderLabel(payment.provider) }} - {{ formatPaymentMethodLabel(payment.method, '-') }}
                                                </p>
                                            </div>
                                            <StatusBadge :tone="salePaymentStatusTone(payment.status)" size="sm" :label="salePaymentStatusLabel(payment.status)" />
                                        </div>

                                        <div class="mt-3 space-y-1 text-xs text-slate-400">
                                            <p>Fecha: <span class="text-slate-300">{{ salePaymentDate(payment) }}</span></p>
                                            <p v-if="payment.provider_order_id">Orden: <span class="text-slate-300">{{ payment.provider_order_id }}</span></p>
                                            <p v-if="payment.provider_status">Estado MP: <span class="text-slate-300">{{ payment.provider_status }}</span></p>
                                        </div>

                                        <button
                                            v-if="payment.can_sync"
                                            type="button"
                                            class="mt-3 inline-flex w-full items-center justify-center rounded-xl border border-cyan-100/25 px-3 py-2 text-xs font-semibold text-cyan-100 hover:bg-slate-800/70 disabled:cursor-not-allowed disabled:opacity-50"
                                            :disabled="syncingPaymentId !== null"
                                            @click="syncMercadoPagoPointPayment(payment)"
                                        >
                                            {{ syncingPaymentId === payment.id ? 'Consultando...' : 'Actualizar estado' }}
                                        </button>
                                    </article>
                                </div>

                                <p v-else class="mt-3 rounded-xl border border-cyan-100/15 bg-slate-950/35 px-4 py-3 text-xs text-slate-400">
                                    Sin cobros registrados.
                                </p>
                            </div>
                        </div>
                    </AppPanel>

                    <AppPanel v-if="receipt_feature_available" title="Comprobante" subtitle="Adjunta o reemplaza el respaldo de la venta sin tocar el resto de la operacion.">
                        <div class="space-y-3 text-sm text-slate-300">
                            <div class="rounded-xl border border-cyan-100/15 bg-slate-950/35 px-4 py-3">
                                <p class="font-semibold text-slate-100">{{ sale.receipt?.original_name || 'Sin comprobante adjunto' }}</p>
                                <p class="mt-1 text-xs text-slate-400">{{ sale.receipt?.uploaded_at ? `Cargado ${sale.receipt.uploaded_at}` : 'Puedes subir un PDF o imagen del ticket.' }}</p>
                            </div>

                            <a v-if="sale.receipt?.download_url" :href="sale.receipt.download_url" class="inline-flex w-full items-center justify-center rounded-xl border border-cyan-100/25 px-4 py-2 text-sm font-semibold text-cyan-100 hover:bg-slate-800/70">
                                Descargar comprobante
                            </a>

                            <form class="grid gap-3" @submit.prevent="submitReceipt">
                                <div>
                                    <label for="sale-receipt-upload" class="mb-1 block text-sm font-medium text-slate-300">Subir archivo</label>
                                    <input
                                        id="sale-receipt-upload"
                                        ref="receiptInput"
                                        type="file"
                                        accept="application/pdf,image/jpeg,image/png,image/webp"
                                        class="w-full rounded-xl border border-cyan-100/25 bg-slate-950/35 px-3 py-2 text-sm text-slate-100 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-800 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-slate-100"
                                        @change="setReceipt"
                                    >
                                    <p class="mt-2 text-xs text-slate-400">Acepta PDF, JPG, PNG o WEBP. Maximo 5 MB.</p>
                                    <p v-if="receiptFileName" class="mt-2 text-xs text-cyan-100">Seleccionado: {{ receiptFileName }}</p>
                                    <p v-if="receiptForm.errors.receipt" class="mt-2 text-xs text-rose-300">{{ receiptForm.errors.receipt }}</p>
                                </div>

                                <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-600 disabled:opacity-50" :disabled="receiptForm.processing || !receiptForm.receipt">
                                    {{ sale.receipt ? 'Reemplazar comprobante' : 'Adjuntar comprobante' }}
                                </button>
                            </form>
                        </div>
                    </AppPanel>
                </aside>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
