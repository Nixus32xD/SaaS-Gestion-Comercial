<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    sale: { type: Object, required: true },
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

const paymentStatusLabel = computed(() => {
    if (props.sale.payment_status === 'partial') return 'Pago parcial';
    if (props.sale.payment_status === 'pending') return 'Fiada';
    return 'Pagada';
});

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
                    <p>Cliente: <strong>{{ sale.customer || '-' }}</strong></p>
                    <p v-if="advanced_sale_settings_enabled">Sector / punto de venta: <strong>{{ sale.sale_sector || '-' }}</strong></p>
                    <p v-if="advanced_sale_settings_enabled">Cuenta de cobro / destino: <strong>{{ sale.payment_destination || '-' }}</strong></p>
                    <p>Estado de pago: <strong>{{ paymentStatusLabel }}</strong></p>
                    <p>Medio de pago inicial: <strong>{{ sale.payment_method ? paymentMethodLabel : 'Sin cobro inicial' }}</strong></p>
                    <p>Subtotal: <strong>{{ money(sale.subtotal) }}</strong></p>
                    <p>Descuento: <strong>{{ money(sale.discount) }}</strong></p>
                    <p>Total: <strong>{{ money(sale.total) }}</strong></p>
                    <p>Pagado acumulado: <strong>{{ money(sale.paid_amount) }}</strong></p>
                    <p>Pendiente actual: <strong>{{ money(sale.pending_amount) }}</strong></p>
                    <p v-if="sale.payment_method === 'cash' && sale.amount_received > 0">Recibido al momento: <strong>{{ money(sale.amount_received) }}</strong></p>
                    <p v-if="sale.payment_method === 'cash' && sale.amount_received > 0">Vuelto inicial: <strong>{{ money(sale.change_amount) }}</strong></p>
                    <p>Notas: <strong>{{ sale.notes || '-' }}</strong></p>
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
