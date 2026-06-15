<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CoreBankingSystem;
use App\Support\KatoAgencyUrl;
use Illuminate\Support\Str;

class CoreBankingSystemController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'superadmin']);
    }

    /**
     * Display a listing of core banking systems
     */
    public function index()
    {
        $cbs = CoreBankingSystem::withCount('businessUnits')
            ->latest()
            ->paginate(20);
        
        return view('superadmin.core-banking-systems.index', compact('cbs'));
    }

    /**
     * Show the form for creating a new core banking system
     */
    public function create()
    {
        return view('superadmin.core-banking-systems.create');
    }

    /**
     * Store a newly created core banking system
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:core_banking_systems,name',
            'code' => 'nullable|string|max:50|unique:core_banking_systems,code',
            'description' => 'nullable|string',
            'base_url' => 'nullable|string|max:255|url',
            'driver' => 'required|in:kato,configurable',
            'auth_type' => 'required|in:jwt_bearer,basic,oauth_password',
            'timeout_seconds' => 'nullable|integer|min:5|max:300',
            'auth_token_endpoint' => 'nullable|string|max:255',
            'auth_token_path' => 'nullable|string|max:255',
            'auth_username_field' => 'nullable|string|max:100',
            'auth_password_field' => 'nullable|string|max:100',
            'auth_token_expires_path' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['timeout_seconds'] = $validated['timeout_seconds'] ?? 30;
        $validated['auth_config'] = $this->buildAuthConfig($request);

        if (($validated['driver'] ?? '') === 'kato') {
            $validated['auth_type'] = 'jwt_bearer';
        }

        unset(
            $validated['auth_token_endpoint'],
            $validated['auth_token_path'],
            $validated['auth_username_field'],
            $validated['auth_password_field'],
            $validated['auth_token_expires_path']
        );

        if (array_key_exists('base_url', $validated)) {
            $validated['base_url'] = $this->normalizeBaseUrl($validated['base_url'] ?? null, $validated['code'] ?? null);
        }

        CoreBankingSystem::create($validated);

        return redirect()->route('superadmin.core-banking-systems.index')
            ->with('success', __('common.created_successfully'));
    }

    /**
     * Show the form for editing the specified core banking system
     */
    public function edit($id)
    {
        $cbs = CoreBankingSystem::findOrFail($id);
        return view('superadmin.core-banking-systems.edit', compact('cbs'));
    }

    /**
     * Update the specified core banking system
     */
    public function update(Request $request, $id)
    {
        $cbs = CoreBankingSystem::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:core_banking_systems,name,' . $id,
            'code' => 'nullable|string|max:50|unique:core_banking_systems,code,' . $id,
            'description' => 'nullable|string',
            'base_url' => 'nullable|string|max:255|url',
            'driver' => 'required|in:kato,configurable',
            'auth_type' => 'required|in:jwt_bearer,basic,oauth_password',
            'timeout_seconds' => 'nullable|integer|min:5|max:300',
            'auth_token_endpoint' => 'nullable|string|max:255',
            'auth_token_path' => 'nullable|string|max:255',
            'auth_username_field' => 'nullable|string|max:100',
            'auth_password_field' => 'nullable|string|max:100',
            'auth_token_expires_path' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $validated['is_active'] ?? $cbs->is_active;
        $validated['timeout_seconds'] = $validated['timeout_seconds'] ?? 30;
        $validated['auth_config'] = $this->buildAuthConfig($request);

        if (($validated['driver'] ?? '') === 'kato') {
            $validated['auth_type'] = 'jwt_bearer';
        }

        unset(
            $validated['auth_token_endpoint'],
            $validated['auth_token_path'],
            $validated['auth_username_field'],
            $validated['auth_password_field'],
            $validated['auth_token_expires_path']
        );

        if (array_key_exists('base_url', $validated)) {
            $validated['base_url'] = $this->normalizeBaseUrl($validated['base_url'] ?? null, $validated['code'] ?? $cbs->code);
        }

        $cbs->update($validated);

        return redirect()->route('superadmin.core-banking-systems.index')
            ->with('success', __('common.updated_successfully'));
    }

    /**
     * Remove the specified core banking system
     */
    public function destroy($id)
    {
        $cbs = CoreBankingSystem::findOrFail($id);

        // Check if any business units are using this CBS
        if ($cbs->businessUnits()->count() > 0) {
            return redirect()->route('superadmin.core-banking-systems.index')
                ->with('error', __('common.cbs_in_use_cannot_delete'));
        }

        $cbs->delete();

        return redirect()->route('superadmin.core-banking-systems.index')
            ->with('success', __('common.deleted_successfully'));
    }

    /**
     * Normalize base_url into the "API root only" convention.
     *
     * For KATO, older configs may have stored full endpoints like
     * ".../company/register" or ".../agency/onboarding/register".
     */
    private function normalizeBaseUrl(?string $url, ?string $code): ?string
    {
        if ($url === null) {
            return null;
        }

        $url = trim($url);
        $url = rtrim($url, '/');

        if (Str::lower((string) $code) !== 'kato') {
            return $url;
        }

        return KatoAgencyUrl::resolveAgencyBase($url) ?? $url;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAuthConfig(Request $request): array
    {
        return array_filter([
            'token_endpoint' => $request->input('auth_token_endpoint'),
            'token_path' => $request->input('auth_token_path'),
            'username_field' => $request->input('auth_username_field'),
            'password_field' => $request->input('auth_password_field'),
            'token_expires_path' => $request->input('auth_token_expires_path'),
        ], fn ($value) => $value !== null && $value !== '');
    }
}