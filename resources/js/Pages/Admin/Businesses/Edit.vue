<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    business: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    name: props.business.name,
    slug: props.business.slug || '',
    owner_name: props.business.owner_name || '',
    email: props.business.email || '',
    phone: props.business.phone || '',
    address: props.business.address || '',
    is_active: Boolean(props.business.is_active),
});

const submit = () => {
    form.put(route('admin.businesses.update', props.business.id));
};
</script>

<template>
    <Head title="Editar comercio" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold text-slate-900">Editar comercio</h2>
                    <p class="mt-1 text-sm text-slate-500">Actualiza datos generales y estado.</p>
                </div>
                <Link :href="route('admin.businesses.index')" class="text-sm font-semibold text-slate-600 hover:text-slate-900">
                    Volver
                </Link>
            </div>
        </template>

        <form class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" @submit.prevent="submit">
            <div class="grid gap-3 md:grid-cols-2">
                <input v-model="form.name" type="text" class="rounded-xl border-slate-300 text-sm" placeholder="Nombre" />
                <input v-model="form.slug" type="text" class="rounded-xl border-slate-300 text-sm" placeholder="Slug" />
                <input v-model="form.owner_name" type="text" class="rounded-xl border-slate-300 text-sm" placeholder="Responsable" />
                <input v-model="form.email" type="email" class="rounded-xl border-slate-300 text-sm" placeholder="Email" />
                <input v-model="form.phone" type="text" class="rounded-xl border-slate-300 text-sm" placeholder="Telefono" />
                <input v-model="form.address" type="text" class="rounded-xl border-slate-300 text-sm" placeholder="Direccion" />
            </div>

            <label class="mt-4 inline-flex items-center gap-2 text-sm text-slate-700">
                <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300">
                Comercio activo
            </label>

            <div class="mt-5 flex justify-end">
                <button
                    type="submit"
                    class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-600 disabled:opacity-50"
                    :disabled="form.processing"
                >
                    Guardar cambios
                </button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
