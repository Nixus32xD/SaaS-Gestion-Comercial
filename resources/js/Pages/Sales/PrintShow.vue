<script setup>
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    business_name: { type: String, required: true },
    sale: { type: Object, required: true },
    advanced_sale_settings_enabled: { type: Boolean, default: false },
    printed_at: { type: String, required: true },
});

const money = (value) => new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    minimumFractionDigits: 2,
}).format(Number(value) || 0);

const paymentMethodLabel = () => (
    props.sale.payment_method === 'transfer'
        ? 'Transferencia'
        : (props.sale.payment_method ? 'Efectivo' : 'Sin cobro inicial')
);

const paymentStatusLabel = () => {
    if (props.sale.payment_status === 'partial') return 'Pago parcial';
    if (props.sale.payment_status === 'pending') return 'Fiada';
    return 'Pagada';
};

const paymentStatusTone = () => {
    if (props.sale.payment_status === 'partial') return 'warning';
    if (props.sale.payment_status === 'pending') return 'danger';
    return 'success';
};

const printPage = () => window.print();
const closePage = () => window.close();
</script>

<template>
    <Head :title="sale.sale_number || `Venta #${sale.id}`" />

    <div class="min-h-screen bg-slate-100 px-4 py-6 text-slate-900 print:bg-white print:px-0 print:py-0">
        <div class="mx-auto max-w-5xl space-y-6">
            <header class="rounded-3xl bg-white p-6 shadow-sm print:hidden">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h1 class="text-3xl font-bold">{{ sale.sale_number || `Venta #${sale.id}` }}</h1>
                        <p class="mt-2 text-sm text-slate-500">{{ business_name }} · generado {{ printed_at }}</p>
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <button type="button" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700" @click="printPage">Imprimir</button>
                        <button type="button" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100" @click="closePage">Cerrar</button>
                    </div>
                </div>
            </header>

            <section class="rounded-3xl bg-white p-6 shadow-sm print:rounded-none print:shadow-none">
                <div class="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Comprobante</p>
                        <h2 class="mt-2 text-2xl font-bold">{{ sale.sale_number || `Venta #${sale.id}` }}</h2>
                        <p class="mt-2 text-sm text-slate-500">{{ business_name }} · {{ sale.sold_at || '-' }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <StatusBadge surface="light" :tone="paymentStatusTone()" :label="paymentStatusLabel()" />
                        <StatusBadge surface="light" tone="neutral" :label="paymentMethodLabel()" />
                        <StatusBadge surface="light" :tone="Number(sale.pending_amount) > 0 ? 'warning' : 'success'" :label="Number(sale.pending_amount) > 0 ? 'Con saldo pendiente' : 'Cobro cerrado'" />
                    </div>
                </div>

                <div class="mt-6 grid gap-4 lg:grid-cols-[minmax(0,1.15fr)_minmax(0,0.85fr)]">
                    <div class="rounded-2xl border border-slate-200 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Operacion</p>
                        <div class="mt-3 grid gap-2 text-sm text-slate-700">
                            <p>Cliente: <strong class="text-slate-900">{{ sale.customer || 'Consumidor final' }}</strong></p>
                            <p>Vendedor: <strong class="text-slate-900">{{ sale.user || '-' }}</strong></p>
                            <p v-if="advanced_sale_settings_enabled">Sector: <strong class="text-slate-900">{{ sale.sale_sector || '-' }}</strong></p>
                            <p v-if="advanced_sale_settings_enabled">Cuenta de cobro: <strong class="text-slate-900">{{ sale.payment_destination || '-' }}</strong></p>
                            <p>Comprobante adjunto: <strong class="text-slate-900">{{ sale.receipt_name || 'No' }}</strong></p>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Importes</p>
                        <div class="mt-3 grid gap-2 text-sm text-slate-700">
                            <p>Subtotal: <strong class="text-slate-900">{{ money(sale.subtotal) }}</strong></p>
                            <p>Descuento: <strong class="text-slate-900">{{ money(sale.discount) }}</strong></p>
                            <p>Total: <strong class="text-slate-900">{{ money(sale.total) }}</strong></p>
                            <p>Cobrado: <strong class="text-slate-900">{{ money(sale.paid_amount) }}</strong></p>
                            <p>Pendiente: <strong class="text-slate-900">{{ money(sale.pending_amount) }}</strong></p>
                            <p v-if="sale.payment_method === 'cash' && sale.amount_received > 0">Recibido: <strong class="text-slate-900">{{ money(sale.amount_received) }}</strong></p>
                            <p v-if="sale.payment_method === 'cash' && sale.amount_received > 0">Vuelto: <strong class="text-slate-900">{{ money(sale.change_amount) }}</strong></p>
                        </div>
                    </div>
                </div>

                <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-slate-500">Producto</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-500">Cantidad</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-500">Precio</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-500">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            <tr v-for="item in sale.items" :key="item.id">
                                <td class="px-4 py-3 align-top">
                                    <p class="font-semibold">{{ item.product_name }}</p>
                                    <p v-if="item.is_manual" class="mt-1 text-xs text-slate-500">Item manual sin stock</p>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <template v-if="item.is_manual">
                                        <span class="text-slate-500">Monto fijo</span>
                                    </template>
                                    <template v-else>
                                        {{ item.quantity }} <span class="text-xs text-slate-500">{{ item.quantity_label }}</span>
                                    </template>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    {{ money(item.unit_price) }}
                                    <span v-if="item.price_label" class="text-xs text-slate-500">{{ item.price_label }}</span>
                                </td>
                                <td class="px-4 py-3 align-top font-semibold">{{ money(item.subtotal) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 rounded-2xl border border-slate-200 p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Notas operativas</p>
                    <p class="mt-3 text-sm text-slate-700">{{ sale.notes || 'Sin notas operativas.' }}</p>
                </div>
            </section>
        </div>
    </div>
</template>
