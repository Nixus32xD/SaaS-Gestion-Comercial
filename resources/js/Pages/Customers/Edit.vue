<script setup>
import AppPanel from '@/Components/AppPanel.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    customer: { type: Object, required: true },
});

const form = useForm({
    name: props.customer.name,
    phone: props.customer.phone || '',
    email: props.customer.email || '',
    address: props.customer.address || '',
    notes: props.customer.notes || '',
    preferred_reminder_channel: props.customer.preferred_reminder_channel || 'whatsapp',
    allow_reminders: Boolean(props.customer.allow_reminders),
    reminder_notes: props.customer.reminder_notes || '',
});

const formWarnings = computed(() => {
    const warnings = [];

    if (String(form.name || '').trim() === '') warnings.push('Falta el nombre del cliente.');
    if (!form.phone && !form.email) warnings.push('Conviene tener telefono o email para contacto rapido.');
    if (!form.allow_reminders || form.preferred_reminder_channel === 'none') warnings.push('El cliente quedara sin recordatorios activos.');

    return warnings;
});

const submit = () => {
    form.put(route('customers.update', props.customer.id));
};
</script>

<template>
    <Head title="Editar cliente" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold text-slate-100">Editar cliente</h2>
                    <p class="mt-1 text-sm text-slate-300/80">Actualiza datos del cliente y sus preferencias de recordatorio.</p>
                </div>
                <Link :href="route('customers.show', customer.id)" class="text-sm font-semibold text-slate-300 hover:text-slate-100">Volver</Link>
            </div>
        </template>

        <form class="grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_21rem]" @submit.prevent="submit">
            <div class="grid gap-6">
                <AppPanel title="Datos de contacto" subtitle="Actualiza la ficha visible en ventas, cobranzas y recordatorios.">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="app-field">
                            <label class="app-field-label">Nombre</label>
                            <input v-model="form.name" type="text" class="w-full rounded-xl text-sm" />
                            <p v-if="form.errors.name" class="text-xs text-rose-300">{{ form.errors.name }}</p>
                        </div>
                        <div class="app-field">
                            <label class="app-field-label">Telefono</label>
                            <input v-model="form.phone" type="text" class="w-full rounded-xl text-sm" />
                            <p v-if="form.errors.phone" class="text-xs text-rose-300">{{ form.errors.phone }}</p>
                        </div>
                        <div class="app-field">
                            <label class="app-field-label">Email</label>
                            <input v-model="form.email" type="email" class="w-full rounded-xl text-sm" />
                            <p v-if="form.errors.email" class="text-xs text-rose-300">{{ form.errors.email }}</p>
                        </div>
                        <div class="app-field">
                            <label class="app-field-label">Direccion</label>
                            <input v-model="form.address" type="text" class="w-full rounded-xl text-sm" />
                            <p v-if="form.errors.address" class="text-xs text-rose-300">{{ form.errors.address }}</p>
                        </div>
                    </div>
                </AppPanel>

                <AppPanel title="Seguimiento y recordatorios" subtitle="Define como se contacta y que observaciones debe ver el equipo.">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="app-field">
                            <label class="app-field-label">Canal preferido</label>
                            <select v-model="form.preferred_reminder_channel" class="w-full rounded-xl bg-slate-950/35 text-sm text-slate-100">
                                <option value="whatsapp">WhatsApp</option>
                                <option value="email">Email</option>
                                <option value="none">Sin recordatorios</option>
                            </select>
                            <p v-if="form.errors.preferred_reminder_channel" class="text-xs text-rose-300">{{ form.errors.preferred_reminder_channel }}</p>
                        </div>
                        <div class="app-field justify-end">
                            <label class="inline-flex items-center gap-2 text-sm text-slate-300">
                                <input v-model="form.allow_reminders" type="checkbox" class="rounded border-cyan-100/25">
                                Permitir recordatorios
                            </label>
                            <p v-if="form.errors.allow_reminders" class="text-xs text-rose-300">{{ form.errors.allow_reminders }}</p>
                        </div>
                        <div class="app-field md:col-span-2">
                            <label class="app-field-label">Notas comerciales</label>
                            <textarea v-model="form.notes" rows="3" class="w-full rounded-xl text-sm" />
                            <p v-if="form.errors.notes" class="text-xs text-rose-300">{{ form.errors.notes }}</p>
                        </div>
                        <div class="app-field md:col-span-2">
                            <label class="app-field-label">Notas de recordatorio</label>
                            <textarea v-model="form.reminder_notes" rows="3" class="w-full rounded-xl text-sm" />
                            <p v-if="form.errors.reminder_notes" class="text-xs text-rose-300">{{ form.errors.reminder_notes }}</p>
                        </div>
                    </div>
                </AppPanel>
            </div>

            <aside class="app-sticky-column">
                <AppPanel title="Estado del cliente" :tone="formWarnings.length ? 'warning' : 'success'" subtitle="Lectura rapida antes de guardar cambios.">
                    <div class="app-chip-row">
                        <StatusBadge :tone="form.allow_reminders ? 'success' : 'warning'" :label="form.allow_reminders ? 'Con recordatorios' : 'Sin recordatorios'" />
                        <StatusBadge tone="info" :label="form.preferred_reminder_channel === 'none' ? 'Sin canal' : form.preferred_reminder_channel" />
                    </div>

                    <div class="mt-4 space-y-3 text-sm text-slate-300">
                        <div class="flex items-center justify-between gap-3">
                            <span>Nombre</span>
                            <span class="text-right font-semibold text-slate-100">{{ form.name || 'Pendiente' }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span>Telefono</span>
                            <span class="text-right font-semibold text-slate-100">{{ form.phone || '-' }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span>Email</span>
                            <span class="text-right font-semibold text-slate-100">{{ form.email || '-' }}</span>
                        </div>
                    </div>

                    <div v-if="formWarnings.length" class="mt-4 rounded-xl border border-amber-300/25 bg-amber-400/10 p-3 text-sm text-amber-100">
                        <p v-for="warning in formWarnings" :key="warning">{{ warning }}</p>
                    </div>

                    <template #footer>
                        <div class="grid gap-3">
                            <button type="submit" class="w-full rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-600" :disabled="form.processing">
                                Guardar cambios
                            </button>
                            <Link :href="route('customers.show', customer.id)" class="inline-flex w-full items-center justify-center rounded-xl border border-cyan-100/25 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/70">
                                Volver
                            </Link>
                        </div>
                    </template>
                </AppPanel>
            </aside>
        </form>
    </AuthenticatedLayout>
</template>
