<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BusinessUnit;
use App\Models\User;
use App\Models\Role;
use App\Models\CoreBankingSystem;
use App\Models\TransactionType;
use App\Services\KatoService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class SuperAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'superadmin']);
    }

    public function dashboard()
    {
        $businessUnits = BusinessUnit::withCount('users')->latest()->get();
        $transactionTypesCount = TransactionType::global()->count();
        return view('superadmin.dashboard', compact('businessUnits', 'transactionTypesCount'));
    }

    public function createBusinessUnit()
    {
        $coreBankingSystems = CoreBankingSystem::where('is_active', true)->orderBy('name')->get();
        $globalTransactionTypes = TransactionType::global()->active()->orderBy('name')->get();
        return view('superadmin.business-units.create', compact('coreBankingSystems', 'globalTransactionTypes'));
    }

    public function storeBusinessUnit(Request $request)
    {
        $validated = $request->validate(array_merge([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'account' => 'nullable|string|max:255',
            'tax_number' => 'nullable|string|max:100',
            'mailing_address' => 'nullable|string',
            'relation_manager_phone' => 'nullable|string|max:50',
            'relation_manager_email' => 'nullable|email',
            'sms_sender_number' => 'nullable|string|max:50|regex:/^tel:\+\d+$/',
            'core_banking_system_id' => 'nullable|exists:core_banking_systems,id',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ], $this->katoCredentialValidationRules()));

        $validated['slug'] = Str::slug($validated['name']);
        $validated['is_active'] = true;
        if ($this->isKatoCbsId($validated['core_banking_system_id'] ?? null)) {
            $validated = array_merge($validated, $this->katoCredentialFieldsFromRequest($request));
        }

        // Handle logo upload
        if ($request->hasFile('logo')) {
            $logo = $request->file('logo');
            $logoName = time() . '_' . Str::slug($validated['name']) . '.' . $logo->getClientOriginalExtension();
            $logoPath = $logo->storeAs('business-logos', $logoName, 'public');
            $validated['logo'] = $logoPath;
        }

        $businessUnit = BusinessUnit::create($validated);

        // Sync assigned transaction types
        if ($request->has('transaction_types')) {
            $transactionTypeIds = $request->input('transaction_types', []);
            // Validate that all transaction types are global
            $globalTransactionTypeIds = TransactionType::global()->whereIn('id', $transactionTypeIds)->pluck('id')->toArray();
            $businessUnit->assignedTransactionTypes()->sync($globalTransactionTypeIds);
        }

        $katoRedirect = $this->attemptInitialKatoRegistration($businessUnit, 'superadmin.dashboard');
        if ($katoRedirect) {
            return $katoRedirect;
        }

        return redirect()->route('superadmin.dashboard')
            ->with('success', __('common.created_successfully'));
    }

    public function editBusinessUnit($id)
    {
        $businessUnit = BusinessUnit::with(['coreBankingSystem', 'cbsCredentials'])->findOrFail($id);
        $coreBankingSystems = CoreBankingSystem::where('is_active', true)->orderBy('name')->get();
        $globalTransactionTypes = TransactionType::global()->active()->orderBy('name')->get();
        $assignedTransactionTypeIds = $businessUnit->assignedTransactionTypes()->pluck('transaction_types.id')->toArray();
        return view('superadmin.business-units.edit', compact('businessUnit', 'coreBankingSystems', 'globalTransactionTypes', 'assignedTransactionTypeIds'));
    }

    public function updateBusinessUnit(Request $request, $id)
    {
        $businessUnit = BusinessUnit::findOrFail($id);

        $oldKatoUsername = $businessUnit->kato_admin_username;
        $passwordChanging = $request->filled('kato_admin_password');

        $validated = $request->validate(array_merge([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'account' => 'nullable|string|max:255',
            'tax_number' => 'nullable|string|max:100',
            'mailing_address' => 'nullable|string',
            'relation_manager_phone' => 'nullable|string|max:50',
            'relation_manager_email' => 'nullable|email',
            'sms_sender_number' => 'nullable|string|max:50|regex:/^tel:\+\d+$/',
            'core_banking_system_id' => 'nullable|exists:core_banking_systems,id',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'transaction_types' => 'nullable|array',
            'transaction_types.*' => 'exists:transaction_types,id',
        ], $this->katoCredentialValidationRules(), $this->cbsCredentialValidationRules()));

        // Handle logo upload
        if ($request->hasFile('logo')) {
            // Delete old logo if exists
            if ($businessUnit->logo && Storage::disk('public')->exists($businessUnit->logo)) {
                Storage::disk('public')->delete($businessUnit->logo);
            }
            
            $logo = $request->file('logo');
            $logoName = time() . '_' . Str::slug($validated['name']) . '.' . $logo->getClientOriginalExtension();
            $logoPath = $logo->storeAs('business-logos', $logoName, 'public');
            $validated['logo'] = $logoPath;
        } else {
            // Keep existing logo if no new one uploaded
            unset($validated['logo']);
        }

        // Check if CBS was changed (before update)
        $oldCbsId = $businessUnit->core_banking_system_id;
        $newCbsId = $validated['core_banking_system_id'] ?? null;
        $cbsChanged = $oldCbsId != $newCbsId;
        
        $updateData = $validated;
        if ($this->isKatoCbsId($validated['core_banking_system_id'] ?? null)) {
            $updateData = array_merge($updateData, $this->katoCredentialFieldsFromRequest($request, true));
        }
        unset(
            $updateData['cbs_username'],
            $updateData['cbs_password'],
            $updateData['cbs_bearer_token'],
            $updateData['cbs_extra_headers']
        );
        $businessUnit->update($updateData);

        if ($this->isConfigurableCbsId($validated['core_banking_system_id'] ?? null)) {
            $this->syncCbsCredentials($businessUnit, $request);
        } elseif ($businessUnit->cbsCredentials) {
            $businessUnit->cbsCredentials->delete();
        }
        
        // Sync assigned transaction types
        if ($request->has('transaction_types')) {
            $transactionTypeIds = $request->input('transaction_types', []);
            // Validate that all transaction types are global
            $globalTransactionTypeIds = TransactionType::global()->whereIn('id', $transactionTypeIds)->pluck('id')->toArray();
            $businessUnit->assignedTransactionTypes()->sync($globalTransactionTypeIds);
        } else {
            // If no transaction types provided, clear all assignments
            $businessUnit->assignedTransactionTypes()->sync([]);
        }
        
        $businessUnit->refresh();

        if ($cbsChanged && $oldCbsId) {
            $oldCbs = CoreBankingSystem::find($oldCbsId);
            if ($oldCbs && strtolower($oldCbs->code) === 'kato') {
                $this->clearKatoRegistration($businessUnit);
                $businessUnit->refresh();
            }
        }

        if ($businessUnit->core_banking_system_id && ! $businessUnit->kato_company_id) {
            $katoRedirect = $this->attemptInitialKatoRegistration(
                $businessUnit,
                'superadmin.business-units.edit',
                ['businessUnit' => $businessUnit->id]
            );
            if ($katoRedirect) {
                return $katoRedirect;
            }
        }

        $credentialsChanged = $oldKatoUsername !== $businessUnit->kato_admin_username || $passwordChanging;
        $loginWarning = $this->refreshKatoTokenAfterCredentialChange($businessUnit, $credentialsChanged);

        $flash = ['success' => __('common.updated_successfully')];
        if ($loginWarning) {
            $flash = ['warning' => __('common.updated_successfully').'. '.$loginWarning];
        } elseif ($credentialsChanged && $businessUnit->kato_company_id) {
            $flash = ['success' => __('common.updated_successfully').'. '.__('common.kato_credentials_updated')];
        }

        return redirect()->route('superadmin.business-units.edit', $businessUnit->id)->with($flash);
    }

    /**
     * Manually refresh KATO JWT using stored company admin credentials.
     */
    public function refreshKatoToken($id)
    {
        $businessUnit = BusinessUnit::with('coreBankingSystem')->findOrFail($id);

        $cbs = $businessUnit->coreBankingSystem;
        if (! $cbs || strtolower($cbs->code) !== 'kato') {
            return redirect()->back()
                ->with('error', __('common.kato_core_banking_not_enabled'));
        }

        if (! $businessUnit->kato_company_id) {
            return redirect()->back()
                ->with('error', __('common.kato_company_not_registered'));
        }

        if (! $businessUnit->hasKatoRefreshCredentials()) {
            return redirect()->back()
                ->with('error', __('common.kato_admin_credentials_required'));
        }

        $refreshResult = (new KatoService())->refreshKatoToken($businessUnit);
        if (! $refreshResult['success']) {
            return redirect()->route('superadmin.business-units.edit', $businessUnit->id)
                ->with('warning', $refreshResult['error'] ?? __('common.kato_login_failed'));
        }

        return redirect()->route('superadmin.business-units.edit', $businessUnit->id)
            ->with('success', __('common.kato_token_refreshed'));
    }

    /**
     * Restore KATO admin username from the stored registration API response.
     */
    public function restoreKatoUsername($id)
    {
        $businessUnit = BusinessUnit::with('coreBankingSystem')->findOrFail($id);

        $cbs = $businessUnit->coreBankingSystem;
        if (! $cbs || strtolower($cbs->code) !== 'kato') {
            return redirect()->back()
                ->with('error', __('common.kato_core_banking_not_enabled'));
        }

        if (! $businessUnit->kato_company_id) {
            return redirect()->back()
                ->with('error', __('common.kato_company_not_registered'));
        }

        $registeredUsername = $businessUnit->getKatoRegisteredAdminUsername();
        if (! $registeredUsername) {
            return redirect()->route('superadmin.business-units.edit', $businessUnit->id)
                ->with('warning', __('common.kato_registered_username_not_found'));
        }

        $businessUnit->update(['kato_admin_username' => $registeredUsername]);

        return redirect()->route('superadmin.business-units.edit', $businessUnit->id)
            ->with('success', __('common.kato_username_restored', ['username' => $registeredUsername]));
    }

    /**
     * Manually register business unit with KATO
     */
    public function registerWithKato($id)
    {
        $businessUnit = BusinessUnit::findOrFail($id);
        
        if (!$businessUnit->core_banking_system_id) {
            return redirect()->back()
                ->with('error', __('common.kato_company_not_registered'));
        }

        $cbs = CoreBankingSystem::find($businessUnit->core_banking_system_id);
        
        if (!$cbs || strtolower($cbs->code) !== 'kato' || !$cbs->base_url) {
            return redirect()->back()
                ->with('error', __('common.kato_base_url_not_configured'));
        }

        // Check if already registered (has companyId)
        if ($businessUnit->kato_company_id) {
            return redirect()->back()
                ->with('info', __('common.kato_already_registered'));
        }

        $katoService = new KatoService();
        $result = $katoService->registerCompany($businessUnit, $cbs);
        
        if ($result['success']) {
            $this->applyKatoRegistrationResult($businessUnit, $result);

            return redirect()->back()
                ->with('success', __('common.kato_registration_success'))
                ->with('kato_credentials', $this->katoCredentialsForFlash($result));
        }

        $this->clearKatoRegistration($businessUnit);
        $businessUnit->update(['kato_registration_error' => $result['error']]);
        Log::error('KATO registration failed for business unit', [
            'business_unit_id' => $businessUnit->id,
            'error' => $result['error'],
        ]);

        return redirect()->back()
            ->with('error', __('common.kato_registration_failed').': '.$result['error']);
    }

    public function indexBusinessUnits()
    {
        $businessUnits = BusinessUnit::with('coreBankingSystem')->withCount('users')->latest()->paginate(15);
        return view('superadmin.business-units.index', compact('businessUnits'));
    }

    public function switchBusinessUnit($businessUnitId)
    {
        $businessUnit = BusinessUnit::findOrFail($businessUnitId);
        $user = auth()->user();

        if ($user->isSuperAdmin()) {
            $user->update(['current_business_unit_id' => $businessUnitId]);
            return redirect()->route('business.dashboard')
                ->with('success', __('common.switch_business') . ': ' . $businessUnit->name);
        }

        abort(403);
    }

    /**
     * Switch back to superadmin dashboard from business view.
     */
    public function switchBackToSuperadmin()
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin()) {
            abort(403);
        }

        $user->update(['current_business_unit_id' => null]);
        
        return redirect()->route('superadmin.dashboard')
            ->with('success', __('common.switched_back_to_superadmin'));
    }

    public function showBusinessUsers($businessUnitId)
    {
        $businessUnit = BusinessUnit::with('users')->findOrFail($businessUnitId);
        $roles = $businessUnit->roles;
        return view('superadmin.business-units.users', compact('businessUnit', 'roles'));
    }

    public function addUserToBusiness(Request $request, $businessUnitId)
    {
        $businessUnit = BusinessUnit::findOrFail($businessUnitId);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role_id' => 'nullable|exists:roles,id',
        ]);

        // Create the user
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'preferred_language' => 'en',
        ]);

        // Attach user to business unit
        $businessUnit->users()->attach($user->id, [
            'role_id' => $validated['role_id'] ?? null,
            'is_active' => true,
        ]);

        // Set this as the user's current business unit if they don't have one
        if (!$user->current_business_unit_id) {
            $user->update(['current_business_unit_id' => $businessUnitId]);
        }

        return redirect()->back()->with('success', __('common.created_successfully'));
    }

    public function createRole($businessUnitId)
    {
        $businessUnit = BusinessUnit::findOrFail($businessUnitId);
        return view('superadmin.business-units.create-role', compact('businessUnit'));
    }

    public function storeRole(Request $request, $businessUnitId)
    {
        $businessUnit = BusinessUnit::findOrFail($businessUnitId);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $validated['slug'] = Str::slug($validated['name']);
        $validated['business_unit_id'] = $businessUnitId;

        Role::create($validated);

        return redirect()->route('superadmin.business-units.users', $businessUnitId)
            ->with('success', __('common.created_successfully'));
    }

    /**
     * Show the transaction types assignment interface for a business unit.
     */
    public function manageBusinessTransactionTypes($businessUnitId)
    {
        $businessUnit = BusinessUnit::findOrFail($businessUnitId);
        $globalTransactionTypes = TransactionType::global()->active()->orderBy('name')->get();
        $assignedTransactionTypeIds = $businessUnit->assignedTransactionTypes()->pluck('transaction_types.id')->toArray();
        
        return view('superadmin.business-units.transaction-types', compact('businessUnit', 'globalTransactionTypes', 'assignedTransactionTypeIds'));
    }

    /**
     * Update transaction types assignment for a business unit.
     */
    public function updateBusinessTransactionTypes(Request $request, $businessUnitId)
    {
        $businessUnit = BusinessUnit::findOrFail($businessUnitId);

        $validated = $request->validate([
            'transaction_types' => 'nullable|array',
            'transaction_types.*' => 'exists:transaction_types,id',
        ]);

        $transactionTypeIds = $validated['transaction_types'] ?? [];
        
        // Sync the assigned transaction types
        $businessUnit->assignedTransactionTypes()->sync($transactionTypeIds);

        return redirect()->route('superadmin.business-units.transaction-types', $businessUnitId)
            ->with('success', __('common.updated_successfully'));
    }

    private function isKatoCbsId(?int $cbsId): bool
    {
        if (! $cbsId) {
            return false;
        }

        $cbs = CoreBankingSystem::find($cbsId);

        return $cbs && $cbs->isKatoDriver();
    }

    private function isConfigurableCbsId(?int $cbsId): bool
    {
        if (! $cbsId) {
            return false;
        }

        $cbs = CoreBankingSystem::find($cbsId);

        return $cbs && $cbs->isConfigurableDriver();
    }

    /**
     * @return array<string, string>
     */
    private function cbsCredentialValidationRules(): array
    {
        return [
            'cbs_username' => 'nullable|string|max:255',
            'cbs_password' => 'nullable|string|max:255',
            'cbs_bearer_token' => 'nullable|string',
            'cbs_extra_headers' => 'nullable|string',
        ];
    }

    private function syncCbsCredentials(BusinessUnit $businessUnit, Request $request): void
    {
        $extraHeaders = null;
        if ($request->filled('cbs_extra_headers')) {
            $decoded = json_decode($request->input('cbs_extra_headers'), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $extraHeaders = $decoded;
            }
        }

        $credentials = $businessUnit->cbsCredentials ?: new \App\Models\BusinessUnitCbsCredential([
            'business_unit_id' => $businessUnit->id,
        ]);

        $credentials->username = $request->input('cbs_username');

        if ($request->filled('cbs_password')) {
            $credentials->password = $request->input('cbs_password');
        }

        if ($request->filled('cbs_bearer_token')) {
            $credentials->bearer_token = $request->input('cbs_bearer_token');
        }

        $credentials->extra_headers = $extraHeaders;
        $credentials->save();
    }

    /**
     * @return array<string, string>
     */
    private function katoCredentialValidationRules(): array
    {
        return [
            'kato_admin_name' => 'nullable|string|max:255',
            'kato_admin_username' => 'nullable|string|max:255',
            'kato_admin_password' => 'nullable|string|min:8',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function katoCredentialFieldsFromRequest(Request $request, bool $passwordOptionalOnUpdate = false): array
    {
        $fields = [];

        if ($passwordOptionalOnUpdate) {
            if ($request->filled('kato_admin_name')) {
                $fields['kato_admin_name'] = $request->input('kato_admin_name');
            }
            if ($request->filled('kato_admin_username')) {
                $fields['kato_admin_username'] = $request->input('kato_admin_username');
            }
        } else {
            $fields['kato_admin_name'] = $request->input('kato_admin_name');
            $fields['kato_admin_username'] = $request->input('kato_admin_username');
        }

        if ($request->filled('kato_admin_password')) {
            $fields['kato_admin_password'] = $request->input('kato_admin_password');
        } elseif (! $passwordOptionalOnUpdate && $request->has('kato_admin_password')) {
            $fields['kato_admin_password'] = null;
        }

        return $fields;
    }

    private function applyKatoRegistrationResult(BusinessUnit $businessUnit, array $result): void
    {
        $registrationData = $result['data'] ?? [];

        $businessUnit->update([
            'kato_company_id' => $registrationData['companyId'] ?? null,
            'kato_admin_user_id' => $registrationData['adminUserId'] ?? null,
            'kato_token' => $registrationData['token'] ?? null,
            'kato_token_expires_at' => ! empty($registrationData['expiresIn'])
                ? now()->addSeconds((int) $registrationData['expiresIn'])
                : null,
            'kato_admin_username' => $registrationData['adminUsername'] ?? null,
            'kato_admin_password' => $registrationData['adminPassword'] ?? null,
            'kato_admin_name' => $registrationData['adminName'] ?? $businessUnit->kato_admin_name,
            'kato_registered_at' => now(),
            'kato_registration_error' => null,
            'kato_api_response' => $result['full_response'] ?? null,
        ]);
    }

    private function clearKatoRegistration(BusinessUnit $businessUnit): void
    {
        $businessUnit->update([
            'kato_company_id' => null,
            'kato_admin_user_id' => null,
            'kato_token' => null,
            'kato_token_expires_at' => null,
            'kato_admin_username' => null,
            'kato_admin_name' => null,
            'kato_admin_password' => null,
            'kato_registered_at' => null,
            'kato_registration_error' => null,
            'kato_api_response' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $routeParams
     * @return \Illuminate\Http\RedirectResponse|null
     */
    private function attemptInitialKatoRegistration(
        BusinessUnit $businessUnit,
        string $redirectRoute,
        array $routeParams = []
    ) {
        if (! $businessUnit->core_banking_system_id || $businessUnit->kato_company_id) {
            return null;
        }

        $cbs = CoreBankingSystem::find($businessUnit->core_banking_system_id);
        if (! $cbs || strtolower($cbs->code) !== 'kato') {
            return null;
        }

        if (! $cbs->base_url && ! config('kato.default_base_url')) {
            return null;
        }

        $katoService = new KatoService();
        $result = $katoService->registerCompany($businessUnit, $cbs);

        if ($result['success']) {
            $this->applyKatoRegistrationResult($businessUnit, $result);

            return redirect()->route($redirectRoute, $routeParams)
                ->with('success', __('common.kato_registration_success'))
                ->with('kato_credentials', $this->katoCredentialsForFlash($result));
        }

        $this->clearKatoRegistration($businessUnit);
        $businessUnit->update(['kato_registration_error' => $result['error']]);
        Log::error('KATO registration failed for business unit', [
            'business_unit_id' => $businessUnit->id,
            'error' => $result['error'],
        ]);

        return redirect()->route($redirectRoute, $routeParams)
            ->with('warning', __('common.kato_registration_failed').': '.$result['error']);
    }

    private function refreshKatoTokenAfterCredentialChange(
        BusinessUnit $businessUnit,
        bool $credentialsChanged
    ): ?string {
        if (! $credentialsChanged || ! $businessUnit->kato_company_id) {
            return null;
        }

        $cbs = $businessUnit->coreBankingSystem ?? CoreBankingSystem::find($businessUnit->core_banking_system_id);
        if (! $cbs || strtolower($cbs->code) !== 'kato') {
            return null;
        }

        $refreshResult = (new KatoService())->refreshKatoToken($businessUnit);
        if ($refreshResult['success']) {
            return null;
        }

        return __('common.kato_login_failed').': '.($refreshResult['error'] ?? '');
    }

    /**
     * @return array<string, string|null>
     */
    private function katoCredentialsForFlash(array $result): array
    {
        return [
            'adminName' => $result['data']['adminName'] ?? null,
            'adminUsername' => $result['data']['adminUsername'] ?? null,
            'adminPassword' => $result['data']['adminPassword'] ?? null,
        ];
    }
}
