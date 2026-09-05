<script setup>
import AppPanel from '@/Components/AppPanel.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const props = defineProps({
    source_branch: { type: Object, required: true },
    destination_branches: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
    search: { type: String, default: '' },
    idempotency_key: { type: String, required: true },
    transfers: { type: Object, required: true },
});

const searchForm = reactive({ search: props.search });
const form = useForm({
    to_branch_id: '',
    product_id: '',
    quantity: '',
    notes: '',
    idempotency_key: props.idempotency_key,
    expected_from_branch_id: props.source_branch.id,
});

const selectedProduct = computed(() => props.products.find(
    (product) => Number(product.id) === Number(form.product_id),
));
const selectedDestination = computed(() => props.destination_branches.find(
    (branch) => Number(branch.id) === Number(form.to_branch_id),
));
const quantityStep = computed(() => selectedProduct.value?.quantity_step || '1');
const quantityLabel = computed(() => selectedProduct.value?.quantity_label || 'un');
const availableStock = computed(() => Number(selectedProduct.value?.available_stock || 0));
const exceedsAvailableStock = computed(() => Number(form.quantity || 0) > availableStock.value);
const canSubmit = computed(() => (
    form.to_branch_id !== ''
    && form.product_id !== ''
    && Number(form.quantity) > 0
    && !exceedsAvailableStock.value
    && !form.processing
));

const searchProducts = () => {
    router.get(route('inventory.transfers.index'), {
        search: searchForm.search.trim() || undefined,
    }, {
        preserveState: true,
        replace: true,
        only: ['products', 'search', 'transfers'],
    });
};

const submit = () => {
    form.post(route('inventory.transfers.store'), {
        preserveScroll: true,
    });
};

const formatDate = (value) => (value ? new Intl.DateTimeFormat('es-AR', {
    dateStyle: 'short',
    timeStyle: 'short',
}).format(new Date(value)) : '');
</script>

