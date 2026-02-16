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
        $validated = $request->validate([
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
        ]);

        $validated['slug'] = Str::slug($validated['name']);
        $validated['is_active'] = true;

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

        // Attempt KATO registration if CBS is KATO and not already registered
        if ($businessUnit->core_banking_system_id && !$businessUnit->kato_company_id) {
            $cbs = CoreBankingSystem::find($businessUnit->core_banking_system_id);
            if ($cbs && strtolower($cbs->code) === 'kato' && $cbs->base_url) {
                $katoService = new KatoService();
                $result = $katoService->registerCompany($businessUnit, $cbs);
                
                if ($result['success']) {
                    $businessUnit->update([
                        'kato_company_id' => $result['data']['companyId'],
                        'kato_admin_user_id' => $result['data']['adminUserId'],
                        'kato_registered_at' => now(),
                        'kato_registration_error' => null,
                        'kato_api_response' => $result['full_response'] ?? null,
                    ]);
                    return redirect()->route('superadmin.dashboard')
                        ->with('success', __('common.kato_registration_success'));
                } else {
                    $businessUnit->update([
                        'kato_company_id' => null,
                        'kato_admin_user_id' => null,
                        'kato_registered_at' => null,
                        'kato_registration_error' => $result['error'],
                        'kato_api_response' => null,
                    ]);
                    Log::error('KATO registration failed for business unit', [
                        'business_unit_id' => $businessUnit->id,
                        'error' => $result['error']
                    ]);
                    return redirect()->route('superadmin.dashboard')
                        ->with('warning', __('common.kato_registration_failed') . ': ' . $result['error']);
                }
            }
        }

        return redirect()->route('superadmin.dashboard')
            ->with('success', __('common.created_successfully'));
    }

    public function editBusinessUnit($id)
    {
        $businessUnit = BusinessUnit::with('coreBankingSystem')->findOrFail($id);
        $coreBankingSystems = CoreBankingSystem::where('is_active', true)->orderBy('name')->get();
        $globalTransactionTypes = TransactionType::global()->active()->orderBy('name')->get();
        $assignedTransactionTypeIds = $businessUnit->assignedTransactionTypes()->pluck('transaction_types.id')->toArray();
        return view('superadmin.business-units.edit', compact('businessUnit', 'coreBankingSystems', 'globalTransactionTypes', 'assignedTransactionTypeIds'));
    }

    public function updateBusinessUnit(Request $request, $id)
    {
        $businessUnit = BusinessUnit::findOrFail($id);

        $validated = $request->validate([
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
        ]);

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
        
        $businessUnit->update($validated);
        
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
        
        // Refresh to get updated values
        $businessUnit->refresh();

        // Attempt KATO registration if:
        // 1. CBS changed to KATO, OR
        // 2. CBS is already KATO but not registered yet (no companyId)
        if ($businessUnit->core_banking_system_id && !$businessUnit->kato_company_id) {
            $cbs = CoreBankingSystem::find($businessUnit->core_banking_system_id);
            if ($cbs && strtolower($cbs->code) === 'kato' && $cbs->base_url) {
                // If CBS was changed to KATO, or if not registered yet, attempt registration
                if ($cbsChanged || !$businessUnit->kato_registered_at) {
                    $katoService = new KatoService();
                    $result = $katoService->registerCompany($businessUnit, $cbs);
                    
                    if ($result['success']) {
                        $businessUnit->update([
                            'kato_company_id' => $result['data']['companyId'],
                            'kato_admin_user_id' => $result['data']['adminUserId'],
                            'kato_registered_at' => now(),
                            'kato_registration_error' => null,
                            'kato_api_response' => $result['full_response'] ?? null,
                        ]);
                        return redirect()->route('superadmin.business-units.edit', $businessUnit->id)
                            ->with('success', __('common.updated_successfully') . '. ' . __('common.kato_registration_success'));
                    } else {
                        $businessUnit->update([
                            'kato_company_id' => null,
                            'kato_admin_user_id' => null,
                            'kato_registered_at' => null,
                            'kato_registration_error' => $result['error'],
                            'kato_api_response' => null,
                        ]);
                        Log::error('KATO registration failed for business unit', [
                            'business_unit_id' => $businessUnit->id,
                            'error' => $result['error']
                        ]);
                        return redirect()->route('superadmin.business-units.edit', $businessUnit->id)
                            ->with('warning', __('common.updated_successfully') . '. ' . __('common.kato_registration_failed') . ': ' . $result['error']);
                    }
                }
            } elseif ($cbsChanged && $oldCbsId) {
                // If CBS was changed FROM KATO to something else, clear KATO fields
                $oldCbs = CoreBankingSystem::find($oldCbsId);
                if ($oldCbs && strtolower($oldCbs->code) === 'kato') {
                    $businessUnit->update([
                        'kato_company_id' => null,
                        'kato_admin_user_id' => null,
                        'kato_registered_at' => null,
                        'kato_registration_error' => null,
                        'kato_api_response' => null,
                    ]);
                }
            }
        }

        return redirect()->route('superadmin.business-units.index')
            ->with('success', __('common.updated_successfully'));
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
            $businessUnit->update([
                'kato_company_id' => $result['data']['companyId'],
                'kato_admin_user_id' => $result['data']['adminUserId'],
                'kato_registered_at' => now(),
                'kato_registration_error' => null,
                'kato_api_response' => $result['full_response'] ?? null,
            ]);
            return redirect()->back()
                ->with('success', __('common.kato_registration_success'));
        } else {
            $businessUnit->update([
                'kato_company_id' => null,
                'kato_admin_user_id' => null,
                'kato_registered_at' => null,
                'kato_registration_error' => $result['error'],
                'kato_api_response' => null,
            ]);
            Log::error('KATO registration failed for business unit', [
                'business_unit_id' => $businessUnit->id,
                'error' => $result['error']
            ]);
            return redirect()->back()
                ->with('error', __('common.kato_registration_failed') . ': ' . $result['error']);
        }
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
}
