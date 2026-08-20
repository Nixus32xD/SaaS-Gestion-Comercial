<script setup>
import AppPanel from '@/Components/AppPanel.vue';
import MetricCard from '@/Components/MetricCard.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
    summary: { type: Object, required: true },
    historical_summary: { type: Object, default: () => ({ periods: [] }) },
    performance_series: { type: Object, default: () => ({ periods: [] }) },
    daily_totals: { type: Array, default: () => [] },
    low_stock_products: { type: Array, default: () => [] },
    top_sold_products: { type: Array, default: () => [] },
    latest_sales: { type: Array, default: () => [] },
    latest_purchases: { type: Array, default: () => [] },
    expiration_alerts: { type: Array, default: () => [] },
    advanced_sales: { type: Object, default: () => ({ enabled: false, sales_by_sector: [], sales_by_payment_destination: [] }) },
});

const page = usePage();
const selectedPeriodKey = ref('last_14_days');

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

const topSoldMax = computed(() => {
    const max = props.top_sold_products.reduce((carry, item) => Math.max(carry, Number(item.sold_quantity) || 0), 0);
    return max > 0 ? max : 1;
});

const topSoldWidth = (value) => `${Math.max(8, Math.round(((Number(value) || 0) / topSoldMax.value) * 100))}%`;

const periodTabLabels = {
    last_14_days: '14 dias',
    current_month: 'Mensual',
    current_year: 'Anual',
    all_time: 'Total',
};

const greeting = computed(() => {
    const hour = new Date().getHours();

    if (hour < 13) return 'Buenos dias';
    if (hour < 20) return 'Buenas tardes';

    return 'Buenas noches';
});

const userFirstName = computed(() => {
    const name = String(page.props.auth?.user?.name || '').trim();

    return name ? name.split(/\s+/)[0] : 'equipo';
});

const businessName = computed(() => page.props.business?.name || 'Comercio');

const advancedSalesEnabled = computed(() => Boolean(props.advanced_sales?.enabled));

const historicalPeriods = computed(() => {
    const periods = Array.isArray(props.historical_summary?.periods)
        ? props.historical_summary.periods
        : [];

    return periods.map((period) => ({
        ...period,
        sales_total: Number(period.sales_total) || 0,
        purchases_total: Number(period.purchases_total) || 0,
        net_total: Number(period.net_total) || 0,
        sales_count: Number(period.sales_count) || 0,
        purchases_count: Number(period.purchases_count) || 0,
        average_ticket: Number(period.average_ticket) || 0,
    }));
});

const selectablePeriods = computed(() => historicalPeriods.value.map((period) => ({
    ...period,
    tab_label: periodTabLabels[period.key] || period.label,
})));

const selectedPeriod = computed(() => (
    historicalPeriods.value.find((period) => period.key === selectedPeriodKey.value)
    || historicalPeriods.value[0]
    || {
        key: 'last_14_days',
        label: 'Ultimos 14 dias',
        range_label: '',
        sales_total: 0,
        purchases_total: 0,
        net_total: 0,
        sales_count: 0,
        purchases_count: 0,
        average_ticket: 0,
    }
));

const performancePeriods = computed(() => {
    const periods = Array.isArray(props.performance_series?.periods)
        ? props.performance_series.periods
        : [];

    return periods.map((period) => ({
        ...period,
        points: Array.isArray(period.points) ? period.points.map((point) => ({
            ...point,
            sales_total: Number(point.sales_total) || 0,
            purchases_total: Number(point.purchases_total) || 0,
            net_total: Number(point.net_total) || 0,
        })) : [],
    }));
});

const fallbackSeries = computed(() => ({
    key: 'last_14_days',
    label: '14 dias',
    granularity: 'day',
    range_label: selectedPeriod.value.range_label,
    points: dailyTotals.value.map((row) => ({
        bucket: row.date,
        label: shortDate(row.date),
        sales_total: row.sales_total,
        purchases_total: row.purchases_total,
        net_total: row.sales_total - row.purchases_total,
    })),
}));

const selectedSeries = computed(() => (
    performancePeriods.value.find((period) => period.key === selectedPeriodKey.value)
    || fallbackSeries.value
));

const chartPoints = computed(() => selectedSeries.value.points || []);

