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
                    <h2 class="text-2xl font-bold text-slate-100">Nuevo comercio</h2>
                    <p class="mt-1 text-sm text-slate-300/80">Crea el comercio y su usuario admin inicial.</p>
                </div>
                <Link
                    :href="route('admin.businesses.index')"
                    class="rounded-lg border border-cyan-100/25 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/70"
                >
                    Volver
                </Link>
            </div>
        </template>

        <form class="grid gap-6" @submit.prevent="submit">
            <section class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-sm backdrop-blur">
                <h3 class="text-base font-semibold text-slate-100">Datos del comercio</h3>
                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <input v-model="form.name" type="text" class="rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Nombre" />
                    <input v-model="form.slug" type="text" class="rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Slug (opcional)" />
                    <input v-model="form.owner_name" type="text" class="rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Responsable" />
                    <input v-model="form.email" type="email" class="rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Email" />
                    <input v-model="form.phone" type="text" class="rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Telefono" />
                    <input v-model="form.address" type="text" class="rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Direccion" />
                </div>
            </section>

            <section class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-sm backdrop-blur">
                <h3 class="text-base font-semibold text-slate-100">Admin inicial</h3>
                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <input v-model="form.admin.name" type="text" class="rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Nombre" />
                    <input v-model="form.admin.email" type="email" class="rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Email" />
                    <input v-model="form.admin.password" type="password" class="rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Password" />
                    <input v-model="form.admin.password_confirmation" type="password" class="rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Confirmar password" />
                </div>
            </section>

            <div class="flex justify-end">
                <button
                    type="submit"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50"
                    :disabled="form.processing"
                >
                    Crear comercio
                </button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
