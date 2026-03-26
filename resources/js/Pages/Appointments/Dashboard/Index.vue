<script setup>
import AppointmentsLayout from '@/Layouts/AppointmentsLayout.vue';
defineProps({ summary: Object });
</script>
<template>
  <AppointmentsLayout>
    <h1 class="mb-4 text-xl font-semibold">Turnos</h1>
    <div class="grid gap-4 md:grid-cols-3">
      <div class="rounded border p-4">Servicios: {{ summary.services_count }}</div>
      <div class="rounded border p-4">Profesionales: {{ summary.staff_count }}</div>
      <div class="rounded border p-4">Turnos hoy: {{ summary.today_appointments }}</div>
    </div>
  </AppointmentsLayout>
</template>
