<script setup>
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    summary: { type: Object, required: true },
    daily_totals: { type: Array, default: () => [] },
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

const shortDate = (value) => new Intl.DateTimeFormat('es-AR', {
    day: '2-digit',
    month: '2-digit',
}).format(new Date(`${value}T00:00:00`));

const rawDailyTotals = computed(() => {
    if (Array.isArray(props.daily_totals)) {
        return props.daily_totals;
    }

    if (props.daily_totals && typeof props.daily_totals === 'object') {
        return Object.values(props.daily_totals);
    }

    return [];
});

const dailyTotals = computed(() => rawDailyTotals.value.map((row) => ({
    date: row.date,
    sales_total: Number(row.sales_total) || 0,
    purchases_total: Number(row.purchases_total) || 0,
})));

const maxTrendValue = computed(() => {
    const max = dailyTotals.value.reduce((carry, row) => Math.max(carry, row.sales_total, row.purchases_total), 0);
    return max > 0 ? max : 1;
});

const trendSalesTotal = computed(() => dailyTotals.value.reduce((carry, row) => carry + row.sales_total, 0));
const trendPurchasesTotal = computed(() => dailyTotals.value.reduce((carry, row) => carry + row.purchases_total, 0));

const topSoldMax = computed(() => {
    const max = props.top_sold_products.reduce((carry, item) => Math.max(carry, Number(item.sold_quantity) || 0), 0);
    return max > 0 ? max : 1;
});

const topSoldWidth = (value) => `${Math.max(8, Math.round(((Number(value) || 0) / topSoldMax.value) * 100))}%`;

const trendPoints = (key) => {
    if (!dailyTotals.value.length) return '';

    if (dailyTotals.value.length === 1) {
        const onlyValue = Number(dailyTotals.value[0][key]) || 0;
        const y = 100 - ((onlyValue / maxTrendValue.value) * 100);
        return `0,${y.toFixed(2)} 100,${y.toFixed(2)}`;
    }

    return dailyTotals.value.map((row, index) => {
        const x = (index / (dailyTotals.value.length - 1)) * 100;
        const y = 100 - ((Number(row[key]) / maxTrendValue.value) * 100);
        return `${x.toFixed(2)},${y.toFixed(2)}`;
    }).join(' ');
};

