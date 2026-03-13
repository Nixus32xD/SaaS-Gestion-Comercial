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
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-slate-300">Nombre</label>
                        <input v-model="form.name" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Nombre del comercio" />
                    </div>
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-slate-300">Slug</label>
                        <input v-model="form.slug" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Slug (opcional)" />
                    </div>
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-slate-300">Responsable</label>
                        <input v-model="form.owner_name" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Nombre del responsable" />
                    </div>
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-slate-300">Email</label>
                        <input v-model="form.email" type="email" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Email del comercio" />
                    </div>
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-slate-300">Telefono</label>
                        <input v-model="form.phone" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Telefono de contacto" />
                    </div>
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-slate-300">Direccion</label>
                        <input v-model="form.address" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Direccion del comercio" />
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-sm backdrop-blur">
                <h3 class="text-base font-semibold text-slate-100">Admin inicial</h3>
                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-slate-300">Nombre</label>
                        <input v-model="form.admin.name" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Nombre del admin" />
                    </div>
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-slate-300">Email</label>
                        <input v-model="form.admin.email" type="email" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Email de acceso" />
                    </div>
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-slate-300">Password</label>
                        <input v-model="form.admin.password" type="password" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Password inicial" />
                    </div>
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-slate-300">Confirmar password</label>
                        <input v-model="form.admin.password_confirmation" type="password" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Repite la password" />
                    </div>
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
