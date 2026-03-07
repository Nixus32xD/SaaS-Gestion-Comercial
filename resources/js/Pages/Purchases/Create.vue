<script setup>
import { computed, reactive } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    suppliers: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
});

const state = reactive({
    mode: 'existing',
    search: '',
    product_id: '',
    quantity: 1,
    unit_cost: 0,
    new_product: {
        name: '',
        barcode: '',
        sku: '',
        unit_type: 'unit',
        sale_price: 0,
        min_stock: 0,
    },
});

const nowLocalDateTime = () => {
    const date = new Date();
    const offsetMinutes = date.getTimezoneOffset();
    const localDate = new Date(date.getTime() - (offsetMinutes * 60000));

    return localDate.toISOString().slice(0, 16);
};

const form = useForm({
    supplier_id: '',
    purchased_at: nowLocalDateTime(),
    notes: '',
    items: [],
});

const filteredProducts = computed(() => {
    const term = state.search.trim().toLowerCase();

    if (term === '') {
        return props.products.slice(0, 40);
    }

    return props.products.filter((product) => (
        (product.name || '').toLowerCase().includes(term)
        || (product.barcode || '').toLowerCase().includes(term)
        || (product.sku || '').toLowerCase().includes(term)
    ));
});

const selectedProduct = computed(() => props.products.find((product) => product.id === Number(state.product_id)) || null);

const addItem = () => {
    const quantity = Number(state.quantity || 0);
    const unitCost = Number(state.unit_cost || 0);

    if (quantity <= 0 || unitCost < 0) return;

    if (state.mode === 'existing') {
        const product = selectedProduct.value;
        if (!product) return;

        form.items.push({
            product_id: product.id,
            quantity: Number(quantity.toFixed(3)),
            unit_cost: Number(unitCost.toFixed(2)),
            product: null,
        });

        state.product_id = '';
    } else {
        if (!state.new_product.name.trim()) return;

        form.items.push({
            product_id: null,
            quantity: Number(quantity.toFixed(3)),
            unit_cost: Number(unitCost.toFixed(2)),
            product: {
                name: state.new_product.name,
                barcode: state.new_product.barcode || null,
                sku: state.new_product.sku || null,
                unit_type: state.new_product.unit_type,
                sale_price: Number(state.new_product.sale_price || 0),
                min_stock: Number(state.new_product.min_stock || 0),
            },
        });

        state.new_product = {
            name: '',
            barcode: '',
            sku: '',
            unit_type: 'unit',
            sale_price: 0,
            min_stock: 0,
        };
    }

    state.quantity = 1;
    state.unit_cost = 0;
};

const removeItem = (index) => {
    form.items.splice(index, 1);
};

const itemLabel = (item) => {
    if (item.product_id) {
        return props.products.find((product) => product.id === Number(item.product_id))?.name || `Producto #${item.product_id}`;
    }

    return item.product?.name || 'Producto nuevo';
};

const total = computed(() => form.items.reduce((acc, item) => acc + (Number(item.quantity) * Number(item.unit_cost)), 0));

const money = (value) => new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    minimumFractionDigits: 2,
}).format(Number(value) || 0);

const submit = () => {
    form.post(route('purchases.store'));
};
</script>

