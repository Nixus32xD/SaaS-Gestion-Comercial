<script setup>
import AppointmentsLayout from '@/Layouts/AppointmentsLayout.vue';
import { useForm } from '@inertiajs/vue3';
const props = defineProps({ settings: Object });
const form = useForm({ ...props.settings });
</script>
<template><AppointmentsLayout><h1 class="mb-4 text-xl font-semibold">Configuración de turnos</h1>
<form class="grid gap-2 md:grid-cols-2" @submit.prevent="form.put(route('appointments.settings.update'))">
<input v-model="form.booking_window_days" type="number" class="rounded border p-2 text-black" placeholder="booking_window_days"/>
<input v-model="form.min_notice_minutes" type="number" class="rounded border p-2 text-black" placeholder="min_notice_minutes"/>
<input v-model="form.cancellation_notice_minutes" type="number" class="rounded border p-2 text-black" placeholder="cancellation_notice_minutes"/>
<input v-model="form.default_slot_interval_minutes" type="number" class="rounded border p-2 text-black" placeholder="default_slot_interval_minutes"/>
<button class="rounded bg-cyan-700 px-3 py-2">Guardar</button>
</form></AppointmentsLayout></template>
