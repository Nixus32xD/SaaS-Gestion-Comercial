<script setup>
import AppPanel from '@/Components/AppPanel.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    product: { type: Object, required: true },
    inventory: { type: Object, required: true },
});

const reasons = [
    { value: 'physical_count', label: 'Conteo físico' },
    { value: 'breakage', label: 'Rotura' },
    { value: 'expiration', label: 'Vencimiento' },
    { value: 'shrinkage', label: 'Merma' },
    { value: 'data_entry_error', label: 'Error de carga' },
    { value: 'found_stock', label: 'Mercadería encontrada' },
    { value: 'administrative_correction', label: 'Corrección administrativa' },
    { value: 'other', label: 'Otro' },
];

const form = useForm({
    direction: 'increase',
    quantity: 1,
    reason: 'physical_count',
    notes: '',
    batch_code: '',
    expires_at: '',
    unit_cost: '',
    expected_branch_id: props.inventory.branch_id,
});

const isIncrease = computed(() => form.direction === 'increase');
const quantityStep = computed(() => (props.product.unit_type === 'weight' && props.product.weight_unit === 'kg' ? '0.001' : '1'));
const quantityMin = computed(() => quantityStep.value);
const delta = computed(() => (isIncrease.value ? 1 : -1) * Number(form.quantity || 0));
const estimatedStock = computed(() => Number((Number(props.inventory.stock) + delta.value).toFixed(3)));
const estimatedAvailable = computed(() => Number((estimatedStock.value - Number(props.inventory.reserved_stock)).toFixed(3)));
const wouldViolateReservation = computed(() => estimatedStock.value < Number(props.inventory.reserved_stock));

const submit = () => {
    form.transform((data) => ({
        ...data,
        delta: delta.value,
        expected_branch_id: props.inventory.branch_id,
    })).post(route('products.inventory-adjustments.store', props.product.id));
};
</script>

