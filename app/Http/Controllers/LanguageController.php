<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class LanguageController extends Controller
{
    public function switchLanguage(Request $request, $locale)
    {
        $availableLocales = ['en', 'fr', 'pt'];
        
        if (!in_array($locale, $availableLocales)) {
            return redirect()->back()->with('error', __('common.invalid_language'));
        }

        // Set locale in session
        Session::put('locale', $locale);
        App::setLocale($locale);

        // Update user's preferred language if authenticated
        if (Auth::check()) {
            Auth::user()->update(['preferred_language' => $locale]);
        }

        return redirect()->back()->with('success', __('common.language_changed'));
    }
}

