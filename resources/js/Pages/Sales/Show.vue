<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    sale: { type: Object, required: true },
});

const money = (value) => new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    minimumFractionDigits: 2,
}).format(Number(value) || 0);
</script>

<template>
    <Head :title="sale.sale_number || `Venta #${sale.id}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold text-slate-900">Detalle de venta</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ sale.sale_number || `Venta #${sale.id}` }} - {{ sale.sold_at }}</p>
                </div>
                <Link :href="route('sales.index')" class="text-sm font-semibold text-slate-600 hover:text-slate-900">Volver</Link>
            </div>
        </template>

        <div class="grid gap-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="grid gap-2 text-sm text-slate-700">
                    <p>Vendedor: <strong>{{ sale.user || '-' }}</strong></p>
                    <p>Subtotal: <strong>{{ money(sale.subtotal) }}</strong></p>
                    <p>Descuento: <strong>{{ money(sale.discount) }}</strong></p>
                    <p>Total: <strong>{{ money(sale.total) }}</strong></p>
                    <p>Notas: <strong>{{ sale.notes || '-' }}</strong></p>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-base font-semibold text-slate-900">Items</h3>
                <div class="mt-4 overflow-hidden rounded-xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Producto</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Cantidad</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Precio</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="item in sale.items" :key="item.id">
                                <td class="px-3 py-2 font-semibold text-slate-900">{{ item.product_name }}</td>
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
