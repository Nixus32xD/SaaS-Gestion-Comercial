<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    summary: { type: Object, required: true },
    low_stock_products: { type: Array, default: () => [] },
    top_sold_products: { type: Array, default: () => [] },
    latest_sales: { type: Array, default: () => [] },
    latest_purchases: { type: Array, default: () => [] },
    expiration_alerts: { type: Array, default: () => [] },
});

const money = (value) => new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    minimumFractionDigits: 2,
}).format(Number(value) || 0);
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold text-slate-900">Dashboard</h2>
                    <p class="mt-1 text-sm text-slate-500">Resumen general del comercio.</p>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('sales.create')" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Nueva venta</Link>
                    <Link :href="route('purchases.create')" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Nueva compra</Link>
                </div>
            </div>
        </template>

        <div class="grid gap-6">
            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs uppercase tracking-wider text-slate-500">Ventas de hoy</p>
                    <p class="mt-2 text-3xl font-bold text-slate-900">{{ money(summary.today_sales) }}</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs uppercase tracking-wider text-slate-500">Ventas del mes</p>
                    <p class="mt-2 text-3xl font-bold text-slate-900">{{ money(summary.month_sales) }}</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs uppercase tracking-wider text-slate-500">Productos</p>
                    <p class="mt-2 text-3xl font-bold text-slate-900">{{ summary.products_count }}</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs uppercase tracking-wider text-slate-500">Proveedores</p>
                    <p class="mt-2 text-3xl font-bold text-slate-900">{{ summary.suppliers_count }}</p>
                </article>
            </section>

            <section class="grid gap-4 lg:grid-cols-2">
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="text-base font-semibold text-slate-900">Productos con stock bajo</h3>
                    <ul v-if="low_stock_products.length" class="mt-3 space-y-2 text-sm">
                        <li v-for="product in low_stock_products" :key="product.id" class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-rose-700">
                            {{ product.name }} - actual {{ product.stock }} / minimo {{ product.min_stock }}
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-sm text-slate-500">No hay alertas de stock bajo.</p>
                </article>

                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="text-base font-semibold text-slate-900">Productos mas vendidos</h3>
                    <ul v-if="top_sold_products.length" class="mt-3 space-y-2 text-sm">
                        <li v-for="item in top_sold_products" :key="`${item.product_id}-${item.product_name}`" class="rounded-lg border border-slate-200 px-3 py-2">
                            <span class="font-medium text-slate-800">{{ item.product_name }}</span>
                            <span class="ml-2 text-slate-500">{{ item.sold_quantity }} unidades</span>
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-sm text-slate-500">Sin ventas registradas aun.</p>
                </article>
            </section>

            <section class="grid gap-4 lg:grid-cols-2">
                <article class="rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm lg:col-span-2">
                    <h3 class="text-base font-semibold text-amber-900">Alertas de vencimiento</h3>
                    <ul v-if="expiration_alerts.length" class="mt-3 space-y-2 text-sm">
                        <li v-for="item in expiration_alerts" :key="item.id" class="rounded-lg border border-amber-300 bg-white/70 px-3 py-2">
                            <span class="font-medium text-slate-800">{{ item.product_name }}</span>
                            <span class="ml-2 text-slate-600">vence {{ item.expires_at }}</span>
                            <span class="ml-2 font-semibold" :class="item.status === 'expired' ? 'text-rose-700' : 'text-amber-700'">
                                {{ item.status === 'expired' ? 'Vencido' : `Faltan ${item.days_remaining} dias` }}
                            </span>
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-sm text-slate-600">No hay productos proximos a vencer.</p>
                </article>

                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="text-base font-semibold text-slate-900">Ultimas ventas</h3>
                    <ul v-if="latest_sales.length" class="mt-3 space-y-2 text-sm">
                        <li v-for="sale in latest_sales" :key="sale.id" class="rounded-lg border border-slate-200 px-3 py-2">
                            <p class="font-medium text-slate-800">{{ sale.sale_number || `Venta #${sale.id}` }} - {{ money(sale.total) }}</p>
                            <p class="text-xs text-slate-500">{{ sale.sold_at }} - {{ sale.user || '-' }}</p>
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-sm text-slate-500">Sin ventas recientes.</p>
                </article>

                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="text-base font-semibold text-slate-900">Ultimas compras</h3>
                    <ul v-if="latest_purchases.length" class="mt-3 space-y-2 text-sm">
                        <li v-for="purchase in latest_purchases" :key="purchase.id" class="rounded-lg border border-slate-200 px-3 py-2">
                            <p class="font-medium text-slate-800">{{ purchase.purchase_number || `Compra #${purchase.id}` }} - {{ money(purchase.total) }}</p>
                            <p class="text-xs text-slate-500">{{ purchase.purchased_at }} - {{ purchase.supplier || 'Sin proveedor' }}</p>
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-sm text-slate-500">Sin compras recientes.</p>
                </article>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
