<script setup>
import AppointmentsLayout from '@/Layouts/AppointmentsLayout.vue';
import { useForm } from '@inertiajs/vue3';
const props = defineProps({ services: Array, categories: Array });
const form = useForm({ name: '', service_category_id: '', description: '', duration_minutes: 30, price: 0, is_active: true });
const submit = () => form.post(route('appointments.services.store'));
const remove = (id) => { if (confirm('Eliminar servicio?')) form.delete(route('appointments.services.destroy', id)); };
</script>
<template>
  <AppointmentsLayout>
    <h1 class="mb-4 text-xl font-semibold">Servicios</h1>
    <form class="mb-6 grid gap-2 md:grid-cols-3" @submit.prevent="submit">
      <input v-model="form.name" class="rounded border p-2 text-black" placeholder="Nombre" />
      <input v-model="form.duration_minutes" type="number" class="rounded border p-2 text-black" placeholder="Duración" />
      <input v-model="form.price" type="number" step="0.01" class="rounded border p-2 text-black" placeholder="Precio" />
      <button class="rounded bg-cyan-700 px-3 py-2">Guardar</button>
    </form>
    <table class="w-full text-sm"><tr><th>Servicio</th><th>Duración</th><th>Precio</th><th></th></tr>
      <tr v-for="item in props.services" :key="item.id"><td>{{ item.name }}</td><td>{{ item.duration_minutes }} min</td><td>{{ item.price }}</td><td><button @click="remove(item.id)">Eliminar</button></td></tr>
    </table>
  </AppointmentsLayout>
</template>
