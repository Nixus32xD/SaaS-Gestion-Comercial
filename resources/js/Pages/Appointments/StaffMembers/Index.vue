<script setup>
import AppointmentsLayout from '@/Layouts/AppointmentsLayout.vue';
import { useForm } from '@inertiajs/vue3';
const props = defineProps({ staff_members: Array });
const form = useForm({ name: '', email: '', phone: '', is_active: true });
</script>
<template><AppointmentsLayout><h1 class="mb-4 text-xl font-semibold">Profesionales</h1>
<form class="mb-4 flex gap-2" @submit.prevent="form.post(route('appointments.staff-members.store'))"><input v-model="form.name" class="rounded border p-2 text-black" placeholder="Nombre"/><button class="rounded bg-cyan-700 px-3">Guardar</button></form>
<ul><li v-for="item in props.staff_members" :key="item.id">{{ item.name }}</li></ul>
</AppointmentsLayout></template>
