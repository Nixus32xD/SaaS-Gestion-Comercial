<script setup>
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    users: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'staff',
    is_active: true,
});

const totalUsers = computed(() => props.users.length);
const activeUsers = computed(() => props.users.filter((user) => user.is_active).length);
const inactiveUsers = computed(() => props.users.filter((user) => !user.is_active).length);

const submit = () => {
    form.post(route('users.store'), {
        onSuccess: () => form.reset('name', 'email', 'password', 'password_confirmation'),
    });
};

const toggleStatus = (user) => {
    router.patch(route('users.status', user.id), {
        is_active: !user.is_active,
    });
};
</script>

<template>
    <Head title="Usuarios" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-2xl font-bold leading-tight text-slate-100">Usuarios del comercio</h2>
                <p class="mt-1 text-sm text-slate-300">Gestiona administradores y staff del comercio actual.</p>
            </div>
        </template>

        <div class="grid gap-6">
            <section class="grid gap-4 md:grid-cols-3">
                <article class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-[0_20px_45px_rgba(8,47,73,0.36)] backdrop-blur">
                    <p class="text-xs uppercase tracking-wider text-cyan-100/70">Usuarios activos</p>
                    <p class="mt-2 text-3xl font-bold text-slate-100">{{ activeUsers }}</p>
                </article>
                <article class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-[0_20px_45px_rgba(8,47,73,0.36)] backdrop-blur">
                    <p class="text-xs uppercase tracking-wider text-cyan-100/70">Usuarios inactivos</p>
                    <p class="mt-2 text-3xl font-bold text-slate-100">{{ inactiveUsers }}</p>
                </article>
                <article class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-[0_20px_45px_rgba(8,47,73,0.36)] backdrop-blur">
                    <p class="text-xs uppercase tracking-wider text-cyan-100/70">Total</p>
                    <p class="mt-2 text-3xl font-bold text-slate-100">{{ totalUsers }}</p>
                </article>
            </section>

            <section class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-[0_20px_45px_rgba(8,47,73,0.36)] backdrop-blur">
                <h3 class="text-base font-semibold text-slate-100">Alta de usuario interno</h3>
                <p class="mt-1 text-sm text-slate-300">Crea cuentas internas con acceso al comercio actual.</p>

                <form class="mt-4 grid gap-3 md:grid-cols-3" @submit.prevent="submit">
                    <input
                        v-model="form.name"
                        type="text"
                        class="rounded-xl border-slate-300 bg-white text-sm text-slate-900"
                        placeholder="Nombre"
                    />
                    <input
                        v-model="form.email"
                        type="email"
                        class="rounded-xl border-slate-300 bg-white text-sm text-slate-900"
                        placeholder="Email"
                    />
                    <select v-model="form.role" class="rounded-xl border-slate-300 bg-white text-sm text-slate-900">
                        <option value="staff">Staff</option>
                        <option value="admin">Admin</option>
                    </select>
                    <input
                        v-model="form.password"
                        type="password"
                        class="rounded-xl border-slate-300 bg-white text-sm text-slate-900"
                        placeholder="Password"
                    />
                    <input
                        v-model="form.password_confirmation"
                        type="password"
                        class="rounded-xl border-slate-300 bg-white text-sm text-slate-900"
                        placeholder="Confirmar password"
                    />
                    <label class="flex items-center gap-2 rounded-xl border border-cyan-100/20 px-3 py-2 text-sm text-slate-200">
                        <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 text-cyan-300 focus:ring-cyan-300" />
                        Usuario activo
                    </label>
                    <button
                        type="submit"
                        class="rounded-xl bg-cyan-300 px-4 py-2 text-sm font-semibold text-slate-900 hover:bg-cyan-200 md:col-span-3"
                        :disabled="form.processing"
                    >
                        Crear usuario
                    </button>
                </form>
            </section>

            <section class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-[0_20px_45px_rgba(8,47,73,0.36)] backdrop-blur">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-semibold text-slate-100">Usuarios registrados</h3>
                    <p class="text-xs text-slate-300">{{ page.props.business?.name }}</p>
                </div>

                <div class="mt-4 overflow-x-auto rounded-xl border border-cyan-100/20">
                    <table class="min-w-full divide-y divide-cyan-100/15 text-sm">
                        <thead class="bg-slate-950/45">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-slate-300">Nombre</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300">Email</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300">Rol</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300">Estado</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300">Ultimo acceso</th>
                                <th class="px-3 py-2 text-right font-medium text-slate-300"></th>
                            </tr>
                        </thead>
                        <tbody v-if="users.length" class="divide-y divide-cyan-100/10 bg-slate-900/25">
                            <tr v-for="user in users" :key="user.id">
                                <td class="px-3 py-2 font-medium text-slate-100">{{ user.name }}</td>
                                <td class="px-3 py-2 text-slate-200">{{ user.email }}</td>
                                <td class="px-3 py-2 text-slate-200">{{ user.role === 'admin' ? 'Admin' : 'Staff' }}</td>
                                <td class="px-3 py-2">
                                    <span
                                        class="rounded-full px-2 py-1 text-xs font-semibold"
                                        :class="user.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700'"
                                    >
                                        {{ user.is_active ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-slate-300">{{ user.last_login_at ?? 'Sin ingreso' }}</td>
                                <td class="px-3 py-2 text-right">
                                    <button
                                        type="button"
                                        class="rounded-lg border border-cyan-100/25 px-3 py-1 text-xs font-semibold text-slate-100 hover:bg-slate-800/60"
                                        @click="toggleStatus(user)"
                                    >
                                        {{ user.is_active ? 'Desactivar' : 'Activar' }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                        <tbody v-else>
                            <tr>
                                <td colspan="6" class="px-3 py-6 text-center text-slate-300">
                                    No hay usuarios internos cargados para este comercio.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
