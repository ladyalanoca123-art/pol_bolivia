<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(RegisterRequest $request): RedirectResponse
    {
        // Solo campos validados y normalizados (RegisterRequest). El rol y el estado los asigna
        // la base de datos; nunca se toman del formulario.
        $datos = $request->validated();

        $user = User::create([
            'nombres' => $datos['nombres'],
            'apellidos' => $datos['apellidos'],
            'email' => $datos['email'],
            'telefono' => $datos['telefono'] ?? null,
            'password' => Hash::make($datos['password']),
        ]);

        event(new Registered($user));

        Auth::login($user);

        // Igual que al iniciar sesión: nuevo identificador de sesión (evita la fijación de sesión).
        $request->session()->regenerate();

        // Si intentaba entrar a una página protegida, vuelve allí (url.intended, que Laravel guarda
        // solo a partir de rutas de esta misma aplicación). Si no, al catálogo público de mascotas.
        return redirect()->intended(route('mascotas.catalogo', absolute: false));
    }
}
