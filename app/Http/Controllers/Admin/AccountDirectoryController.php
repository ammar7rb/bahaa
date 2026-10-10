<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminAccountDirectoryService;
use App\Utils\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Exports\AdminAccountDirectoryExport;
use Maatwebsite\Excel\Facades\Excel;

class AccountDirectoryController extends Controller
{
    private function authorizePeople(): void
    {
        abort_unless(auth('admin')->check() && Helpers::module_permission_check('people'),403);
    }

    public function index(Request $request,AdminAccountDirectoryService $directory)
    {
        $this->authorizePeople();
        $filters=$this->filters($request);
        $accounts=$directory->query($filters)->paginate($filters['per_page'] ?? 20)->withQueryString();
        return view('admin-views.accounts.index',['accounts'=>$accounts,'contacts'=>$directory->contacts(collect($accounts->items())),'filters'=>$filters]);
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'q'=>'nullable|string|max:100','type'=>'nullable|in:customer,vendor','status'=>'nullable|in:active,frozen,pending,rejected',
            'sort'=>'nullable|in:identifier,name,status,created_at,orders_count,order_amount','direction'=>'nullable|in:asc,desc','per_page'=>'nullable|integer|in:20,50,100',
            'joined_from'=>'nullable|date_format:Y-m-d','joined_to'=>['nullable','date_format:Y-m-d',...($request->filled('joined_from')?['after_or_equal:joined_from']:[])],
            'ordered_from'=>'nullable|date_format:Y-m-d','ordered_to'=>['nullable','date_format:Y-m-d',...($request->filled('ordered_from')?['after_or_equal:ordered_from']:[])],
            'first'=>'nullable|integer|min:1|max:100000',
        ]);
    }

    public function export(Request $request,AdminAccountDirectoryService $directory)
    {
        $this->authorizePeople();
        return Excel::download(new AdminAccountDirectoryExport($directory,$this->filters($request)),'accounts-'.now()->format('Y-m-d').'.xlsx',\Maatwebsite\Excel\Excel::XLSX,['Cache-Control'=>'private, no-store']);
    }

    public function print(Request $request,AdminAccountDirectoryService $directory)
    {
        $this->authorizePeople();
        $filters=$this->filters($request);
        $accounts=$directory->query($filters)->get();
        return response()->view('admin-views.accounts.print',['accounts'=>$accounts,'contacts'=>$directory->contacts($accounts),'filters'=>$filters])->header('Cache-Control','private, no-store');
    }

    public function status(Request $request,string $kind,int $id,AdminAccountDirectoryService $directory)
    {
        $this->authorizePeople();
        $input=$request->validate(['action'=>'required|in:freeze,reactivate']);
        try {
        $message=DB::transaction(function () use ($directory,$kind,$id,$input) {
            $account=$directory->account($kind,$id,true);
            $freeze=$input['action']==='freeze';
            if ($kind==='vendor') {
                if (!in_array($account->status,['approved','suspended'],true)) return translate('directory_vendor_review_required');
                $account->status=$freeze?'suspended':'approved';
                if ($freeze) $account->auth_token=null;
            } else {
                $account->is_active=!$freeze;
                if ($freeze) $directory->revokeCustomerTokens($id);
            }
            if ($freeze && \Illuminate\Support\Facades\Schema::hasColumn($account->getTable(),'remember_token')) $account->remember_token=null;
            $account->save();
            return translate('directory_status_saved');
        });
        } catch (\Illuminate\Database\QueryException $error) {
            report($error);
            return back()->withErrors(['directory'=>translate('directory_save_failed')]);
        }
        return back()->with('directory_notice',$message);
    }

    public function destroy(Request $request,string $kind,int $id,AdminAccountDirectoryService $directory)
    {
        $this->authorizePeople();
        abort_unless((int)auth('admin')->user()->admin_role_id===1,403);
        $account=$directory->account($kind,$id);
        $identifier=($kind==='customer'?'c':'m').$account->id;
        $input=$request->validate(['confirmation'=>'required|string','acknowledge'=>'accepted']);
        if ($input['confirmation']!==$identifier) return back()->withErrors(['directory'=>translate('directory_confirmation_mismatch')]);
        try {
            $blocked=$directory->permanentlyDelete($kind,$id);
            if ($blocked) return back()->withErrors(['directory'=>translate('directory_delete_blocked').' '.implode('، ',$blocked)]);
        } catch (\Throwable $error) {
            report($error);
            return back()->withErrors(['directory'=>translate('directory_delete_failed')]);
        }
        return back()->with('directory_notice',translate('directory_deleted'));
    }
}
