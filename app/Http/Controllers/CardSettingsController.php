<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CardSettings;
use App\Models\BusinessUnit;

class CardSettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'business.access']);
    }

    /**
     * Display card settings.
     */
    public function index()
    {
        $businessUnit = auth()->user()->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        // Check permission
        if (!auth()->user()->hasPermission('manage-card-settings', $businessUnit->id)) {
            abort(403, __('common.unauthorized_access'));
        }

        $cardSettings = CardSettings::getOrCreateForBusinessUnit($businessUnit->id);

        return view('cards.settings', compact('cardSettings', 'businessUnit'));
    }

    /**
     * Update card settings.
     */
    public function update(Request $request)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        // Check permission
        if (!auth()->user()->hasPermission('manage-card-settings', $businessUnit->id)) {
            abort(403, __('common.unauthorized_access'));
        }

        $validated = $request->validate([
            'card_prefix' => 'required|string|max:20',
            'auto_increment_length' => 'required|integer|min:4|max:10',
            'default_expiration_years' => 'required|integer|min:1|max:10',
            'default_daily_transaction_limit' => 'nullable|numeric|min:0',
            'default_daily_withdrawal_limit' => 'nullable|numeric|min:0',
        ]);

        $cardSettings = CardSettings::getOrCreateForBusinessUnit($businessUnit->id);
        $cardSettings->update($validated);

        return redirect()->route('cards.settings')
            ->with('success', __('common.card_settings_updated_successfully'));
    }
}
