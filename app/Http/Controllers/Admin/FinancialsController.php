<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\FinancialsService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FinancialsController extends Controller
{
    public function __construct(private readonly FinancialsService $financials) {}

    public function index()
    {
        return redirect()->route('admin.report.financials.company');
    }

    private function filters(Request $request, bool $customers): array
    {
        $sources = array_keys($this->financials->sources($customers));
        $data = $request->validate([
            'source' => ['nullable', Rule::in($sources)],
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
            'customer_id' => 'nullable|integer|min:1',
            'seller_id' => $customers ? 'prohibited' : 'nullable|integer|min:1',
            'q' => 'nullable|string|max:100',
            'status' => 'nullable|string|max:60',
            'method' => 'nullable|string|max:60',
            'kind' => 'nullable|string|max:100',
            'review' => ['nullable', Rule::in(['pending','approved','rejected'])],
            'bucket'=>['nullable',Rule::in(\App\Services\Admin\VendorFinancialsReadService::BUCKETS)],
            'metric'=>'nullable|string|max:60','all_dates'=>'nullable|boolean',
            'per_page' => ['nullable', Rule::in([20, 50, 100])],
            'page' => 'nullable|integer|min:1',
            'tab' => ['nullable', Rule::in(['accounts', 'transactions'])],
        ]);
        $data['from'] = $data['from'] ?? now()->startOfMonth()->toDateString();
        $data['to'] = $data['to'] ?? now()->toDateString();
        // Validate the effective defaults as well as explicitly supplied dates.
        validator($data, ['to' => 'after_or_equal:from'])->validate();
        $data['source'] = $data['source'] ?? ($customers ? 'wallet' : 'orders');
        return $data;
    }

    public function company(Request $request)
    {
        // Existing statement/detail URLs remain valid; only the company landing gains tabs.
        if ($request->filled('source')) {
            $filters = $this->filters($request, false);
            return view('admin-views.financials.index', $this->financials->workspace($filters, false));
        }
        $filters = $request->validate([
            'tab'=>['nullable', Rule::in([...\App\Services\Admin\CompanyFinancialsService::TABS,'overview'])],
            'account_id'=>'nullable|integer|min:1','category'=>'nullable|string|max:60',
            'confirmation'=>'nullable|in:confirmed,unconfirmed','movement'=>'nullable|in:external,internal,unclassified,vendor_collected,adjustment',
            'metric'=>'nullable|string|max:60',
            'register'=>['nullable',Rule::in(\App\Services\Admin\VendorFinancialsReadService::FLOWS)],
            'bucket'=>['nullable',Rule::in(\App\Services\Admin\VendorFinancialsReadService::BUCKETS)],
            'direction'=>'nullable|in:credit,debit','event_type'=>'nullable|string|max:100',
            'settlement_window'=>'nullable|in:overdue,due_soon,escalated','all_dates'=>'nullable|boolean',
            'from'=>'nullable|date_format:Y-m-d','to'=>'nullable|date_format:Y-m-d',
            'seller_id'=>'nullable|integer|min:1','customer_id'=>'nullable|integer|min:1',
            'q'=>'nullable|string|max:100','status'=>'nullable|string|max:60','method'=>'nullable|string|max:60',
            'account_type'=>['nullable',Rule::in(['customer','vendor'])],
            'flow'=>['nullable',Rule::in(['refunds','refund_requests','refund_admin','refund_ledger','refund_edits'])],
            'settlement_status'=>['nullable',Rule::in(['held','disputed','released','reversed'])],
            'due'=>['nullable',Rule::in(['upcoming','due','overdue'])],
            'per_page'=>['nullable',Rule::in([20,50,100])],'page'=>'nullable|integer|min:1',
        ]);
        $filters['tab'] = ($filters['tab'] ?? 'incoming') === 'overview' ? 'incoming' : ($filters['tab'] ?? 'incoming');
        if ($filters['tab'] === 'incoming') {
            $filters += ['confirmation'=>'confirmed','from'=>null,'to'=>null];
        } else {
            $filters['from'] ??= now()->startOfMonth()->toDateString();
            $filters['to'] ??= now()->toDateString();
        }
        if (!empty($filters['from']) && !empty($filters['to'])) validator($filters,['to'=>'after_or_equal:from'])->validate();
        try {
            return view('admin-views.financials.company', app(\App\Services\Admin\CompanyFinancialsService::class)->workspace($filters));
        } catch (\Illuminate\Database\QueryException $exception) {
            report($exception);
            return response()->view('admin-views.financials.company',['filters'=>$filters,'tab'=>$filters['tab'],'loadError'=>true],503);
        }
    }

    public function customers(Request $request)
    {
        $filters=$request->validate(['q'=>'nullable|string|max:100','account_type'=>'nullable|in:customer,vendor','account_id'=>'nullable|integer|min:1','customer_id'=>'nullable|integer|min:1','seller_id'=>'nullable|integer|min:1','per_page'=>'nullable|in:20,50,100','page'=>'nullable|integer|min:1']);
        if (!empty($filters['customer_id'])) $filters=array_replace($filters,['account_type'=>'customer','account_id'=>$filters['customer_id']]);
        if (!empty($filters['seller_id'])) $filters=array_replace($filters,['account_type'=>'vendor','account_id'=>$filters['seller_id']]);
        $accounts=app(\App\Services\Admin\FinancialAccountsReadService::class)->accounts($filters);
        $openStatement=$request->get('tab') === 'transactions' && !empty($filters['account_id']) && !empty($filters['account_type']) ? route('admin.report.financials.statement',[$filters['account_type'],$filters['account_id']]) : null;
        return view('admin-views.financials.accounts',compact('accounts','filters','openStatement')+['baseCurrency'=>$this->financials->baseCurrency()]);
    }

    public function statement(Request $request,string $type,int $id)
    {
        abort_unless(in_array($type,['customer','vendor']),404);
        $filters=$request->validate(['from'=>'nullable|date_format:Y-m-d','to'=>'nullable|date_format:Y-m-d','category'=>'nullable|string|max:60','movement'=>'nullable|in:external,internal,unclassified,vendor_collected,adjustment,ledger,refund','confirmation'=>'nullable|in:confirmed,unconfirmed','method'=>'nullable|string|max:60','q'=>'nullable|string|max:100','page'=>'nullable|integer|min:1','per_page'=>'nullable|in:20,50,100']);
        if (!empty($filters['from']) && !empty($filters['to'])) validator($filters,['to'=>'after_or_equal:from'])->validate();
        $filters['account_type']=$type;$filters['account_id']=$id;
        $account=app(\App\Services\Admin\FinancialAccountsReadService::class)->account($type,$id);
        $data=app(\App\Services\Admin\FinancialActivityReadService::class)->workspace($filters,true);
        $data+=compact('account')+['statement'=>true,'action'=>route('admin.report.financials.statement',[$type,$id]),'baseCurrency'=>$this->financials->baseCurrency()];
        return response()->view('admin-views.financials.statement',$data)->header('X-Financial-Statement','1')->header('Cache-Control','private, no-store');
    }

    public function entry(string $source, int $id)
    {
        return view('admin-views.financials.entry', $this->financials->entry($source, $id));
    }
}
