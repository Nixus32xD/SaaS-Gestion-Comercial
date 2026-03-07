<script setup>
import { computed, reactive } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    summary: {
        type: Array,
        default: () => [],
    },
    stockSnapshot: {
        type: Array,
        default: () => [],
    },
    alerts: {
        type: Array,
        default: () => [],
    },
    movementLabels: {
        type: Object,
        default: () => ({
            type: 'Tipo',
            product: 'Producto',
            quantity: 'Cantidad',
            date: 'Fecha',
            notes: 'Detalle',
        }),
    },
    movements: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();

const state = reactive({
    search: '',
    onlyLowStock: false,
    showAdjustForm: false,
});

const form = useForm({
    product_id: '',
    type: 'positive',
    qty: 1,
    notes: '',
});

const normalizedSearch = computed(() => state.search.trim().toLowerCase());

const filteredStock = computed(() => (
    props.stockSnapshot.filter((row) => {
        const matchQuery = normalizedSearch.value === '' || (
            row.product.toLowerCase().includes(normalizedSearch.value) ||
            row.sku.toLowerCase().includes(normalizedSearch.value)
        );

        const isLow = row.stock <= row.minimum;
        const matchStockLevel = !state.onlyLowStock || isLow;

        return matchQuery && matchStockLevel;
    })
));

const applyAdjustment = () => {
    form.post(route('inventory.adjust'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('product_id', 'type', 'qty', 'notes');
            form.type = 'positive';
            form.qty = 1;
            state.showAdjustForm = false;
        },
    });
};

