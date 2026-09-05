<?php

$activities = collect(explode(',', (string) env('FISCAL_ACTIVITIES', '')))
    ->map(fn (string $activity): string => trim($activity))
    ->filter()
    ->map(fn (string $activity): int => (int) $activity)
    ->filter(fn (int $activity): bool => $activity > 0)
    ->values()
    ->all();

return [
    'enabled' => env('FISCAL_ENABLED', false),
    'base_url' => env('FISCAL_API_BASE_URL'),
    'token' => env('FISCAL_API_TOKEN'),
    'timeout' => (int) env('FISCAL_API_TIMEOUT', 60),
    'connect_timeout' => (int) env('FISCAL_API_CONNECT_TIMEOUT', 3),
    'reconciliation' => [
        'max_attempts' => (int) env('FISCAL_RECONCILIATION_MAX_ATTEMPTS', 5),
        'stale_minutes' => (int) env('FISCAL_RECONCILIATION_STALE_MINUTES', 5),
        'scan_limit' => (int) env('FISCAL_RECONCILIATION_SCAN_LIMIT', 100),
        'backoff_seconds' => [15, 60, 300, 900, 3600],
    ],
    'environment' => env('FISCAL_ENVIRONMENT', 'testing'),

    'defaults' => [
        'point_of_sale' => (int) env('FISCAL_DEFAULT_POINT_OF_SALE', 2),
        'fiscal_condition' => env('FISCAL_DEFAULT_CONDITION', 'monotributo'),
        'document_type' => env('FISCAL_DEFAULT_DOCUMENT_TYPE', 'invoice_c'),
        'cbte_type' => (int) env('FISCAL_DEFAULT_CBTE_TYPE', 11),
        'concept' => (int) env('FISCAL_DEFAULT_CONCEPT', 1),
        'authorization_mode' => env('FISCAL_DEFAULT_AUTHORIZATION_MODE', 'cae'),
        'vat_treatment' => env('FISCAL_DEFAULT_VAT_TREATMENT', 'gravado'),
        'vat_rate' => (float) env('FISCAL_DEFAULT_VAT_RATE', 21),
        'currency' => env('FISCAL_DEFAULT_CURRENCY', 'PES'),
        'currency_rate' => (float) env('FISCAL_DEFAULT_CURRENCY_RATE', 1),
        'activities' => $activities,
    ],

    'authorization_modes' => [
        ['value' => 'cae', 'label' => 'CAE normal'],
        ['value' => 'caea', 'label' => 'CAEA contingencia'],
        ['value' => 'auto', 'label' => 'Automatico'],
    ],

    'environments' => [
        ['value' => 'testing', 'label' => 'Testing'],
        ['value' => 'production', 'label' => 'Produccion'],
    ],

    'fiscal_conditions' => [
        ['value' => 'monotributo', 'label' => 'Monotributista'],
        ['value' => 'responsable_inscripto', 'label' => 'Responsable Inscripto'],
        ['value' => 'exento', 'label' => 'IVA Exento'],
    ],

    'receiver_iva_conditions' => [
        ['value' => 'consumidor_final', 'label' => 'Consumidor Final'],
        ['value' => 'responsable_inscripto', 'label' => 'Responsable Inscripto'],
        ['value' => 'monotributo', 'label' => 'Monotributista'],
        ['value' => 'exento', 'label' => 'IVA Exento'],
    ],

    'vat_treatments' => [
        ['value' => 'gravado', 'label' => 'Gravado'],
        ['value' => 'exento', 'label' => 'Exento'],
        ['value' => 'no_gravado', 'label' => 'No gravado'],
    ],

    'vat_rates' => [
        ['id' => 5, 'value' => 21, 'label' => '21%'],
        ['id' => 4, 'value' => 10.5, 'label' => '10.5%'],
        ['id' => 6, 'value' => 27, 'label' => '27%'],
        ['id' => 8, 'value' => 5, 'label' => '5%'],
        ['id' => 9, 'value' => 2.5, 'label' => '2.5%'],
        ['id' => 3, 'value' => 0, 'label' => '0%'],
    ],

    'document_types' => [
        ['value' => 'invoice_a', 'label' => 'Factura A', 'default_cbte_type' => 1],
        ['value' => 'invoice_b', 'label' => 'Factura B', 'default_cbte_type' => 6],
        ['value' => 'invoice_c', 'label' => 'Factura C', 'default_cbte_type' => 11],
    ],

    'voucher_types' => [
        ['value' => 1, 'label' => 'Factura A', 'document_type' => 'invoice_a'],
        ['value' => 2, 'label' => 'Nota de debito A', 'document_type' => 'debit_note_a'],
        ['value' => 3, 'label' => 'Nota de credito A', 'document_type' => 'credit_note_a'],
        ['value' => 6, 'label' => 'Factura B', 'document_type' => 'invoice_b'],
        ['value' => 7, 'label' => 'Nota de debito B', 'document_type' => 'debit_note_b'],
        ['value' => 8, 'label' => 'Nota de credito B', 'document_type' => 'credit_note_b'],
        ['value' => 11, 'label' => 'Factura C', 'document_type' => 'invoice_c'],
        ['value' => 12, 'label' => 'Nota de debito C', 'document_type' => 'debit_note_c'],
        ['value' => 13, 'label' => 'Nota de credito C', 'document_type' => 'credit_note_c'],
        ['value' => 51, 'label' => 'Factura M', 'document_type' => 'invoice_m'],
        ['value' => 52, 'label' => 'Nota de debito M', 'document_type' => 'debit_note_m'],
        ['value' => 53, 'label' => 'Nota de credito M', 'document_type' => 'credit_note_m'],
    ],
];
