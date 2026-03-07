<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    defaultCurrency: {
        type: String,
        default: 'ARS',
    },
});

const form = useForm({
    name: '',
    email: '',
    business_name: '',
    branch_name: '',
    currency: props.defaultCurrency,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Crear comercio" />

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="business_name" value="Nombre del comercio" />

                <TextInput
                    id="business_name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.business_name"
                    required
                    autofocus
                    autocomplete="organization"
                />

                <InputError class="mt-2" :message="form.errors.business_name" />
            </div>

            <div class="mt-4">
                <InputLabel for="branch_name" value="Sucursal principal" />

                <TextInput
                    id="branch_name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.branch_name"
                    required
                    autocomplete="organization-title"
                />

                <InputError class="mt-2" :message="form.errors.branch_name" />
            </div>

            <div class="mt-4">
                <InputLabel for="name" value="Nombre del responsable" />

                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.name"
                    required
                    autocomplete="name"
                />

                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div class="mt-4">
                <InputLabel for="email" value="Email de acceso" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-4">
                <InputLabel for="currency" value="Moneda" />

                <TextInput
                    id="currency"
                    type="text"
                    class="mt-1 block w-full uppercase"
                    v-model="form.currency"
                    maxlength="3"
                    required
                />

                <InputError class="mt-2" :message="form.errors.currency" />
            </div>

            <div class="mt-4">
                <InputLabel for="password" value="Contraseña" />

                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password"
                    required
                    autocomplete="new-password"
                />

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4">
                <InputLabel
                    for="password_confirmation"
                    value="Confirmar contraseña"
                />

                <TextInput
                    id="password_confirmation"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                />

                <InputError
                    class="mt-2"
                    :message="form.errors.password_confirmation"
                />
            </div>

            <div class="mt-4 flex items-center justify-end">
                <Link
                    :href="route('login')"
                    class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    ¿Ya tenés cuenta?
                </Link>

                <PrimaryButton
                    class="ms-4"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Crear comercio
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
