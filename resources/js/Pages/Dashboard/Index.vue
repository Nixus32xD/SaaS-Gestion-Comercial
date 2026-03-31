<script setup>
import AppPanel from '@/Components/AppPanel.vue';
import MetricCard from '@/Components/MetricCard.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
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
    advanced_sales: { type: Object, default: () => ({ enabled: false, sales_by_sector: [], sales_by_payment_destination: [] }) },
});

const moneyFormatter = new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    minimumFractionDigits: 2,
});

const money = (value) => moneyFormatter.format(Number(value) || 0);

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

const advancedSalesEnabled = computed(() => Boolean(props.advanced_sales?.enabled));

const expirationAlerts = computed(() => {
    const items = Array.isArray(props.expiration_alerts) ? props.expiration_alerts : [];

    return items.map((item) => {
        const daysRemaining = Number(item.days_remaining);
        const normalizedDays = Number.isFinite(daysRemaining) ? daysRemaining : null;

        let urgency = 'upcoming_later';
        let urgencyLabel = 'Proximamente';
        let cardClass = 'border-slate-700/70 bg-slate-950/40';
        let badgeClass = 'bg-slate-700/80 text-slate-100';

        if (item.status === 'expired') {
            urgency = 'expired';
            urgencyLabel = 'Vencido';
            cardClass = 'border-rose-300/35 bg-rose-400/12';
            badgeClass = 'bg-rose-500/20 text-rose-100';
        } else if (normalizedDays !== null && normalizedDays <= 7) {
            urgency = 'upcoming_urgent';
            urgencyLabel = 'Urgente';
            cardClass = 'border-amber-300/35 bg-amber-400/12';
            badgeClass = 'bg-amber-400/20 text-amber-100';
        } else if (normalizedDays !== null && normalizedDays <= 15) {
            urgency = 'upcoming_soon';
            urgencyLabel = 'Pronto';
            cardClass = 'border-yellow-300/30 bg-yellow-400/10';
            badgeClass = 'bg-yellow-400/20 text-yellow-100';
        } else if (item.status === 'upcoming') {
            urgency = 'upcoming_later';
            urgencyLabel = 'A controlar';
            cardClass = 'border-cyan-300/25 bg-cyan-400/10';
            badgeClass = 'bg-cyan-400/20 text-cyan-100';
        }

        return {
            ...item,
            urgency,
            urgencyLabel,
            cardClass,
            badgeClass,
        };
    });
});

const expirationGroups = computed(() => {
    const groups = [
        {
            key: 'expired',
            title: 'Vencidos',
            empty: 'Sin lotes vencidos.',
            items: expirationAlerts.value.filter((item) => item.urgency === 'expired'),
        },
        {
            key: 'upcoming_urgent',
            title: 'Vencen en 7 dias',
            empty: 'Sin lotes criticos.',
            items: expirationAlerts.value.filter((item) => item.urgency === 'upcoming_urgent'),
        },
        {
            key: 'upcoming_other',
            title: 'Proximos despues',
            empty: 'Sin otros lotes proximos a vencer.',
            items: expirationAlerts.value.filter((item) => ['upcoming_soon', 'upcoming_later'].includes(item.urgency)),
        },
    ];

    return groups.filter((group) => group.items.length > 0);
});

const lowStockSummary = computed(() => ({
    total: props.low_stock_products.length,
    out_of_stock: props.low_stock_products.filter((product) => Number(product.stock) <= 0).length,
    low_only: props.low_stock_products.filter((product) => Number(product.stock) > 0).length,
}));

const expirationSummary = computed(() => ({
    total: expirationAlerts.value.length,
    expired: expirationAlerts.value.filter((item) => item.urgency === 'expired').length,
    urgent: expirationAlerts.value.filter((item) => item.urgency === 'upcoming_urgent').length,
}));

