export const paymentMethodOptions = [
    { value: 'cash', label: 'Efectivo', requiresDestination: false },
    { value: 'transfer', label: 'Transferencia', requiresDestination: true },
    { value: 'qr', label: 'QR', requiresDestination: true },
    { value: 'debit_card', label: 'Tarjeta debito', requiresDestination: true },
    { value: 'credit_card', label: 'Tarjeta credito', requiresDestination: true },
];

export const paymentMethodLabel = (value, fallback = 'Sin cobro inicial') => {
    if (!value) return fallback;

    return paymentMethodOptions.find((option) => option.value === value)?.label || value;
};

export const paymentMethodRequiresDestination = (value) => (
    paymentMethodOptions.find((option) => option.value === value)?.requiresDestination === true
);
