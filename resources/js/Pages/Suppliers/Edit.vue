<script setup>
import AppPanel from '@/Components/AppPanel.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    supplier: { type: Object, required: true },
});

const form = useForm({
    name: props.supplier.name,
    contact_name: props.supplier.contact_name || '',
    phone: props.supplier.phone || '',
    email: props.supplier.email || '',
    address: props.supplier.address || '',
    notes: props.supplier.notes || '',
});

const supplierWarnings = computed(() => {
    const warnings = [];

    if (String(form.name || '').trim() === '') warnings.push('Falta el nombre del proveedor.');
    if (!form.phone && !form.email) warnings.push('Conviene tener telefono o email cargados para compras.');

    return warnings;
});

const submit = () => {
    form.put(route('suppliers.update', props.supplier.id));
};
</script>

<template>
    <Head title="Editar proveedor" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold text-slate-100">Editar proveedor</h2>
                    <p class="mt-1 text-sm text-slate-300/80">Actualiza datos del proveedor.</p>
                </div>
                <Link :href="route('suppliers.index')" class="text-sm font-semibold text-slate-300 hover:text-slate-100">Volver</Link>
            </div>
        </template>

        <form class="grid gap-6 xl:grid-cols-[minmax(0,1.4fr)_20rem]" @submit.prevent="submit">
            <AppPanel title="Ficha del proveedor" subtitle="Mantiene actualizados los datos que usa compras para operar rapido.">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="app-field">
                        <label class="app-field-label">Nombre</label>
                        <input v-model="form.name" type="text" class="w-full rounded-xl text-sm" placeholder="Nombre del proveedor" />
                    </div>
                    <div class="app-field">
                        <label class="app-field-label">Contacto</label>
                        <input v-model="form.contact_name" type="text" class="w-full rounded-xl text-sm" placeholder="Persona de contacto" />
                    </div>
                    <div class="app-field">
                        <label class="app-field-label">Telefono</label>
                        <input v-model="form.phone" type="text" class="w-full rounded-xl text-sm" placeholder="Opcional" />
                    </div>
                    <div class="app-field">
                        <label class="app-field-label">Email</label>
                        <input v-model="form.email" type="email" class="w-full rounded-xl text-sm" placeholder="Opcional" />
                    </div>
                    <div class="app-field md:col-span-2">
                        <label class="app-field-label">Direccion</label>
                        <input v-model="form.address" type="text" class="w-full rounded-xl text-sm" placeholder="Direccion o ubicacion" />
                    </div>
                    <div class="app-field md:col-span-2">
                        <label class="app-field-label">Notas</label>
                        <textarea v-model="form.notes" rows="3" class="w-full rounded-xl text-sm" placeholder="Observaciones opcionales" />
                    </div>
                </div>
            </AppPanel>

            <aside class="app-sticky-column">
                <AppPanel title="Resumen" :tone="supplierWarnings.length ? 'warning' : 'success'" subtitle="Chequeo corto antes de guardar.">
                    <div class="app-chip-row">
                        <StatusBadge :tone="form.phone ? 'success' : 'neutral'" :label="form.phone ? 'Con telefono' : 'Sin telefono'" />
                        <StatusBadge :tone="form.email ? 'info' : 'neutral'" :label="form.email ? 'Con email' : 'Sin email'" />
                    </div>

                    <div class="mt-4 space-y-3 text-sm text-slate-300">
                        <div class="flex items-center justify-between gap-3">
                            <span>Proveedor</span>
                            <span class="text-right font-semibold text-slate-100">{{ form.name || 'Pendiente' }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span>Contacto</span>
                            <span class="text-right font-semibold text-slate-100">{{ form.contact_name || '-' }}</span>
                        </div>
                    </div>

                    <div v-if="supplierWarnings.length" class="mt-4 rounded-xl border border-amber-300/25 bg-amber-400/10 p-3 text-sm text-amber-100">
                        <p v-for="warning in supplierWarnings" :key="warning">{{ warning }}</p>
                    </div>

                    <template #footer>
                        <div class="grid gap-3">
                            <button type="submit" class="w-full rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-600" :disabled="form.processing">
                                Guardar cambios
                            </button>
                            <Link :href="route('suppliers.index')" class="inline-flex w-full items-center justify-center rounded-xl border border-cyan-100/25 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/70">
                                Volver
                            </Link>
                        </div>
                    </template>
                </AppPanel>
            </aside>
        </form>
    </AuthenticatedLayout>
</template>
