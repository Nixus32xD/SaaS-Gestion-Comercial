<script setup>
import AppointmentsLayout from '@/Layouts/AppointmentsLayout.vue';
import { useForm } from '@inertiajs/vue3';
const props = defineProps({ appointments: Array, services: Array, staff_members: Array, customers: Array, filters: Object });
const form = useForm({ service_id: '', appointment_customer_id: '', staff_member_id: '', starts_at: '', status: 'scheduled', notes: '' });
</script>
<template><AppointmentsLayout><h1 class="mb-4 text-xl font-semibold">Calendario de turnos</h1>
<form class="mb-4 grid gap-2 md:grid-cols-4" @submit.prevent="form.post(route('appointments.appointments.store'))">
<select v-model="form.service_id" class="rounded border p-2 text-black"><option value="">Servicio</option><option v-for="s in services" :value="s.id">{{ s.name }}</option></select>
<select v-model="form.appointment_customer_id" class="rounded border p-2 text-black"><option value="">Cliente</option><option v-for="c in customers" :value="c.id">{{ c.name }}</option></select>
<input v-model="form.starts_at" type="datetime-local" class="rounded border p-2 text-black"/>
<button class="rounded bg-cyan-700 px-3">Crear turno</button>
</form>
<table class="w-full text-sm"><tr><th>Fecha</th><th>Servicio</th><th>Cliente</th><th>Estado</th></tr><tr v-for="a in appointments" :key="a.id"><td>{{ a.starts_at }}</td><td>{{ a.service }}</td><td>{{ a.customer }}</td><td>{{ a.status }}</td></tr></table>
</AppointmentsLayout></template>
