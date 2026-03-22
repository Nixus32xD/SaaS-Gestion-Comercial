<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    product: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
    suppliers: { type: Array, default: () => [] },
});

const form = useForm({
    category_id: props.product.category_id || '',
    supplier_id: props.product.supplier_id || '',
    name: props.product.name,
    slug: props.product.slug || '',
    description: props.product.description || '',
    barcode: props.product.barcode || '',
    sku: props.product.sku || '',
    unit_type: props.product.unit_type,
    weight_unit: props.product.weight_unit || 'kg',
    sale_price: Number(props.product.sale_price),
    cost_price: Number(props.product.cost_price),
    stock: Number(props.product.stock),
    batch_code: '',
    batch_expires_at: '',
    min_stock: Number(props.product.min_stock),
    shelf_life_days: props.product.shelf_life_days ? Number(props.product.shelf_life_days) : '',
    expiry_alert_days: Number(props.product.expiry_alert_days || 15),
    is_active: Boolean(props.product.is_active),
});

const dateToYmd = (date) => {
    if (!(date instanceof Date) || Number.isNaN(date.getTime())) return '';

    const offsetMinutes = date.getTimezoneOffset();
    const localDate = new Date(date.getTime() - (offsetMinutes * 60000));
    return localDate.toISOString().slice(0, 10);
};

const shelfLifeDaysToDate = (daysValue) => {
    const days = Number(daysValue || 0);
    if (days <= 0) return '';

    const baseDate = new Date();
    baseDate.setHours(0, 0, 0, 0);
    baseDate.setDate(baseDate.getDate() + days);

    return dateToYmd(baseDate);
};

const toShelfLifeDays = (dateValue) => {
    if (!dateValue) return '';

    const target = new Date(`${dateValue}T00:00:00`);
    if (Number.isNaN(target.getTime())) return '';

    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const diffDays = Math.ceil((target.getTime() - today.getTime()) / 86400000);
    return diffDays > 0 ? diffDays : 1;
};

const shelfLifeDate = ref(shelfLifeDaysToDate(props.product.shelf_life_days));
const isWeightProduct = computed(() => form.unit_type === 'weight');
const measurementUnitLabel = computed(() => (form.weight_unit === 'g' ? 'g' : 'kg'));
const priceLabel = computed(() => {
    if (!isWeightProduct.value) return 'Precio de venta';
    return form.weight_unit === 'g' ? 'Precio de venta por 100 g' : 'Precio de venta por kg';
});
const costLabel = computed(() => {
    if (!isWeightProduct.value) return 'Precio de costo';
    return form.weight_unit === 'g' ? 'Costo por 100 g' : 'Costo por kg';
});
const stockLabel = computed(() => (
    isWeightProduct.value ? `Stock actual (${measurementUnitLabel.value})` : 'Stock actual'
));
const minStockLabel = computed(() => (
    isWeightProduct.value ? `Stock minimo (${measurementUnitLabel.value})` : 'Stock minimo'
));
const quantityStep = computed(() => (isWeightProduct.value && form.weight_unit === 'kg' ? '0.001' : '1'));

const submit = () => {
    form.shelf_life_days = toShelfLifeDays(shelfLifeDate.value);
    form.put(route('products.update', props.product.id));
};
</script>

