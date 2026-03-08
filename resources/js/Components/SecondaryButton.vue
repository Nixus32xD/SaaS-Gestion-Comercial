<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <button
        :type="type"
        class="inline-flex items-center rounded-md border border-cyan-200/35 bg-slate-900/65 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-cyan-100 shadow-sm transition duration-150 ease-in-out hover:bg-slate-800/80 focus:outline-none focus:ring-2 focus:ring-cyan-300 focus:ring-offset-2 focus:ring-offset-slate-900 disabled:opacity-25"
    >
        <slot />
    </button>
</template>
