<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminAccountDirectoryService;
use App\Utils\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountDirectoryController extends Controller
{
    private function authorizePeople(): void
    {
        abort_unless(auth('admin')->check() && Helpers::module_permission_check('people'),403);
    }

    public function index(Request $request,AdminAccountDirectoryService $directory)
    {
        $this->authorizePeople();
        $filters=$request->validate(['q'=>'nullable|string|max:100','type'=>'nullable|in:customer,vendor','status'=>'nullable|in:active,frozen,pending,rejected','sort'=>'nullable|in:identifier,name,status,created_at','direction'=>'nullable|in:asc,desc','per_page'=>'nullable|integer|in:20,50,100']);
        $accounts=$directory->query($filters)->paginate($filters['per_page'] ?? 20)->withQueryString();
        return view('admin-views.accounts.index',['accounts'=>$accounts,'contacts'=>$directory->contacts(collect($accounts->items()))]);
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
