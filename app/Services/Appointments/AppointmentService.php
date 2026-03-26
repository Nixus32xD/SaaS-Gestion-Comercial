<?php

namespace App\Services\Appointments;

use App\Models\Appointments\Appointment;
use App\Models\Appointments\AppointmentSetting;
use App\Models\Appointments\AppointmentStatusHistory;
use App\Models\Appointments\BlockedSlot;
use App\Models\Business;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentService
{
    public function create(Business $business, array $data, ?int $userId = null): Appointment
    {
        return DB::transaction(function () use ($business, $data, $userId): Appointment {
            $startsAt = CarbonImmutable::parse($data['starts_at']);
            $settings = $this->settingsForBusiness($business->id);
            $this->assertBookingRules($business->id, $startsAt, $settings, (int) ($data['staff_member_id'] ?? 0));

            $duration = (int) (\App\Models\Appointments\Service::query()
                ->forBusiness($business->id)
                ->whereKey($data['service_id'])
                ->value('duration_minutes') ?? 0);

            if ($duration <= 0) {
                throw ValidationException::withMessages(['service_id' => 'Servicio invalido para el negocio.']);
            }

            $endsAt = $startsAt->addMinutes($duration);

            $appointment = Appointment::query()->create([
                'business_id' => $business->id,
                'service_id' => $data['service_id'],
                'staff_member_id' => $data['staff_member_id'] ?? null,
                'appointment_customer_id' => $data['appointment_customer_id'],
                'created_by' => $userId,
                'status' => $data['status'] ?? Appointment::STATUS_SCHEDULED,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'notes' => $data['notes'] ?? null,
                'cancelled_at' => ($data['status'] ?? null) === Appointment::STATUS_CANCELLED ? now() : null,
            ]);

            AppointmentStatusHistory::query()->create([
                'business_id' => $business->id,
                'appointment_id' => $appointment->id,
                'from_status' => null,
                'to_status' => $appointment->status,
                'changed_by' => $userId,
                'reason' => $data['cancel_reason'] ?? null,
            ]);

            return $appointment;
        });
    }

    public function update(Appointment $appointment, array $data, ?int $userId = null): Appointment
    {
        return DB::transaction(function () use ($appointment, $data, $userId): Appointment {
            $startsAt = CarbonImmutable::parse($data['starts_at']);
            $settings = $this->settingsForBusiness($appointment->business_id);

            $this->assertBookingRules($appointment->business_id, $startsAt, $settings, (int) ($data['staff_member_id'] ?? 0), $appointment->id);

            $duration = (int) (\App\Models\Appointments\Service::query()
                ->forBusiness($appointment->business_id)
                ->whereKey($data['service_id'])
                ->value('duration_minutes') ?? 0);

            if ($duration <= 0) {
                throw ValidationException::withMessages(['service_id' => 'Servicio invalido para el negocio.']);
            }

            $fromStatus = $appointment->status;
            $toStatus = $data['status'] ?? $appointment->status;

            $appointment->update([
                'service_id' => $data['service_id'],
                'staff_member_id' => $data['staff_member_id'] ?? null,
                'appointment_customer_id' => $data['appointment_customer_id'],
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->addMinutes($duration),
                'status' => $toStatus,
                'notes' => $data['notes'] ?? null,
                'cancelled_at' => $toStatus === Appointment::STATUS_CANCELLED ? now() : null,
            ]);

            if ($fromStatus !== $toStatus) {
                AppointmentStatusHistory::query()->create([
                    'business_id' => $appointment->business_id,
                    'appointment_id' => $appointment->id,
                    'from_status' => $fromStatus,
                    'to_status' => $toStatus,
                    'changed_by' => $userId,
                    'reason' => $data['cancel_reason'] ?? null,
                ]);
            }

            return $appointment->fresh();
        });
    }

    private function settingsForBusiness(int $businessId): AppointmentSetting
    {
        return AppointmentSetting::query()->firstOrCreate(['business_id' => $businessId]);
    }

    private function assertBookingRules(int $businessId, CarbonImmutable $startsAt, AppointmentSetting $settings, int $staffMemberId = 0, ?int $ignoreId = null): void
    {
        if ($startsAt->lessThan(now()->addMinutes($settings->min_notice_minutes))) {
            throw ValidationException::withMessages(['starts_at' => 'No cumple el aviso minimo configurado.']);
        }

        if ($startsAt->greaterThan(now()->addDays($settings->booking_window_days))) {
            throw ValidationException::withMessages(['starts_at' => 'Supera la ventana maxima de reserva.']);
        }

        $blocked = BlockedSlot::query()
            ->forBusiness($businessId)
            ->when($staffMemberId > 0, fn ($query) => $query->where(function ($inner) use ($staffMemberId): void {
                $inner->whereNull('staff_member_id')->orWhere('staff_member_id', $staffMemberId);
            }))
            ->where('starts_at', '<=', $startsAt)
            ->where('ends_at', '>', $startsAt)
            ->exists();

        if ($blocked) {
            throw ValidationException::withMessages(['starts_at' => 'El horario se encuentra bloqueado.']);
        }

        $overlap = Appointment::query()
            ->forBusiness($businessId)
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where('staff_member_id', $staffMemberId > 0 ? $staffMemberId : null)
            ->where('status', '!=', Appointment::STATUS_CANCELLED)
            ->where('starts_at', '<=', $startsAt)
            ->where('ends_at', '>', $startsAt)
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages(['starts_at' => 'El profesional ya tiene un turno en ese horario.']);
        }
    }
}
