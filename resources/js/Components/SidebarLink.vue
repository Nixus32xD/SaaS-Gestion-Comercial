<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href: {
        type: String,
        required: true,
    },
    active: {
        type: Boolean,
        default: false,
    },
    icon: {
        type: String,
        default: '•',
    },
});

const classes = computed(() => (
    props.active
        ? 'group flex items-center gap-3 rounded-xl bg-indigo-500/20 px-3 py-2.5 text-sm font-semibold text-white'
        : 'group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-300 transition hover:bg-slate-800 hover:text-white'
));
</script>

<template>
    <Link :href="href" :class="classes">
        <span class="text-base leading-none">{{ icon }}</span>
        <span><slot /></span>
    </Link>
</template>
