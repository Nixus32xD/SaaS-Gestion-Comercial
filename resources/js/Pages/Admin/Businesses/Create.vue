<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    slug: '',
    owner_name: '',
    email: '',
    phone: '',
    address: '',
    is_active: true,
    admin: {
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    },
});

const submit = () => {
    form.post(route('admin.businesses.store'));
};
</script>

<template>
    <Head title="Nuevo comercio" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold text-slate-900">Nuevo comercio</h2>
                    <p class="mt-1 text-sm text-slate-500">Crea comercio y usuario admin inicial.</p>
                </div>
                <Link :href="route('admin.businesses.index')" class="text-sm font-semibold text-slate-600 hover:text-slate-900">
                    Volver
                </Link>
            </div>
        </template>

        <form class="grid gap-6" @submit.prevent="submit">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-base font-semibold text-slate-900">Datos del comercio</h3>
                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <input v-model="form.name" type="text" class="rounded-xl border-slate-300 text-sm" placeholder="Nombre" />
                    <input v-model="form.slug" type="text" class="rounded-xl border-slate-300 text-sm" placeholder="Slug (opcional)" />
                    <input v-model="form.owner_name" type="text" class="rounded-xl border-slate-300 text-sm" placeholder="Responsable" />
                    <input v-model="form.email" type="email" class="rounded-xl border-slate-300 text-sm" placeholder="Email" />
                    <input v-model="form.phone" type="text" class="rounded-xl border-slate-300 text-sm" placeholder="Telefono" />
                    <input v-model="form.address" type="text" class="rounded-xl border-slate-300 text-sm" placeholder="Direccion" />
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-base font-semibold text-slate-900">Admin inicial</h3>
                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <input v-model="form.admin.name" type="text" class="rounded-xl border-slate-300 text-sm" placeholder="Nombre" />
                    <input v-model="form.admin.email" type="email" class="rounded-xl border-slate-300 text-sm" placeholder="Email" />
                    <input v-model="form.admin.password" type="password" class="rounded-xl border-slate-300 text-sm" placeholder="Password" />
                    <input v-model="form.admin.password_confirmation" type="password" class="rounded-xl border-slate-300 text-sm" placeholder="Confirmar password" />
                </div>
            </section>

            <div class="flex justify-end">
                <button
                    type="submit"
                    class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-600 disabled:opacity-50"
                    :disabled="form.processing"
                >
                    Crear comercio
                </button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
