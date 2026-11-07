<section class="ca-panel ca-directory">
    <header class="ca-section-heading"><h2><i class="fi fi-rr-users" aria-hidden="true"></i>{{ $page==='list'?'قائمة العملاء':'اختر العميل لإدارة معاملاته' }}</h2><span class="ca-result-count">{{ number_format($accounts->total()) }} عميل</span></header>
    <div class="ca-table ca-table-accounts" tabindex="0" role="region" aria-label="قائمة العملاء">
        <table><thead><tr><th scope="col">رقم الحساب</th><th scope="col">الاسم</th><th scope="col">الهاتف</th><th scope="col">البريد الإلكتروني</th><th scope="col">الباقة الحالية</th><th scope="col">حالة الحساب</th><th scope="col">الإجراءات</th></tr></thead><tbody>
        @forelse($accounts as $account)
            @php($state=$accountStates->get($account->id))
            <tr @if($customer?->id===$account->id)aria-selected="true"@endif>
                <td class="ca-reference-cell"><a class="ca-account-reference" href="{{ route('admin.customer.view',$account->id) }}"><i class="fi fi-rr-id-badge" aria-hidden="true"></i><bdi>{{ $identity($account) }}</bdi></a></td>
                <td><a class="ca-person" href="{{ route('admin.customer.view',$account->id) }}"><span class="ca-avatar" aria-hidden="true">{{ mb_substr(trim($account->f_name ?: $account->l_name),0,1) ?: '—' }}</span><span>{{ trim($account->f_name.' '.$account->l_name) ?: 'عميل بدون اسم' }}</span></a></td>
                <td><a class="ca-phone" href="tel:{{ $account->phone }}"><bdi>{{ $account->phone ?: '—' }}</bdi></a></td>
                <td class="ca-email"><a href="mailto:{{ $account->email }}"><bdi>{{ $account->email ?: '—' }}</bdi></a></td>
                <td><span class="ca-package-name"><i class="fi fi-rr-box-open" aria-hidden="true"></i>{{ $packages->get($account->id)?->package_name ?: 'لا توجد باقة نشطة' }}</span></td>
                <td><span class="ca-status" data-state="{{ $state?->account_deleted_at?'closed':($account->account_status==='active'?'active':'suspended') }}"><span class="ca-status-dot" aria-hidden="true"></span>{{ $state?->account_deleted_at?'مغلق':($account->account_status==='active'?'نشط':'موقوف') }}</span></td>
                <td><div class="ca-row-actions">
                    @if($page!=='list')<a class="btn btn-sm btn-outline-primary" href="{{ $accountLink($account->id) }}"><i class="fi fi-rr-check" aria-hidden="true"></i>اختيار العميل</a>
                    @else
                        <a class="ca-icon-action" data-ca-profile href="{{ route('admin.customer.view',$account->id) }}" aria-label="إدارة حساب {{ $identity($account) }}" title="إدارة الحساب"><i class="fi fi-rr-user" aria-hidden="true"></i></a>
                        @if(!$state?->account_deleted_at)
                            @php($statusAction=$account->account_status==='active'?'suspend':'reactivate')
                            @if($can('account_manage'))<a class="ca-icon-action" data-ca-remote href="{{ $accountLink($account->id).'&focus_action='.$statusAction }}" aria-label="{{ $statusAction==='suspend'?'تعليق':'تنشيط' }} حساب {{ $identity($account) }}" title="{{ $statusAction==='suspend'?'تعليق الحساب':'تنشيط الحساب' }}"><i class="fi fi-rr-{{ $statusAction==='suspend'?'pause-circle':'play-circle' }}" aria-hidden="true"></i></a>@endif
                            @if($can('password_reset'))<a class="ca-icon-action" data-ca-remote href="{{ $accountLink($account->id).'&focus_action=password' }}" aria-label="تعيين كلمة مرور لحساب {{ $identity($account) }}" title="تعيين كلمة مرور جديدة"><i class="fi fi-rr-key" aria-hidden="true"></i></a>@endif
                            @if($can('account_close'))<a class="ca-icon-action ca-icon-danger" data-ca-remote href="{{ $accountLink($account->id).'&focus_action=close' }}" aria-label="حذف وإغلاق حساب {{ $identity($account) }}" title="حذف الحساب وإغلاق الوصول"><i class="fi fi-rr-trash" aria-hidden="true"></i></a>@endif
                        @endif
                    @endif
                </div></td>
            </tr>
        @empty<tr><td colspan="7" class="ca-empty"><i class="fi fi-rr-search" aria-hidden="true"></i><strong>لا توجد حسابات مطابقة</strong><span>جرّب الاسم أو رقم الحساب أو الهاتف.</span></td></tr>@endforelse
        </tbody></table>
    </div>
    <footer class="ca-table-footer"><span>{{ $accounts->firstItem() ?? 0 }}–{{ $accounts->lastItem() ?? 0 }} من {{ number_format($accounts->total()) }}</span>{{ $accounts->links() }}</footer>
</section>