<template>
    <Head title="Transferencias de inventario" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-100/70">Inventario</p>
                <h2 class="mt-1 text-2xl font-bold text-slate-100">Transferencias entre sucursales</h2>
                <p class="mt-1 text-sm text-slate-300/80">Mueve stock y lotes en una única operación trazable.</p>
            </div>
        </template>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_25rem]">
            <form class="grid gap-6" @submit.prevent="submit">
                <AppPanel title="Ruta de transferencia" subtitle="La sucursal de origen es la sucursal activa de esta sesión.">
                    <div class="grid gap-4 md:grid-cols-[1fr_auto_1fr] md:items-end">
                        <div class="app-field">
                            <label class="app-field-label">Origen</label>
                            <div class="rounded-xl border border-cyan-300/35 bg-cyan-400/10 px-4 py-3 text-sm font-semibold text-cyan-50">
                                {{ source_branch.name }}
                            </div>
                        </div>
                        <div class="hidden pb-3 text-center text-2xl text-cyan-200 md:block">→</div>
                        <div class="app-field">
                            <label class="app-field-label">Destino</label>
                            <select v-model="form.to_branch_id" class="w-full rounded-xl text-sm">
                                <option value="">Seleccionar sucursal</option>
                                <option v-for="branch in destination_branches" :key="branch.id" :value="branch.id">{{ branch.name }}</option>
                            </select>
                            <p v-if="form.errors.to_branch_id" class="mt-1 text-xs text-rose-300">{{ form.errors.to_branch_id }}</p>
                        </div>
                    </div>
                    <p v-if="destination_branches.length === 0" class="mt-4 rounded-xl border border-amber-300/30 bg-amber-400/10 p-3 text-sm text-amber-100">Necesitás otra sucursal activa para realizar transferencias.</p>
                </AppPanel>

                <AppPanel title="Producto y cantidad" subtitle="El servidor vuelve a validar disponibilidad, reservas y lotes al confirmar.">
                    <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_12rem]">
                        <div class="app-field">
                            <label class="app-field-label">Buscar producto</label>
                            <div class="flex gap-2">
                                <input v-model="searchForm.search" type="search" class="min-w-0 flex-1 rounded-xl text-sm" placeholder="Nombre, código o SKU" @keyup.enter.prevent="searchProducts" />
                                <button type="button" class="rounded-xl border border-cyan-100/25 px-3 text-sm font-semibold text-slate-200 hover:bg-slate-800/60" @click="searchProducts">Buscar</button>
                            </div>
                            <select v-model="form.product_id" class="mt-3 w-full rounded-xl text-sm">
                                <option value="">Seleccionar producto</option>
                                <option v-for="product in products" :key="product.id" :value="product.id">
                                    {{ product.name }} — disponible {{ product.available_stock }} {{ product.quantity_label }}
                                </option>
                            </select>
                            <p v-if="products.length === 0" class="mt-2 text-xs text-slate-400">No hay productos que coincidan con la búsqueda.</p>
                            <p v-if="form.errors.product_id" class="mt-1 text-xs text-rose-300">{{ form.errors.product_id }}</p>
                        </div>
                        <div class="app-field">
                            <label class="app-field-label">Cantidad ({{ quantityLabel }})</label>
                            <input v-model.number="form.quantity" type="number" min="0" :step="quantityStep" class="w-full rounded-xl text-sm" />
                            <p v-if="form.errors.quantity" class="mt-1 text-xs text-rose-300">{{ form.errors.quantity }}</p>
                        </div>
                    </div>
                    <div v-if="selectedProduct" class="mt-4 grid gap-3 sm:grid-cols-3">
                        <div class="app-subsection"><p class="text-xs uppercase tracking-[0.15em] text-cyan-100/70">Disponible</p><p class="mt-1 text-lg font-bold text-slate-100">{{ availableStock }} {{ quantityLabel }}</p></div>
                        <div class="app-subsection"><p class="text-xs uppercase tracking-[0.15em] text-cyan-100/70">Reservado</p><p class="mt-1 text-lg font-bold text-slate-100">{{ selectedProduct.reserved_stock }} {{ quantityLabel }}</p></div>
                        <div class="app-subsection"><p class="text-xs uppercase tracking-[0.15em] text-cyan-100/70">Lotes</p><p class="mt-1 text-sm text-slate-200">Salida FEFO cuando existan</p></div>
                    </div>
                    <p v-if="exceedsAvailableStock" class="mt-4 rounded-xl border border-rose-300/35 bg-rose-400/10 p-3 text-sm text-rose-100">La cantidad supera el stock disponible de origen; no se transferirá stock reservado.</p>
                </AppPanel>

                <AppPanel title="Observación" subtitle="Opcional; se registra en ambos movimientos de stock.">
                    <textarea v-model="form.notes" rows="3" maxlength="2000" class="w-full rounded-xl text-sm" placeholder="Ej. Reposición de mostrador" />
                    <p v-if="form.errors.notes" class="mt-1 text-xs text-rose-300">{{ form.errors.notes }}</p>
                </AppPanel>
            </form>

            <AppPanel title="Confirmación" subtitle="Revisá la ruta antes de confirmar." tone="accent">
                <div class="grid gap-3 text-sm text-slate-300">
                    <div class="app-subsection"><p class="text-xs uppercase tracking-[0.15em] text-cyan-100/70">Ruta</p><p class="mt-2 font-bold text-slate-100">{{ source_branch.name }} → {{ selectedDestination?.name || 'Destino pendiente' }}</p></div>
                    <div class="app-subsection"><p class="text-xs uppercase tracking-[0.15em] text-cyan-100/70">Producto</p><p class="mt-2 font-bold text-slate-100">{{ selectedProduct?.name || 'Producto pendiente' }}</p></div>
                    <div class="app-subsection"><p class="text-xs uppercase tracking-[0.15em] text-cyan-100/70">Cantidad</p><p class="mt-2 text-xl font-bold text-slate-100">{{ form.quantity || 0 }} {{ quantityLabel }}</p></div>
                    <p v-if="form.errors.expected_from_branch_id" class="rounded-xl border border-rose-300/35 bg-rose-400/10 p-3 text-xs text-rose-100">{{ form.errors.expected_from_branch_id }}</p>
                </div>
                <template #footer>
                    <button type="button" class="w-full rounded-xl bg-indigo-600 px-4 py-3 text-sm font-semibold text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50" :disabled="!canSubmit" @click="submit">Confirmar {{ source_branch.name }} → {{ selectedDestination?.name || 'destino' }}</button>
                </template>
            </AppPanel>
        </div>

        <AppPanel class="mt-6" title="Historial de transferencias" subtitle="Cada registro agrupa salida, entrada y los lotes afectados.">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-cyan-100/15 text-xs uppercase tracking-wide text-cyan-100/70"><tr><th class="px-3 py-3">Fecha</th><th class="px-3 py-3">Ruta</th><th class="px-3 py-3">Producto</th><th class="px-3 py-3">Cantidad</th><th class="px-3 py-3">Trazabilidad</th><th class="px-3 py-3">Usuario</th></tr></thead>
                    <tbody>
                        <tr v-for="transfer in transfers.data" :key="transfer.id" class="border-b border-cyan-100/10 text-slate-200">
                            <td class="px-3 py-3 whitespace-nowrap">{{ formatDate(transfer.created_at) }}</td>
                            <td class="px-3 py-3 font-semibold">{{ transfer.from_branch }} → {{ transfer.to_branch }}</td>
                            <td class="px-3 py-3">{{ transfer.product }}</td>
                            <td class="px-3 py-3">{{ transfer.quantity }}</td>
                            <td class="px-3 py-3"><p class="font-mono text-xs text-cyan-100">{{ transfer.reference }}</p><p class="mt-1 text-xs text-slate-400">{{ transfer.batch_allocations_count }} lote(s)</p></td>
                            <td class="px-3 py-3">{{ transfer.created_by }}</td>
                        </tr>
                        <tr v-if="transfers.data.length === 0"><td colspan="6" class="px-3 py-8 text-center text-slate-400">Todavía no hay transferencias registradas.</td></tr>
                    </tbody>
                </table>
            </div>
        </AppPanel>
    </AuthenticatedLayout>
</template>