const maxChartValue = computed(() => {
    const max = chartPoints.value.reduce((carry, point) => Math.max(carry, point.sales_total, point.purchases_total), 0);

    return max > 0 ? max : 1;
});

const chartLabelIndexes = computed(() => {
    const count = chartPoints.value.length;

    if (count === 0) return [];
    if (count <= 7) return chartPoints.value.map((_, index) => index);

    return [
        0,
        Math.floor((count - 1) * 0.2),
        Math.floor((count - 1) * 0.4),
        Math.floor((count - 1) * 0.6),
        Math.floor((count - 1) * 0.8),
        count - 1,
    ];
});

const chartBarHeight = (value) => {
    const amount = Number(value) || 0;

    if (amount <= 0) return '0%';

    return `${Math.max(4, Math.round((amount / maxChartValue.value) * 100))}%`;
};

const chartBars = computed(() => chartPoints.value.map((point, index) => ({
    ...point,
    sales_height: chartBarHeight(point.sales_total),
    purchases_height: chartBarHeight(point.purchases_total),
    show_label: chartLabelIndexes.value.includes(index),
})));

const selectedOperationsCount = computed(() => selectedPeriod.value.sales_count + selectedPeriod.value.purchases_count);

const selectedNetTone = computed(() => {
    if (selectedPeriod.value.sales_count === 0 && selectedPeriod.value.purchases_count === 0) return 'default';

    return selectedPeriod.value.net_total >= 0 ? 'success' : 'warning';
});

const selectedSeriesMode = computed(() => (
    selectedSeries.value.granularity === 'month' ? 'Agrupado por mes' : 'Agrupado por dia'
));

const donutStyle = computed(() => {
    const sales = Math.max(0, selectedPeriod.value.sales_total);
    const purchases = Math.max(0, selectedPeriod.value.purchases_total);
    const total = sales + purchases;

    if (total <= 0) {
        return {
            background: 'conic-gradient(rgba(51, 65, 85, 0.75) 0deg 360deg)',
        };
    }

    const salesDegrees = (sales / total) * 360;

    return {
        background: `conic-gradient(rgb(99, 102, 241) 0deg ${salesDegrees.toFixed(2)}deg, rgb(245, 158, 11) ${salesDegrees.toFixed(2)}deg 360deg)`,
    };
});

const salesShare = computed(() => {
    const sales = Math.max(0, selectedPeriod.value.sales_total);
    const purchases = Math.max(0, selectedPeriod.value.purchases_total);
    const total = sales + purchases;

    return total > 0 ? Math.round((sales / total) * 100) : 0;
});

const purchasesShare = computed(() => {
    const sales = Math.max(0, selectedPeriod.value.sales_total);
    const purchases = Math.max(0, selectedPeriod.value.purchases_total);
    const total = sales + purchases;

    return total > 0 ? Math.round((purchases / total) * 100) : 0;
});

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

