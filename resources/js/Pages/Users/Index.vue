<script setup>
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    users: {
        type: Array,
        default: () => [],
    },
    roles: {
        type: Array,
        default: () => [],
    },
    branches: {
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
    default_branch_id: props.branches[0]?.id ?? '',
    role_id: props.roles[0]?.id ?? '',
    status: 'active',
});

const activeUsers = computed(() => props.users.filter((user) => user.status === 'active').length);
const inactiveUsers = computed(() => props.users.filter((user) => user.status === 'inactive').length);

const submit = () => {
    form.post(route('users.store'), {
        onSuccess: () => form.reset('name', 'email', 'password', 'password_confirmation'),
    });
};

const toggleStatus = (membership) => {
    const nextStatus = membership.status === 'active' ? 'inactive' : 'active';
    router.patch(route('users.status', membership.membership_id), { status: nextStatus });
};
</script>

<template>
    <Head title="Usuarios" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-2xl font-bold leading-tight text-slate-900">Usuarios del comercio</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Multi-tenant: estos usuarios solo pertenecen al comercio activo.
                </p>
            </div>
        </template>

        <div class="grid gap-6">
            <section class="grid gap-4 md:grid-cols-3">
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs uppercase tracking-wider text-slate-500">Usuarios activos</p>
                    <p class="mt-2 text-3xl font-bold text-slate-900">{{ activeUsers }}</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs uppercase tracking-wider text-slate-500">Usuarios inactivos</p>
                    <p class="mt-2 text-3xl font-bold text-slate-900">{{ inactiveUsers }}</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs uppercase tracking-wider text-slate-500">Total</p>
                    <p class="mt-2 text-3xl font-bold text-slate-900">{{ users.length }}</p>
                </article>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-base font-semibold text-slate-900">Alta de usuario interno</h3>
                <p class="mt-1 text-sm text-slate-500">
                    Crea cajeros, vendedores o supervisores para este tenant.
                </p>

                <form class="mt-4 grid gap-3 md:grid-cols-3" @submit.prevent="submit">
                    <input
                        v-model="form.name"
                        type="text"
                        class="rounded-xl border-slate-300 text-sm"
                        placeholder="Nombre"
                    />
                    <input
                        v-model="form.email"
                        type="email"
                        class="rounded-xl border-slate-300 text-sm"
                        placeholder="Email"
                    />
                    <select v-model="form.status" class="rounded-xl border-slate-300 text-sm">
                        <option value="active">Activo</option>
                        <option value="inactive">Inactivo</option>
                    </select>
                    <input
                        v-model="form.password"
                        type="password"
                        class="rounded-xl border-slate-300 text-sm"
                        placeholder="Password"
                    />
                    <input
                        v-model="form.password_confirmation"
                        type="password"
                        class="rounded-xl border-slate-300 text-sm"
                        placeholder="Confirmar password"
                    />
                    <select v-model="form.default_branch_id" class="rounded-xl border-slate-300 text-sm">
                        <option v-for="branch in branches" :key="branch.id" :value="branch.id">
                            {{ branch.name }}
                        </option>
                    </select>
                    <select v-model="form.role_id" class="rounded-xl border-slate-300 text-sm md:col-span-2">
                        <option value="">Sin rol</option>
                        <option v-for="role in roles" :key="role.id" :value="role.id">
                            {{ role.name }}
                        </option>
                    </select>
                    <button
                        type="submit"
                        class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                        :disabled="form.processing"
                    >
                        Crear usuario
                    </button>
                </form>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-semibold text-slate-900">Usuarios del tenant</h3>
                    <p class="text-xs text-slate-500">Administra acceso por estado y rol.</p>
                </div>

                <p
                    v-if="page.props.flash?.success || page.props.flash?.error"
                    class="mt-3 rounded-lg px-3 py-2 text-sm"
                    :class="page.props.flash?.error ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700'"
                >
                    {{ page.props.flash?.error ?? page.props.flash?.success }}
                </p>

                <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200 app-table-wrap">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Nombre</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Email</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Sucursal</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Rol</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Estado</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-500"></th>
                            </tr>
                        </thead>
                        <tbody v-if="users.length" class="divide-y divide-slate-100 bg-white">
                            <tr v-for="membership in users" :key="membership.membership_id">
                                <td class="px-3 py-2 font-medium text-slate-800">
                                    {{ membership.name }}
                                    <span v-if="membership.is_owner" class="ml-2 rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-semibold text-indigo-700">
                                        Owner
                                    </span>
                                </td>
                                <td class="px-3 py-2">{{ membership.email }}</td>
                                <td class="px-3 py-2">{{ membership.branch ?? '-' }}</td>
                                <td class="px-3 py-2">
                                    <span v-if="membership.roles.length">
                                        {{ membership.roles.map((role) => role.name).join(', ') }}
                                    </span>
                                    <span v-else>-</span>
                                </td>
                                <td class="px-3 py-2">
                                    <span
                                        class="rounded-full px-2 py-1 text-xs font-semibold"
                                        :class="membership.status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700'"
                                    >
                                        {{ membership.status === 'active' ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <button
                                        type="button"
                                        class="rounded-lg border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-40"
                                        :disabled="membership.is_owner"
                                        @click="toggleStatus(membership)"
                                    >
                                        {{ membership.status === 'active' ? 'Desactivar' : 'Activar' }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                        <tbody v-else>
                            <tr>
                                <td colspan="6" class="px-3 py-6 text-center text-slate-400">
                                    No hay usuarios asociados a este tenant.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
