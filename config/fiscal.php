<?php

$activities = collect(explode(',', (string) env('FISCAL_ACTIVITIES', '')))
    ->map(fn (string $activity): string => trim($activity))
    ->filter()
    ->map(fn (string $activity): int => (int) $activity)
    ->filter(fn (int $activity): bool => $activity > 0)
    ->values()
    ->all();

$apiUrl = trim((string) env('FISCAL_API_URL', ''));
$defaultBaseUrl = $apiUrl !== ''
    ? rtrim($apiUrl, '/').'/api'
    : 'http://127.0.0.1:8000/api';

return [
    'enabled' => env('FISCAL_ENABLED', false),
    'base_url' => env('FISCAL_API_BASE_URL', $defaultBaseUrl),
    'token' => env('FISCAL_API_TOKEN'),
    'timeout' => (int) env('FISCAL_API_TIMEOUT', 60),
    'connect_timeout' => (int) env('FISCAL_API_CONNECT_TIMEOUT', 3),
    'status_timeout' => (int) env('FISCAL_API_STATUS_TIMEOUT', 5),
    'environment' => env('FISCAL_ENVIRONMENT', env('APP_ENV', 'local')),

    'defaults' => [
        'point_of_sale' => (int) env('FISCAL_DEFAULT_POINT_OF_SALE', 2),
        'document_type' => env('FISCAL_DEFAULT_DOCUMENT_TYPE', 'invoice_c'),
        'cbte_type' => (int) env('FISCAL_DEFAULT_CBTE_TYPE', 11),
        'concept' => (int) env('FISCAL_DEFAULT_CONCEPT', 1),
        'currency' => env('FISCAL_DEFAULT_CURRENCY', 'PES'),
        'currency_rate' => (float) env('FISCAL_DEFAULT_CURRENCY_RATE', 1),
        'activities' => $activities,
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
