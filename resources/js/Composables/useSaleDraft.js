export function useSaleDraft({ form, state, customerOptions, storageKey }) {
    const buildSnapshot = () => ({
        form: {
            customer_id: form.customer_id,
            fiscal_customer: { ...form.fiscal_customer },
            payment_status: form.payment_status,
            payment_provider: form.payment_provider,
            payment_method: form.payment_method,
            sale_sector_id: form.sale_sector_id,
            payment_destination_id: form.payment_destination_id,
            amount_received: form.amount_received,
            paid_amount: form.paid_amount,
            discount: form.discount,
            notes: form.notes,
            items: form.items.map((item) => ({ ...item })),
        },
        state: {
            search: state.search,
            quantity: state.quantity,
            manualItemName: state.manualItemName,
            manualItemAmount: state.manualItemAmount,
            manualItemVatTreatment: state.manualItemVatTreatment,
            manualItemVatRate: state.manualItemVatRate,
        },
    });

    const clear = () => {
        if (typeof window !== 'undefined') window.localStorage.removeItem(storageKey);
    };

    const persist = () => {
        if (typeof window !== 'undefined') {
            window.localStorage.setItem(storageKey, JSON.stringify(buildSnapshot()));
        }
    };

    const restore = () => {
        if (typeof window === 'undefined') return;

        const rawDraft = window.localStorage.getItem(storageKey);
        if (!rawDraft) return;

        try {
            const draft = JSON.parse(rawDraft);
            form.customer_id = draft?.form?.customer_id ?? form.customer_id;
            form.fiscal_customer = draft?.form?.fiscal_customer
                ? { ...form.fiscal_customer, ...draft.form.fiscal_customer }
                : form.fiscal_customer;
            form.payment_status = draft?.form?.payment_status ?? form.payment_status;
            form.payment_provider = draft?.form?.payment_provider ?? form.payment_provider;
            form.payment_method = draft?.form?.payment_method ?? form.payment_method;
            form.sale_sector_id = draft?.form?.sale_sector_id ?? form.sale_sector_id;
            form.payment_destination_id = draft?.form?.payment_destination_id ?? form.payment_destination_id;
            form.amount_received = draft?.form?.amount_received ?? form.amount_received;
            form.paid_amount = draft?.form?.paid_amount ?? form.paid_amount;
            form.discount = draft?.form?.discount ?? form.discount;
            form.notes = draft?.form?.notes ?? form.notes;
            form.items = Array.isArray(draft?.form?.items) ? draft.form.items.map((item) => ({ ...item })) : form.items;

            state.search = draft?.state?.search ?? state.search;
            state.quantity = draft?.state?.quantity ?? state.quantity;
            state.manualItemName = draft?.state?.manualItemName ?? state.manualItemName;
            state.manualItemAmount = draft?.state?.manualItemAmount ?? state.manualItemAmount;
            state.manualItemVatTreatment = draft?.state?.manualItemVatTreatment ?? state.manualItemVatTreatment;
            state.manualItemVatRate = draft?.state?.manualItemVatRate ?? state.manualItemVatRate;
            if (form.items.length) state.helperMessage = 'Se restauro el borrador de la venta en curso.';
        } catch {
            clear();
        }
    };

    const applyCustomerFromQuery = () => {
        if (typeof window === 'undefined') return;

        const url = new URL(window.location.href);
        const customerId = url.searchParams.get('customer_id');
        if (!customerId) return;

        const selected = customerOptions.value.find((customer) => String(customer.id) === String(customerId));
        if (selected) {
            form.customer_id = selected.id;
            state.helperMessage = `Cliente seleccionado para continuar la venta: ${selected.name}`;
        }

        url.searchParams.delete('customer_id');
        window.history.replaceState({}, '', `${url.pathname}${url.search}${url.hash}`);
    };

    return { applyCustomerFromQuery, buildSnapshot, clear, persist, restore };
}
