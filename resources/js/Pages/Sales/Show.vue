<script setup>
import AppPanel from '@/Components/AppPanel.vue';
import MetricCard from '@/Components/MetricCard.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    sale: { type: Object, required: true },
    auto_back: { type: Boolean, default: false },
    advanced_sale_settings_enabled: { type: Boolean, default: false },
    receipt_feature_available: { type: Boolean, default: false },
});

const money = (value) => new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    minimumFractionDigits: 2,
}).format(Number(value) || 0);

const paymentMethodLabel = computed(() => (
    props.sale.payment_method === 'transfer' ? 'Transferencia' : 'Efectivo'
));

const paymentStatusLabel = computed(() => {
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
const redirectSeconds = ref(5);
const showAutoBackMessage = computed(() => props.auto_back === true);
const receiptInput = ref(null);
const receiptForm = useForm({ receipt: null });
const receiptFileName = computed(() => receiptForm.receipt?.name || '');

let redirectTimeout = null;
let countdownInterval = null;

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
                    <p class="mt-1 text-sm text-slate-300/80">{{ sale.sale_number || `Venta #${sale.id}` }} · {{ sale.sold_at }}</p>
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

            <div class="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_22rem]">
                <div class="grid gap-6">
                    <AppPanel title="Resumen de cobro" subtitle="Estado actual del dinero y condiciones con las que quedo registrada la venta.">
                        <div class="app-chip-row">
                            <StatusBadge :tone="paymentStatusTone" :label="paymentStatusLabel" />
                            <StatusBadge tone="neutral" :label="sale.payment_method ? paymentMethodLabel : 'Sin cobro inicial'" />
                            <StatusBadge :tone="pendingTone" :label="Number(sale.pending_amount) > 0 ? 'Con saldo pendiente' : 'Cobro cerrado'" />
                        </div>

                        <div class="mt-4 grid gap-3 md:grid-cols-2 text-sm text-slate-300">
                            <div class="app-subsection">
                                <p class="text-xs uppercase tracking-[0.18em] text-cyan-100/70">Importes</p>
                                <p class="mt-2">Subtotal: <strong class="text-slate-100">{{ money(sale.subtotal) }}</strong></p>
                                <p class="mt-1">Descuento: <strong class="text-slate-100">{{ money(sale.discount) }}</strong></p>
                                <p class="mt-1">Total: <strong class="text-slate-100">{{ money(sale.total) }}</strong></p>
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
                                <p v-if="advanced_sale_settings_enabled" class="mt-1">Cuenta de cobro / destino: <strong class="text-slate-100">{{ sale.payment_destination || '-' }}</strong></p>
                                <p class="mt-1">Fecha: <strong class="text-slate-100">{{ sale.sold_at || '-' }}</strong></p>
                            </div>
                        </div>

                        <div class="mt-4 app-subsection">
                            <p class="app-section-title">Notas</p>
                            <p class="mt-2 text-sm text-slate-300">{{ sale.notes || 'Sin notas operativas.' }}</p>
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
