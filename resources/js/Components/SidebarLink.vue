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
        ? 'group flex items-center gap-3 rounded-xl border border-cyan-200 bg-cyan-50 px-3 py-2.5 text-sm font-semibold text-cyan-900 shadow-sm'
        : 'group flex items-center gap-3 rounded-xl border border-transparent px-3 py-2.5 text-sm font-medium text-slate-700 transition hover:border-slate-200 hover:bg-slate-50 hover:text-slate-900'
));
</script>

<template>
    <Link :href="href" :class="classes">
        <span class="text-base leading-none">{{ icon }}</span>
        <span><slot /></span>
    </Link>
</template>
