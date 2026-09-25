<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\DarkWeb\TorClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function __construct(protected TorClient $torClient) {}

    /**
     * Display the tactical DarkDump login console.
     */
    public function showLogin(): Response|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        // Ensure at least one operator exists for immediate evaluation
        if (User::count() === 0) {
            User::create([
                'name' => 'Operator Prime',
                'email' => 'operator@darkdump.local',
                'password' => Hash::make('DarkDump@2026!'),
            ]);
        }

        return Inertia::render('Auth/Login', [
            'torActive' => $this->torClient->isTorAvailable(),
            'demoCredentials' => [
                'email' => 'operator@darkdump.local',
                'password' => 'DarkDump@2026!',
            ],
            'status' => session('status'),
        ]);
    }

    /**
     * Authenticate operator credentials and initiate workstation session.
     */
    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'login' => 'required|string|max:255',
            'password' => 'required|string',
            'remember' => 'nullable|boolean',
        ]);

        $login = trim($request->input('login'));
        $password = (string) $request->input('password');
        $remember = (bool) $request->input('remember', false);

        $throttleKey = Str::transliterate(Str::lower($login).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'login' => "Too many authentication failures. Station locked for {$seconds} seconds.",
            ]);
        }

        // Determine if input is email or operator handle
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'name';
        $credentials = [$field => $login, 'password' => $password];

        if (! Auth::attempt($credentials, $remember)) {
            // Attempt secondary handle fallback
            $altField = ($field === 'email') ? 'name' : 'email';
            if (! Auth::attempt([$altField => $login, 'password' => $password], $remember)) {
                RateLimiter::hit($throttleKey, 60);

                throw ValidationException::withMessages([
                    'login' => 'Access Denied: Invalid cryptographic key or operator identifier.',
                ]);
            }
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Terminate the operator session and destroy workstation authentication tokens.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
