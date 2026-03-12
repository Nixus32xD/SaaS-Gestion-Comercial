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
                    <h2 class="text-2xl font-bold text-slate-100">Editar comercio</h2>
                    <p class="mt-1 text-sm text-slate-300/80">Actualiza datos generales y estado.</p>
                </div>
                <Link
                    :href="route('admin.businesses.index')"
                    class="rounded-lg border border-cyan-100/25 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/70"
                >
                    Volver
                </Link>
            </div>
        </template>

        <form class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-sm backdrop-blur" @submit.prevent="submit">
            <div class="grid gap-3 md:grid-cols-2">
                <input v-model="form.name" type="text" class="rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Nombre" />
                <input v-model="form.slug" type="text" class="rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Slug" />
                <input v-model="form.owner_name" type="text" class="rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Responsable" />
                <input v-model="form.email" type="email" class="rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Email" />
                <input v-model="form.phone" type="text" class="rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Telefono" />
                <input v-model="form.address" type="text" class="rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Direccion" />
            </div>

            <label class="mt-4 inline-flex items-center gap-2 text-sm text-slate-300">
                <input v-model="form.is_active" type="checkbox" class="rounded border-cyan-100/25 bg-slate-950/35 text-indigo-500 focus:ring-indigo-500">
                Comercio activo
            </label>

            <div class="mt-5 flex justify-end">
                <button
                    type="submit"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50"
                    :disabled="form.processing"
                >
                    Guardar cambios
                </button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
