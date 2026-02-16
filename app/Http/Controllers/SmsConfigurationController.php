<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SmsConfiguration;
use Illuminate\Support\Facades\Log;

class SmsConfigurationController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'superadmin']);
    }

    /**
     * Display the SMS configuration
     */
    public function index()
    {
        $config = SmsConfiguration::getActive();
        
        if (!$config) {
            return redirect()->route('superadmin.sms.config.create');
        }

        return view('sms.config.index', compact('config'));
    }

    /**
     * Show the form for creating SMS configuration
     */
    public function create()
    {
        $config = SmsConfiguration::getActive();
        
        if ($config) {
            return redirect()->route('superadmin.sms.config.edit');
        }

        return view('sms.config.create');
    }

    /**
     * Store a newly created SMS configuration
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|string|max:255',
            'client_secret' => 'required|string',
            'jwt_token' => 'nullable|string',
            'api_endpoint' => 'nullable|string|url',
            'sender_name' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ]);

        // Deactivate any existing configurations
        SmsConfiguration::where('is_active', true)->update(['is_active' => false]);

        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['api_endpoint'] = $validated['api_endpoint'] ?? 'https://api.orange.com/smsmessaging/v1';

        SmsConfiguration::create($validated);

        return redirect()->route('superadmin.sms.config.index')
            ->with('success', __('common.sms_configuration_created'));
    }

    /**
     * Show the form for editing SMS configuration
     */
    public function edit()
    {
        $config = SmsConfiguration::getActive();
        
        if (!$config) {
            return redirect()->route('superadmin.sms.config.create');
        }

        return view('sms.config.edit', compact('config'));
    }

    /**
     * Update the SMS configuration
     */
    public function update(Request $request)
    {
        $config = SmsConfiguration::getActive();
        
        if (!$config) {
            return redirect()->route('superadmin.sms.config.create');
        }

        $validated = $request->validate([
            'client_id' => 'required|string|max:255',
            'client_secret' => 'nullable|string', // Allow null for update
            'jwt_token' => 'nullable|string',
            'api_endpoint' => 'nullable|string|url',
            'sender_name' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;
        
        // Only update client_secret if provided
        if (empty($validated['client_secret'])) {
            unset($validated['client_secret']);
        }
        
        // Only update jwt_token if provided
        if (empty($validated['jwt_token'])) {
            unset($validated['jwt_token']);
        }

        $config->update($validated);

        return redirect()->route('superadmin.sms.config.index')
            ->with('success', __('common.sms_configuration_updated'));
    }

    /**
     * Test SMS configuration
     */
    public function test(Request $request)
    {
        $request->validate([
            'test_number' => 'required|string|max:20',
        ]);

        // TODO: Implement SMS test sending
        // For now, just check if configuration is valid
        
        $config = SmsConfiguration::getActive();
        
        if (!$config || !SmsConfiguration::isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => __('common.sms_configuration_not_found')
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => __('common.sms_configuration_valid')
        ]);
    }
}
