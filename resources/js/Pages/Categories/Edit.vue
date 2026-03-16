<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    category: { type: Object, required: true },
});

const form = useForm({
    name: props.category.name,
    slug: props.category.slug || '',
    description: props.category.description || '',
    is_active: Boolean(props.category.is_active),
});

const submit = () => {
    form.put(route('categories.update', props.category.id));
};
</script>

<template>
    <Head title="Editar categoria" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold text-slate-100">Editar categoria</h2>
                    <p class="mt-1 text-sm text-slate-300/80">Actualiza la organizacion del catalogo.</p>
                </div>
                <Link :href="route('categories.index')" class="text-sm font-semibold text-slate-300 hover:text-slate-100">Volver</Link>
            </div>
        </template>

        <form class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 backdrop-blur p-5 shadow-sm" @submit.prevent="submit">
            <div class="grid gap-3 md:grid-cols-2">
                <div class="space-y-1">
                    <label class="text-sm font-medium text-slate-300">Nombre</label>
                    <input v-model="form.name" type="text" class="w-full rounded-xl border-cyan-100/25 text-sm" />
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-medium text-slate-300">Slug</label>
                    <input v-model="form.slug" type="text" class="w-full rounded-xl border-cyan-100/25 text-sm" />
                </div>
                <div class="space-y-1 md:col-span-2">
                    <label class="text-sm font-medium text-slate-300">Descripcion</label>
                    <textarea v-model="form.description" rows="3" class="w-full rounded-xl border-cyan-100/25 text-sm" />
                </div>
            </div>

            <label class="mt-4 inline-flex items-center gap-2 text-sm text-slate-300">
                <input v-model="form.is_active" type="checkbox" class="rounded border-cyan-100/25">
                Categoria activa
            </label>

            <div class="mt-5 flex justify-end">
                <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-600" :disabled="form.processing">
                    Guardar cambios
                </button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
