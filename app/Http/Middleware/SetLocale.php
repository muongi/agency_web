<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $availableLocales = ['en', 'fr', 'pt'];
        $locale = 'en'; // Default

        // Priority 1: Check if locale is in URL parameter
        if ($request->has('lang') && in_array($request->lang, $availableLocales)) {
            $locale = $request->lang;
            Session::put('locale', $locale);
        }
        // Priority 2: Check session
        elseif (Session::has('locale') && in_array(Session::get('locale'), $availableLocales)) {
            $locale = Session::get('locale');
        }
        // Priority 3: Check authenticated user's preference
        elseif (Auth::check() && Auth::user()->preferred_language) {
            $locale = Auth::user()->preferred_language;
            Session::put('locale', $locale);
        }
        // Priority 4: Check browser language
        elseif ($request->hasHeader('Accept-Language')) {
            $browserLocale = substr($request->header('Accept-Language'), 0, 2);
            if (in_array($browserLocale, $availableLocales)) {
                $locale = $browserLocale;
            }
        }

        App::setLocale($locale);
        
        return $next($request);
    }
}

