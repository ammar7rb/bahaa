<?php

namespace App\Http\Controllers\Admin\Customer;

use App\Http\Controllers\Controller;
use App\Models\{User, CustomerMonthlySubscription, CustomerSubscriptionPayment, CustomerBalanceDeposit, WalletTransaction, OrderAdminPurchaseRefund};
use App\Services\{AdminAccountDirectoryService, CustomerAccountAccess, CustomerAccountAdjustmentService, CustomerAccountDebtService, CustomerPackageWalletService, CustomerPackageCatalogService, CustomerMonthlyAllowanceService, CustomerAccountDeletionService, CustomerProfileAuditService, CustomerPackageCancellationService};
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerAccountsController extends Controller
{
    public function index(Request $request, string $page = 'list')
    {
        abort_unless(in_array($page,['list','payments','debts','subscriptions'],true),404);
        abort_unless(\App\Utils\Helpers::module_permission_check('people'),403);
        if ($page !== 'list') CustomerAccountAccess::authorize('financial_view');
        $data=$request->validate(['q'=>'nullable|string|max:100','customer_id'=>'nullable|integer|min:1',
            'tab'=>'nullable|in:overview,requests,transactions,refunds,active,settled,history,current,subscriptions,actions',
            'focus_action'=>'nullable|in:suspend,reactivate,password,close',
            'status'=>'nullable|in:active,frozen,pending,rejected','operation'=>'nullable|string|max:50',
            'from'=>'nullable|date_format:Y-m-d','to'=>'nullable|date_format:Y-m-d'.($request->filled('from')?'|after_or_equal:from':''),
            'page'=>'nullable|integer|min:1']);
        if($request->boolean('lookup')){ $items=app(AdminAccountDirectoryService::class)->query(['type'=>'customer','q'=>$data['q']??null])->limit(12)->get(); return response()->json($items->map(fn($item)=>['id'=>$item->id,'label'=>\App\Services\AdministrativeReferenceService::format('customer',$item->id).' — '.trim($item->f_name.' '.$item->l_name)]))->header('Cache-Control','private, no-store'); }
        $customer=$request->filled('customer_id')?User::where('id','>',0)->findOrFail($data['customer_id']):null;
        $directory=app(AdminAccountDirectoryService::class)->query(['type'=>'customer','q'=>$data['q']??null,'status'=>$data['status']??null]);
        if ($customer) $directory->where('accounts.id',$customer->id);
        $accounts=$directory->paginate(20)->withQueryString();
        $ids=$accounts->pluck('id');
        $accountStates=User::whereIn('id',$ids)->get(['id','is_active','account_deleted_at'])->keyBy('id');
        $packages=CustomerMonthlySubscription::whereIn('customer_id',$ids)->whereNotNull('package_id')->where('starts_at','<=',now())->where('expires_at','>',now())->latest('id')->get()->unique('customer_id')->keyBy('customer_id');
        $allowedTabs=match($page){'payments'=>['active','settled','history','overview','requests','transactions','refunds'],'debts'=>['active','settled','history'],'subscriptions'=>['current','subscriptions','actions'],default=>['overview']};
        $tab=in_array($data['tab']??null,$allowedTabs,true)?$data['tab']:$allowedTabs[0];
        $focusedAction=$page==='list' && $customer ? ($data['focus_action']??null) : null;
        if($focusedAction && !CustomerAccountAccess::allows($focusedAction==='password'?'password_reset':($focusedAction==='close'?'account_close':'account_manage'))) $focusedAction=null;
        $debts=DB::table('customer_account_debts')->when($customer,fn($q)=>$q->where('customer_id',$customer->id));
        $debtTotal=(clone $debts)->selectRaw('COALESCE(SUM(amount-paid_amount),0) AS remaining')->value('remaining');
        $rows=null;$repayments=collect();$debtAccounts=collect();
        if ($page==='debts' || ($page==='payments' && in_array($tab,['active','settled','history'],true))) {
            if (!$customer && !empty($data['q'])) $debts->whereIn('customer_id',app(AdminAccountDirectoryService::class)->query(['type'=>'customer','q'=>$data['q']])->select('accounts.id'));
            $debts->when($tab==='history',fn($q)=>$q->whereNotNull('archived_at'),fn($q)=>$q->whereNull('archived_at'));
            if ($tab==='active') $debts->whereColumn('amount','>','paid_amount');
            if ($tab==='settled') $debts->whereNotNull('settled_at');
            $rows=$debts->latest('id')->paginate(20)->withQueryString();
            $repayments=DB::table('customer_debt_repayments')->whereIn('debt_id',$rows->pluck('id'))->orderBy('id')->get()->groupBy('debt_id');
            $debtAccounts=User::whereIn('id',$rows->pluck('customer_id'))->get()->keyBy('id');
        }
        $requests=$customer?CustomerBalanceDeposit::where('customer_id',$customer->id)->where('wallet_type','purchase')->latest('id')->paginate(20,['*'],'requests_page')->withQueryString():null;
        $fundingAllocations=$requests?DB::table('customer_debt_repayments')->where('customer_id',$customer->id)->whereIn('funding_reference',$requests->map(fn($deposit)=>'customer-offline-deposit-'.$deposit->id))->get()->groupBy('funding_reference'):collect();
        $fundingDebts=DB::table('customer_account_debts')->whereIn('id',$fundingAllocations->flatten(1)->pluck('debt_id'))->get()->keyBy('id');
        $transactions=$customer?WalletTransaction::where('user_id',$customer->id)->latest('id')->paginate(20,['*'],'transactions_page')->withQueryString():null;
        $refunds=$customer?OrderAdminPurchaseRefund::where('customer_id',$customer->id)->where('reason','customer_canceled_waiting_for_package')->latest('id')->paginate(20,['*'],'refunds_page')->withQueryString():null;
        $returns=$customer?\App\Models\RefundRequest::where('customer_id',$customer->id)->latest('id')->paginate(20,['*'],'returns_page')->withQueryString():null;
        $payments=CustomerSubscriptionPayment::with('customer')->when($customer,fn($q)=>$q->where('customer_id',$customer->id));
        $actions=DB::table('customer_account_actions')->when($customer,fn($q)=>$q->where('customer_id',$customer->id));
        if (!$customer && !empty($data['q'])) {
            $scope=app(AdminAccountDirectoryService::class)->query(['type'=>'customer','q'=>$data['q']])->select('accounts.id');
            $payments->whereIn('customer_id',clone $scope);$actions->whereIn('customer_id',clone $scope);
        }
        if (!empty($data['operation'])) {$payments->where('purchase_kind',$data['operation']);$actions->where('operation',$data['operation']);}
        foreach (['from'=>'>=','to'=>'<='] as $field=>$operator) if (!empty($data[$field])) {
            $payments->whereDate('created_at',$operator,$data[$field]);$actions->whereDate('created_at',$operator,$data[$field]);
        }
        $payments=$payments->latest('id')->paginate(20,['*'],'payments_page')->withQueryString();
        $paymentActions=DB::table('customer_account_actions')->whereIn('financial_reference',$payments->pluck('payment_reference')->filter())->get()->keyBy('financial_reference');
        $paymentSubscriptions=CustomerMonthlySubscription::whereIn('payment_id',$payments->pluck('id'))->get()->keyBy('payment_id');
        $actions=$actions->latest('id')->paginate(20,['*'],'actions_page')->withQueryString();
        $currentSubscriptions=null;$subscriptionCustomers=collect();
        if($page==='subscriptions' && $tab==='current') {
            $currentQuery=CustomerMonthlySubscription::query()->whereNotNull('package_id')->where('starts_at','<=',now())->where('expires_at','>',now())
                ->whereNotExists(fn($q)=>$q->selectRaw('1')->from('customer_monthly_subscriptions as newer')->whereColumn('newer.customer_id','customer_monthly_subscriptions.customer_id')->whereColumn('newer.id','>','customer_monthly_subscriptions.id')->where('newer.starts_at','<=',now())->where('newer.expires_at','>',now()));
            if($customer) $currentQuery->where('customer_id',$customer->id);
            elseif(!empty($data['q'])) $currentQuery->whereIn('customer_id',app(AdminAccountDirectoryService::class)->query(['type'=>'customer','q'=>$data['q']])->select('accounts.id'));
            $currentSubscriptions=$currentQuery->orderBy('expires_at')->paginate(20,['*'],'subscriptions_page')->withQueryString();
            $subscriptionCustomers=User::whereIn('id',$currentSubscriptions->pluck('customer_id'))->get()->keyBy('id');
        }
        $active=$customer?app(CustomerMonthlyAllowanceService::class)->active($customer->id):null;
        return view('admin-views.customer.accounts.index',compact('page','tab','customer','accounts','accountStates','focusedAction','packages','debtTotal','rows','repayments','debtAccounts','requests','fundingAllocations','fundingDebts','transactions','refunds','returns','payments','paymentActions','paymentSubscriptions','actions','currentSubscriptions','subscriptionCustomers','active')+[
            'catalog'=>app(CustomerPackageCatalogService::class)->available()->get(),
            'currency'=>getWebConfig('currency_model')==='multi_currency'?'USD':getCurrencyCode(type:'default'),
            'debtPolicyReady'=>config('customer_accounts.debt_allocation')==='oldest_first',
        ]);
    }

    private function identity(Request $request, string $permission, bool $optionalReason=false): array
    {
        CustomerAccountAccess::authorize($permission);
        $data=$request->validate(['customer_id'=>'required|integer|min:1|exists:users,id','confirmation'=>'required|string|max:50','reason'=>($optionalReason?'nullable':'required').'|string|max:2000','request_key'=>'required|uuid']);
        $customer=User::findOrFail($data['customer_id']);
        if ($data['confirmation']!==$customer->administrative_reference) throw ValidationException::withMessages(['confirmation'=>'اكتب رقم حساب العميل كما يظهر لتأكيد العملية.']);
        $data['reason']=trim((string)($data['reason']??''));
        return [$customer,$data];
    }

    public function credit(Request $request, \App\Services\CustomerManualCreditService $service)
    {
        [$customer,$data]=$this->identity($request,'wallet_adjust');
        $mode=$request->validate(['credit_kind'=>'required|in:adjustment,confirmed_payment,return_credit','approval_reference'=>'nullable|string|max:191|required_unless:credit_kind,adjustment','verified_approval'=>'accepted']);
        if($mode['credit_kind']==='return_credit')CustomerAccountAccess::authorize('refunds_manage');
        $amount=$request->validate(['amount'=>'required|numeric|gt:0|max:10000000|decimal:0,3']);
        return $this->perform(fn()=> $service->credit($customer->id,(string)$amount['amount'],$data['reason'],(int)auth('admin')->id(),$data['request_key'],$mode['credit_kind'],$mode['approval_reference']??''), 'تمت إضافة الرصيد وتسجيل الحركة بنجاح.');
    }

    public function debt(Request $request, CustomerAccountDebtService $service)
    {
        [$customer,$data]=$this->identity($request,'debts_manage');
        $amount=$request->validate(['amount'=>'required|numeric|gt:0|max:10000000|decimal:0,3']);
        return $this->perform(fn()=> $service->create($customer->id,(string)$amount['amount'],$data['reason'],(int)auth('admin')->id(),$data['request_key']), 'تم تسجيل المديونية.');
    }

    public function archive(Request $request, int $debt, CustomerAccountDebtService $service)
    {
        [$customer]=$this->identity($request,'debts_manage');
        return $this->perform(fn()=> $service->archive($customer->id,$debt,(int)auth('admin')->id()), 'نُقل الدين المسدد إلى سجل الديون، مع حفظ تاريخه.');
    }

    public function subscription(Request $request, CustomerPackageWalletService $service)
    {
        [$customer,$context]=$this->identity($request,'subscriptions_manage');
        $data=$request->validate(['operation'=>'required|in:purchase,renew_limit','package_id'=>'required|integer|min:1',
            'quote_token'=>'required|string|size:64|regex:/^[a-f0-9]{64}$/','expected_amount'=>'required|numeric|gt:0|max:10000000|decimal:0,3',
            'expected_subscription_id'=>'nullable|integer|min:1','expected_revision'=>'required|integer|min:0',
            'support_reference'=>'nullable|string|max:191','authorized_request'=>'accepted']);
        $data['request_key']=$context['request_key'];
        return $this->perform(fn()=> $service->transact($customer->id,$data,['admin_id'=>(int)auth('admin')->id(),'source'=>$request->filled('support_reference')?'support':'admin','reason'=>$context['reason'],'support_reference'=>$data['support_reference']??null]), 'تمت معاملة الباقة من المحفظة وتسجيل نتيجتها.');
    }

    public function account(Request $request)
    {
        $operation=$request->validate(['operation'=>'required|in:suspend,reactivate,password,close'])['operation'];
        [$customer,$data]=$this->identity($request,$operation==='password'?'password_reset':($operation==='close'?'account_close':'account_manage'),true);
        if ($operation==='password') $request->validate(['password'=>'required|string|min:8|confirmed']);
        return $this->perform(function () use ($request,$customer,$data,$operation) {
            DB::transaction(function () use ($request,$customer,$data,$operation) {
                $customer=User::lockForUpdate()->findOrFail($customer->id);
                $signature=hash('sha256',json_encode([$customer->id,$operation,$data['reason']]));
                $prior=DB::table('customer_account_actions')->where('request_key',$data['request_key'])->first();
                if ($prior) {if ($prior->signature!==$signature || (int)$prior->admin_id!==(int)auth('admin')->id()) throw new DomainException('مرجع الطلب مستخدم لعملية أخرى.');return;}
                if ($customer->account_deleted_at) throw new DomainException('الحساب مغلق؛ لا يمكن إعادة تنشيطه أو تغيير بيانات دخوله.');
                if ($operation==='close') app(CustomerAccountDeletionService::class)->confirm($customer->id);
                else {
                    if ($operation==='password') {$customer->password=Hash::make($request->input('password'));$customer->remember_token=null;}
                    else $customer->is_active=$operation==='reactivate';
                    $customer->save();
                    app(AdminAccountDirectoryService::class)->revokeCustomerTokens($customer->id);
                    if ($operation!=='password') app(\App\Services\CustomerPurchaseEligibilityService::class)->reevaluateCustomer($customer->id);
                }
                DB::table('customer_account_actions')->insert(['customer_id'=>$customer->id,'admin_id'=>auth('admin')->id(),'request_key'=>$data['request_key'],
                    'operation'=>'account_'.$operation,'signature'=>$signature,'source'=>'admin','reason'=>$data['reason'],'created_at'=>now(),'updated_at'=>now()]);
            },3);
        },'تم تنفيذ إجراء الحساب وحفظ سجل الإدارة.');
    }

    public function subscriptionControl(Request $request)
    {
        [$customer,$data]=$this->identity($request,'subscriptions_manage');
        $input=$request->validate(['control'=>'required|in:auto_on,auto_off,cancel','support_reference'=>'nullable|string|max:191','authorized_request'=>'accepted']);
        return $this->perform(function () use ($customer,$data,$input) {
            DB::transaction(function () use ($customer,$data,$input) {
                $customer=User::lockForUpdate()->findOrFail($customer->id);
                if ($customer->account_deleted_at) throw new DomainException('الحساب مغلق.');
                $signature=hash('sha256',json_encode([$customer->id,$input['control'],$data['reason'],$input['support_reference']??null]));
                $prior=DB::table('customer_account_actions')->where('request_key',$data['request_key'])->first();
                if ($prior) {if ($prior->signature!==$signature || (int)$prior->admin_id!==(int)auth('admin')->id()) throw new DomainException('مرجع مستخدم لعملية أخرى.');return;}
                $active=app(CustomerMonthlyAllowanceService::class)->active($customer->id);
                if ($input['control']==='auto_on' && (!$customer->is_active || !$active)) throw new DomainException('تفعيل التجديد التلقائي يحتاج حسابًا وباقة نشطين.');
                $before=(bool)$customer->package_auto_renew;
                $customer->package_auto_renew=$input['control']==='auto_on';
                if ($input['control']!=='auto_on') $customer->package_auto_renew_failed_at=null;
                $customer->save();
                DB::table('customer_account_actions')->insert(['customer_id'=>$customer->id,'admin_id'=>auth('admin')->id(),'request_key'=>$data['request_key'],'signature'=>$signature,
                    'operation'=>'subscription_'.$input['control'],'source'=>empty($input['support_reference'])?'admin':'support','reason'=>$data['reason'],
                    'support_reference'=>$input['support_reference']??null,'details'=>json_encode(['auto_renew_before'=>$before,'auto_renew_after'=>(bool)$customer->package_auto_renew,'effective_at'=>$input['control']==='cancel'?$active?->expires_at?->toIso8601String():now()->toIso8601String(),'consent_confirmed'=>true]),'created_at'=>now(),'updated_at'=>now()]);
                app(\App\Services\CustomerPaymentNotificationService::class)->notify($customer->id,'subscription-control-'.$data['request_key'],
                    $input['control']==='auto_on'?'تم تفعيل التجديد التلقائي للباقة بناء على طلبكم.':($input['control']==='cancel'?'تم إلغاء التجديد القادم؛ تظل باقتكم سارية حتى تاريخ انتهائها دون رد تلقائي للرسوم.':'تم تعطيل التجديد التلقائي للباقة.'));
            },3);
        },'تم حفظ قرار الاشتراك وتوثيق طلب العميل.');
    }

    public function refund(Request $request, int $refund, CustomerPackageCancellationService $service)
    {
        [$customer,$data]=$this->identity($request,'refunds_manage');
        $record=OrderAdminPurchaseRefund::where('customer_id',$customer->id)->findOrFail($refund);
        return $this->perform(fn()=> $service->complete($record->id,(int)auth('admin')->id(),'purchase_wallet',null,$data['reason']), 'تم رد المبلغ إلى المحفظة وتسجيل مرجع الاسترداد.');
    }

    public function returnCredit(Request $request, int $refund, \App\Services\CustomerAccountRefundService $service)
    {
        [$customer,$data]=$this->identity($request,'refunds_manage');
        abort_unless(\App\Utils\Helpers::module_permission_check('orders'),403);
        return $this->perform(fn()=> $service->credit($customer->id,$refund,(int)auth('admin')->id(),$data['reason'],$data['request_key']), 'تم رد قيمة المرتجع إلى المحفظة مع تحديث سجل المرتجع الأصلي.');
    }

    private function perform(callable $action, string $success)
    {
        try {$action();} catch (DomainException $error) {throw ValidationException::withMessages(['operation'=>$error->getMessage()]);}
        catch (\Illuminate\Database\QueryException $error) {
            \Illuminate\Support\Facades\Log::error('Customer account operation could not be confirmed',['exception_type'=>get_class($error),'request_key'=>request('request_key')]);
            throw ValidationException::withMessages(['operation'=>'تعذر تأكيد نتيجة العملية. تحقق من سجل الحركات قبل تكرارها. مرجع الطلب: '.request('request_key')]);
        }
        return back()->with('customer_account_message',$success);
    }
}
