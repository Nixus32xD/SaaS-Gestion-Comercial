<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\StoreBusinessUserRequest;
use App\Http\Requests\Users\UpdateBusinessUserStatusRequest;
use App\Models\User;
use App\Support\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class BusinessUserController extends Controller
{
    public function index(CurrentBusiness $currentBusiness): Response
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);

        $users = User::query()
            ->forBusiness($business->id)
            ->orderByRaw("case when role = 'admin' then 0 else 1 end")
            ->orderBy('name')
            ->get();

        return Inertia::render('Users/Index', [
            'users' => $users->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'is_active' => $user->is_active,
                'last_login_at' => $user->last_login_at?->format('Y-m-d H:i'),
            ]),
        ]);
    }

    public function store(StoreBusinessUserRequest $request, CurrentBusiness $currentBusiness): RedirectResponse
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);

        $data = $request->validated();

        User::query()->create([
            'business_id' => $business->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'is_active' => (bool) $data['is_active'],
            'email_verified_at' => now(),
        ]);

        return back()->with('success', 'Usuario creado correctamente.');
    }

    public function updateStatus(
        UpdateBusinessUserStatusRequest $request,
        CurrentBusiness $currentBusiness,
        User $user
    ): RedirectResponse {
        $business = $currentBusiness->get();
        $actor = $request->user();

        abort_if($business === null || $actor === null, 404);
        abort_if($user->business_id !== $business->id, 403);
        abort_if(! $user->isBusinessUser(), 403);

        if ($user->id === $actor->id) {
            return back()->with('error', 'No puedes cambiar tu propio estado.');
        }

        $newStatus = (bool) $request->validated('is_active');

        if (! $newStatus && $user->isBusinessAdmin()) {
            $otherActiveAdmins = User::query()
                ->forBusiness($business->id)
                ->where('role', 'admin')
                ->where('is_active', true)
                ->whereKeyNot($user->id)
                ->exists();

            if (! $otherActiveAdmins) {
                return back()->with('error', 'Debe quedar al menos un admin activo en el comercio.');
            }
        }

        $user->update([
            'is_active' => $newStatus,
        ]);

        return back()->with('success', 'Estado del usuario actualizado.');
    }
}
