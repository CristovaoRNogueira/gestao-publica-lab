<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class RegisterController extends Controller
{
    /**
     * Display the registration view.
     */
    public function showRegistrationForm(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     */
    public function register(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Autenticar automaticamente
        Auth::login($user);

        // O usuário novo não tem memberships (0).
        // Isso fará o DashboardController redirecionar para o /onboarding
        // O LoginController@selectTenantAfterLogin seria ignorado de qualquer forma
        // porque não é chamado aqui, mas o middleware e dashboard farão o roteamento correto.

        return redirect()->intended('/dashboard');
    }
}
