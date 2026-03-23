<?php

namespace App\Services;

use App\Mail\BusinessCreatedWelcomeMail;
use App\Mail\BusinessUserAccessMail;
use App\Models\Business;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class UserAccessMailService
{
    public function sendBusinessCreatedMail(Business $business, User $adminUser, string $plainPassword): void
    {
        $recipients = collect([
            [
                'email' => $this->normalizeEmail($business->email),
                'name' => $business->name,
                'is_admin' => false,
            ],
            [
                'email' => $this->normalizeEmail($adminUser->email),
                'name' => $adminUser->name,
                'is_admin' => true,
            ],
        ])
            ->filter(fn (array $recipient): bool => $recipient['email'] !== '')
            ->unique('email')
            ->values();

        foreach ($recipients as $recipient) {
            try {
                Mail::to($recipient['email'], $recipient['name'])
                    ->queue(new BusinessCreatedWelcomeMail(
                        businessName: $business->name,
                        recipientName: (string) $recipient['name'],
                        recipientIsAdmin: (bool) $recipient['is_admin'],
                        adminName: $adminUser->name,
                        adminEmail: $adminUser->email,
                        plainPassword: $plainPassword,
                        applicationFunctions: $this->applicationFunctions(),
                        adminPermissions: $this->permissionsForRole('admin'),
                        loginUrl: route('login'),
                        passwordResetUrl: route('password.request'),
                        queueName: $this->notificationQueue(),
                    ));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
    }

    public function sendBusinessUserCreatedMail(Business $business, User $user, string $plainPassword): void
    {
        try {
            Mail::to($user->email, $user->name)
                ->queue(new BusinessUserAccessMail(
                    businessName: $business->name,
                    userName: $user->name,
                    roleLabel: $this->roleLabel($user->role),
                    permissions: $this->permissionsForRole($user->role),
                    plainPassword: $plainPassword,
                    isActive: $user->is_active,
                    loginUrl: route('login'),
                    passwordResetUrl: route('password.request'),
                    queueName: $this->notificationQueue(),
                ));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    /**
     * @return list<string>
     */
    public function applicationFunctions(): array
    {
        return [
            'Dashboard operativo con seguimiento diario',
            'Categorias, productos y proveedores',
            'Compras, ventas y actualizacion automatica de stock',
            'Control de lotes y vencimientos',
            'Usuarios internos por comercio',
            'Notificaciones por mail para stock y vencimientos',
        ];
    }

    /**
     * @return list<string>
     */
    public function permissionsForRole(string $role): array
    {
        return match ($role) {
            'admin' => [
                'Dashboard, categorias, productos y proveedores',
                'Compras y ventas del comercio',
                'Gestion de usuarios del comercio',
                'Configuracion de notificaciones',
                'Mi cuenta',
            ],
            'staff' => [
                'Dashboard, categorias, productos y proveedores',
                'Compras y ventas del comercio',
                'Mi cuenta',
            ],
            default => ['Acceso segun configuracion del sistema'],
        };
    }

    public function roleLabel(string $role): string
    {
        return match ($role) {
            'admin' => 'Administrador del comercio',
            'staff' => 'Staff del comercio',
            default => ucfirst($role),
        };
    }

    private function normalizeEmail(?string $email): string
    {
        return mb_strtolower(trim((string) $email));
    }

    private function notificationQueue(): string
    {
        $queue = trim((string) config('queue.notifications_queue', 'notifications'));

        return $queue !== '' ? $queue : 'default';
    }
}
