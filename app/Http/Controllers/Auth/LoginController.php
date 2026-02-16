<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * Show the login form.
     *
     * @return \Illuminate\View\View
     */
    public function showLoginForm()
    {
        return view('auth.login');
    }

    /**
     * Handle a login request to the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\Response
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (Auth::attempt($request->only('email', 'password'), $request->filled('remember'))) {
            $request->session()->regenerate();
            $user = Auth::user();

            // Set user's preferred language as the session locale
            if ($user->preferred_language) {
                $availableLocales = ['en', 'fr', 'pt'];
                if (in_array($user->preferred_language, $availableLocales)) {
                    $request->session()->put('locale', $user->preferred_language);
                    app()->setLocale($user->preferred_language);
                }
            }

            // If super admin, redirect to super admin dashboard
            if ($user->isSuperAdmin()) {
                return redirect()->intended(route('superadmin.dashboard'));
            }

            // For regular users, set their first business unit as current if not set
            if (!$user->current_business_unit_id) {
                $firstBusinessUnit = $user->businessUnits()->first();
                if ($firstBusinessUnit) {
                    $user->update(['current_business_unit_id' => $firstBusinessUnit->id]);
                }
            }

            // If user has a business unit, redirect to business dashboard
            if ($user->current_business_unit_id) {
                return redirect()->intended(route('business.dashboard'));
            }

            // Otherwise, redirect to home (user has no business unit assigned)
            return redirect()->intended('/')->with('error', 'No business unit assigned. Please contact administrator.');
        }

        throw ValidationException::withMessages([
            'email' => [__('auth.failed')],
        ]);
    }

    /**
     * Log the user out of the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
