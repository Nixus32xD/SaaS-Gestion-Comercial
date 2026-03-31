<script setup>
import { computed } from 'vue';

const props = defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    hint: { type: String, default: '' },
    tone: { type: String, default: 'default' },
});

const cardClass = computed(() => {
    const toneClasses = {
        default: 'border-cyan-100/20 bg-slate-900/45',
        accent: 'border-cyan-300/30 bg-cyan-400/10',
        success: 'border-emerald-300/30 bg-emerald-400/10',
        warning: 'border-amber-300/30 bg-amber-400/10',
        danger: 'border-rose-300/30 bg-rose-400/10',
    };

    return [
        'rounded-2xl border p-5 shadow-[0_20px_45px_rgba(8,47,73,0.36)] backdrop-blur',
        toneClasses[props.tone] || toneClasses.default,
    ];
});

const valueClass = computed(() => ({
    default: 'text-slate-100',
    accent: 'text-cyan-50',
    success: 'text-emerald-50',
    warning: 'text-amber-50',
    danger: 'text-rose-50',
}[props.tone] || 'text-slate-100'));
</script>

<template>
    <article :class="cardClass">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-xs uppercase tracking-[0.18em] text-cyan-100/70">{{ label }}</p>
                <p class="mt-2 text-3xl font-bold" :class="valueClass">{{ value }}</p>
            </div>
            <slot name="badge" />
        </div>
        <p v-if="hint" class="mt-3 text-sm text-slate-300/80">{{ hint }}</p>
        <div v-if="$slots.default" class="mt-4">
            <slot />
        </div>
    </article>
</template>
