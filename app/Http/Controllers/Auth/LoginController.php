<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Services\TenantResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class LoginController extends Controller
{
    /**
     * Display the login form.
     */
    public function showLoginForm(): Response
    {
        return Inertia::render('Auth/Login');
    }

    /**
     * Handle an incoming login request.
     */
    public function login(Request $request, TenantResolver $resolver): RedirectResponse
    {
        $this->ensureIsNotRateLimited($request);

        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey($request));

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));

        $request->session()->regenerate();

        $this->selectTenantAfterLogin($request, $resolver);

        return redirect()->intended('/dashboard');
    }

    /**
     * Handle logout.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    /**
     * After login, check the user's active tenant memberships and
     * auto-select if exactly one exists (ADR-005).
     */
    private function selectTenantAfterLogin(Request $request, TenantResolver $resolver): void
    {
        $user = $request->user();

        $activeMemberships = $user->memberships()
            ->where('is_active', true)
            ->whereHas('tenant', fn ($q) => $q->where('is_active', true))
            ->get();

        if ($activeMemberships->count() === 1) {
            $membership = $activeMemberships->first();
            $resolved = $resolver->resolve($membership->tenant_id, $user);

            if ($resolved) {
                $request->session()->put('tenant_id', $resolved->tenant->id);
                return;
            }
        }

        // 0 or N>1 memberships: no tenant auto-selected.
        // tenant_id remains absent from session.
        $request->session()->forget('tenant_id');
    }

    /**
     * Ensure the login request is not rate limited.
     */
    private function ensureIsNotRateLimited(Request $request): void
    {
        $key = $this->throttleKey($request);

        if (! RateLimiter::tooManyAttempts($key, 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    private function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower($request->string('email')) . '|' . $request->ip());
    }
}
