<script setup>
import AppPanel from '@/Components/AppPanel.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { computed, reactive, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    categories: { type: Array, default: () => [] },
    suppliers: { type: Array, default: () => [] },
    global_catalog: { type: Object, default: () => ({ enabled: false }) },
});

const form = useForm({
    global_product_id: '',
    category_id: '',
    supplier_id: '',
    name: '',
    slug: '',
    description: '',
    barcode: '',
    sku: '',
    unit_type: 'unit',
    weight_unit: 'kg',
    sale_price: 0,
    cost_price: 0,
    stock: 0,
    batch_code: '',
    batch_expires_at: '',
    min_stock: 0,
    shelf_life_days: '',
    expiry_alert_days: 15,
    is_active: true,
});

const lookup = reactive({
    status: 'idle',
    localProduct: null,
    globalProduct: null,
    conflict: null,
    searchedBy: null,
});

const shelfLifeDate = ref('');
const isLookingUpCatalog = ref(false);

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
    isWeightProduct.value ? `Stock inicial (${measurementUnitLabel.value})` : 'Stock inicial'
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
const completionScore = computed(() => {
    const checks = [
        String(form.name || '').trim() !== '',
        salePrice.value > 0,
        costPrice.value > 0,
        String(form.category_id || '').trim() !== '',
        (!globalCatalogApplied.value && (String(form.barcode || '').trim() !== '' || String(form.sku || '').trim() !== '')) || globalCatalogApplied.value,
    ];

    return checks.filter(Boolean).length;
});
const completionLabel = computed(() => `${completionScore.value}/5 puntos operativos`);
const productWarnings = computed(() => {
    const warnings = [];

    if (salePrice.value <= 0) {
        warnings.push('Falta precio de venta.');
    }

    if (costPrice.value <= 0) {
        warnings.push('Falta costo para medir rentabilidad.');
    }

    if (stockValue.value <= 0) {
        warnings.push('El producto nace sin stock disponible.');
    } else if (stockValue.value <= minStockValue.value) {
        warnings.push('El stock inicial ya queda en nivel bajo.');
    }

    if (String(form.name || '').trim() === '') {
        warnings.push('Completa el nombre para poder identificarlo rapido en venta y compra.');
    }

    return warnings;
});
const summaryTone = computed(() => {
    if (productWarnings.value.length >= 3) return 'danger';
    if (productWarnings.value.length > 0) return 'warning';

    return 'success';
});
const globalCatalogApplied = computed(() => String(form.global_product_id || '').trim() !== '');

const toShelfLifeDays = (dateValue) => {
    if (!dateValue) return '';

    const target = new Date(`${dateValue}T00:00:00`);
    if (Number.isNaN(target.getTime())) return '';

    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const diffDays = Math.ceil((target.getTime() - today.getTime()) / 86400000);
    return diffDays > 0 ? diffDays : 1;
};

const clearLookup = () => {
    lookup.status = 'idle';
    lookup.localProduct = null;
    lookup.globalProduct = null;
    lookup.conflict = null;
    lookup.searchedBy = null;
};

const clearGlobalSelection = () => {
    form.global_product_id = '';

    if (lookup.status === 'applied') {
        lookup.status = 'idle';
    }
};

const lookupCatalog = async () => {
    const barcode = String(form.barcode || '').trim();
    const sku = String(form.sku || '').trim();

    if (barcode === '' && sku === '') {
        clearLookup();
        return;
    }

    isLookingUpCatalog.value = true;

    try {
        const { data } = await window.axios.get(route('products.catalog.lookup'), {
            params: { barcode, sku },
        });

        form.global_product_id = '';
        lookup.localProduct = data?.local_product || null;
        lookup.globalProduct = data?.global_product || null;
        lookup.conflict = data?.conflict || null;
        lookup.searchedBy = data?.searched_by || null;

        if (lookup.localProduct) {
            lookup.status = 'found_local';
            return;
        }

        if (lookup.conflict) {
            lookup.status = 'conflict';
            return;
        }

        if (lookup.globalProduct) {
            lookup.status = 'found_global';
            return;
        }

        lookup.status = 'not_found';
    } catch (error) {
        clearLookup();
        lookup.status = 'error';
    } finally {
        isLookingUpCatalog.value = false;
    }
};

