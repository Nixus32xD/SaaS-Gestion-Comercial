<script setup>
import AppPanel from '@/Components/AppPanel.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
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
const salePrice = computed(() => Number(form.sale_price || 0));
const costPrice = computed(() => Number(form.cost_price || 0));
const stockValue = computed(() => Number(form.stock || 0));
const minStockValue = computed(() => Number(form.min_stock || 0));
const marginAmount = computed(() => Number((salePrice.value - costPrice.value).toFixed(2)));
const marginPercent = computed(() => {
    if (costPrice.value <= 0) return null;

    return Number((((salePrice.value - costPrice.value) / costPrice.value) * 100).toFixed(1));
});
const stockStatusTone = computed(() => {
    if (stockValue.value <= 0) return 'danger';
    if (stockValue.value <= minStockValue.value) return 'warning';

    return 'success';
});
const productWarnings = computed(() => {
    const warnings = [];

    if (salePrice.value <= 0) warnings.push('Falta precio de venta.');
    if (costPrice.value <= 0) warnings.push('Falta costo para medir rentabilidad.');
    if (stockValue.value <= 0) warnings.push('El producto quedo sin stock disponible.');
    else if (stockValue.value <= minStockValue.value) warnings.push('El producto esta en stock bajo.');

    if (String(form.name || '').trim() === '') {
        warnings.push('Completa el nombre para identificarlo rapido.');
    }

    return warnings;
});
const summaryTone = computed(() => {
    if (productWarnings.value.length >= 3) return 'danger';
    if (productWarnings.value.length > 0) return 'warning';

    return 'success';
});

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

        <form class="grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_21rem]" @submit.prevent="submit">
            <div class="grid gap-6">
                <AppPanel title="Stock y lotes" subtitle="Ajusta el stock actual y registra lote solo cuando el cambio agrega mercaderia.">
                    <div class="grid gap-3 sm:grid-cols-3 text-sm text-slate-300">
                        <div class="app-subsection">
                            <p class="text-xs uppercase tracking-[0.18em] text-cyan-100/70">Stock total</p>
                            <p class="mt-2 text-2xl font-bold text-slate-100">{{ props.product.batch_summary.total_stock }}</p>
                        </div>
                        <div class="app-subsection">
                            <p class="text-xs uppercase tracking-[0.18em] text-cyan-100/70">Lotes activos</p>
                            <p class="mt-2 text-2xl font-bold text-slate-100">{{ props.product.batch_summary.tracked_stock }}</p>
                        </div>
                        <div class="app-subsection">
                            <p class="text-xs uppercase tracking-[0.18em] text-cyan-100/70">Sin lote historico</p>
                            <p class="mt-2 text-2xl font-bold text-slate-100">{{ props.product.batch_summary.untracked_stock }}</p>
                        </div>
                    </div>
                    <p class="mt-4 text-xs text-slate-400">Si aumentas stock desde esta pantalla puedes indicar lote y vencimiento. Si reduces stock, el sistema consume por FEFO y luego usa el remanente historico sin lote.</p>
                </AppPanel>

                <AppPanel title="Informacion basica" subtitle="Mantiene ordenado el catalogo y reduce errores de identificacion en mostrador.">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="app-field">
                            <label class="app-field-label">Nombre</label>
                            <input v-model="form.name" type="text" class="w-full rounded-xl text-sm" placeholder="Nombre del producto" />
                            <p v-if="form.errors.name" class="text-xs text-rose-300">{{ form.errors.name }}</p>
                        </div>
                        <div class="app-field">
                            <label class="app-field-label">Slug</label>
                            <input v-model="form.slug" type="text" class="w-full rounded-xl text-sm" placeholder="Opcional" />
                            <p v-if="form.errors.slug" class="text-xs text-rose-300">{{ form.errors.slug }}</p>
                        </div>
                        <div class="app-field">
                            <label class="app-field-label">Categoria</label>
                            <select v-model="form.category_id" class="w-full rounded-xl text-sm">
                                <option value="">Sin categoria</option>
                                <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                            </select>
                            <p v-if="form.errors.category_id" class="text-xs text-rose-300">{{ form.errors.category_id }}</p>
                        </div>
                        <div class="app-field">
                            <label class="app-field-label">Proveedor</label>
                            <select v-model="form.supplier_id" class="w-full rounded-xl text-sm">
                                <option value="">Sin proveedor</option>
                                <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">{{ supplier.name }}</option>
                            </select>
                            <p v-if="form.errors.supplier_id" class="text-xs text-rose-300">{{ form.errors.supplier_id }}</p>
                        </div>
                        <div class="app-field">
                            <label class="app-field-label">Tipo de unidad</label>
                            <select v-model="form.unit_type" class="w-full rounded-xl text-sm">
                                <option value="unit">Unidad</option>
                                <option value="weight">Peso</option>
                            </select>
                        </div>
                        <div v-if="isWeightProduct" class="app-field">
                            <label class="app-field-label">Gestion por peso</label>
                            <select v-model="form.weight_unit" class="w-full rounded-xl text-sm">
                                <option value="kg">Kilos</option>
                                <option value="g">Gramos</option>
                            </select>
                        </div>
                        <div class="app-field md:col-span-2">
                            <label class="app-field-label">Descripcion</label>
                            <textarea v-model="form.description" rows="3" class="w-full rounded-xl text-sm" placeholder="Descripcion opcional" />
                            <p v-if="form.errors.description" class="text-xs text-rose-300">{{ form.errors.description }}</p>
                        </div>
                    </div>
                </AppPanel>

                <AppPanel title="Precios e identificacion" subtitle="Ajusta codigos, precio y costo con una referencia rapida del margen actual.">
                    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_18rem]">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="app-field">
                                <label class="app-field-label">Codigo de barras</label>
                                <input v-model="form.barcode" type="text" class="w-full rounded-xl text-sm" placeholder="Opcional" />
                            </div>
                            <div class="app-field">
                                <label class="app-field-label">SKU</label>
                                <input v-model="form.sku" type="text" class="w-full rounded-xl text-sm" placeholder="Opcional" />
                            </div>
                            <div class="app-field">
                                <label class="app-field-label">{{ priceLabel }}</label>
                                <input v-model.number="form.sale_price" type="number" min="0" step="0.01" class="w-full rounded-xl text-sm" />
                                <p v-if="form.errors.sale_price" class="text-xs text-rose-300">{{ form.errors.sale_price }}</p>
                            </div>
                            <div class="app-field">
                                <label class="app-field-label">{{ costLabel }}</label>
                                <input v-model.number="form.cost_price" type="number" min="0" step="0.01" class="w-full rounded-xl text-sm" />
                                <p v-if="form.errors.cost_price" class="text-xs text-rose-300">{{ form.errors.cost_price }}</p>
                            </div>
                        </div>

                        <div class="app-subsection">
                            <p class="text-xs uppercase tracking-[0.18em] text-cyan-100/70">Margen estimado</p>
                            <p class="mt-3 text-2xl font-bold text-slate-100">${{ marginAmount.toFixed(2) }}</p>
                            <p class="mt-2 text-sm text-slate-300">
                                <span v-if="marginPercent !== null">{{ marginPercent }}% sobre costo</span>
                                <span v-else>Completa el costo para medir rentabilidad.</span>
                            </p>
                        </div>
                    </div>
                </AppPanel>

                <AppPanel title="Stock y vencimientos" subtitle="Usa lote solo cuando el ajuste suma stock y revisa rapido si el producto ya entro en nivel critico.">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="app-field">
                            <label class="app-field-label">{{ stockLabel }}</label>
                            <input v-model.number="form.stock" type="number" min="0" :step="quantityStep" class="w-full rounded-xl text-sm" />
                            <p v-if="form.errors.stock" class="text-xs text-rose-300">{{ form.errors.stock }}</p>
                        </div>
                        <div class="app-field">
                            <label class="app-field-label">{{ minStockLabel }}</label>
                            <input v-model.number="form.min_stock" type="number" min="0" :step="quantityStep" class="w-full rounded-xl text-sm" />
                            <p v-if="form.errors.min_stock" class="text-xs text-rose-300">{{ form.errors.min_stock }}</p>
                        </div>
                        <div class="app-field">
                            <label class="app-field-label">Lote para ingreso o ajuste positivo</label>
                            <input v-model="form.batch_code" type="text" class="w-full rounded-xl text-sm" placeholder="Opcional, se genera automatico" />
                            <p v-if="form.errors.batch_code" class="text-xs text-rose-300">{{ form.errors.batch_code }}</p>
                        </div>
                        <div class="app-field">
                            <label class="app-field-label">Vencimiento del lote</label>
                            <input v-model="form.batch_expires_at" type="date" class="w-full rounded-xl text-sm" />
                            <p v-if="form.errors.batch_expires_at" class="text-xs text-rose-300">{{ form.errors.batch_expires_at }}</p>
                        </div>
                        <div class="app-field">
                            <label class="app-field-label">Fecha de vencimiento de referencia</label>
                            <input v-model="shelfLifeDate" type="date" class="w-full rounded-xl text-sm" />
                        </div>
                        <div class="app-field">
                            <label class="app-field-label">Alerta antes de vencer (dias)</label>
                            <input v-model.number="form.expiry_alert_days" type="number" min="1" step="1" class="w-full rounded-xl text-sm" />
                            <p v-if="form.errors.expiry_alert_days" class="text-xs text-rose-300">{{ form.errors.expiry_alert_days }}</p>
                        </div>
                    </div>
                </AppPanel>

                <AppPanel title="Configuracion final" subtitle="Desactiva el producto solo cuando quieras dejarlo fuera del flujo operativo.">
                    <label class="inline-flex items-center gap-3 rounded-xl border border-cyan-100/15 bg-slate-950/35 px-4 py-3 text-sm text-slate-300">
                        <input v-model="form.is_active" type="checkbox" class="rounded border-cyan-100/25">
                        <span>
                            <span class="block font-semibold text-slate-100">Producto activo</span>
                            <span class="mt-1 block text-xs text-slate-400">Si lo desactivas, sigue en catalogo pero deja de ser operativo.</span>
                        </span>
                    </label>
                </AppPanel>

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end xl:hidden">
                    <Link :href="route('products.index')" class="inline-flex items-center justify-center rounded-xl border border-cyan-100/20 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/60">
                        Volver al listado
                    </Link>
                    <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500" :disabled="form.processing">
                        Guardar cambios
                    </button>
                </div>
            </div>

            <div class="app-sticky-column grid gap-6">
                <AppPanel title="Resumen operativo" :tone="summaryTone">
                    <div class="app-chip-row">
                        <StatusBadge :tone="salePrice > 0 ? 'success' : 'warning'" :label="salePrice > 0 ? 'Con precio' : 'Sin precio'" />
                        <StatusBadge :tone="costPrice > 0 ? 'success' : 'warning'" :label="costPrice > 0 ? 'Con costo' : 'Sin costo'" />
                        <StatusBadge :tone="stockStatusTone" :label="stockValue > 0 ? `Stock ${stockValue}` : 'Sin stock'" />
                        <StatusBadge :tone="props.product.batch_summary.batches_count > 0 ? 'info' : 'neutral'" :label="props.product.batch_summary.batches_count > 0 ? `${props.product.batch_summary.batches_count} lotes` : 'Sin lotes'" />
                        <StatusBadge :tone="form.is_active ? 'success' : 'neutral'" :label="form.is_active ? 'Activo' : 'Inactivo'" />
                    </div>

                    <div class="mt-4 grid gap-3">
                        <div class="app-subsection">
                            <p class="text-xs uppercase tracking-[0.18em] text-cyan-100/70">Margen estimado</p>
                            <p class="mt-2 text-xl font-bold text-slate-100">${{ marginAmount.toFixed(2) }}</p>
                            <p class="mt-1 text-xs text-slate-400">
                                <span v-if="marginPercent !== null">{{ marginPercent }}% sobre costo</span>
                                <span v-else>Sin referencia hasta cargar costo.</span>
                            </p>
                        </div>
                        <div class="app-subsection">
                            <p class="text-xs uppercase tracking-[0.18em] text-cyan-100/70">Stock sin lote historico</p>
                            <p class="mt-2 text-xl font-bold text-slate-100">{{ props.product.batch_summary.untracked_stock }}</p>
                            <p class="mt-1 text-xs text-slate-400">Detecta remanentes viejos no trazados por lote.</p>
                        </div>
                    </div>

                    <div v-if="productWarnings.length" class="mt-4 rounded-xl border border-amber-300/30 bg-amber-400/10 p-4 text-sm text-amber-100">
                        <p class="font-semibold">Puntos para revisar</p>
                        <ul class="mt-2 space-y-2">
                            <li v-for="warning in productWarnings" :key="warning">{{ warning }}</li>
                        </ul>
                    </div>
                    <div v-else class="mt-4 rounded-xl border border-emerald-300/30 bg-emerald-400/10 p-4 text-sm text-emerald-100">
                        El producto queda consistente para operar y seguir por lote.
                    </div>

                    <template #footer>
                        <div class="grid gap-3">
                            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500" :disabled="form.processing">
                                Guardar cambios
                            </button>
                            <Link :href="route('products.index')" class="inline-flex items-center justify-center rounded-xl border border-cyan-100/20 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/60">
                                Volver al listado
                            </Link>
                        </div>
                    </template>
                </AppPanel>
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
