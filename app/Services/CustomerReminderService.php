<?php

namespace App\Services;

use App\Mail\CustomerDebtReminderMail;
use App\Models\Business;
use App\Models\Customer;
use App\Models\CustomerReminder;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class CustomerReminderService
{
    public function __construct(private readonly CustomerAccountService $customerAccountService)
    {
    }

    /**
     * @return array{balance: float, pending_sales_count: int, message: string, url: string}
     */
    public function generateWhatsappReminder(Business $business, Customer $customer, User $user): array
    {
        $this->guardReminderAvailability($customer, CustomerReminder::CHANNEL_WHATSAPP);

        $phone = $this->sanitizeWhatsappPhone($customer->phone);

        if ($phone === null) {
            throw ValidationException::withMessages([
                'phone' => 'El cliente no tiene un telefono valido para WhatsApp.',
            ]);
        }

        $summary = $this->summary($customer);
        $message = $this->buildReminderMessage($business, $customer, $summary['balance'], $summary['pending_sales_count']);
        $url = 'https://wa.me/'.$phone.'?text='.rawurlencode($message);

        CustomerReminder::query()->create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'channel' => CustomerReminder::CHANNEL_WHATSAPP,
            'status' => CustomerReminder::STATUS_GENERATED,
            'subject' => 'Recordatorio de deuda por WhatsApp',
            'message_snapshot' => $message,
            'target' => $url,
            'meta' => [
                'phone' => $phone,
                'pending_sales_count' => $summary['pending_sales_count'],
                'balance' => $summary['balance'],
            ],
            'sent_at' => now(),
            'sent_by' => $user->id,
        ]);

        $customer->forceFill([
            'last_reminder_at' => now(),
        ])->save();

        return [
            'balance' => $summary['balance'],
            'pending_sales_count' => $summary['pending_sales_count'],
            'message' => $message,
            'url' => $url,
        ];
    }

    public function sendEmailReminder(Business $business, Customer $customer, User $user): CustomerReminder
    {
        $this->guardReminderAvailability($customer, CustomerReminder::CHANNEL_EMAIL);

        if (! filled($customer->email)) {
            throw ValidationException::withMessages([
                'email' => 'El cliente no tiene un email configurado.',
            ]);
        }

        $summary = $this->summary($customer);
        $message = $this->buildReminderMessage($business, $customer, $summary['balance'], $summary['pending_sales_count']);
        $subject = $this->emailSubject($business, $customer);

        try {
            Mail::to($customer->email)->send(new CustomerDebtReminderMail(
                businessName: $business->name,
                customerName: $customer->name,
                subjectLine: $subject,
                balanceLabel: $this->formatMoney($summary['balance']),
                pendingSalesCount: $summary['pending_sales_count'],
                reminderMessage: $message,
            ));

            $reminder = CustomerReminder::query()->create([
                'business_id' => $business->id,
                'customer_id' => $customer->id,
                'channel' => CustomerReminder::CHANNEL_EMAIL,
                'status' => CustomerReminder::STATUS_SENT,
                'subject' => $subject,
                'message_snapshot' => $message,
                'target' => $customer->email,
                'meta' => [
                    'pending_sales_count' => $summary['pending_sales_count'],
                    'balance' => $summary['balance'],
                ],
                'sent_at' => now(),
                'sent_by' => $user->id,
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            CustomerReminder::query()->create([
                'business_id' => $business->id,
                'customer_id' => $customer->id,
                'channel' => CustomerReminder::CHANNEL_EMAIL,
                'status' => CustomerReminder::STATUS_FAILED,
                'subject' => $subject,
                'message_snapshot' => $message,
                'target' => $customer->email,
                'meta' => [
                    'pending_sales_count' => $summary['pending_sales_count'],
                    'balance' => $summary['balance'],
                    'error' => $exception->getMessage(),
                ],
                'sent_at' => now(),
                'sent_by' => $user->id,
            ]);

            throw ValidationException::withMessages([
                'email' => 'No se pudo enviar el recordatorio por email en este momento.',
            ]);
        }

        $customer->forceFill([
            'last_reminder_at' => now(),
        ])->save();

        return $reminder;
    }

    /**
     * @return array{balance: float, pending_sales_count: int}
     */
    public function summary(Customer $customer): array
    {
        return [
            'balance' => $this->customerAccountService->currentBalance($customer),
            'pending_sales_count' => Sale::query()
                ->forBusiness($customer->business_id)
                ->where('customer_id', $customer->id)
                ->where('pending_amount', '>', 0)
                ->count(),
        ];
    }

    public function buildReminderMessage(
        Business $business,
        Customer $customer,
        float $balance,
        int $pendingSalesCount
    ): string {
        $firstName = trim((string) collect(explode(' ', $customer->name))->filter()->first()) ?: $customer->name;
        $message = "Hola {$firstName}, te escribimos de {$business->name}. ";
        $message .= "Te recordamos que actualmente tenes un saldo pendiente de {$this->formatMoney($balance)}.";

        if ($pendingSalesCount > 0) {
            $message .= ' Tenes '.($pendingSalesCount === 1 ? '1 comprobante pendiente' : "{$pendingSalesCount} comprobantes pendientes").'.';
        }

        $message .= ' Si queres, podes acercarte o escribirnos para regularizarlo y evitar que la deuda siga aumentando. Gracias.';

        return $message;
    }

    public function emailSubject(Business $business, Customer $customer): string
    {
        return 'Recordatorio de saldo pendiente - '.$business->name.' - '.$customer->name;
    }

    public function sanitizeWhatsappPhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        $defaultCountryCode = preg_replace('/\D+/', '', (string) config('services.whatsapp.default_country_code', '54')) ?? '';

        if ($defaultCountryCode !== '' && ! str_starts_with($digits, $defaultCountryCode) && strlen($digits) <= 11) {
            $digits = ltrim($digits, '0');
            $digits = $defaultCountryCode.$digits;
        }

        return $digits !== '' ? $digits : null;
    }

    private function formatMoney(float $amount): string
    {
        return '$'.number_format($amount, 2, ',', '.');
    }

    private function guardReminderAvailability(Customer $customer, string $channel): void
    {
        if (! $customer->allow_reminders || $customer->preferred_reminder_channel === 'none') {
            throw ValidationException::withMessages([
                'allow_reminders' => 'El cliente tiene los recordatorios deshabilitados.',
            ]);
        }

        if ($channel === CustomerReminder::CHANNEL_WHATSAPP && ! filled($customer->phone)) {
            throw ValidationException::withMessages([
                'phone' => 'El cliente no tiene telefono configurado.',
            ]);
        }

        if ($channel === CustomerReminder::CHANNEL_EMAIL && ! filled($customer->email)) {
            throw ValidationException::withMessages([
                'email' => 'El cliente no tiene email configurado.',
            ]);
        }
    }
}