const applyGlobalProduct = () => {
    if (!lookup.globalProduct) return;

    form.global_product_id = lookup.globalProduct.id;
    form.name = lookup.globalProduct.name || form.name;

    if (String(form.barcode || '').trim() === '' && lookup.globalProduct.barcode) {
        form.barcode = lookup.globalProduct.barcode;
    }

    if (String(form.sku || '').trim() === '' && lookup.globalProduct.sku) {
        form.sku = lookup.globalProduct.sku;
    }

    if (lookup.globalProduct.suggested_category?.id) {
        form.category_id = lookup.globalProduct.suggested_category.id;
    }

    lookup.status = 'applied';
};

const handleIdentifierKeydown = (event) => {
    if (event.key !== 'Enter') return;

    event.preventDefault();
    void lookupCatalog();
};

const submit = () => {
    form.shelf_life_days = toShelfLifeDays(shelfLifeDate.value);
    form.post(route('products.store'));
};
</script>

<template>
    <Head title="Nuevo producto" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold text-slate-100">Nuevo producto</h2>
                    <p class="mt-1 text-sm text-slate-300/80">Alta de producto para ventas y compras, con apoyo opcional del catalogo global.</p>
                </div>
                <Link :href="route('products.index')" class="text-sm font-semibold text-slate-300 hover:text-slate-100">Volver</Link>
            </div>
        </template>

        <form class="grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_21rem]" @submit.prevent="submit">
            <div class="grid gap-6">
                <section v-if="global_catalog.enabled" class="rounded-2xl border border-cyan-100/20 bg-slate-950/35 p-4">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-slate-100">Lookup en catalogo global</h3>
                            <p class="mt-1 text-xs text-slate-300/80">Busca por codigo de barras o SKU. El catalogo global ya no toma productos sin alguno de esos identificadores.</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="rounded-xl border border-cyan-100/25 px-3 py-2 text-sm font-semibold text-slate-200 hover:bg-slate-800/70 disabled:opacity-60"
                                :disabled="isLookingUpCatalog"
                                @click="lookupCatalog"
                            >
                                {{ isLookingUpCatalog ? 'Buscando...' : 'Buscar en catalogo' }}
                            </button>
                            <button
                                v-if="form.global_product_id"
                                type="button"
                                class="rounded-xl border border-rose-300/35 px-3 py-2 text-sm font-semibold text-rose-100 hover:bg-rose-400/15"
                                @click="clearGlobalSelection"
                            >
                                Quitar vinculo global
                            </button>
                        </div>
                    </div>

                    <div v-if="lookup.status === 'found_local'" class="mt-4 rounded-xl border border-amber-200/35 bg-amber-300/10 p-4 text-sm text-amber-100">
                        <p class="font-semibold">Ese producto ya existe en este comercio.</p>
                        <p class="mt-1">Nombre: {{ lookup.localProduct.name }}</p>
                        <p class="mt-1">SKU: {{ lookup.localProduct.sku || 'Sin SKU' }}</p>
                        <p class="mt-1">Categoria: {{ lookup.localProduct.category || 'Sin categoria' }}</p>
                        <Link :href="route('products.edit', lookup.localProduct.id)" class="mt-3 inline-flex rounded-lg border border-amber-100/35 px-3 py-2 text-xs font-semibold text-amber-50 hover:bg-amber-100/10">
                            Ver producto existente
                        </Link>
                    </div>

                    <div v-else-if="lookup.status === 'found_global'" class="mt-4 rounded-xl border border-emerald-200/35 bg-emerald-300/10 p-4 text-sm text-emerald-100">
                        <p class="font-semibold">Producto encontrado en el catalogo global. Puedes reutilizarlo para autocompletar la base local.</p>
                        <p class="mt-2">Nombre: {{ lookup.globalProduct.name }}</p>
                        <p class="mt-1">Barcode: {{ lookup.globalProduct.barcode || 'Sin barcode' }}</p>
                        <p class="mt-1">SKU: {{ lookup.globalProduct.sku || 'Sin SKU' }}</p>
                        <p class="mt-1">Categoria global: {{ lookup.globalProduct.category?.name || 'Sin categoria' }}</p>
                        <p class="mt-1">
                            Categoria sugerida para este comercio:
                            {{ lookup.globalProduct.suggested_category?.name || 'No se encontro coincidencia segura' }}
                        </p>
                        <button type="button" class="mt-3 rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-500" @click="applyGlobalProduct">
                            Usar producto del catalogo global
                        </button>
                    </div>

                    <div v-else-if="lookup.status === 'applied'" class="mt-4 rounded-xl border border-cyan-200/35 bg-cyan-300/10 p-4 text-sm text-cyan-100">
                        <p class="font-semibold">Producto global aplicado al formulario.</p>
                        <p class="mt-1">Se autocompletaron nombre y categoria cuando hubo coincidencia segura.</p>
                        <p class="mt-1">Los precios, costo, stock y vencimiento siguen siendo exclusivos de este comercio.</p>
                    </div>

                    <div v-else-if="lookup.status === 'conflict'" class="mt-4 rounded-xl border border-amber-200/35 bg-amber-300/10 p-4 text-sm text-amber-100">
                        {{ lookup.conflict }}
                    </div>

                    <div v-else-if="lookup.status === 'not_found'" class="mt-4 rounded-xl border border-slate-100/15 bg-slate-900/30 p-4 text-sm text-slate-300">
                        No se encontro una coincidencia por barcode o SKU en el catalogo global. Puedes crear el producto manualmente y seguir con el flujo actual.
                    </div>

                    <div v-else-if="lookup.status === 'error'" class="mt-4 rounded-xl border border-rose-200/35 bg-rose-300/10 p-4 text-sm text-rose-100">
                        No se pudo consultar el catalogo global en este momento.
                    </div>
                </section>

                <AppPanel title="Informacion basica" subtitle="Completa primero lo que el comerciante necesita reconocer rapido en venta, compra y reposicion.">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="app-field">
                            <label class="app-field-label">Nombre</label>
                            <input v-model="form.name" type="text" class="w-full rounded-xl text-sm" placeholder="Nombre del producto" />
                            <p class="app-field-help">Usa un nombre corto y facil de encontrar en mostrador.</p>
                            <p v-if="form.errors.name" class="text-xs text-rose-300">{{ form.errors.name }}</p>
                        </div>
                        <div class="app-field">
                            <label class="app-field-label">Slug</label>
                            <input v-model="form.slug" type="text" class="w-full rounded-xl text-sm" placeholder="Opcional" />
                            <p class="app-field-help">Si lo dejas vacio, se genera automaticamente.</p>
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
                            <label class="app-field-label">Proveedor principal</label>
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
                            <p v-if="form.errors.unit_type" class="text-xs text-rose-300">{{ form.errors.unit_type }}</p>
                        </div>
                        <div v-if="isWeightProduct" class="app-field">
                            <label class="app-field-label">Gestion por peso</label>
                            <select v-model="form.weight_unit" class="w-full rounded-xl text-sm">
                                <option value="kg">Kilos</option>
                                <option value="g">Gramos</option>
                            </select>
                            <p v-if="form.errors.weight_unit" class="text-xs text-rose-300">{{ form.errors.weight_unit }}</p>
                        </div>
                        <div class="app-field md:col-span-2">
                            <label class="app-field-label">Descripcion</label>
                            <textarea v-model="form.description" rows="3" class="w-full rounded-xl text-sm" placeholder="Descripcion opcional" />
                            <p v-if="form.errors.description" class="text-xs text-rose-300">{{ form.errors.description }}</p>
                        </div>
                    </div>
                </AppPanel>

                <AppPanel title="Precios e identificacion" subtitle="Con estos datos el producto queda listo para vender, medir margen y encontrarlo rapido con lector o busqueda.">
                    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_18rem]">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="app-field">
                                <label class="app-field-label">Codigo de barras</label>
                                <input v-model="form.barcode" type="text" class="w-full rounded-xl text-sm" placeholder="Opcional" @keydown="handleIdentifierKeydown" />
                                <p v-if="form.errors.barcode" class="text-xs text-rose-300">{{ form.errors.barcode }}</p>
                            </div>
                            <div class="app-field">
                                <label class="app-field-label">SKU</label>
                                <input v-model="form.sku" type="text" class="w-full rounded-xl text-sm" placeholder="Opcional" @keydown="handleIdentifierKeydown" />
                                <p v-if="form.errors.sku" class="text-xs text-rose-300">{{ form.errors.sku }}</p>
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

                <AppPanel title="Stock, lotes y vencimientos" subtitle="Define con cuanto arranca el producto y desde cuando debe generar alertas operativas.">
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
                            <label class="app-field-label">Lote inicial</label>
                            <input v-model="form.batch_code" type="text" class="w-full rounded-xl text-sm" placeholder="Opcional, se genera automatico" />
                            <p v-if="form.errors.batch_code" class="text-xs text-rose-300">{{ form.errors.batch_code }}</p>
                        </div>
                        <div class="app-field">
                            <label class="app-field-label">Vencimiento del lote inicial</label>
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
                <AppPanel title="Configuracion final" subtitle="Marca si el producto queda disponible desde ahora para la operacion diaria.">
                    <label class="inline-flex items-center gap-3 rounded-xl border border-cyan-100/15 bg-slate-950/35 px-4 py-3 text-sm text-slate-300">
                        <input v-model="form.is_active" type="checkbox" class="rounded border-cyan-100/25">
                        <span>
                            <span class="block font-semibold text-slate-100">Producto activo</span>
                            <span class="mt-1 block text-xs text-slate-400">Si lo desactivas, queda en catalogo pero fuera del flujo operativo.</span>
                        </span>
                    </label>
                </AppPanel>

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end xl:hidden">
                    <Link :href="route('products.index')" class="inline-flex items-center justify-center rounded-xl border border-cyan-100/20 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/60">
                        Volver al listado
                    </Link>
                    <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500" :disabled="form.processing">
                        Guardar producto
                    </button>
                </div>
            </div>

            <div class="app-sticky-column grid gap-6">
                <AppPanel title="Resumen operativo" :tone="summaryTone">
                    <div class="app-chip-row">
                        <StatusBadge :tone="globalCatalogApplied ? 'info' : 'neutral'" :label="globalCatalogApplied ? 'Catalogo aplicado' : 'Carga manual'" />
                        <StatusBadge :tone="salePrice > 0 ? 'success' : 'warning'" :label="salePrice > 0 ? 'Con precio' : 'Sin precio'" />
                        <StatusBadge :tone="costPrice > 0 ? 'success' : 'warning'" :label="costPrice > 0 ? 'Con costo' : 'Sin costo'" />
                        <StatusBadge :tone="stockStatusTone" :label="stockValue > 0 ? `Stock ${stockValue}` : 'Sin stock'" />
                        <StatusBadge :tone="form.is_active ? 'success' : 'neutral'" :label="form.is_active ? 'Activo' : 'Inactivo'" />
                    </div>

                    <div class="mt-4 grid gap-3">
                        <div class="app-subsection">
                            <p class="text-xs uppercase tracking-[0.18em] text-cyan-100/70">Estado de carga</p>
                            <p class="mt-2 text-xl font-bold text-slate-100">{{ completionLabel }}</p>
                            <p class="mt-1 text-xs text-slate-400">Nombre, categoria, precio, costo e identificacion.</p>
                        </div>
                        <div class="app-subsection">
                            <p class="text-xs uppercase tracking-[0.18em] text-cyan-100/70">Margen estimado</p>
                            <p class="mt-2 text-xl font-bold text-slate-100">${{ marginAmount.toFixed(2) }}</p>
                            <p class="mt-1 text-xs text-slate-400">
                                <span v-if="marginPercent !== null">{{ marginPercent }}% sobre costo</span>
                                <span v-else>Sin referencia hasta cargar costo.</span>
                            </p>
                        </div>
                    </div>

                    <div v-if="productWarnings.length" class="mt-4 rounded-xl border border-amber-300/30 bg-amber-400/10 p-4 text-sm text-amber-100">
                        <p class="font-semibold">Revisar antes de guardar</p>
                        <ul class="mt-2 space-y-2">
                            <li v-for="warning in productWarnings" :key="warning">{{ warning }}</li>
                        </ul>
                    </div>
                    <div v-else class="mt-4 rounded-xl border border-emerald-300/30 bg-emerald-400/10 p-4 text-sm text-emerald-100">
                        El producto queda listo para una operacion basica de venta y compra.
                    </div>

                    <template #footer>
                        <div class="grid gap-3">
                            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500" :disabled="form.processing">
                                Guardar producto
                            </button>
                            <Link :href="route('products.index')" class="inline-flex items-center justify-center rounded-xl border border-cyan-100/20 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/60">
                                Volver al listado
                            </Link>
                        </div>
                    </template>
                </AppPanel>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
