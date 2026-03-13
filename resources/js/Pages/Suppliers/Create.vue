<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    contact_name: '',
    phone: '',
    email: '',
    address: '',
    notes: '',
});

const submit = () => {
    form.post(route('suppliers.store'));
};
</script>

<template>
    <Head title="Nuevo proveedor" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold text-slate-100">Nuevo proveedor</h2>
                    <p class="mt-1 text-sm text-slate-300/80">Carga proveedores para compras.</p>
                </div>
                <Link :href="route('suppliers.index')" class="text-sm font-semibold text-slate-300 hover:text-slate-100">Volver</Link>
            </div>
        </template>

        <form class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 backdrop-blur p-5 shadow-sm" @submit.prevent="submit">
            <div class="grid gap-3 md:grid-cols-2">
                <div class="space-y-1">
                    <label class="text-sm font-medium text-slate-300">Nombre</label>
                    <input v-model="form.name" type="text" class="w-full rounded-xl border-cyan-100/25 text-sm" placeholder="Nombre del proveedor" />
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-medium text-slate-300">Contacto</label>
                    <input v-model="form.contact_name" type="text" class="w-full rounded-xl border-cyan-100/25 text-sm" placeholder="Persona de contacto" />
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-medium text-slate-300">Telefono</label>
                    <input v-model="form.phone" type="text" class="w-full rounded-xl border-cyan-100/25 text-sm" placeholder="Opcional" />
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-medium text-slate-300">Email</label>
                    <input v-model="form.email" type="email" class="w-full rounded-xl border-cyan-100/25 text-sm" placeholder="Opcional" />
                </div>
                <div class="space-y-1 md:col-span-2">
                    <label class="text-sm font-medium text-slate-300">Direccion</label>
                    <input v-model="form.address" type="text" class="w-full rounded-xl border-cyan-100/25 text-sm" placeholder="Direccion o ubicacion" />
                </div>
                <div class="space-y-1 md:col-span-2">
                    <label class="text-sm font-medium text-slate-300">Notas</label>
                    <textarea v-model="form.notes" rows="3" class="w-full rounded-xl border-cyan-100/25 text-sm" placeholder="Observaciones opcionales" />
                </div>
            </div>

            <div class="mt-5 flex justify-end">
                <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-600" :disabled="form.processing">
                    Guardar proveedor
                </button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>

