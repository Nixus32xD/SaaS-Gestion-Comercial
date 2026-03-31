<script setup>
import AppPanel from '@/Components/AppPanel.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});
</script>

<template>
    <Head title="Mi cuenta" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold leading-tight text-slate-100">Mi cuenta</h2>
                <p class="mt-1 text-sm text-slate-300/80">Administra tus datos de acceso y la seguridad de la cuenta.</p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <AppPanel title="Perfil" subtitle="Actualiza tus datos personales y el correo asociado a la cuenta.">
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                        class="max-w-xl"
                    />
                </AppPanel>

                <AppPanel title="Seguridad" subtitle="Mantiene protegida la cuenta con una contrasena segura.">
                    <UpdatePasswordForm class="max-w-xl" />
                </AppPanel>

                <AppPanel title="Zona critica" subtitle="Usa esta accion solo si realmente necesitas eliminar la cuenta.">
                    <DeleteUserForm class="max-w-xl" />
                </AppPanel>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