const netFlow = computed(() => selectedPeriod.value.net_total);
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
        description: `En ${selectedPeriod.value.label.toLowerCase()} la diferencia fue ${money(Math.abs(netFlow.value))}.`,
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
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-cyan-100/70">Resumen general</p>
                    <h2 class="mt-2 break-words text-3xl font-bold text-slate-100">{{ greeting }}, {{ userFirstName }}</h2>
                    <p class="mt-1 break-words text-sm text-slate-300">{{ businessName }} - Periodo {{ selectedPeriod.range_label || 'sin movimientos' }}</p>
                </div>
                <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                    <Link :href="route('sales.create')" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Nueva venta</Link>
                    <Link :href="route('purchases.create')" class="inline-flex items-center justify-center rounded-lg border border-cyan-200/35 bg-slate-900/45 px-4 py-2 text-sm font-semibold text-cyan-100 hover:bg-slate-800/60">Nueva compra</Link>
                </div>
            </div>
        </template>

        <div class="min-w-0 grid gap-6">
            <AppPanel padding="sm">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-[0.18em] text-cyan-100/70">Periodo</p>
                        <p class="mt-1 text-sm font-semibold text-slate-100">{{ selectedPeriod.range_label || selectedSeries.range_label }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4 xl:min-w-[34rem]">
                        <button
                            v-for="period in selectablePeriods"
                            :key="period.key"
                            type="button"
                            class="rounded-lg border px-3 py-2 text-xs font-semibold uppercase tracking-[0.14em] transition"
                            :class="selectedPeriodKey === period.key
                                ? 'border-cyan-200/60 bg-cyan-300/18 text-white shadow-[0_0_24px_rgba(34,211,238,0.16)]'
                                : 'border-cyan-100/15 bg-slate-950/35 text-slate-300 hover:border-cyan-200/35 hover:bg-slate-900/70'"
                            @click="selectedPeriodKey = period.key"
                        >
                            {{ period.tab_label }}
                        </button>
                    </div>
                </div>
            </AppPanel>

            <section class="app-kpi-grid">
                <MetricCard
                    label="Ingresos del periodo"
                    :value="money(selectedPeriod.sales_total)"
                    :hint="`${selectedPeriod.sales_count} ventas registradas.`"
                    tone="accent"
                />
                <MetricCard
                    label="Compras del periodo"
                    :value="money(selectedPeriod.purchases_total)"
                    :hint="`${selectedPeriod.purchases_count} compras registradas.`"
                    tone="warning"
                />
                <MetricCard
                    label="Balance neto"
                    :value="money(selectedPeriod.net_total)"
                    :hint="selectedPeriod.net_total >= 0 ? 'Ingresos por encima de compras.' : 'Compras por encima de ingresos.'"
                    :tone="selectedNetTone"
                />
                <MetricCard
                    label="Operaciones"
                    :value="selectedOperationsCount"
                    :hint="`Ticket promedio ${money(selectedPeriod.average_ticket)}.`"
                />
            </section>

            <section class="grid gap-4 lg:grid-cols-3 xl:grid-cols-4">
                <article class="min-w-0 rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-[0_20px_45px_rgba(8,47,73,0.36)] backdrop-blur lg:col-span-2">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <h3 class="text-base font-semibold text-slate-100">Evolucion de ventas y compras</h3>
                            <p class="mt-1 text-sm text-slate-300/80">{{ selectedSeriesMode }} - {{ selectedSeries.range_label }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-4 text-xs text-slate-300">
                            <span class="inline-flex items-center gap-1">
                                <span class="h-2.5 w-2.5 rounded-full bg-indigo-500"></span>
                                Ventas
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span>
                                Compras
                            </span>
                        </div>
                    </div>

                    <div v-if="chartBars.length" class="mt-5 rounded-xl border border-cyan-100/20 bg-slate-950/45 p-4">
                        <div class="relative h-64 overflow-hidden">
                            <div class="absolute inset-x-0 bottom-8 top-0 grid grid-rows-4">
                                <span class="border-t border-slate-700/70"></span>
                                <span class="border-t border-slate-700/55"></span>
                                <span class="border-t border-slate-700/45"></span>
                                <span class="border-t border-slate-700/35"></span>
                            </div>

                            <div class="relative z-10 flex h-full items-end gap-2 overflow-x-auto pb-8">
                                <div
                                    v-for="point in chartBars"
                                    :key="point.bucket"
                                    class="flex h-full min-w-9 flex-1 flex-col justify-end gap-2"
                                >
                                    <div class="flex h-[calc(100%-1.75rem)] items-end justify-center gap-1">
                                        <div class="w-3 rounded-t bg-indigo-500 shadow-[0_0_14px_rgba(99,102,241,0.28)]" :style="{ height: point.sales_height }"></div>
                                        <div class="w-3 rounded-t bg-amber-500 shadow-[0_0_14px_rgba(245,158,11,0.22)]" :style="{ height: point.purchases_height }"></div>
                                    </div>
                                    <p class="h-5 truncate text-center text-[11px] text-slate-400">
                                        {{ point.show_label ? point.label : '' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <p v-else class="mt-3 text-sm text-slate-300">Sin datos para graficar.</p>
                </article>

                <article class="min-w-0 rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-[0_20px_45px_rgba(8,47,73,0.36)] backdrop-blur">
                    <h3 class="text-base font-semibold text-slate-100">Composicion del periodo</h3>
                    <p class="mt-1 text-sm text-slate-300/80">{{ selectedPeriod.label }}</p>

                    <div class="mt-6 flex justify-center">
                        <div class="grid h-44 w-44 place-items-center rounded-full p-4" :style="donutStyle">
                            <div class="grid h-full w-full place-items-center rounded-full border border-cyan-100/10 bg-slate-950">
                                <div class="text-center">
                                    <p class="text-3xl font-bold text-slate-100">{{ selectedOperationsCount }}</p>
                                    <p class="mt-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">movimientos</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <dl class="mt-6 grid gap-3 text-sm">
                        <div class="flex items-center justify-between gap-3 rounded-lg border border-cyan-100/10 bg-slate-950/35 px-3 py-2">
                            <dt class="inline-flex items-center gap-2 text-slate-300">
                                <span class="h-2.5 w-2.5 rounded-full bg-indigo-500"></span>
                                Ventas
                            </dt>
                            <dd class="font-semibold text-slate-100">{{ salesShare }}%</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3 rounded-lg border border-cyan-100/10 bg-slate-950/35 px-3 py-2">
                            <dt class="inline-flex items-center gap-2 text-slate-300">
                                <span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span>
                                Compras
                            </dt>
                            <dd class="font-semibold text-slate-100">{{ purchasesShare }}%</dd>
                        </div>
                    </dl>
                </article>

                <article class="min-w-0 rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-[0_20px_45px_rgba(8,47,73,0.36)] backdrop-blur">
                    <h3 class="text-base font-semibold text-slate-100">Productos mas vendidos</h3>
                    <p class="mt-1 text-sm text-slate-300/80">Referencia para reposicion y compras.</p>
                    <ul v-if="top_sold_products.length" class="mt-4 space-y-3 text-sm">
                        <li v-for="item in top_sold_products" :key="`${item.product_id}-${item.product_name}`">
                            <div class="mb-1 flex items-center justify-between gap-2">
                                <span class="min-w-0 flex-1 truncate font-medium text-slate-100">{{ item.product_name }}</span>
                                <span class="shrink-0 text-right text-xs text-slate-300">{{ item.sold_quantity }} {{ item.sold_quantity_label }}</span>
                            </div>
                            <div class="h-2.5 rounded-full bg-slate-800">
                                <div class="h-2.5 rounded-full bg-indigo-500" :style="{ width: topSoldWidth(item.sold_quantity) }"></div>
                            </div>
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-sm text-slate-300">Sin ventas registradas aun.</p>
                </article>
            </section>

            <AppPanel :title="operationPulse.label" :subtitle="operationPulse.message" :tone="operationPulse.tone">
                <template #actions>
                    <StatusBadge :tone="operationPulse.tone" :label="`${summary.products_count} productos`" />
                    <StatusBadge tone="info" :label="`${summary.suppliers_count} proveedores`" />
                </template>

                <div class="grid gap-2 xl:grid-cols-3">
                    <article v-for="card in priorityCards" :key="card.key" class="min-w-0 rounded-xl border border-cyan-100/15 bg-slate-950/30 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="truncate text-sm font-semibold text-slate-100">{{ card.title }}</h3>
                                <p class="mt-1 text-xs text-slate-300/80">{{ card.description }}</p>
                            </div>
                            <StatusBadge :tone="card.tone" size="sm" :label="card.tone === 'success' ? 'OK' : 'Revisar'" />
                        </div>
                        <Link :href="card.href" class="mt-3 inline-flex items-center rounded-lg border border-cyan-100/20 px-3 py-1.5 text-xs font-semibold text-slate-200 hover:bg-slate-800/60">
                            {{ card.action }}
                        </Link>
                    </article>
                </div>
            </AppPanel>

            <section v-if="advancedSalesEnabled" class="grid gap-4 lg:grid-cols-2">
                <article class="min-w-0 rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-[0_20px_45px_rgba(8,47,73,0.36)] backdrop-blur">
                    <h3 class="text-base font-semibold text-slate-100">Ventas del mes por sector</h3>
                    <ul v-if="advanced_sales.sales_by_sector?.length" class="mt-3 space-y-2 text-sm">
                        <li v-for="sector in advanced_sales.sales_by_sector" :key="sector.id" class="rounded-lg border border-cyan-100/20 bg-slate-950/40 px-3 py-2">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <p class="min-w-0 break-words font-medium text-slate-100">{{ sector.name }}</p>
                                <p class="shrink-0 text-right text-slate-200">{{ money(sector.total) }}</p>
                            </div>
                            <p class="mt-1 text-xs text-slate-300">{{ sector.sales_count }} ventas</p>
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-sm text-slate-300">Sin ventas sectorizadas este mes.</p>
                </article>

                <article class="min-w-0 rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-[0_20px_45px_rgba(8,47,73,0.36)] backdrop-blur">
                    <h3 class="text-base font-semibold text-slate-100">Cobros del mes por destino</h3>
                    <ul v-if="advanced_sales.sales_by_payment_destination?.length" class="mt-3 space-y-2 text-sm">
                        <li v-for="destination in advanced_sales.sales_by_payment_destination" :key="destination.id" class="rounded-lg border border-cyan-100/20 bg-slate-950/40 px-3 py-2">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <p class="min-w-0 break-words font-medium text-slate-100">{{ destination.name }}</p>
                                <p class="shrink-0 text-right text-slate-200">{{ money(destination.total) }}</p>
                            </div>
                            <p class="mt-1 text-xs text-slate-300">{{ destination.sales_count }} ventas</p>
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-sm text-slate-300">Sin cobros asociados a destinos este mes.</p>
                </article>
            </section>

            <section class="grid gap-4 lg:grid-cols-3">
                <article class="min-w-0 rounded-2xl border border-amber-200/40 bg-amber-300/12 p-5 shadow-[0_20px_45px_rgba(8,47,73,0.36)] backdrop-blur lg:col-span-3">
                    <h3 class="text-base font-semibold text-amber-100">Alertas de vencimiento</h3>
                    <div v-if="expirationGroups.length" class="mt-4 grid gap-4 xl:grid-cols-3">
                        <section v-for="group in expirationGroups" :key="group.key" class="min-w-0 rounded-xl border border-amber-200/20 bg-slate-950/30 p-3">
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
                                            <p class="break-words font-medium text-slate-100">{{ item.product_name }}</p>
                                            <p class="mt-1 break-words text-xs text-slate-300">
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

                <article class="min-w-0 rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-[0_20px_45px_rgba(8,47,73,0.36)] backdrop-blur">
                    <h3 class="text-base font-semibold text-slate-100">Productos con stock bajo</h3>
                    <ul v-if="low_stock_products.length" class="mt-3 space-y-2 text-sm">
                        <li v-for="product in low_stock_products" :key="product.id" class="rounded-lg border border-rose-300/40 bg-rose-400/12 px-3 py-2 text-rose-100">
                            <span class="break-words">{{ product.name }} - actual {{ product.stock }} / minimo {{ product.min_stock }}</span>
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-sm text-slate-300">No hay alertas de stock bajo.</p>
                </article>

                <article class="min-w-0 rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-[0_20px_45px_rgba(8,47,73,0.36)] backdrop-blur">
                    <h3 class="text-base font-semibold text-slate-100">Ultimas ventas</h3>
                    <ul v-if="latest_sales.length" class="mt-3 space-y-2 text-sm">
                        <li v-for="sale in latest_sales" :key="sale.id" class="rounded-lg border border-cyan-100/20 bg-slate-950/40 px-3 py-2">
                            <p class="break-words font-medium text-slate-100">{{ sale.sale_number || `Venta #${sale.id}` }} - {{ money(sale.total) }}</p>
                            <p class="break-words text-xs text-slate-300">{{ sale.sold_at }} - {{ sale.user || '-' }}</p>
                            <p v-if="advancedSalesEnabled" class="mt-1 text-xs text-slate-400">
                                {{ sale.sale_sector || 'Sin sector' }} | {{ sale.payment_destination || 'Sin destino' }}
                            </p>
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-sm text-slate-300">Sin ventas recientes.</p>
                </article>
            </section>

            <section class="min-w-0 rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-[0_20px_45px_rgba(8,47,73,0.36)] backdrop-blur">
                <h3 class="text-base font-semibold text-slate-100">Ultimas compras</h3>
                <ul v-if="latest_purchases.length" class="mt-3 space-y-2 text-sm">
                    <li v-for="purchase in latest_purchases" :key="purchase.id" class="rounded-lg border border-cyan-100/20 bg-slate-950/40 px-3 py-2">
                        <p class="break-words font-medium text-slate-100">{{ purchase.purchase_number || `Compra #${purchase.id}` }} - {{ money(purchase.total) }}</p>
                        <p class="break-words text-xs text-slate-300">{{ purchase.purchased_at }} - {{ purchase.supplier || 'Sin proveedor' }}</p>
                    </li>
                </ul>
                <p v-else class="mt-3 text-sm text-slate-300">Sin compras recientes.</p>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
