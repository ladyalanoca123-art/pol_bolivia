<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Se usa el mecanismo de Laravel (envía el enlace solo si la cuenta existe), pero la
        // respuesta visible es siempre la misma: no revela si el correo está registrado.
        // defer(): el envío ocurre después de responder, así el tiempo de respuesta y un posible
        // fallo del servidor de correo tampoco delatan si la cuenta existe.
        $credenciales = $request->only('email');
        defer(fn () => Password::sendResetLink($credenciales));

        return back()->with('status', __('passwords.sent'));
    }
}
