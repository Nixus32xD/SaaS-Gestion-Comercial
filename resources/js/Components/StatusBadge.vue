<script setup>
import { computed } from 'vue';

const props = defineProps({
    label: { type: String, default: '' },
    tone: { type: String, default: 'neutral' },
    size: { type: String, default: 'md' },
    surface: { type: String, default: 'dark' },
});

const badgeClass = computed(() => {
    const toneClasses = {
        dark: {
            neutral: 'border-slate-200/20 bg-slate-700/60 text-slate-100',
            info: 'border-cyan-300/30 bg-cyan-400/15 text-cyan-100',
            success: 'border-emerald-300/30 bg-emerald-400/15 text-emerald-100',
            warning: 'border-amber-300/30 bg-amber-400/15 text-amber-100',
            danger: 'border-rose-300/30 bg-rose-400/15 text-rose-100',
            highlight: 'border-fuchsia-300/30 bg-fuchsia-400/15 text-fuchsia-100',
        },
        light: {
            neutral: 'border-slate-300 bg-slate-200 text-slate-800',
            info: 'border-cyan-300 bg-cyan-100 text-cyan-800',
            success: 'border-emerald-300 bg-emerald-100 text-emerald-800',
            warning: 'border-amber-300 bg-amber-100 text-amber-800',
            danger: 'border-rose-300 bg-rose-100 text-rose-800',
            highlight: 'border-fuchsia-300 bg-fuchsia-100 text-fuchsia-800',
        },
    };

    const sizeClasses = {
        sm: 'px-2 py-0.5 text-[11px]',
        md: 'px-2.5 py-1 text-xs',
    };

    return [
        'inline-flex items-center rounded-full border font-semibold',
        toneClasses[props.surface]?.[props.tone] || toneClasses.dark[props.tone] || toneClasses.dark.neutral,
        sizeClasses[props.size] || sizeClasses.md,
    ];
});
</script>

<template>
    <span :class="badgeClass">
        <slot>{{ label }}</slot>
    </span>
</template>
