<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    product: { type: Object, required: true },
    suppliers: { type: Array, default: () => [] },
});

const form = useForm({
    supplier_id: props.product.supplier_id || '',
    name: props.product.name,
    slug: props.product.slug || '',
    description: props.product.description || '',
    barcode: props.product.barcode || '',
    sku: props.product.sku || '',
    unit_type: props.product.unit_type,
    sale_price: Number(props.product.sale_price),
    cost_price: Number(props.product.cost_price),
    stock: Number(props.product.stock),
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
            <div class="grid gap-3 md:grid-cols-2">
                <input v-model="form.name" type="text" class="rounded-xl border-cyan-100/25 text-sm" placeholder="Nombre" />
                <input v-model="form.slug" type="text" class="rounded-xl border-cyan-100/25 text-sm" placeholder="Slug" />
                <select v-model="form.supplier_id" class="rounded-xl border-cyan-100/25 text-sm">
                    <option value="">Sin proveedor</option>
                    <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">{{ supplier.name }}</option>
                </select>
                <select v-model="form.unit_type" class="rounded-xl border-cyan-100/25 text-sm">
                    <option value="unit">Unidad</option>
                    <option value="weight">Peso</option>
                </select>
                <input v-model="form.barcode" type="text" class="rounded-xl border-cyan-100/25 text-sm" placeholder="Codigo de barras" />
                <input v-model="form.sku" type="text" class="rounded-xl border-cyan-100/25 text-sm" placeholder="SKU" />
                <input v-model.number="form.sale_price" type="number" min="0" step="0.01" class="rounded-xl border-cyan-100/25 text-sm" placeholder="Precio venta" />
                <input v-model.number="form.cost_price" type="number" min="0" step="0.01" class="rounded-xl border-cyan-100/25 text-sm" placeholder="Precio costo" />
                <input v-model.number="form.stock" type="number" min="0" step="0.001" class="rounded-xl border-cyan-100/25 text-sm" placeholder="Stock" />
                <input v-model.number="form.min_stock" type="number" min="0" step="0.001" class="rounded-xl border-cyan-100/25 text-sm" placeholder="Stock minimo" />
                <input v-model="shelfLifeDate" type="date" class="rounded-xl border-cyan-100/25 text-sm" />
                <input v-model.number="form.expiry_alert_days" type="number" min="1" step="1" class="rounded-xl border-cyan-100/25 text-sm" placeholder="Alerta de vencimiento (dias)" />
                <textarea v-model="form.description" rows="3" class="rounded-xl border-cyan-100/25 text-sm md:col-span-2" placeholder="Descripcion" />
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
    </AuthenticatedLayout>
</template>

