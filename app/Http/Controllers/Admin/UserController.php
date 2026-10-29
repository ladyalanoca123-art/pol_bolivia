<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterUsersRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Requests\Admin\UpdateUserStatusRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(FilterUsersRequest $request): View
    {
        $filters = $request->validated();

        $users = User::query()
            ->when($filters['buscar'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('nombres', 'ilike', "%{$search}%")
                        ->orWhere('apellidos', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%");
                });
            })
            ->when($filters['rol'] ?? null, fn ($query, string $role) => $query->where('rol', $role))
            ->when(
                $filters['estado'] ?? null,
                fn ($query, string $status) => $query->where('estado', $status === 'activo')
            )
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.usuarios.index', compact('users', 'filters'));
    }

    public function updateStatus(UpdateUserStatusRequest $request, User $user): RedirectResponse
    {
        $user->forceFill([
            'estado' => $request->boolean('estado'),
        ])->save();

        return to_route('admin.usuarios.index', $request->query())
            ->with('status', 'El estado del usuario fue actualizado.');
    }

    public function show(User $user): View
    {
        Gate::authorize('view', $user);

        return view('admin.usuarios.show', compact('user'));
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        return view('admin.usuarios.edit', compact('user'));
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $user->forceFill($request->validated())->save();

        return to_route('admin.usuarios.show', $user)
            ->with('status', 'Los datos del usuario fueron actualizados.');
    }
}
