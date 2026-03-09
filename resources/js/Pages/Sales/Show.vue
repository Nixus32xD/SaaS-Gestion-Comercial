<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    sale: { type: Object, required: true },
    auto_back: { type: Boolean, default: false },
});

const money = (value) => new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    minimumFractionDigits: 2,
}).format(Number(value) || 0);

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
            <div class="flex items-center justify-between gap-3">
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
                    <p>Subtotal: <strong>{{ money(sale.subtotal) }}</strong></p>
                    <p>Descuento: <strong>{{ money(sale.discount) }}</strong></p>
                    <p>Total: <strong>{{ money(sale.total) }}</strong></p>
                    <p>Notas: <strong>{{ sale.notes || '-' }}</strong></p>
                </div>
            </section>

            <section class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 backdrop-blur p-5 shadow-sm">
                <h3 class="text-base font-semibold text-slate-100">Items</h3>
                <div class="mt-4 overflow-x-auto rounded-xl border border-cyan-100/20 app-table-wrap">
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
                                <td class="px-3 py-2 font-semibold text-slate-100">{{ item.product_name }}</td>
                                <td class="px-3 py-2">{{ item.quantity }}</td>
                                <td class="px-3 py-2">{{ money(item.unit_price) }}</td>
                                <td class="px-3 py-2">{{ money(item.subtotal) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>

