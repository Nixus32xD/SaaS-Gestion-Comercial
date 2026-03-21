<script setup>
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
    const name = String(form.name || '').trim();

    if (barcode === '' && name === '') {
        clearLookup();
        return;
    }

    isLookingUpCatalog.value = true;

    try {
        const { data } = await window.axios.get(route('products.catalog.lookup'), {
            params: { barcode, name },
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

    if (lookup.globalProduct.suggested_category?.id) {
        form.category_id = lookup.globalProduct.suggested_category.id;
    }

    lookup.status = 'applied';
};

const handleBarcodeKeydown = (event) => {
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

        <form class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 backdrop-blur p-5 shadow-sm" @submit.prevent="submit">
            <div class="grid gap-4">
                <section v-if="global_catalog.enabled" class="rounded-2xl border border-cyan-100/20 bg-slate-950/35 p-4">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-slate-100">Lookup en catalogo global</h3>
                            <p class="mt-1 text-xs text-slate-300/80">Busca primero por codigo de barras. Si no tienes barcode, tambien puedes buscar por nombre.</p>
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
                        <p class="mt-1">Categoria: {{ lookup.localProduct.category || 'Sin categoria' }}</p>
                        <Link :href="route('products.edit', lookup.localProduct.id)" class="mt-3 inline-flex rounded-lg border border-amber-100/35 px-3 py-2 text-xs font-semibold text-amber-50 hover:bg-amber-100/10">
                            Ver producto existente
                        </Link>
                    </div>

                    <div v-else-if="lookup.status === 'found_global'" class="mt-4 rounded-xl border border-emerald-200/35 bg-emerald-300/10 p-4 text-sm text-emerald-100">
                        <p class="font-semibold">Producto encontrado en el catalogo global. Puedes reutilizarlo para autocompletar la base local.</p>
                        <p class="mt-2">Nombre: {{ lookup.globalProduct.name }}</p>
                        <p class="mt-1">Barcode: {{ lookup.globalProduct.barcode || 'Sin barcode' }}</p>
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
                        No se encontro una coincidencia global segura. Puedes crear el producto manualmente y seguir con el flujo actual.
                    </div>

                    <div v-else-if="lookup.status === 'error'" class="mt-4 rounded-xl border border-rose-200/35 bg-rose-300/10 p-4 text-sm text-rose-100">
                        No se pudo consultar el catalogo global en este momento.
                    </div>
                </section>

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
                        <input v-model="form.barcode" type="text" class="w-full rounded-xl border-cyan-100/25 text-sm" placeholder="Opcional" @keydown="handleBarcodeKeydown" />
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
            </div>

            <label class="mt-4 inline-flex items-center gap-2 text-sm text-slate-300">
                <input v-model="form.is_active" type="checkbox" class="rounded border-cyan-100/25">
                Producto activo
            </label>

            <div class="mt-5 flex justify-end">
                <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-600" :disabled="form.processing">
                    Guardar producto
                </button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>