<template>
    <Head title="Ajustar inventario" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold text-slate-100">Ajustar inventario</h2>
                    <p class="mt-1 text-sm text-slate-300/80">El ajuste se aplicará solo en la sucursal activa.</p>
                </div>
                <Link :href="route('products.edit', product.id)" class="text-sm font-semibold text-slate-300 hover:text-slate-100">Volver al producto</Link>
            </div>
        </template>

        <form class="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_22rem]" @submit.prevent="submit">
            <div class="grid gap-6">
                <AppPanel title="Contexto del ajuste" :subtitle="`Sucursal actual: ${inventory.branch_name}`">
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 text-sm text-slate-300">
                        <div class="app-subsection"><p class="text-xs uppercase tracking-[0.18em] text-cyan-100/70">Producto</p><p class="mt-2 font-bold text-slate-100">{{ product.name }}</p></div>
                        <div class="app-subsection"><p class="text-xs uppercase tracking-[0.18em] text-cyan-100/70">Stock físico</p><p class="mt-2 text-xl font-bold text-slate-100">{{ inventory.stock }} {{ product.quantity_label }}</p></div>
                        <div class="app-subsection"><p class="text-xs uppercase tracking-[0.18em] text-cyan-100/70">Reservado</p><p class="mt-2 text-xl font-bold text-slate-100">{{ inventory.reserved_stock }}</p></div>
                        <div class="app-subsection"><p class="text-xs uppercase tracking-[0.18em] text-cyan-100/70">Disponible</p><p class="mt-2 text-xl font-bold text-slate-100">{{ inventory.available_stock }}</p></div>
                    </div>
                </AppPanel>

                <AppPanel title="Detalle" subtitle="La cantidad es un delta; el servidor recalcula el resultado con el inventario bloqueado.">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="app-field">
                            <label class="app-field-label">Tipo</label>
                            <select v-model="form.direction" class="w-full rounded-xl text-sm">
                                <option value="increase">Aumentar</option>
                                <option value="decrease">Disminuir</option>
                            </select>
                        </div>
                        <div class="app-field">
                            <label class="app-field-label">Cantidad ({{ product.quantity_label }})</label>
                            <input v-model.number="form.quantity" type="number" :min="quantityMin" :step="quantityStep" class="w-full rounded-xl text-sm" />
                            <p v-if="form.errors.delta" class="text-xs text-rose-300">{{ form.errors.delta }}</p>
                        </div>
                        <div class="app-field">
                            <label class="app-field-label">Motivo</label>
                            <select v-model="form.reason" class="w-full rounded-xl text-sm">
                                <option v-for="reason in reasons" :key="reason.value" :value="reason.value">{{ reason.label }}</option>
                            </select>
                            <p v-if="form.errors.reason" class="text-xs text-rose-300">{{ form.errors.reason }}</p>
                        </div>
                        <div class="app-field">
                            <label class="app-field-label">Notas</label>
                            <input v-model="form.notes" type="text" class="w-full rounded-xl text-sm" :placeholder="form.reason === 'other' ? 'Explicación obligatoria' : 'Opcional'" />
                            <p v-if="form.errors.notes" class="text-xs text-rose-300">{{ form.errors.notes }}</p>
                        </div>
                    </div>

                    <div v-if="isIncrease" class="mt-5 grid gap-4 border-t border-cyan-100/15 pt-5 md:grid-cols-3">
                        <div class="app-field"><label class="app-field-label">Lote</label><input v-model="form.batch_code" type="text" class="w-full rounded-xl text-sm" placeholder="Opcional, se genera automático" /></div>
                        <div class="app-field"><label class="app-field-label">Vencimiento</label><input v-model="form.expires_at" type="date" class="w-full rounded-xl text-sm" /></div>
                        <div class="app-field"><label class="app-field-label">Costo unitario</label><input v-model="form.unit_cost" type="number" min="0" step="0.01" class="w-full rounded-xl text-sm" placeholder="Opcional" /></div>
                    </div>
                    <p v-else class="mt-5 rounded-xl border border-cyan-100/15 bg-slate-950/35 p-4 text-sm text-slate-300">La disminución consume lotes de esta sucursal por FEFO y luego, si existe, inventario histórico sin lote.</p>
                </AppPanel>
            </div>

            <AppPanel title="Resultado estimado" subtitle="Vista previa; el valor final se determina al confirmar.">
                <div class="grid gap-3 text-sm text-slate-300">
                    <div class="app-subsection"><p class="text-xs uppercase tracking-[0.18em] text-cyan-100/70">Delta</p><p class="mt-2 text-xl font-bold text-slate-100">{{ delta > 0 ? '+' : '' }}{{ delta }}</p></div>
                    <div class="app-subsection"><p class="text-xs uppercase tracking-[0.18em] text-cyan-100/70">Stock final</p><p class="mt-2 text-xl font-bold text-slate-100">{{ estimatedStock }}</p></div>
                    <div class="app-subsection"><p class="text-xs uppercase tracking-[0.18em] text-cyan-100/70">Disponible final</p><p class="mt-2 text-xl font-bold text-slate-100">{{ estimatedAvailable }}</p></div>
                    <p v-if="wouldViolateReservation" class="rounded-xl border border-rose-300/35 bg-rose-400/10 p-3 text-xs text-rose-100">No se puede dejar stock por debajo de lo reservado en esta sucursal.</p>
                    <p v-if="form.errors.expected_branch_id" class="rounded-xl border border-rose-300/35 bg-rose-400/10 p-3 text-xs text-rose-100">{{ form.errors.expected_branch_id }}</p>
                </div>
                <template #footer>
                    <div class="grid gap-3">
                        <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50" :disabled="form.processing || wouldViolateReservation">Confirmar ajuste</button>
                        <Link :href="route('products.edit', product.id)" class="rounded-xl border border-cyan-100/20 px-4 py-2 text-center text-sm font-semibold text-slate-300 hover:bg-slate-800/60">Cancelar</Link>
                    </div>
                </template>
            </AppPanel>
        </form>
    </AuthenticatedLayout>
</template>
