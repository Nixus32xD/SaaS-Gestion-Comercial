<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    phone: '',
    email: '',
    address: '',
    notes: '',
    preferred_reminder_channel: 'whatsapp',
    allow_reminders: true,
    reminder_notes: '',
});

const submit = () => {
    form.post(route('customers.store'));
};
</script>

<template>
    <Head title="Nuevo cliente" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold text-slate-100">Nuevo cliente</h2>
                    <p class="mt-1 text-sm text-slate-300/80">Carga clientes para ventas fiadas, parciales y recordatorios.</p>
                </div>
                <Link :href="route('customers.index')" class="text-sm font-semibold text-slate-300 hover:text-slate-100">Volver</Link>
            </div>
        </template>

        <form class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 backdrop-blur p-5 shadow-sm" @submit.prevent="submit">
            <div class="grid gap-3 md:grid-cols-2">
                <div class="space-y-1">
                    <label class="text-sm font-medium text-slate-300">Nombre</label>
                    <input v-model="form.name" type="text" class="w-full rounded-xl border-cyan-100/25 text-sm" placeholder="Nombre del cliente" />
                    <p v-if="form.errors.name" class="text-xs text-rose-300">{{ form.errors.name }}</p>
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-medium text-slate-300">Telefono</label>
                    <input v-model="form.phone" type="text" class="w-full rounded-xl border-cyan-100/25 text-sm" placeholder="Preferentemente con codigo de pais" />
                    <p v-if="form.errors.phone" class="text-xs text-rose-300">{{ form.errors.phone }}</p>
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-medium text-slate-300">Email</label>
                    <input v-model="form.email" type="email" class="w-full rounded-xl border-cyan-100/25 text-sm" placeholder="Opcional" />
                    <p v-if="form.errors.email" class="text-xs text-rose-300">{{ form.errors.email }}</p>
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-medium text-slate-300">Canal preferido</label>
                    <select v-model="form.preferred_reminder_channel" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100">
                        <option value="whatsapp">WhatsApp</option>
                        <option value="email">Email</option>
                        <option value="none">Sin recordatorios</option>
                    </select>
                    <p v-if="form.errors.preferred_reminder_channel" class="text-xs text-rose-300">{{ form.errors.preferred_reminder_channel }}</p>
                </div>
                <div class="space-y-1 md:col-span-2">
                    <label class="text-sm font-medium text-slate-300">Direccion</label>
                    <input v-model="form.address" type="text" class="w-full rounded-xl border-cyan-100/25 text-sm" placeholder="Direccion o ubicacion" />
                    <p v-if="form.errors.address" class="text-xs text-rose-300">{{ form.errors.address }}</p>
                </div>
                <div class="space-y-1 md:col-span-2">
                    <label class="text-sm font-medium text-slate-300">Notas comerciales</label>
                    <textarea v-model="form.notes" rows="3" class="w-full rounded-xl border-cyan-100/25 text-sm" placeholder="Observaciones del cliente" />
                    <p v-if="form.errors.notes" class="text-xs text-rose-300">{{ form.errors.notes }}</p>
                </div>
                <div class="space-y-1 md:col-span-2">
                    <label class="text-sm font-medium text-slate-300">Notas de recordatorio</label>
                    <textarea v-model="form.reminder_notes" rows="3" class="w-full rounded-xl border-cyan-100/25 text-sm" placeholder="Preferencias de contacto, horarios, mensajes, etc." />
                    <p v-if="form.errors.reminder_notes" class="text-xs text-rose-300">{{ form.errors.reminder_notes }}</p>
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-slate-300 md:col-span-2">
                    <input v-model="form.allow_reminders" type="checkbox" class="rounded border-cyan-100/25">
                    Permitir recordatorios automaticos/manuales
                </label>
                <p v-if="form.errors.allow_reminders" class="text-xs text-rose-300 md:col-span-2">{{ form.errors.allow_reminders }}</p>
            </div>

            <div class="mt-5 flex justify-end">
                <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-600" :disabled="form.processing">
                    Guardar cliente
                </button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
