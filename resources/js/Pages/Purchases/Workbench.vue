<script setup>
import { computed, reactive } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    summary: {
        type: Array,
        default: () => [],
    },
    statuses: {
        type: Array,
        default: () => [],
    },
    products: {
        type: Array,
        default: () => [],
    },
    recentPurchases: {
        type: Array,
        default: () => [],
    },
});

const state = reactive({
    search: '',
    status: 'all',
    showForm: false,
});

const page = usePage();

const form = useForm({
    supplier_name: '',
    status: 'draft',
    expected_at: '',
    notes: '',
    items: [],
});

const lineDraft = reactive({
    product_id: props.products[0]?.id ?? '',
    qty: 1,
    unit_cost: props.products[0]?.base_price ?? 0,
    description: '',
});

const moneyFormatter = new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    minimumFractionDigits: 2,
});

const normalizedSearch = computed(() => state.search.trim().toLowerCase());

const filteredPurchases = computed(() => (
    props.recentPurchases.filter((purchase) => {
        const byStatus = state.status === 'all' || purchase.status === state.status;
        const bySearch = normalizedSearch.value === '' || (
            purchase.number.toLowerCase().includes(normalizedSearch.value) ||
            purchase.supplier.toLowerCase().includes(normalizedSearch.value)
        );

        return byStatus && bySearch;
    })
));

const statusName = (key) => props.statuses.find((status) => status.key === key)?.name ?? key;

const statusClass = (key) => {
    if (key === 'received') return 'bg-emerald-100 text-emerald-700';
    if (key === 'sent') return 'bg-sky-100 text-sky-700';
    if (key === 'cancelled') return 'bg-rose-100 text-rose-700';
    return 'bg-amber-100 text-amber-700';
};

const formatMoney = (value) => moneyFormatter.format(Number(value) || 0);

const selectedProduct = computed(() => (
    props.products.find((product) => product.id === Number(lineDraft.product_id)) ?? null
));

const computedTotal = computed(() => (
    form.items.reduce((carry, item) => carry + (Number(item.qty) * Number(item.unit_cost)), 0)
));

const addItem = () => {
    if (!lineDraft.product_id || !lineDraft.qty || Number(lineDraft.qty) <= 0) {
        return;
    }

    const product = selectedProduct.value;
    if (!product) {
        return;
    }

    const productId = Number(lineDraft.product_id);
    const qty = Number(lineDraft.qty);
    const unitCost = Number(lineDraft.unit_cost || product.base_price || 0);

    const existing = form.items.find((item) => item.product_id === productId);
    if (existing) {
        existing.qty = Number((Number(existing.qty) + qty).toFixed(3));
        existing.unit_cost = unitCost;
        existing.description = lineDraft.description?.trim() || existing.description || product.name;
    } else {
        form.items.push({
            product_id: productId,
            description: lineDraft.description?.trim() || product.name,
            qty: Number(qty.toFixed(3)),
            unit_cost: Number(unitCost.toFixed(2)),
        });
    }

    lineDraft.qty = 1;
    lineDraft.description = '';
};

const removeItem = (index) => {
    form.items.splice(index, 1);
};

const lineSubtotal = (item) => formatMoney(Number(item.qty) * Number(item.unit_cost));

const moveToStatus = (purchaseId, targetStatus) => {
    router.patch(route('purchases.status', purchaseId), { status: targetStatus }, {
        preserveScroll: true,
    });
};

const createPurchase = () => {
    if (!form.items.length) {
        return;
    }

    form.post(route('purchases.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('supplier_name', 'status', 'expected_at', 'notes', 'items');
            form.status = 'draft';
            form.items = [];
            state.showForm = false;
        },
    });
};
</script>