const trendLabelIndexes = computed(() => {
    const count = dailyTotals.value.length;

    if (count === 0) return [];
    if (count <= 6) return dailyTotals.value.map((_, index) => index);

    return [
        0,
        Math.floor((count - 1) * 0.2),
        Math.floor((count - 1) * 0.4),
        Math.floor((count - 1) * 0.6),
        Math.floor((count - 1) * 0.8),
        count - 1,
    ];
});
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

            <section class="grid gap-4 lg:grid-cols-3">
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2">
                    <h3 class="text-base font-semibold text-slate-900">Tendencia diaria (14 dias)</h3>
                    <div class="mt-2 flex items-center gap-4 text-xs text-slate-500">
                        <span class="inline-flex items-center gap-1">
                            <span class="h-2.5 w-2.5 rounded-full bg-indigo-500"></span>
                            Ventas
                        </span>
                        <span class="inline-flex items-center gap-1">
                            <span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                            Compras
                        </span>
                    </div>
                    <p class="mt-2 text-xs text-slate-500">
                        Totales 14 dias: ventas {{ money(trendSalesTotal) }} | compras {{ money(trendPurchasesTotal) }}
                    </p>

                    <div v-if="dailyTotals.length" class="mt-4">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <svg viewBox="0 0 100 100" preserveAspectRatio="none" class="h-52 w-full">
                                <line x1="0" y1="0" x2="100" y2="0" class="stroke-slate-200" stroke-width="0.6" />
                                <line x1="0" y1="25" x2="100" y2="25" class="stroke-slate-200" stroke-width="0.6" />
                                <line x1="0" y1="50" x2="100" y2="50" class="stroke-slate-200" stroke-width="0.6" />
                                <line x1="0" y1="75" x2="100" y2="75" class="stroke-slate-200" stroke-width="0.6" />
                                <line x1="0" y1="100" x2="100" y2="100" class="stroke-slate-200" stroke-width="0.6" />
                                <polyline :points="trendPoints('purchases_total')" fill="none" class="stroke-emerald-500" stroke-width="1.8" />
                                <polyline :points="trendPoints('sales_total')" fill="none" class="stroke-indigo-500" stroke-width="1.8" />
                            </svg>
                        </div>
                        <div class="mt-2 grid grid-cols-6 text-[11px] text-slate-500">
                            <span v-for="index in trendLabelIndexes" :key="`label-${index}`" class="text-center">
                                {{ shortDate(dailyTotals[index].date) }}
                            </span>
                        </div>
                    </div>

                    <p v-else class="mt-3 text-sm text-slate-500">Sin datos diarios para graficar.</p>
                </article>

                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="text-base font-semibold text-slate-900">Productos mas vendidos</h3>
                    <ul v-if="top_sold_products.length" class="mt-3 space-y-3 text-sm">
                        <li v-for="item in top_sold_products" :key="`${item.product_id}-${item.product_name}`">
                            <div class="mb-1 flex items-center justify-between gap-2">
                                <span class="truncate font-medium text-slate-800">{{ item.product_name }}</span>
                                <span class="text-xs text-slate-500">{{ item.sold_quantity }} u</span>
                            </div>
                            <div class="h-2.5 rounded-full bg-slate-100">
                                <div class="h-2.5 rounded-full bg-indigo-500" :style="{ width: topSoldWidth(item.sold_quantity) }"></div>
                            </div>
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-sm text-slate-500">Sin ventas registradas aun.</p>
                </article>
            </section>

            <section class="grid gap-4 lg:grid-cols-2">
                <article class="rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm lg:col-span-2">
                    <h3 class="text-base font-semibold text-amber-900">Alertas de vencimiento</h3>
                    <ul v-if="expiration_alerts.length" class="mt-3 space-y-2 text-sm">
                        <li v-for="item in expiration_alerts" :key="item.purchase_item_id" class="rounded-lg border border-amber-300 bg-white/70 px-3 py-2">
                            <span class="font-medium text-slate-800">{{ item.product_name }}</span>
                            <span v-if="item.purchase_number" class="ml-2 text-slate-500">lote {{ item.purchase_number }}</span>
                            <span class="ml-2 text-slate-600">vence {{ item.expires_at }}</span>
                            <span class="ml-2 font-semibold" :class="item.status === 'expired' ? 'text-rose-700' : 'text-amber-700'">
                                {{ item.status === 'expired' ? 'Vencido' : `Faltan ${item.days_remaining} dias` }}
                            </span>
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-sm text-slate-600">No hay productos proximos a vencer.</p>
                </article>

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
                    <h3 class="text-base font-semibold text-slate-900">Ultimas ventas</h3>
                    <ul v-if="latest_sales.length" class="mt-3 space-y-2 text-sm">
                        <li v-for="sale in latest_sales" :key="sale.id" class="rounded-lg border border-slate-200 px-3 py-2">
                            <p class="font-medium text-slate-800">{{ sale.sale_number || `Venta #${sale.id}` }} - {{ money(sale.total) }}</p>
                            <p class="text-xs text-slate-500">{{ sale.sold_at }} - {{ sale.user || '-' }}</p>
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-sm text-slate-500">Sin ventas recientes.</p>
                </article>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-base font-semibold text-slate-900">Ultimas compras</h3>
                <ul v-if="latest_purchases.length" class="mt-3 space-y-2 text-sm">
                    <li v-for="purchase in latest_purchases" :key="purchase.id" class="rounded-lg border border-slate-200 px-3 py-2">
                        <p class="font-medium text-slate-800">{{ purchase.purchase_number || `Compra #${purchase.id}` }} - {{ money(purchase.total) }}</p>
                        <p class="text-xs text-slate-500">{{ purchase.purchased_at }} - {{ purchase.supplier || 'Sin proveedor' }}</p>
                    </li>
                </ul>
                <p v-else class="mt-3 text-sm text-slate-500">Sin compras recientes.</p>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
