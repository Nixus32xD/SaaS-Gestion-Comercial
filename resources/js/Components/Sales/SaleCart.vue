<script setup>
const props = defineProps({
    items: { type: Array, default: () => [] },
    errors: { type: Array, default: () => [] },
    lineSubtotal: { type: Function, required: true },
});

defineEmits(['remove']);

const money = (value) => new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    minimumFractionDigits: 2,
}).format(Number(value) || 0);

</script>

<template>
    <section aria-labelledby="sale-cart-title">
        <h3 id="sale-cart-title" class="mt-5 app-section-title">Carrito</h3>

        <div v-if="items.length" class="mt-4 grid gap-3 md:hidden">
            <article v-for="(item, index) in items" :key="`${item.product_id}-${index}`" class="rounded-xl border border-cyan-100/20 bg-slate-950/35 p-4 text-sm text-slate-300">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-semibold text-slate-100">{{ item.product_name }}</p>
                            <span v-if="item.is_manual" class="rounded-full bg-amber-300/15 px-2 py-0.5 text-[11px] font-semibold text-amber-100">Sin stock</span>
                        </div>
                        <p class="mt-1 text-xs text-slate-400">
                            <template v-if="item.is_manual">Monto fijo - {{ money(item.unit_price) }}</template>
                            <template v-else>{{ item.quantity }} {{ item.quantity_label }} - {{ money(item.unit_price) }} {{ item.price_label }}</template>
                        </p>
                        <p class="mt-1 text-xs text-slate-400">IVA: {{ item.vat_label || 'IVA 21%' }}</p>
                    </div>
                    <button type="button" class="shrink-0 rounded-lg border border-rose-300/45 px-2 py-1 text-xs font-semibold text-rose-100 hover:bg-rose-400/20" :aria-label="`Quitar ${item.product_name}`" @click="$emit('remove', index)">Quitar</button>
                </div>
                <p class="mt-3 text-sm">Subtotal: <strong class="text-slate-100">{{ money(lineSubtotal(item)) }}</strong></p>
            </article>
        </div>

        <div class="mt-4 hidden overflow-x-auto rounded-xl border border-cyan-100/20 app-table-wrap md:block">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-950/35"><tr><th scope="col" class="px-3 py-2 text-left font-medium text-slate-300/80">Producto</th><th scope="col" class="px-3 py-2 text-left font-medium text-slate-300/80">Cantidad</th><th scope="col" class="px-3 py-2 text-left font-medium text-slate-300/80">Precio</th><th scope="col" class="px-3 py-2 text-left font-medium text-slate-300/80">IVA</th><th scope="col" class="px-3 py-2 text-left font-medium text-slate-300/80">Subtotal</th><th scope="col" class="px-3 py-2 text-left font-medium text-slate-300/80"><span class="sr-only">Acciones</span></th></tr></thead>
                <tbody v-if="items.length" class="divide-y divide-slate-100">
                    <tr v-for="(item, index) in items" :key="`${item.product_id}-${index}`">
                        <td class="px-3 py-2 font-semibold text-slate-100"><div class="flex flex-wrap items-center gap-2"><span>{{ item.product_name }}</span><span v-if="item.is_manual" class="rounded-full bg-amber-300/15 px-2 py-0.5 text-[11px] font-semibold text-amber-100">Sin stock</span></div></td>
                        <td class="px-3 py-2"><span v-if="item.is_manual" class="text-xs text-slate-400">Monto fijo</span><template v-else>{{ item.quantity }} <span class="text-xs text-slate-400">{{ item.quantity_label }}</span></template></td>
                        <td class="px-3 py-2">{{ money(item.unit_price) }} <span v-if="item.price_label" class="text-xs text-slate-400">{{ item.price_label }}</span></td>
                        <td class="px-3 py-2 text-slate-300">{{ item.vat_label || 'IVA 21%' }}</td>
                        <td class="px-3 py-2">{{ money(lineSubtotal(item)) }}</td>
                        <td class="px-3 py-2 text-right"><button type="button" class="rounded-lg border border-rose-300/45 px-2 py-1 text-xs font-semibold text-rose-100 hover:bg-rose-400/20" :aria-label="`Quitar ${item.product_name}`" @click="$emit('remove', index)">Quitar</button></td>
                    </tr>
                </tbody>
                <tbody v-else><tr><td colspan="6" class="px-3 py-5 text-center text-slate-400">Agrega productos para continuar.</td></tr></tbody>
            </table>
        </div>

        <div v-if="errors.length" class="mt-3 rounded-xl border border-rose-300/35 bg-rose-400/10 p-3 text-sm text-rose-100" aria-live="polite">
            <p v-for="(message, index) in errors" :key="`${message}-${index}`">{{ message }}</p>
        </div>
    </section>
</template>