<template>
    <Head title="Compras" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold leading-tight text-slate-900">Compras</h2>
                    <p class="mt-1 text-sm text-slate-500">Gestion de proveedores y ordenes de reposicion.</p>
                </div>
                <button
                    type="button"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500"
                    @click="state.showForm = !state.showForm"
                >
                    {{ state.showForm ? 'Cerrar formulario' : 'Nueva orden' }}
                </button>
            </div>
        </template>

        <div class="grid gap-6">
            <section class="rounded-2xl border border-indigo-200 bg-indigo-50 p-4 text-sm text-indigo-900">
                <p class="font-semibold">Como funciona Compras</p>
                <p class="mt-1">
                    Flujo recomendado: <strong>Borrador</strong> → <strong>Enviada</strong> → <strong>Recibida</strong>.
                    Si una orden se descarta, pasala a <strong>Cancelada</strong>.
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
                v-if="state.showForm"
                class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
            >
                <div class="grid gap-3 md:grid-cols-4">
                    <input
                        v-model="form.supplier_name"
                        type="text"
                        class="rounded-xl border-slate-300 text-sm"
                        placeholder="Proveedor"
                    />
                    <select v-model="form.status" class="rounded-xl border-slate-300 text-sm">
                        <option v-for="status in statuses" :key="status.key" :value="status.key">{{ status.name }}</option>
                    </select>
                    <input
                        v-model="form.expected_at"
                        type="date"
                        class="rounded-xl border-slate-300 text-sm"
                    />
                    <input
                        v-model="form.notes"
                        type="text"
                        class="rounded-xl border-slate-300 text-sm"
                        placeholder="Notas"
                    />
                </div>

                <div class="grid gap-3 rounded-xl border border-slate-200 p-3 md:grid-cols-5">
                    <select v-model="lineDraft.product_id" class="rounded-xl border-slate-300 text-sm md:col-span-2">
                        <option value="">Producto</option>
                        <option v-for="product in products" :key="product.id" :value="product.id">
                            {{ product.name }} ({{ product.sku || 'sin SKU' }})
                        </option>
                    </select>
                    <input
                        v-model.number="lineDraft.qty"
                        type="number"
                        min="0.001"
                        step="0.001"
                        class="rounded-xl border-slate-300 text-sm"
                        placeholder="Cantidad"
                    />
                    <input
                        v-model.number="lineDraft.unit_cost"
                        type="number"
                        min="0"
                        step="0.01"
                        class="rounded-xl border-slate-300 text-sm"
                        placeholder="Costo unitario"
                    />
                    <button
                        type="button"
                        class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        @click="addItem"
                    >
                        Agregar item
                    </button>
                    <input
                        v-model="lineDraft.description"
                        type="text"
                        class="rounded-xl border-slate-300 text-sm md:col-span-5"
                        placeholder="Descripcion opcional de la linea"
                    />
                </div>

                <div class="overflow-hidden rounded-xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Producto</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Cantidad</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Costo</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Subtotal</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-500"></th>
                            </tr>
                        </thead>
                        <tbody v-if="form.items.length" class="divide-y divide-slate-100 bg-white">
                            <tr v-for="(item, index) in form.items" :key="`${item.product_id}-${index}`">
                                <td class="px-3 py-2">{{ item.description }}</td>
                                <td class="px-3 py-2">{{ item.qty }}</td>
                                <td class="px-3 py-2">{{ formatMoney(item.unit_cost) }}</td>
                                <td class="px-3 py-2">{{ lineSubtotal(item) }}</td>
                                <td class="px-3 py-2 text-right">
                                    <button
                                        type="button"
                                        class="rounded-lg border border-rose-300 px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50"
                                        @click="removeItem(index)"
                                    >
                                        Quitar
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                        <tbody v-else>
                            <tr>
                                <td colspan="5" class="px-3 py-4 text-center text-slate-400">
                                    Agrega items para crear la orden.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm font-semibold text-slate-700">
                        Total calculado: <span class="text-slate-900">{{ formatMoney(computedTotal) }}</span>
                    </p>
                    <button
                        type="button"
                        class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-600 disabled:opacity-50"
                        :disabled="form.processing || !form.items.length"
                        @click="createPurchase"
                    >
                        Guardar orden
                    </button>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-base font-semibold text-slate-900">Ordenes de compra</h3>
                    <div class="flex flex-wrap gap-2">
                        <input
                            v-model="state.search"
                            type="text"
                            class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="Buscar por proveedor o numero"
                        />
                        <select v-model="state.status" class="rounded-xl border-slate-300 text-sm">
                            <option value="all">Todos</option>
                            <option v-for="status in statuses" :key="status.key" :value="status.key">
                                {{ status.name }}
                            </option>
                        </select>
                    </div>
                </div>

                <p
                    v-if="page.props.flash?.success || page.props.flash?.error"
                    class="mt-3 rounded-lg px-3 py-2 text-sm"
                    :class="page.props.flash?.error ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700'"
                >
                    {{ page.props.flash?.error ?? page.props.flash?.success }}
                </p>

                <div class="mt-5 overflow-hidden rounded-xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Nro Orden</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Proveedor</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Estado</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Items</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Total</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Entrega estimada</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-500"></th>
                            </tr>
                        </thead>
                        <tbody v-if="filteredPurchases.length" class="divide-y divide-slate-100 bg-white">
                            <tr v-for="purchase in filteredPurchases" :key="purchase.id">
                                <td class="px-3 py-2 font-medium text-slate-800">{{ purchase.number }}</td>
                                <td class="px-3 py-2">{{ purchase.supplier }}</td>
                                <td class="px-3 py-2">
                                    <span class="rounded-full px-2 py-1 text-xs font-semibold" :class="statusClass(purchase.status)">
                                        {{ statusName(purchase.status) }}
                                    </span>
                                </td>
                                <td class="px-3 py-2">{{ purchase.items_count }}</td>
                                <td class="px-3 py-2">{{ formatMoney(purchase.total) }}</td>
                                <td class="px-3 py-2">{{ purchase.expected_at || '-' }}</td>
                                <td class="px-3 py-2 text-right">
                                    <div class="flex flex-wrap justify-end gap-1">
                                        <button
                                            v-if="purchase.status === 'draft'"
                                            type="button"
                                            class="rounded-lg border border-sky-300 px-2 py-1 text-xs font-semibold text-sky-700 hover:bg-sky-50"
                                            @click="moveToStatus(purchase.id, 'sent')"
                                        >
                                            Enviar
                                        </button>
                                        <button
                                            v-if="purchase.status === 'sent'"
                                            type="button"
                                            class="rounded-lg border border-emerald-300 px-2 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-50"
                                            @click="moveToStatus(purchase.id, 'received')"
                                        >
                                            Recibir
                                        </button>
                                        <button
                                            v-if="purchase.status !== 'cancelled' && purchase.status !== 'received'"
                                            type="button"
                                            class="rounded-lg border border-rose-300 px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50"
                                            @click="moveToStatus(purchase.id, 'cancelled')"
                                        >
                                            Cancelar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                        <tbody v-else>
                            <tr>
                                <td colspan="7" class="px-3 py-6 text-center text-slate-400">
                                    No se encontraron ordenes para los filtros elegidos.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