<template>
    <Head title="Editar producto" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold text-slate-100">Editar producto</h2>
                    <p class="mt-1 text-sm text-slate-300/80">Actualiza catalogo y stock.</p>
                </div>
                <Link :href="route('products.index')" class="text-sm font-semibold text-slate-300 hover:text-slate-100">Volver</Link>
            </div>
        </template>

        <form class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 backdrop-blur p-5 shadow-sm" @submit.prevent="submit">
            <div class="mb-5 rounded-2xl border border-cyan-100/20 bg-slate-950/35 p-4 text-sm text-slate-300">
                <p class="font-semibold text-slate-100">Stock y lotes</p>
                <p class="mt-2">Stock total: <strong>{{ props.product.batch_summary.total_stock }}</strong></p>
                <p>Lotes activos: <strong>{{ props.product.batch_summary.tracked_stock }}</strong></p>
                <p v-if="props.product.batch_summary.untracked_stock > 0">Stock sin lote historico: <strong>{{ props.product.batch_summary.untracked_stock }}</strong></p>
                <p class="mt-2 text-xs text-slate-400">Si aumentas stock desde esta pantalla puedes indicar lote y vencimiento. Si reduces stock, el sistema consume por FEFO y luego usa el remanente historico sin lote.</p>
            </div>

            <div class="grid gap-3 md:grid-cols-2">
                <div class="space-y-1">
                    <label class="text-sm font-medium text-slate-300">Nombre</label>
                    <input v-model="form.name" type="text" class="w-full rounded-xl border-cyan-100/25 text-sm" placeholder="Nombre del producto" />
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-medium text-slate-300">Slug</label>
                    <input v-model="form.slug" type="text" class="w-full rounded-xl border-cyan-100/25 text-sm" placeholder="Slug (opcional)" />
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-medium text-slate-300">Categoria</label>
                    <select v-model="form.category_id" class="w-full rounded-xl border-cyan-100/25 text-sm">
                        <option value="">Sin categoria</option>
                        <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                    </select>
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-medium text-slate-300">Proveedor</label>
                    <select v-model="form.supplier_id" class="w-full rounded-xl border-cyan-100/25 text-sm">
                        <option value="">Sin proveedor</option>
                        <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">{{ supplier.name }}</option>
                    </select>
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-medium text-slate-300">Tipo de unidad</label>
                    <select v-model="form.unit_type" class="w-full rounded-xl border-cyan-100/25 text-sm">
                        <option value="unit">Unidad</option>
                        <option value="weight">Peso</option>
                    </select>
                </div>
                <div v-if="isWeightProduct" class="space-y-1">
                    <label class="text-sm font-medium text-slate-300">Gestion por peso</label>
                    <select v-model="form.weight_unit" class="w-full rounded-xl border-cyan-100/25 text-sm">
                        <option value="kg">Kilos</option>
                        <option value="g">Gramos</option>
                    </select>
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-medium text-slate-300">Codigo de barras</label>
                    <input v-model="form.barcode" type="text" class="w-full rounded-xl border-cyan-100/25 text-sm" placeholder="Opcional" />
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-medium text-slate-300">SKU</label>
                    <input v-model="form.sku" type="text" class="w-full rounded-xl border-cyan-100/25 text-sm" placeholder="Opcional" />
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-medium text-slate-300">{{ priceLabel }}</label>
                    <input v-model.number="form.sale_price" type="number" min="0" step="0.01" class="w-full rounded-xl border-cyan-100/25 text-sm" />
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-medium text-slate-300">{{ costLabel }}</label>
                    <input v-model.number="form.cost_price" type="number" min="0" step="0.01" class="w-full rounded-xl border-cyan-100/25 text-sm" />
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-medium text-slate-300">{{ stockLabel }}</label>
                    <input v-model.number="form.stock" type="number" min="0" :step="quantityStep" class="w-full rounded-xl border-cyan-100/25 text-sm" />
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-medium text-slate-300">Lote para ingreso o ajuste positivo</label>
                    <input v-model="form.batch_code" type="text" class="w-full rounded-xl border-cyan-100/25 text-sm" placeholder="Opcional, se genera automatico" />
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-medium text-slate-300">Vencimiento del lote</label>
                    <input v-model="form.batch_expires_at" type="date" class="w-full rounded-xl border-cyan-100/25 text-sm" />
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-medium text-slate-300">{{ minStockLabel }}</label>
                    <input v-model.number="form.min_stock" type="number" min="0" :step="quantityStep" class="w-full rounded-xl border-cyan-100/25 text-sm" />
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-medium text-slate-300">Fecha de vencimiento (referencia)</label>
                    <input v-model="shelfLifeDate" type="date" class="w-full rounded-xl border-cyan-100/25 text-sm" />
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-medium text-slate-300">Alerta antes de vencer (dias)</label>
                    <input v-model.number="form.expiry_alert_days" type="number" min="1" step="1" class="w-full rounded-xl border-cyan-100/25 text-sm" />
                </div>
                <div class="space-y-1 md:col-span-2">
                    <label class="text-sm font-medium text-slate-300">Descripcion</label>
                    <textarea v-model="form.description" rows="3" class="w-full rounded-xl border-cyan-100/25 text-sm" placeholder="Descripcion opcional" />
                </div>
            </div>

            <label class="mt-4 inline-flex items-center gap-2 text-sm text-slate-300">
                <input v-model="form.is_active" type="checkbox" class="rounded border-cyan-100/25">
                Producto activo
            </label>

            <div class="mt-5 flex justify-end">
                <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-600" :disabled="form.processing">
                    Guardar cambios
                </button>
            </div>
        </form>

        <section class="mt-6 rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-slate-100">Lotes activos</h3>
                    <p class="mt-1 text-sm text-slate-300/80">Control de cantidades y vencimientos usado por la salida FEFO.</p>
                </div>
                <span class="rounded-full border border-cyan-100/20 px-3 py-1 text-xs font-semibold text-cyan-100">{{ props.product.batch_summary.batches_count }} lotes</span>
            </div>

            <div class="mt-4 overflow-x-auto rounded-xl border border-cyan-100/20 app-table-wrap">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-950/35">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-slate-300/80">Lote</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-300/80">Cantidad</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-300/80">Costo</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-300/80">Vencimiento</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-300/80">Estado</th>
                        </tr>
                    </thead>
                    <tbody v-if="props.product.batches.length" class="divide-y divide-slate-100">
                        <tr v-for="batch in props.product.batches" :key="batch.id">
                            <td class="px-3 py-2 font-semibold text-slate-100">{{ batch.batch_code }}</td>
                            <td class="px-3 py-2 text-slate-300">{{ batch.quantity }}</td>
                            <td class="px-3 py-2 text-slate-300">{{ batch.unit_cost ?? '-' }}</td>
                            <td class="px-3 py-2 text-slate-300">{{ batch.expires_at || 'Sin vencimiento' }}</td>
                            <td class="px-3 py-2">
                                <span
                                    class="rounded-full px-2.5 py-1 text-xs font-semibold"
                                    :class="{
                                        'bg-rose-400/15 text-rose-100': batch.status === 'expired',
                                        'bg-amber-300/15 text-amber-100': batch.status === 'upcoming',
                                        'bg-slate-700/50 text-slate-200': batch.status === 'no_expiration',
                                        'bg-emerald-400/15 text-emerald-100': batch.status === 'valid',
                                    }"
                                >
                                    {{ batch.status_label }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                    <tbody v-else>
                        <tr>
                            <td colspan="5" class="px-3 py-6 text-center text-slate-400">Este producto todavia no tiene lotes activos cargados.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </AuthenticatedLayout>
</template>

