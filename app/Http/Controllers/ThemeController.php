<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ThemeController extends Controller
{
    public function switchTheme(Request $request)
    {
        $theme = $request->input('theme', 'dark');
        
        if (!in_array($theme, ['dark', 'light'])) {
            $theme = 'dark';
        }
        
        session(['theme' => $theme]);
        
        return redirect()->back();
    }
}

