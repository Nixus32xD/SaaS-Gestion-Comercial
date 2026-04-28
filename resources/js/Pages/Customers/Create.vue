<script setup>
import AppPanel from '@/Components/AppPanel.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    return_to: { type: String, default: null },
});

const form = useForm({
    name: '',
    phone: '',
    email: '',
    address: '',
    notes: '',
    preferred_reminder_channel: 'whatsapp',
    allow_reminders: true,
    reminder_notes: '',
    return_to: props.return_to,
});

const backHref = computed(() => (
    props.return_to === 'sales.create' ? route('sales.create') : route('customers.index')
));
const backLabel = computed(() => (
    props.return_to === 'sales.create' ? 'Volver a la venta' : 'Volver'
));
const formWarnings = computed(() => {
    const warnings = [];

    if (String(form.name || '').trim() === '') warnings.push('Falta el nombre del cliente.');
    if (!form.phone && !form.email) warnings.push('Conviene cargar telefono o email para contacto y cobranza.');
    if (!form.allow_reminders || form.preferred_reminder_channel === 'none') warnings.push('El cliente quedara sin recordatorios automaticos.');

    return warnings;
});

const summaryTone = computed(() => {
    if (String(form.name || '').trim() === '') return 'warning';
    return 'success';
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
                    <p class="mt-1 text-sm text-slate-300/80">Carga clientes para ventas fiadas, parciales y seguimiento comercial.</p>
                </div>
                <Link :href="backHref" class="text-sm font-semibold text-slate-300 hover:text-slate-100">{{ backLabel }}</Link>
            </div>
        </template>

        <form class="grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_21rem]" @submit.prevent="submit">
            <div class="grid gap-6">
                <AppPanel v-if="return_to === 'sales.create'" tone="accent" title="Alta rapida desde venta" subtitle="Al guardar, vuelves a la venta y el cliente queda seleccionado sin perder el borrador.">
                    <div class="app-chip-row">
                        <StatusBadge tone="info" label="Retorno a caja" />
                        <StatusBadge tone="success" label="Venta preservada" />
                    </div>
                </AppPanel>

                <AppPanel title="Datos de contacto" subtitle="Lo minimo para identificar y contactar rapido al cliente en caja o cobranza.">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="app-field">
                            <label class="app-field-label">Nombre</label>
                            <input v-model="form.name" type="text" class="w-full rounded-xl text-sm" placeholder="Nombre del cliente" />
                            <p v-if="form.errors.name" class="text-xs text-rose-300">{{ form.errors.name }}</p>
                        </div>
                        <div class="app-field">
                            <label class="app-field-label">Telefono</label>
                            <input v-model="form.phone" type="text" class="w-full rounded-xl text-sm" placeholder="Preferentemente con codigo de pais" />
                            <p v-if="form.errors.phone" class="text-xs text-rose-300">{{ form.errors.phone }}</p>
                        </div>
                        <div class="app-field">
                            <label class="app-field-label">Email</label>
                            <input v-model="form.email" type="email" class="w-full rounded-xl text-sm" placeholder="Opcional" />
                            <p v-if="form.errors.email" class="text-xs text-rose-300">{{ form.errors.email }}</p>
                        </div>
                        <div class="app-field">
                            <label class="app-field-label">Direccion</label>
                            <input v-model="form.address" type="text" class="w-full rounded-xl text-sm" placeholder="Direccion o ubicacion" />
                            <p v-if="form.errors.address" class="text-xs text-rose-300">{{ form.errors.address }}</p>
                        </div>
                    </div>
                </AppPanel>

                <AppPanel title="Seguimiento comercial" subtitle="Define si el cliente recibira recordatorios y deja notas utiles para futuras ventas o cobranza.">
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
                                Permitir recordatorios automaticos/manuales
                            </label>
                            <p v-if="form.errors.allow_reminders" class="text-xs text-rose-300">{{ form.errors.allow_reminders }}</p>
                        </div>
                        <div class="app-field md:col-span-2">
                            <label class="app-field-label">Notas comerciales</label>
                            <textarea v-model="form.notes" rows="3" class="w-full rounded-xl text-sm" placeholder="Observaciones del cliente" />
                            <p v-if="form.errors.notes" class="text-xs text-rose-300">{{ form.errors.notes }}</p>
                        </div>
                        <div class="app-field md:col-span-2">
                            <label class="app-field-label">Notas de recordatorio</label>
                            <textarea v-model="form.reminder_notes" rows="3" class="w-full rounded-xl text-sm" placeholder="Preferencias de contacto, horarios, mensajes, etc." />
                            <p v-if="form.errors.reminder_notes" class="text-xs text-rose-300">{{ form.errors.reminder_notes }}</p>
                        </div>
                    </div>
                </AppPanel>
            </div>

            <aside class="app-sticky-column">
                <AppPanel title="Resumen del cliente" :tone="summaryTone" subtitle="Chequeo corto antes de guardar.">
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
                                Guardar cliente
                            </button>
                            <Link :href="backHref" class="inline-flex w-full items-center justify-center rounded-xl border border-cyan-100/25 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/70">
                                {{ backLabel }}
                            </Link>
                        </div>
                    </template>
                </AppPanel>
            </aside>
        </form>
    </AuthenticatedLayout>
</template>