const quickAdjust = (row, mode) => {
    router.post(route('inventory.adjust'), {
        product_id: row.id,
        type: mode === 'minus' ? 'negative' : 'positive',
        qty: 1,
        notes: `Ajuste rapido ${mode === 'minus' ? '-1' : '+1'}`,
    }, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Stock" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold leading-tight text-slate-900">Stock</h2>
                    <p class="mt-1 text-sm text-slate-500">Control de inventario, alertas y ajustes.</p>
                </div>
                <button
                    type="button"
                    class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    @click="state.showAdjustForm = !state.showAdjustForm"
                >
                    {{ state.showAdjustForm ? 'Cerrar ajuste' : 'Nuevo ajuste' }}
                </button>
            </div>
        </template>

        <div class="grid gap-6">
            <section class="rounded-2xl border border-indigo-200 bg-indigo-50 p-4 text-sm text-indigo-900">
                <p class="font-semibold">Como funciona el ajuste de stock</p>
                <p class="mt-1">
                    El ajuste positivo suma unidades y el negativo descuenta. Cada ajuste genera un movimiento
                    y actualiza alertas de minimo automaticamente.
                </p>
            </section>

            <section class="grid gap-4 md:grid-cols-3">
                <article
                    v-for="item in summary"
                    :key="item.label"
                    class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <p class="text-xs uppercase tracking-wider text-slate-500">{{ item.label }}</p>
                    <p class="mt-2 text-3xl font-bold text-slate-900">{{ item.value }}</p>
                </article>
            </section>

            <section
                v-if="state.showAdjustForm"
                class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm md:grid-cols-4"
            >
                <select v-model="form.product_id" class="rounded-xl border-slate-300 text-sm">
                    <option value="">Seleccionar producto</option>
                    <option v-for="row in stockSnapshot" :key="row.id" :value="row.id">{{ row.product }}</option>
                </select>
                <select v-model="form.type" class="rounded-xl border-slate-300 text-sm">
                    <option value="positive">Ajuste positivo</option>
                    <option value="negative">Ajuste negativo</option>
                </select>
                <input
                    v-model.number="form.qty"
                    type="number"
                    min="0.01"
                    step="0.01"
                    class="rounded-xl border-slate-300 text-sm"
                    placeholder="Cantidad"
                />
                <input
                    v-model="form.notes"
                    type="text"
                    class="rounded-xl border-slate-300 text-sm"
                    placeholder="Motivo"
                />
                <button
                    type="button"
                    class="rounded-xl bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-600 md:col-span-4"
                    :disabled="form.processing"
                    @click="applyAdjustment"
                >
                    Aplicar ajuste
                </button>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-base font-semibold text-slate-900">Stock por producto</h3>
                    <div class="flex flex-wrap items-center gap-2">
                        <input
                            v-model="state.search"
                            type="text"
                            class="rounded-xl border-slate-300 text-sm"
                            placeholder="Buscar por nombre o SKU"
                        />
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input v-model="state.onlyLowStock" type="checkbox" class="rounded border-slate-300">
                            Solo bajo minimo
                        </label>
                    </div>
                </div>

                <p
                    v-if="page.props.flash?.success || page.props.flash?.error"
                    class="mt-3 rounded-lg px-3 py-2 text-sm"
                    :class="page.props.flash?.error ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700'"
                >
                    {{ page.props.flash?.error ?? page.props.flash?.success }}
                </p>

                <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200 app-table-wrap">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Producto</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">SKU</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Sucursal</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Stock</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Reservado</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Minimo</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-500"></th>
                            </tr>
                        </thead>
                        <tbody v-if="filteredStock.length" class="divide-y divide-slate-100 bg-white">
                            <tr
                                v-for="row in filteredStock"
                                :key="row.id"
                                :class="row.stock <= row.minimum ? 'bg-rose-50/60' : ''"
                            >
                                <td class="px-3 py-2 font-medium text-slate-800">{{ row.product }}</td>
                                <td class="px-3 py-2">{{ row.sku }}</td>
                                <td class="px-3 py-2">{{ row.branch }}</td>
                                <td class="px-3 py-2">{{ row.stock }}</td>
                                <td class="px-3 py-2">{{ row.reserved }}</td>
                                <td class="px-3 py-2">{{ row.minimum }}</td>
                                <td class="px-3 py-2 text-right">
                                    <div class="flex justify-end gap-1">
                                        <button
                                            type="button"
                                            class="rounded-lg border border-rose-300 px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50"
                                            @click="quickAdjust(row, 'minus')"
                                        >
                                            -1
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-lg border border-emerald-300 px-2 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-50"
                                            @click="quickAdjust(row, 'plus')"
                                        >
                                            +1
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                        <tbody v-else>
                            <tr>
                                <td colspan="7" class="px-3 py-6 text-center text-slate-400">
                                    No hay productos para los filtros seleccionados.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="grid gap-6 lg:grid-cols-2">
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="text-base font-semibold text-slate-900">Alertas activas</h3>
                    <ul v-if="alerts.length" class="mt-3 space-y-2 text-sm text-slate-600">
                        <li
                            v-for="alert in alerts"
                            :key="alert.id"
                            class="rounded-lg border border-slate-200 px-3 py-2"
                        >
                            {{ alert.product }} ({{ alert.branch }}) - actual {{ alert.current }} / minimo {{ alert.minimum }}
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-sm text-slate-500">No hay alertas pendientes.</p>
                </article>

                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="text-base font-semibold text-slate-900">Ultimos movimientos</h3>
                    <ul v-if="movements.length" class="mt-3 space-y-2 text-sm text-slate-600">
                        <li
                            v-for="movement in movements.slice(0, 8)"
                            :key="movement.id"
                            class="rounded-lg border border-slate-200 px-3 py-2"
                        >
                            <p class="font-medium text-slate-800">
                                {{ movementLabels.type }}: {{ movement.type_label }}
                            </p>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ movementLabels.product }}: {{ movement.product }}
                            </p>
                            <p class="text-xs text-slate-500">
                                {{ movementLabels.quantity }}: {{ movement.quantity }}
                            </p>
                            <p class="text-xs text-slate-500">
                                {{ movementLabels.date }}: {{ movement.at }}
                            </p>
                            <p class="text-xs text-slate-500">
                                {{ movementLabels.notes }}: {{ movement.notes }}
                            </p>
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-sm text-slate-500">Sin movimientos registrados.</p>
                </article>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
