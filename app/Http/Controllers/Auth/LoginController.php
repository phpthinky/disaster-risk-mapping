<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }

    /**
     * Use 'username' field instead of 'email' for authentication.
     */
    public function username(): string
    {
        return 'username';
    }

    /**
     * Redirect to role-specific dashboard after login.
     */
    protected function redirectTo(): string
    {
        return match (auth()->user()->role) {
            'admin'          => route('dashboard'),
            'division_chief' => route('dashboard.division'),
            'barangay_staff' => route('dashboard.barangay'),
            default          => route('dashboard'),
        };
    }

    /**
     * Check is_active flag after credentials are validated.
     */
    protected function authenticated(Request $request, $user): void
    {
        if (! $user->is_active) {
            $this->guard()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'username' => 'Your account has been deactivated. Please contact the administrator.',
            ]);
        }
    }
}
