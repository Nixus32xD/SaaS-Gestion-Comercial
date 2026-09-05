<script setup>
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    users: {
        type: Array,
        default: () => [],
    },
    roles: { type: Array, default: () => [] },
    branches: { type: Array, default: () => [] },
    permissionGroups: { type: Object, default: () => ({}) },
});

const page = usePage();

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role_ids: [],
    branch_ids: [],
    is_active: true,
});

const totalUsers = computed(() => props.users.length);
const activeUsers = computed(() => props.users.filter((user) => user.is_active).length);
const inactiveUsers = computed(() => props.users.filter((user) => !user.is_active).length);
const selectedUser = ref(null);
const accessForm = useForm({ role_ids: [], branch_ids: [] });
const effectivePermissions = computed(() => [...new Set(
    props.roles.filter((role) => accessForm.role_ids.includes(role.id)).flatMap((role) => role.permission_codes),
)].sort());

const submit = () => {
    form.post(route('users.store'), {
        onSuccess: () => form.reset(),
    });
};

const toggleStatus = (user) => {
    router.patch(route('users.status', user.id), {
        is_active: !user.is_active,
    });
};

const editAccess = (user) => {
    if (user.is_owner) return;
    selectedUser.value = user;
    accessForm.role_ids = [...user.roles.map((role) => role.id)];
    accessForm.branch_ids = [...user.branch_ids];
};

const saveAccess = () => {
    if (!selectedUser.value) return;
    accessForm.put(route('users.access.update', selectedUser.value.id), {
        preserveScroll: true,
        onSuccess: () => { selectedUser.value = null; },
    });
};
</script>

<template>
    <Head title="Usuarios" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-2xl font-bold leading-tight text-slate-100">Usuarios del comercio</h2>
                <p class="mt-1 text-sm text-slate-300">Asigná roles, permisos efectivos y sucursales habilitadas.</p>
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
                    <label class="rounded-xl border border-cyan-100/20 p-3 text-sm text-slate-200 md:col-span-3">
                        <span class="font-medium">Roles</span>
                        <span class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            <label v-for="role in roles" :key="role.id" class="flex items-center gap-2">
                                <input v-model="form.role_ids" :value="role.id" type="checkbox" class="rounded" />
                                {{ role.name }}
                            </label>
                        </span>
                    </label>
                    <label class="rounded-xl border border-cyan-100/20 p-3 text-sm text-slate-200 md:col-span-3">
                        <span class="font-medium">Sucursales habilitadas</span>
                        <span class="mt-2 flex flex-wrap gap-4">
                            <label v-for="branch in branches" :key="branch.id" class="flex items-center gap-2">
                                <input v-model="form.branch_ids" :value="branch.id" type="checkbox" class="rounded" />
                                {{ branch.name }}
                            </label>
                        </span>
                    </label>
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

                <div class="mt-4 grid gap-3 md:hidden">
                    <article v-for="user in users" :key="user.id" class="rounded-xl border border-cyan-100/20 bg-slate-950/35 p-4 text-sm text-slate-300">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-semibold text-slate-100">{{ user.name }}</p>
                                <p class="mt-1 break-all text-xs text-slate-400">{{ user.email }}</p>
                                <p class="mt-1 text-xs text-slate-400">{{ user.is_owner ? 'Propietario' : user.roles.map((role) => role.name).join(', ') || 'Sin roles' }}</p>
                            </div>
                            <span
                                class="rounded-full px-2 py-1 text-xs font-semibold"
                                :class="user.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700'"
                            >
                                {{ user.is_active ? 'Activo' : 'Inactivo' }}
                            </span>
                        </div>

                        <p class="mt-3 text-xs text-slate-400">Ultimo acceso: <span class="text-slate-200">{{ user.last_login_at ?? 'Sin ingreso' }}</span></p>

                        <div class="mt-3 flex flex-wrap gap-2">
                            <button
                                v-if="!user.is_owner"
                                type="button"
                                class="rounded-lg border border-cyan-100/25 px-3 py-1 text-xs font-semibold text-slate-100 hover:bg-slate-800/60"
                                @click="toggleStatus(user)"
                            >
                                {{ user.is_active ? 'Desactivar' : 'Activar' }}
                            </button>
                            <button v-if="!user.is_owner" type="button" class="rounded-lg border border-cyan-100/25 px-3 py-1 text-xs font-semibold text-slate-100 hover:bg-slate-800/60" @click="editAccess(user)">Accesos</button>
                        </div>
                    </article>
                </div>

                <div class="mt-4 hidden overflow-x-auto rounded-xl border border-cyan-100/20 md:block">
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
                                <td class="px-3 py-2 text-slate-200">{{ user.is_owner ? 'Propietario' : user.roles.map((role) => role.name).join(', ') || 'Sin roles' }}</td>
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
                                        v-if="!user.is_owner"
                                        type="button"
                                        class="rounded-lg border border-cyan-100/25 px-3 py-1 text-xs font-semibold text-slate-100 hover:bg-slate-800/60"
                                        @click="toggleStatus(user)"
                                    >
                                        {{ user.is_active ? 'Desactivar' : 'Activar' }}
                                    </button>
                                    <button v-if="!user.is_owner" type="button" class="ml-2 rounded-lg border border-cyan-100/25 px-3 py-1 text-xs font-semibold text-slate-100 hover:bg-slate-800/60" @click="editAccess(user)">Accesos</button>
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

            <section v-if="selectedUser" class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-[0_20px_45px_rgba(8,47,73,0.36)] backdrop-blur">
                <h3 class="text-base font-semibold text-slate-100">Accesos de {{ selectedUser.name }}</h3>
                <form class="mt-4 grid gap-4" @submit.prevent="saveAccess">
                    <div class="grid gap-2 md:grid-cols-2">
                        <label v-for="role in roles" :key="role.id" class="rounded-lg border border-cyan-100/15 p-3 text-sm text-slate-200">
                            <input v-model="accessForm.role_ids" :value="role.id" type="checkbox" class="mr-2 rounded" />{{ role.name }}
                        </label>
                    </div>
                    <div class="flex flex-wrap gap-4 text-sm text-slate-200">
                        <label v-for="branch in branches" :key="branch.id"><input v-model="accessForm.branch_ids" :value="branch.id" type="checkbox" class="mr-2 rounded" />{{ branch.name }}</label>
                    </div>
                    <div class="rounded-xl border border-cyan-100/15 p-3 text-xs text-slate-300">
                        <p class="font-semibold text-slate-100">Permisos efectivos</p>
                        <div v-for="(codes, module) in permissionGroups" :key="module" class="mt-2"><span class="font-medium">{{ module }}:</span> {{ codes.filter((code) => effectivePermissions.includes(code)).join(', ') || 'sin permisos' }}</div>
                    </div>
                    <div class="flex gap-2"><button type="submit" class="rounded-xl bg-cyan-300 px-4 py-2 text-sm font-semibold text-slate-900" :disabled="accessForm.processing">Guardar accesos</button><button type="button" class="rounded-xl border border-cyan-100/25 px-4 py-2 text-sm" @click="selectedUser = null">Cancelar</button></div>
                </form>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
