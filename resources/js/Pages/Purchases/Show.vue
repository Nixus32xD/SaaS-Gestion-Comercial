<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    purchase: { type: Object, required: true },
});

const money = (value) => new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    minimumFractionDigits: 2,
}).format(Number(value) || 0);
</script>

<template>
    <Head :title="purchase.purchase_number || `Compra #${purchase.id}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold text-slate-900">Detalle de compra</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ purchase.purchase_number || `Compra #${purchase.id}` }} - {{ purchase.purchased_at }}</p>
                </div>
                <Link :href="route('purchases.index')" class="text-sm font-semibold text-slate-600 hover:text-slate-900">Volver</Link>
            </div>
        </template>

        <div class="grid gap-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="grid gap-2 text-sm text-slate-700">
                    <p>Usuario: <strong>{{ purchase.user || '-' }}</strong></p>
                    <p>Proveedor: <strong>{{ purchase.supplier || '-' }}</strong></p>
                    <p>Subtotal: <strong>{{ money(purchase.subtotal) }}</strong></p>
                    <p>Total: <strong>{{ money(purchase.total) }}</strong></p>
                    <p>Notas: <strong>{{ purchase.notes || '-' }}</strong></p>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-base font-semibold text-slate-900">Items</h3>
                <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200 app-table-wrap">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Producto</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Cantidad</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Costo</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="item in purchase.items" :key="item.id">
                                <td class="px-3 py-2 font-semibold text-slate-900">{{ item.product_name }}</td>
                                <td class="px-3 py-2">{{ item.quantity }}</td>
                                <td class="px-3 py-2">{{ money(item.unit_cost) }}</td>
                                <td class="px-3 py-2">{{ money(item.subtotal) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
