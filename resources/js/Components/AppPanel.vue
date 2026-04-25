<script setup>
import { computed, useSlots } from 'vue';

const props = defineProps({
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    tone: { type: String, default: 'default' },
    padding: { type: String, default: 'md' },
});

const slots = useSlots();

const panelClass = computed(() => {
    const toneClasses = {
        default: 'border-cyan-100/20 bg-slate-900/45',
        muted: 'border-cyan-100/15 bg-slate-950/35',
        accent: 'border-cyan-300/30 bg-cyan-400/10',
        success: 'border-emerald-300/30 bg-emerald-400/10',
        warning: 'border-amber-300/30 bg-amber-400/10',
        danger: 'border-rose-300/30 bg-rose-400/10',
    };

    const paddingClasses = {
        sm: 'p-4',
        md: 'p-5',
        lg: 'p-6',
    };

    return [
        'rounded-2xl border shadow-[0_20px_45px_rgba(8,47,73,0.36)] backdrop-blur',
        toneClasses[props.tone] || toneClasses.default,
        paddingClasses[props.padding] || paddingClasses.md,
    ];
});

const hasHeader = computed(() => (
    Boolean(props.title || props.subtitle || slots.header || slots.actions)
));
</script>

<template>
    <section :class="panelClass">
        <div v-if="hasHeader" class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <slot name="header">
                <div>
                    <h3 v-if="title" class="text-base font-semibold text-slate-100">{{ title }}</h3>
                    <p v-if="subtitle" class="mt-1 text-sm text-slate-300/80">{{ subtitle }}</p>
                </div>
            </slot>

            <div v-if="$slots.actions" class="flex shrink-0 flex-wrap gap-2">
                <slot name="actions" />
            </div>
        </div>

        <div :class="hasHeader ? 'mt-4' : ''">
            <slot />
        </div>

        <div v-if="$slots.footer" class="mt-4 border-t border-cyan-100/10 pt-4">
            <slot name="footer" />
        </div>
    </section>
</template>
