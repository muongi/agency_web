<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\InsuranceProduct;
use App\Models\SunuCorrectionRequest;
use App\Services\SunuCorrectionService;
use Illuminate\Http\Request;

/**
 * Web side of collector error reports: list, decide (approve / reject), then apply the
 * correction by hand (cancel / fix premium, cancel / fix subscription) or close.
 * Decision and correction are separate permissions so two people can be required.
 */
class SunuCorrectionWebController extends Controller
{
    public const PERMISSIONS = [
        'index' => 'view-sunu-corrections',
        'show' => 'view-sunu-corrections',
        'approve' => 'approve-sunu-corrections',
        'reject' => 'approve-sunu-corrections',
        'apply' => 'apply-sunu-corrections',
        'close' => 'apply-sunu-corrections',
    ];

    public const ACTIONS = [
        'cancel-premium' => SunuCorrectionRequest::TARGET_PREMIUM,
        'premium-amount' => SunuCorrectionRequest::TARGET_PREMIUM,
        'premium-policy' => SunuCorrectionRequest::TARGET_PREMIUM,
        'cancel-proposal' => SunuCorrectionRequest::TARGET_PROPOSAL,
        'proposal-identity' => SunuCorrectionRequest::TARGET_PROPOSAL,
    ];

    public function __construct(protected SunuCorrectionService $corrections)
    {
        $this->middleware(['auth', 'business.access']);
        $this->middleware(function (Request $request, $next) {
            $slug = self::PERMISSIONS[$request->route()->getActionMethod()] ?? null;
            $user = $request->user();
            $bu = $user->currentBusinessUnit;
            if ($slug && $bu && ! $user->isSuperAdmin() && ! $user->hasPermission($slug, $bu->id)) {
                abort(403, __('common.unauthorized_access'));
            }

            return $next($request);
        });
    }

    protected function businessUnit()
    {
        return auth()->user()->currentBusinessUnit;
    }

    protected function find(int $id): SunuCorrectionRequest
    {
        return SunuCorrectionRequest::where('business_unit_id', $this->businessUnit()->id)->findOrFail($id);
    }

    public function index(Request $request)
    {
        $bu = $this->businessUnit();
        if (! $bu) {
            return redirect()->route('business.dashboard')->with('error', __('common.no_business_unit_assigned'));
        }

        $query = SunuCorrectionRequest::with(['agent', 'terminal', 'decider', 'resolver'])
            ->where('business_unit_id', $bu->id)
            ->latest('id');

        foreach (['status', 'target_type', 'request_type'] as $f) {
            if ($request->filled($f)) {
                $query->where($f, $request->input($f));
            }
        }
        if ($request->filled('agent_id')) {
            $query->where('requested_by_agent_id', (int) $request->input('agent_id'));
        }
        if ($request->filled('from')) {
            $query->whereDate('requested_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('requested_at', '<=', $request->input('to'));
        }
        if ($request->filled('q')) {
            $q = '%'.$request->input('q').'%';
            $query->where(fn ($w) => $w->where('reference', 'like', $q)->orWhere('target_reference', 'like', $q));
        }

        return view('business.sunu.corrections.index', [
            'requests' => $query->paginate(25)->withQueryString(),
            'agents' => Agent::where('business_unit_id', $bu->id)->orderBy('first_name')->get(),
            'pendingCount' => SunuCorrectionRequest::where('business_unit_id', $bu->id)
                ->where('status', SunuCorrectionRequest::STATUS_PENDING)->count(),
        ]);
    }

    public function show(int $id)
    {
        $corr = $this->find($id)->load(['agent', 'terminal', 'decider', 'resolver', 'events']);
        $target = $corr->target_type === SunuCorrectionRequest::TARGET_PREMIUM
            ? $corr->premium()->with(['policy.client', 'policy.product', 'proposal.product', 'agent', 'terminal', 'correctionRequests'])->first()
            : $corr->proposal()->with(['product', 'agent', 'terminal', 'client', 'correctionRequests'])->first();

        return view('business.sunu.corrections.show', [
            'corr' => $corr,
            'target' => $target,
            'products' => InsuranceProduct::where('business_unit_id', $corr->business_unit_id)->orderBy('name_fr')->get(),
        ]);
    }

    public function approve(Request $request, int $id)
    {
        $data = $request->validate(['note' => 'nullable|string|max:1000']);

        return $this->run($id, fn ($corr) => $this->corrections->approve($corr, $request->user(), $data['note'] ?? null),
            'sunu_corr_flash_approved');
    }

    public function reject(Request $request, int $id)
    {
        $data = $request->validate(['note' => 'required|string|max:1000']);

        return $this->run($id, fn ($corr) => $this->corrections->reject($corr, $request->user(), $data['note']),
            'sunu_corr_flash_rejected');
    }

    public function apply(Request $request, int $id, string $action)
    {
        abort_unless(isset(self::ACTIONS[$action]), 404);
        $rules = ['note' => 'required|string|max:1000'];
        $rules += match ($action) {
            'premium-amount' => ['amount' => 'required|numeric|min:1'],
            'premium-policy' => ['policy_number' => 'required|string|max:64', 'insurance_product_id' => 'nullable|integer'],
            'proposal-identity' => [
                'last_name' => 'nullable|string|max:120',
                'first_names' => 'nullable|string|max:200',
                'phone' => 'nullable|string|max:32',
                'insurance_product_id' => 'nullable|integer',
            ],
            default => [],
        };
        $data = $request->validate($rules);
        $user = $request->user();

        return $this->run($id, fn ($corr) => match ($action) {
            'cancel-premium' => $this->corrections->cancelPremium($corr, $user, $data['note']),
            'premium-amount' => $this->corrections->changePremiumAmount($corr, $user, (float) $data['amount'], $data['note']),
            'premium-policy' => $this->corrections->changePremiumPolicy($corr, $user, $data['policy_number'],
                ! empty($data['insurance_product_id']) ? (int) $data['insurance_product_id'] : null, $data['note']),
            'cancel-proposal' => $this->corrections->cancelProposal($corr, $user, $data['note']),
            'proposal-identity' => $this->corrections->changeProposalIdentity($corr, $user, $data, $data['note']),
        }, 'sunu_corr_flash_applied');
    }

    public function close(Request $request, int $id)
    {
        $data = $request->validate(['note' => 'required|string|max:1000']);

        return $this->run($id, fn ($corr) => $this->corrections->close($corr, $request->user(), $data['note']),
            'sunu_corr_flash_closed');
    }

    protected function run(int $id, callable $do, string $successKey)
    {
        $corr = $this->find($id);
        try {
            $do($corr);
        } catch (\DomainException $e) {
            return redirect()->route('business.sunu.corrections.show', $corr->id)
                ->with('error', __('common.'.$e->getMessage()))->withInput();
        }

        return redirect()->route('business.sunu.corrections.show', $corr->id)
            ->with('success', __('common.'.$successKey, ['ref' => $corr->reference]));
    }
}
