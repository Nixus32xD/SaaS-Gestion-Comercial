<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    suppliers: { type: Array, default: () => [] },
});

const form = useForm({
    supplier_id: '',
    name: '',
    slug: '',
    description: '',
    barcode: '',
    sku: '',
    unit_type: 'unit',
    sale_price: 0,
    cost_price: 0,
    stock: 0,
    min_stock: 0,
    shelf_life_days: '',
    expiry_alert_days: 15,
    is_active: true,
});

const shelfLifeDate = ref('');

const toShelfLifeDays = (dateValue) => {
    if (!dateValue) return '';

    const target = new Date(`${dateValue}T00:00:00`);
    if (Number.isNaN(target.getTime())) return '';

    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const diffDays = Math.ceil((target.getTime() - today.getTime()) / 86400000);
    return diffDays > 0 ? diffDays : 1;
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
                    <p class="mt-1 text-sm text-slate-300/80">Alta de producto para ventas y compras.</p>
                </div>
                <Link :href="route('products.index')" class="text-sm font-semibold text-slate-300 hover:text-slate-100">Volver</Link>
            </div>
        </template>

        <form class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 backdrop-blur p-5 shadow-sm" @submit.prevent="submit">
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
                <div class="space-y-1">
                    <label class="text-sm font-medium text-slate-300">Codigo de barras</label>
                    <input v-model="form.barcode" type="text" class="w-full rounded-xl border-cyan-100/25 text-sm" placeholder="Opcional" />
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-medium text-slate-300">SKU</label>
                    <input v-model="form.sku" type="text" class="w-full rounded-xl border-cyan-100/25 text-sm" placeholder="Opcional" />
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-medium text-slate-300">Precio de venta</label>
                    <input v-model.number="form.sale_price" type="number" min="0" step="0.01" class="w-full rounded-xl border-cyan-100/25 text-sm" />
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-medium text-slate-300">Precio de costo</label>
                    <input v-model.number="form.cost_price" type="number" min="0" step="0.01" class="w-full rounded-xl border-cyan-100/25 text-sm" />
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-medium text-slate-300">Stock inicial</label>
                    <input v-model.number="form.stock" type="number" min="0" step="0.001" class="w-full rounded-xl border-cyan-100/25 text-sm" />
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-medium text-slate-300">Stock minimo</label>
                    <input v-model.number="form.min_stock" type="number" min="0" step="0.001" class="w-full rounded-xl border-cyan-100/25 text-sm" />
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
                    Guardar producto
                </button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>

