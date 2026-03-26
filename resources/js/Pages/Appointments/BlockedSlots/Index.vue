<script setup>
import AppointmentsLayout from '@/Layouts/AppointmentsLayout.vue';
import { useForm } from '@inertiajs/vue3';
const props = defineProps({ blocked_slots: Array, staff_members: Array });
const form = useForm({ staff_member_id: '', starts_at: '', ends_at: '', reason: '' });
</script>
<template><AppointmentsLayout><h1 class="mb-4 text-xl font-semibold">Bloqueos</h1>
<form class="mb-4 grid gap-2 md:grid-cols-4" @submit.prevent="form.post(route('appointments.blocked-slots.store'))"><input v-model="form.starts_at" type="datetime-local" class="rounded border p-2 text-black"/><input v-model="form.ends_at" type="datetime-local" class="rounded border p-2 text-black"/><input v-model="form.reason" class="rounded border p-2 text-black" placeholder="Motivo"/><button class="rounded bg-cyan-700 px-3">Bloquear</button></form>
<ul><li v-for="item in props.blocked_slots" :key="item.id">{{ item.starts_at }} - {{ item.reason }}</li></ul>
</AppointmentsLayout></template>