const netFlow = computed(() => trendSalesTotal.value - trendPurchasesTotal.value);
const operationPulse = computed(() => {
    if (expirationSummary.value.expired > 0 || lowStockSummary.value.out_of_stock > 0) {
        return {
            tone: 'danger',
            label: 'Atencion hoy',
            message: 'Hay alertas criticas que conviene revisar antes de seguir vendiendo.',
        };
    }

    if (expirationSummary.value.urgent > 0 || lowStockSummary.value.low_only > 0) {
        return {
            tone: 'warning',
            label: 'Seguimiento necesario',
            message: 'Hay productos que requieren control operativo durante la jornada.',
        };
    }

    return {
        tone: 'success',
        label: 'Operacion estable',
        message: 'No hay alertas urgentes visibles en este momento.',
    };
});

const priorityCards = computed(() => ([
    {
        key: 'stock',
        title: lowStockSummary.value.out_of_stock > 0
            ? `${lowStockSummary.value.out_of_stock} productos agotados`
            : `${lowStockSummary.value.total} productos con alerta`,
        description: lowStockSummary.value.total > 0
            ? 'Revisa faltantes, bajo stock y productos que ya no estan listos para vender.'
            : 'No hay alertas de stock para resolver ahora.',
        tone: lowStockSummary.value.out_of_stock > 0 ? 'danger' : (lowStockSummary.value.total > 0 ? 'warning' : 'success'),
        href: route('products.index', lowStockSummary.value.out_of_stock > 0 ? { no_stock: 1 } : { low_stock: 1 }),
        action: lowStockSummary.value.total > 0 ? 'Ver productos' : 'Abrir catalogo',
    },
    {
        key: 'expiration',
        title: expirationSummary.value.expired > 0
            ? `${expirationSummary.value.expired} lotes vencidos`
            : `${expirationSummary.value.total} lotes a controlar`,
        description: expirationSummary.value.total > 0
            ? 'Mira primero los lotes vencidos o proximos para evitar merma y errores de venta.'
            : 'No hay vencimientos urgentes visibles.',
        tone: expirationSummary.value.expired > 0 ? 'danger' : (expirationSummary.value.total > 0 ? 'warning' : 'success'),
        href: route('products.index'),
        action: 'Revisar lotes',
    },
    {
        key: 'flow',
        title: netFlow.value >= 0 ? 'Caja comercial positiva' : 'Compras por encima de ventas',
        description: `En los ultimos 14 dias la diferencia fue ${money(Math.abs(netFlow.value))}.`,
        tone: netFlow.value >= 0 ? 'success' : 'warning',
        href: route('sales.index'),
        action: 'Ver ventas',
    },
]));
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold text-slate-100">Dashboard</h2>
                    <p class="mt-1 text-sm text-slate-300">Resumen general del comercio.</p>
                </div>
                <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                    <Link :href="route('sales.create')" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Nueva venta</Link>
                    <Link :href="route('purchases.create')" class="inline-flex items-center justify-center rounded-lg border border-cyan-200/35 bg-slate-900/45 px-4 py-2 text-sm font-semibold text-cyan-100 hover:bg-slate-800/60">Nueva compra</Link>
                </div>
            </div>
        </template>

        <div class="grid gap-6">
            <section class="app-kpi-grid">
                <MetricCard label="Ventas de hoy" :value="money(summary.today_sales)" hint="Importe acumulado del dia." tone="accent" />
                <MetricCard label="Ventas del mes" :value="money(summary.month_sales)" hint="Referencia comercial para la gestion del mes." />
                <MetricCard
                    label="Alertas de stock"
                    :value="lowStockSummary.total"
                    :hint="lowStockSummary.total > 0 ? `${lowStockSummary.out_of_stock} agotados y ${lowStockSummary.low_only} en minimo.` : 'Sin alertas visibles.'"
                    :tone="lowStockSummary.out_of_stock > 0 ? 'danger' : (lowStockSummary.total > 0 ? 'warning' : 'success')"
                />
                <MetricCard
                    label="Lotes a revisar"
                    :value="expirationSummary.total"
                    :hint="expirationSummary.total > 0 ? `${expirationSummary.expired} vencidos y ${expirationSummary.urgent} urgentes.` : 'Sin vencimientos urgentes.'"
                    :tone="expirationSummary.expired > 0 ? 'danger' : (expirationSummary.total > 0 ? 'warning' : 'success')"
                />
            </section>

            <AppPanel :title="operationPulse.label" :subtitle="operationPulse.message" :tone="operationPulse.tone">
                <template #actions>
                    <StatusBadge :tone="operationPulse.tone" :label="`${summary.products_count} productos`" />
                    <StatusBadge tone="info" :label="`${summary.suppliers_count} proveedores`" />
                </template>

                <div class="grid gap-3 xl:grid-cols-3">
                    <article v-for="card in priorityCards" :key="card.key" class="app-subsection">
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="text-sm font-semibold text-slate-100">{{ card.title }}</h3>
                            <StatusBadge :tone="card.tone" size="sm" :label="card.tone === 'success' ? 'OK' : 'Revisar'" />
                        </div>
                        <p class="mt-2 text-sm text-slate-300/80">{{ card.description }}</p>
                        <Link :href="card.href" class="mt-4 inline-flex items-center rounded-lg border border-cyan-100/20 px-3 py-2 text-xs font-semibold text-slate-200 hover:bg-slate-800/60">
                            {{ card.action }}
                        </Link>
                    </article>
                </div>
            </AppPanel>

            <section class="grid gap-4 lg:grid-cols-3">
                <article class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-[0_20px_45px_rgba(8,47,73,0.36)] backdrop-blur lg:col-span-2">
                    <h3 class="text-base font-semibold text-slate-100">Tendencia diaria (14 dias)</h3>
                    <div class="mt-2 flex items-center gap-4 text-xs text-slate-300">
                        <span class="inline-flex items-center gap-1">
                            <span class="h-2.5 w-2.5 rounded-full bg-indigo-500"></span>
                            Ventas
                        </span>
                        <span class="inline-flex items-center gap-1">
                            <span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                            Compras
                        </span>
                    </div>
                    <p class="mt-2 text-xs text-slate-300">
                        Totales 14 dias: ventas {{ money(trendSalesTotal) }} | compras {{ money(trendPurchasesTotal) }}
                    </p>

                    <div v-if="dailyTotals.length" class="mt-4">
                        <div class="rounded-xl border border-cyan-100/20 bg-slate-950/45 p-3">
                            <svg viewBox="0 0 100 100" preserveAspectRatio="none" class="h-52 w-full">
                                <line x1="0" y1="0" x2="100" y2="0" class="stroke-slate-700" stroke-width="0.6" />
                                <line x1="0" y1="25" x2="100" y2="25" class="stroke-slate-700" stroke-width="0.6" />
                                <line x1="0" y1="50" x2="100" y2="50" class="stroke-slate-700" stroke-width="0.6" />
                                <line x1="0" y1="75" x2="100" y2="75" class="stroke-slate-700" stroke-width="0.6" />
                                <line x1="0" y1="100" x2="100" y2="100" class="stroke-slate-700" stroke-width="0.6" />
                                <polyline :points="trendPoints('purchases_total')" fill="none" class="stroke-emerald-500" stroke-width="1.8" />
                                <polyline :points="trendPoints('sales_total')" fill="none" class="stroke-indigo-500" stroke-width="1.8" />
                            </svg>
                        </div>
                        <div class="mt-2 grid grid-cols-6 text-[11px] text-slate-300">
                            <span v-for="index in trendLabelIndexes" :key="`label-${index}`" class="text-center">
                                {{ shortDate(dailyTotals[index].date) }}
                            </span>
                        </div>
                    </div>

                    <p v-else class="mt-3 text-sm text-slate-300">Sin datos diarios para graficar.</p>
                </article>

                <article class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-[0_20px_45px_rgba(8,47,73,0.36)] backdrop-blur">
                    <h3 class="text-base font-semibold text-slate-100">Productos mas vendidos</h3>
                    <ul v-if="top_sold_products.length" class="mt-3 space-y-3 text-sm">
                        <li v-for="item in top_sold_products" :key="`${item.product_id}-${item.product_name}`">
                            <div class="mb-1 flex items-center justify-between gap-2">
                                <span class="truncate font-medium text-slate-100">{{ item.product_name }}</span>
                                <span class="text-xs text-slate-300">{{ item.sold_quantity }} {{ item.sold_quantity_label }}</span>
                            </div>
                            <div class="h-2.5 rounded-full bg-slate-800">
                                <div class="h-2.5 rounded-full bg-indigo-500" :style="{ width: topSoldWidth(item.sold_quantity) }"></div>
                            </div>
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-sm text-slate-300">Sin ventas registradas aun.</p>
                </article>
            </section>

            <section v-if="advancedSalesEnabled" class="grid gap-4 lg:grid-cols-2">
                <article class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-[0_20px_45px_rgba(8,47,73,0.36)] backdrop-blur">
                    <h3 class="text-base font-semibold text-slate-100">Ventas del mes por sector</h3>
                    <ul v-if="advanced_sales.sales_by_sector?.length" class="mt-3 space-y-2 text-sm">
                        <li v-for="sector in advanced_sales.sales_by_sector" :key="sector.id" class="rounded-lg border border-cyan-100/20 bg-slate-950/40 px-3 py-2">
                            <div class="flex items-center justify-between gap-3">
                                <p class="font-medium text-slate-100">{{ sector.name }}</p>
                                <p class="text-slate-200">{{ money(sector.total) }}</p>
                            </div>
                            <p class="mt-1 text-xs text-slate-300">{{ sector.sales_count }} ventas</p>
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-sm text-slate-300">Sin ventas sectorizadas este mes.</p>
                </article>

                <article class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-[0_20px_45px_rgba(8,47,73,0.36)] backdrop-blur">
                    <h3 class="text-base font-semibold text-slate-100">Cobros del mes por cuenta</h3>
                    <ul v-if="advanced_sales.sales_by_payment_destination?.length" class="mt-3 space-y-2 text-sm">
                        <li v-for="destination in advanced_sales.sales_by_payment_destination" :key="destination.id" class="rounded-lg border border-cyan-100/20 bg-slate-950/40 px-3 py-2">
                            <div class="flex items-center justify-between gap-3">
                                <p class="font-medium text-slate-100">{{ destination.name }}</p>
                                <p class="text-slate-200">{{ money(destination.total) }}</p>
                            </div>
                            <p class="mt-1 text-xs text-slate-300">{{ destination.sales_count }} ventas</p>
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-sm text-slate-300">Sin cobros asociados a cuentas este mes.</p>
                </article>
            </section>

            <section class="grid gap-4 lg:grid-cols-2">
                <article class="rounded-2xl border border-amber-200/40 bg-amber-300/12 p-5 shadow-[0_20px_45px_rgba(8,47,73,0.36)] backdrop-blur lg:col-span-2">
                    <h3 class="text-base font-semibold text-amber-100">Alertas de vencimiento</h3>
                    <div v-if="expirationGroups.length" class="mt-4 grid gap-4 xl:grid-cols-3">
                        <section v-for="group in expirationGroups" :key="group.key" class="rounded-xl border border-amber-200/20 bg-slate-950/30 p-3">
                            <div class="flex items-center justify-between gap-2">
                                <h4 class="text-sm font-semibold text-slate-100">{{ group.title }}</h4>
                                <span class="rounded-full border border-slate-600/70 px-2 py-0.5 text-[11px] font-semibold text-slate-200">
                                    {{ group.items.length }}
                                </span>
                            </div>

                            <ul class="mt-3 space-y-2 text-sm">
                                <li
                                    v-for="item in group.items"
                                    :key="item.batch_id"
                                    class="rounded-lg border px-3 py-2"
                                    :class="item.cardClass"
                                >
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <p class="font-medium text-slate-100">{{ item.product_name }}</p>
                                            <p class="mt-1 text-xs text-slate-300">
                                                <span v-if="item.batch_code">Lote {{ item.batch_code }}</span>
                                                <span v-if="item.quantity" class="ml-2">Stock {{ item.quantity }}</span>
                                                <span class="ml-2">Vence {{ item.expires_at }}</span>
                                            </p>
                                        </div>
                                        <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold" :class="item.badgeClass">
                                            {{ item.urgencyLabel }}
                                        </span>
                                    </div>
                                    <p class="mt-2 text-xs font-medium" :class="item.status === 'expired' ? 'text-rose-200' : 'text-amber-100'">
                                        {{ item.status === 'expired' ? 'Este lote ya esta vencido.' : `Faltan ${item.days_remaining} dias para vencer.` }}
                                    </p>
                                </li>
                            </ul>
                        </section>
                    </div>
                    <p v-else class="mt-3 text-sm text-slate-300">No hay productos proximos a vencer.</p>
                </article>

                <article class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-[0_20px_45px_rgba(8,47,73,0.36)] backdrop-blur">
                    <h3 class="text-base font-semibold text-slate-100">Productos con stock bajo</h3>
                    <ul v-if="low_stock_products.length" class="mt-3 space-y-2 text-sm">
                        <li v-for="product in low_stock_products" :key="product.id" class="rounded-lg border border-rose-300/40 bg-rose-400/12 px-3 py-2 text-rose-100">
                            {{ product.name }} - actual {{ product.stock }} / minimo {{ product.min_stock }}
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-sm text-slate-300">No hay alertas de stock bajo.</p>
                </article>

                <article class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-[0_20px_45px_rgba(8,47,73,0.36)] backdrop-blur">
                    <h3 class="text-base font-semibold text-slate-100">Ultimas ventas</h3>
                    <ul v-if="latest_sales.length" class="mt-3 space-y-2 text-sm">
                        <li v-for="sale in latest_sales" :key="sale.id" class="rounded-lg border border-cyan-100/20 bg-slate-950/40 px-3 py-2">
                            <p class="font-medium text-slate-100">{{ sale.sale_number || `Venta #${sale.id}` }} - {{ money(sale.total) }}</p>
                            <p class="text-xs text-slate-300">{{ sale.sold_at }} - {{ sale.user || '-' }}</p>
                            <p v-if="advancedSalesEnabled" class="mt-1 text-xs text-slate-400">
                                {{ sale.sale_sector || 'Sin sector' }} | {{ sale.payment_destination || 'Sin cuenta' }}
                            </p>
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-sm text-slate-300">Sin ventas recientes.</p>
                </article>
            </section>

            <section class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-[0_20px_45px_rgba(8,47,73,0.36)] backdrop-blur">
                <h3 class="text-base font-semibold text-slate-100">Ultimas compras</h3>
                <ul v-if="latest_purchases.length" class="mt-3 space-y-2 text-sm">
                    <li v-for="purchase in latest_purchases" :key="purchase.id" class="rounded-lg border border-cyan-100/20 bg-slate-950/40 px-3 py-2">
                        <p class="font-medium text-slate-100">{{ purchase.purchase_number || `Compra #${purchase.id}` }} - {{ money(purchase.total) }}</p>
                        <p class="text-xs text-slate-300">{{ purchase.purchased_at }} - {{ purchase.supplier || 'Sin proveedor' }}</p>
                    </li>
                </ul>
                <p v-else class="mt-3 text-sm text-slate-300">Sin compras recientes.</p>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