<template>
    <Head title="Nueva compra" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold text-slate-900">Nueva compra</h2>
                    <p class="mt-1 text-sm text-slate-500">Registra compra y actualiza stock.</p>
                </div>
                <Link :href="route('purchases.index')" class="text-sm font-semibold text-slate-600 hover:text-slate-900">Volver</Link>
            </div>
        </template>

        <form class="grid gap-6" @submit.prevent="submit">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="grid gap-3 md:grid-cols-3">
                    <select v-model="form.supplier_id" class="rounded-xl border-slate-300 text-sm">
                        <option value="">Sin proveedor</option>
                        <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">{{ supplier.name }}</option>
                    </select>
                    <input v-model="form.purchased_at" type="datetime-local" class="rounded-xl border-slate-300 text-sm" />
                    <input v-model="form.notes" type="text" class="rounded-xl border-slate-300 text-sm" placeholder="Notas" />
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center gap-4">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input v-model="state.mode" type="radio" value="existing" class="border-slate-300">
                        Usar producto existente
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input v-model="state.mode" type="radio" value="new" class="border-slate-300">
                        Crear producto nuevo
                    </label>
                </div>

                <div v-if="state.mode === 'existing'" class="mt-4 grid gap-3 md:grid-cols-3">
                    <input v-model="state.search" type="text" class="rounded-xl border-slate-300 text-sm" placeholder="Buscar producto" />
                    <select v-model="state.product_id" class="rounded-xl border-slate-300 text-sm md:col-span-2">
                        <option value="">Seleccionar producto</option>
                        <option v-for="product in filteredProducts" :key="product.id" :value="product.id">{{ product.name }} ({{ product.sku || product.barcode || 'sin codigo' }})</option>
                    </select>
                </div>

                <div v-else class="mt-4 grid gap-3 md:grid-cols-3">
                    <input v-model="state.new_product.name" type="text" class="rounded-xl border-slate-300 text-sm" placeholder="Nombre producto" />
                    <input v-model="state.new_product.barcode" type="text" class="rounded-xl border-slate-300 text-sm" placeholder="Barcode" />
                    <input v-model="state.new_product.sku" type="text" class="rounded-xl border-slate-300 text-sm" placeholder="SKU" />
                    <select v-model="state.new_product.unit_type" class="rounded-xl border-slate-300 text-sm">
                        <option value="unit">Unidad</option>
                        <option value="weight">Peso</option>
                    </select>
                    <input v-model.number="state.new_product.sale_price" type="number" min="0" step="0.01" class="rounded-xl border-slate-300 text-sm" placeholder="Precio venta sugerido" />
                    <input v-model.number="state.new_product.min_stock" type="number" min="0" step="0.001" class="rounded-xl border-slate-300 text-sm" placeholder="Stock minimo" />
                </div>

                <div class="mt-4 grid gap-3 md:grid-cols-3">
                    <input v-model.number="state.quantity" type="number" min="0.001" step="0.001" class="rounded-xl border-slate-300 text-sm" placeholder="Cantidad" />
                    <input v-model.number="state.unit_cost" type="number" min="0" step="0.01" class="rounded-xl border-slate-300 text-sm" placeholder="Costo unitario" />
                    <button type="button" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="addItem">Agregar item</button>
                </div>

                <div class="mt-4 overflow-hidden rounded-xl border border-slate-200">
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
                        <tbody v-if="form.items.length" class="divide-y divide-slate-100">
                            <tr v-for="(item, index) in form.items" :key="index">
                                <td class="px-3 py-2 font-semibold text-slate-900">{{ itemLabel(item) }}</td>
                                <td class="px-3 py-2">{{ item.quantity }}</td>
                                <td class="px-3 py-2">{{ money(item.unit_cost) }}</td>
                                <td class="px-3 py-2">{{ money(Number(item.quantity) * Number(item.unit_cost)) }}</td>
                                <td class="px-3 py-2 text-right">
                                    <button type="button" class="rounded-lg border border-rose-300 px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50" @click="removeItem(index)">Quitar</button>
                                </td>
                            </tr>
                        </tbody>
                        <tbody v-else>
                            <tr>
                                <td colspan="5" class="px-3 py-5 text-center text-slate-400">Agrega items para registrar la compra.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-slate-700">Total: <strong>{{ money(total) }}</strong></p>
                <div class="mt-4 flex justify-end">
                    <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-600" :disabled="form.processing || !form.items.length">
                        Confirmar compra
                    </button>
                </div>
            </section>
        </form>
    </AuthenticatedLayout>
</template>
