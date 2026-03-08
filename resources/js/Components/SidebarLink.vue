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
        default: '*',
    },
});

const classes = computed(() => (
    props.active
        ? 'group flex items-center gap-3 rounded-xl border border-cyan-200/35 bg-cyan-300/20 px-3 py-2.5 text-sm font-semibold text-cyan-100 shadow-[0_10px_24px_rgba(8,47,73,0.25)]'
        : 'group flex items-center gap-3 rounded-xl border border-transparent px-3 py-2.5 text-sm font-medium text-slate-300 transition hover:border-cyan-200/25 hover:bg-slate-800/70 hover:text-cyan-100'
));
</script>

<template>
    <Link :href="href" :class="classes">
        <span class="text-base leading-none">{{ icon }}</span>
        <span><slot /></span>
    </Link>
</template>
