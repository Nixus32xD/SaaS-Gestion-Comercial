<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
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
                    <h2 class="text-2xl font-bold text-slate-900">Editar proveedor</h2>
                    <p class="mt-1 text-sm text-slate-500">Actualiza datos del proveedor.</p>
                </div>
                <Link :href="route('suppliers.index')" class="text-sm font-semibold text-slate-600 hover:text-slate-900">Volver</Link>
            </div>
        </template>

        <form class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" @submit.prevent="submit">
            <div class="grid gap-3 md:grid-cols-2">
                <input v-model="form.name" type="text" class="rounded-xl border-slate-300 text-sm" placeholder="Nombre" />
                <input v-model="form.contact_name" type="text" class="rounded-xl border-slate-300 text-sm" placeholder="Contacto" />
                <input v-model="form.phone" type="text" class="rounded-xl border-slate-300 text-sm" placeholder="Telefono" />
                <input v-model="form.email" type="email" class="rounded-xl border-slate-300 text-sm" placeholder="Email" />
                <input v-model="form.address" type="text" class="rounded-xl border-slate-300 text-sm md:col-span-2" placeholder="Direccion" />
                <textarea v-model="form.notes" rows="3" class="rounded-xl border-slate-300 text-sm md:col-span-2" placeholder="Notas" />
            </div>

            <div class="mt-5 flex justify-end">
                <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-600" :disabled="form.processing">
                    Guardar cambios
                </button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
