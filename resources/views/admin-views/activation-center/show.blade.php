@extends('layouts.admin.app')

@section('title', translate('activation_case_details'))

@section('content')
    @php
        $subject = $activationCase->subjectModel();
        $prefix = $activationCase->subject_type === 'seller' ? 'M' : 'C';
    @endphp
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
            <div>
                <a href="{{ route('admin.activation-center.index') }}" class="text-muted">â† {{ translate('account_activation_center') }}</a>
                <h2 class="h1 mb-1">{{ translate('activation_case') }} #{{ $activationCase->id }} â€” {{ $prefix }}{{ $activationCase->subject_id }}</h2>
                <p class="mb-0 text-muted">{{ translate('activation_case_private_admin_workspace') }}</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                @if($activationCase->subject_type === 'seller' && $subject)
                    <a class="btn btn-primary" target="_blank" href="{{ route('admin.vendors.view', $subject->id) }}">
                        <i class="fi fi-rr-store-alt"></i> {{ translate('seller_administrative_profile') }}
                    </a>
                @endif
                <a class="btn btn-outline-primary" target="_blank" href="{{ route('admin.activation-center.print', $activationCase) }}">
                    <i class="fi fi-rr-print"></i> {{ translate('print_complete_case') }}
                </a>
            </div>
        </div>

        @if($activationCase->isCompleted())
            <div class="alert alert-success">
                {{ translate('completed_activation_is_hidden_from_account_but_retained_for_admin') }}
                @if($activationCase->reviewedBy)
                    <strong>{{ $activationCase->reviewedBy->name }}</strong> â€” {{ $activationCase->reviewed_at?->format('d M Y, h:i A') }}
                @endif
            </div>
        @endif

        @if($activationCase->subject_type === 'customer' && $subject)
            @include('admin-views.customer.purchase-state', ['customer' => $subject])
            @unless($activationCase->isCompleted())
                <form class="card p-3 mb-3" method="post" action="{{ route('admin.activation-center.customer-profile',$activationCase) }}" dir="rtl">
                    @csrf
                    <h3 class="fs-16 mb-3">استكمال بيانات العميل</h3>
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label" for="activation-customer-name">الاسم</label><input class="form-control" id="activation-customer-name" name="name" value="{{ old('name',$subject->name ?: $subject->f_name) }}" required maxlength="100" autocomplete="name"></div>
                        <div class="col-md-4"><label class="form-label" for="activation-customer-phone">رقم الهاتف</label><input class="form-control" id="activation-customer-phone" name="phone" value="{{ old('phone',$subject->phone) }}" required type="tel" inputmode="tel" dir="ltr" autocomplete="tel-national"></div>
                        <div class="col-md-4"><label class="form-label" for="activation-customer-email">البريد الإلكتروني</label><input class="form-control" id="activation-customer-email" name="email" value="{{ old('email',$subject->email) }}" type="email" dir="ltr" maxlength="254" autocomplete="email"></div>
                    </div>
                    <div class="mt-3"><button class="btn btn-primary" type="submit">حفظ البيانات</button></div>
                </form>
            @endunless
        @endif
        <div class="row g-3">
            <div class="col-xl-8">
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">{{ translate('account_summary') }}</h4>
                        <span class="badge badge-soft-info">{{ translate($activationCase->status) }}</span>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4"><small class="text-muted d-block">{{ translate('name') }}</small><strong>{{ trim(($subject?->f_name ?? '').' '.($subject?->l_name ?? '')) ?: ($subject?->name ?? 'â€”') }}</strong></div>
                            <div class="col-md-4"><small class="text-muted d-block">{{ translate('email') }}</small><strong>{{ $subject?->email ?? 'â€”' }}</strong></div>
                            <div class="col-md-4"><small class="text-muted d-block">{{ translate('phone') }}</small><strong>{{ $subject?->phone ?? 'â€”' }}</strong></div>
                            @if($activationCase->subject_type === 'seller')
                                <div class="col-md-4"><small class="text-muted d-block">{{ translate('shop') }}</small><strong>{{ $subject?->shop?->name ?? 'â€”' }}</strong></div>
                                <div class="col-md-4"><small class="text-muted d-block">{{ translate('seller_status') }}</small><strong>{{ $subject?->status ?? 'â€”' }}</strong></div>
                            @else
                                <div class="col-md-4"><small class="text-muted d-block">{{ translate('address') }}</small><strong>{{ $subject?->street_address ?? 'â€”' }}</strong></div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><h4 class="mb-0">{{ translate('activation_conversation') }}</h4></div>
                    <div id="activation-live-messages" class="card-body" style="max-height:520px;overflow:auto">
                        @forelse($messages as $message)
                            @php
                                $isAdmin = ($message['sender_type'] ?? '') === 'admin';
                                $messageAttachments = $message['attachments'] ?? [];

                                if (is_string($messageAttachments)) {
                                    $decodedAttachments = json_decode($messageAttachments, true);
                                    $messageAttachments = json_last_error() === JSON_ERROR_NONE ? $decodedAttachments : [];
                                } elseif ($messageAttachments instanceof \Illuminate\Support\Collection) {
                                    $messageAttachments = $messageAttachments->all();
                                } elseif (is_object($messageAttachments)) {
                                    $messageAttachments = (array) $messageAttachments;
                                }

                                $messageAttachments = is_array($messageAttachments) ? $messageAttachments : [];
                                if (isset($messageAttachments['url'])) {
                                    $messageAttachments = [$messageAttachments];
                                }
                            @endphp
                            <div class="d-flex mb-3 {{ $isAdmin ? 'justify-content-end' : 'justify-content-start' }}">
                                <div class="p-3 rounded {{ $isAdmin ? 'bg-primary text-white' : 'bg-light' }}" style="max-width:80%">
                                    <small class="d-block mb-1 {{ $isAdmin ? 'text-white-50' : 'text-muted' }}">{{ translate($message['sender_type'] ?? 'system') }} Â· {{ $message['created_at'] ?? '' }}</small>
                                    <div>{!! nl2br(e($message['body'] ?? '')) !!}</div>
                                    @if(!empty($messageAttachments))
                                        <div class="d-flex flex-wrap gap-2 mt-2">
                                            @foreach($messageAttachments as $attachment)
                                                @php
                                                    $attachment = is_object($attachment)
                                                        ? (array) $attachment
                                                        : (is_array($attachment) ? $attachment : []);
                                                @endphp
                                                @if(!empty($attachment['url']))
                                                    <a class="text-reset border rounded p-1" href="{{ $attachment['url'] }}" target="_blank" rel="noopener">
                                                        @if(str_starts_with((string)($attachment['mime_type'] ?? ''), 'image/'))
                                                            <img src="{{ $attachment['url'] }}" width="80" height="80" style="object-fit:cover" alt="{{ $attachment['name'] ?? 'attachment' }}">
                                                        @else
                                                            {{ $attachment['name'] ?? translate('attachment') }}
                                                        @endif
                                                    </a>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-muted py-4">{{ translate('no_activation_messages_found') }}</div>
                        @endforelse
                    </div>
                    @if(!$activationCase->isCompleted())
                        <div class="card-footer">
                            <form id="activation-live-form" method="post" action="{{ route('admin.activation-center.reply', $activationCase) }}">
                                @csrf
                                <label class="form-label">{{ translate('reply_to_account') }}</label>
                                <textarea class="form-control mb-2" name="message" rows="3" required maxlength="5000"></textarea>
                                <button class="btn btn-primary" type="submit">{{ translate('send_message') }}</button>
                            </form>
                        </div>
                    @endif
                </div>

                <div class="card mb-3 border-primary-subtle">
                    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
                        <div>
                            <h4 class="mb-1">{{ translate('verified_profile_information') }} &amp; {{ translate('private_verification_documents') }}</h4>
                            <small class="text-muted">{{ translate('activation_case_private_admin_workspace') }}</small>
                        </div>
                        <div class="d-flex flex-wrap gap-2 small">
                            <span class="badge badge-soft-info px-3 py-2">{{ $activationCase->fields->count() }} {{ translate('fields') }}</span>
                            <span class="badge badge-soft-secondary px-3 py-2">{{ $activationCase->documents->count() }} {{ translate('documents') }}</span>
                            <span class="badge badge-soft-success px-3 py-2">{{ $activationCase->documents->where('verification_status', 'verified')->count() }} {{ translate('verified') }}</span>
                        </div>
                    </div>
                    <div class="card-body p-3 p-xl-4">
                        <div class="row g-4">
                            <div class="col-12">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h5 class="mb-0">{{ translate('verified_profile_information') }}</h5>
                                    <small class="text-muted">{{ translate('staff_only_profile_information') }}</small>
                                </div>
                        <div class="table-responsive border rounded mb-3">
                            <table class="table table-sm align-middle">
                                <thead><tr><th>{{ translate('field') }}</th><th>{{ translate('value') }}</th><th>{{ translate('verification') }}</th><th>{{ translate('employee_and_time') }}</th></tr></thead>
                                <tbody>
                                @forelse($activationCase->fields as $field)
                                    <tr>
                                        <td>{{ $field->label }} @if($field->is_required)<span class="text-danger">*</span>@endif</td>
                                        <td class="text-break">{{ $field->value ?: 'â€”' }}</td>
                                        <td><span class="badge badge-soft-{{ $field->is_verified ? 'success' : 'warning' }}">{{ translate($field->is_verified ? 'verified' : 'pending') }}</span></td>
                                        <td><small>{{ $field->createdBy?->name ?? 'â€”' }}<br>{{ $field->updated_at?->format('d M Y, h:i A') }}</small></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted">{{ translate('no_profile_information_added') }}</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                        @if(!$activationCase->isCompleted())
                            <form method="post" action="{{ route('admin.activation-center.fields.store', $activationCase) }}" class="border rounded p-3 bg-light">
                                @csrf
                                <h5>{{ translate('add_or_update_profile_information') }}</h5>
                                <p class="small text-muted mb-3">{{ translate('profile_field_plain_language_hint') }}</p>
                                <div class="row g-2">
                                    <div class="col-md-5"><input class="form-control" name="label" required placeholder="{{ translate('field_label') }}"></div>
                                    <div class="col-md-7"><input class="form-control" name="value" placeholder="{{ translate('field_value') }}"></div>
                                    <div class="col-md-8 d-flex flex-wrap gap-3 align-items-center">
                                        <label class="mb-0"><input type="checkbox" name="is_sensitive" value="1" checked> {{ translate('sensitive_private_data') }}</label>
                                        <label class="mb-0"><input type="checkbox" name="is_required" value="1"> {{ translate('required_before_activation') }}</label>
                                        <label class="mb-0"><input type="checkbox" name="is_verified" value="1"> {{ translate('verified_by_employee') }}</label>
                                    </div>
                                    <div class="col-md-4 text-end"><button class="btn btn-primary" type="submit">{{ translate('save_information') }}</button></div>
                                </div>
                            </form>
                        @endif
                            </div>
                            <div class="col-12">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h5 class="mb-0">{{ translate('private_verification_documents') }}</h5>
                                    <span class="small text-muted">{{ $activationCase->documents->where('verification_status', 'verified')->count() }}/{{ $activationCase->documents->count() }} {{ translate('verified') }} · {{ translate('uploaded_files_are_private') }}</span>
                                </div>
                                <div class="border rounded p-3 p-xl-4 bg-light">
                        <div class="table-responsive mb-3 bg-white border rounded">
                            <table class="table table-sm align-middle">
                                <thead><tr><th>{{ translate('document_type') }}</th><th>{{ translate('file') }}</th><th>{{ translate('upload_status') }}</th><th>{{ translate('status') }}</th><th>{{ translate('action') }}</th></tr></thead>
                                <tbody>
                                @forelse($activationCase->documents as $document)
                                    <tr>
                                        <td><strong>{{ $document->document_type === 'other' ? ($document->custom_name ?: translate('other')) : translate($document->document_type) }}</strong> @if($document->is_required)<span class="text-danger">*</span>@endif<br><small class="text-muted">{{ $document->document_number }}</small></td>
                                        <td>{{ $document->original_name }}<br><small>{{ number_format($document->size_bytes / 1024, 1) }} KB</small></td>
                                        <td><span class="badge badge-soft-success">{{ translate('uploaded') }}</span><br><small class="text-muted">{{ $document->created_at?->format('d M Y, h:i A') }}</small></td>
                                        <td><span class="badge badge-soft-info">{{ translate($document->verification_status) }}</span></td>
                                        <td class="d-flex flex-wrap gap-1">
                                            <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.activation-center.documents.download', [$activationCase, $document]) }}">{{ translate('download') }}</a>
                                            @if(!$activationCase->isCompleted())
                                                <form method="post" action="{{ route('admin.activation-center.documents.verify', [$activationCase, $document]) }}">
                                                    @csrf
                                                    <input type="hidden" name="verification_status" value="verified">
                                                    <button class="btn btn-outline-success btn-sm">{{ translate('verify') }}</button>
                                                </form>
                                                <form method="post" action="{{ route('admin.activation-center.documents.verify', [$activationCase, $document]) }}">
                                                    @csrf
                                                    <input type="hidden" name="verification_status" value="rejected">
                                                    <button class="btn btn-outline-danger btn-sm">{{ translate('reject') }}</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted py-4">{{ translate('no_documents_added') }}<br><small>{{ translate('no_files_uploaded_yet') }}</small></td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                        @if(!$activationCase->isCompleted())
                            <form method="post" enctype="multipart/form-data" action="{{ route('admin.activation-center.documents.store', $activationCase) }}" class="border rounded p-3 bg-white">
                                @csrf
                                <h5>{{ translate('add_private_document') }}</h5>
                                <p class="small text-muted mb-3">{{ translate('private_document_upload_hint') }}</p>
                                <div class="row g-2">
                                    <div class="col-md-3">
                                        <select class="form-control" id="activation-document-type" name="document_type" required>
                                            @foreach(['national_id', 'tax_card', 'commercial_register', 'address_proof', 'other'] as $type)
                                                <option value="{{ $type }}">{{ translate($type) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3 d-none" id="other-document-name-wrap"><input class="form-control" id="other-document-name" name="other_document_name" maxlength="120" placeholder="{{ translate('other_document_name') }}"></div>
                                    <div class="col-md-3"><input class="form-control" name="document_number" placeholder="{{ translate('document_number') }}"></div>
                                    <div class="col-md-3"><input class="form-control" type="date" name="expires_at"></div>
                                    <div class="col-md-3"><input class="form-control" type="file" name="document" required accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx"></div>
                                    <div class="col-md-8 d-flex gap-3 align-items-center">
                                        <label class="mb-0"><input type="checkbox" name="is_required" value="1"> {{ translate('required_before_activation') }}</label>
                                        <label class="mb-0"><input type="checkbox" name="is_verified" value="1"> {{ translate('verified_by_employee') }}</label>
                                    </div>
                                    <div class="col-md-4 text-end"><button class="btn btn-primary" type="submit">{{ translate('upload_privately') }}</button></div>
                                </div>
                            </form>
                        @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card mb-3">
                    <div class="card-header"><h4 class="mb-0">{{ translate('case_owner') }}</h4></div>
                    <div class="card-body">
                        @if(!$activationCase->isCompleted())
                            <form method="post" action="{{ route('admin.activation-center.assign', $activationCase) }}">
                                @csrf
                                <label class="form-label">{{ translate('assigned_employee') }}</label>
                                <select class="form-control mb-2" name="assigned_admin_id" required>
                                    @foreach($admins as $admin)
                                        <option value="{{ $admin->id }}" @selected($activationCase->assigned_admin_id === $admin->id)>{{ $admin->name }}</option>
                                    @endforeach
                                </select>
                                <button class="btn btn-outline-primary w-100">{{ translate('assign_case') }}</button>
                            </form>
                        @else
                            <div><small class="text-muted">{{ translate('assigned_employee') }}</small><br><strong>{{ $activationCase->assignedAdmin?->name ?? 'â€”' }}</strong></div>
                        @endif
                    </div>
                </div>

                @if(!$activationCase->isCompleted())
                    <div class="card mb-3 border-primary">
                        <div class="card-header"><h4 class="mb-0">{{ translate('quick_activation_decision') }}</h4></div>
                        <div class="card-body">
                            <p class="text-muted">{{ translate('required_evidence_must_be_verified_before_activation') }}</p>
                            <form method="post" action="{{ route('admin.activation-center.decision', $activationCase) }}">
                                @csrf
                                <textarea class="form-control mb-2" name="note" rows="3" placeholder="{{ translate('decision_note') }}"></textarea>
                                <div class="d-grid gap-2">
                                    <button class="btn btn-success" name="decision" value="approve">{{ translate('activate_account_now') }}</button>
                                    <button class="btn btn-warning" name="decision" value="request_completion">{{ translate('request_more_information') }}</button>
                                    <button class="btn btn-danger" name="decision" value="reject">{{ translate('reject_activation') }}</button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif

                <div class="card">
                    <div class="card-header"><h4 class="mb-0">{{ translate('audit_timeline') }}</h4></div>
                    <div class="card-body">
                        @forelse($activationCase->events as $event)
                            <div class="border-start ps-3 pb-3">
                                <strong>{{ translate($event->event_type) }}</strong>
                                <div class="small text-muted">{{ $event->admin?->name ?? translate('system') }} Â· {{ $event->created_at?->format('d M Y, h:i A') }}</div>
                                @if($event->note)<div class="small mt-1">{{ $event->note }}</div>@endif
                            </div>
                        @empty
                            <div class="text-muted">{{ translate('no_audit_events') }}</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        (function () {
            const box=document.getElementById('activation-live-messages'), form=document.getElementById('activation-live-form');
            const documentType=document.getElementById('activation-document-type'), otherDocumentNameWrap=document.getElementById('other-document-name-wrap'), otherDocumentName=document.getElementById('other-document-name');
            const endpoint=@json(route('admin.activation-center.messages', $activationCase)); let ready=false, seen=new Set();
            const esc=v=>String(v??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));
            function append(m){if(!box||seen.has(String(m.id)))return;seen.add(String(m.id));const admin=m.sender_type==='admin',el=document.createElement('div');el.className='d-flex mb-3 '+(admin?'justify-content-end':'justify-content-start');el.innerHTML='<div class="p-3 rounded '+(admin?'bg-primary text-white':'bg-light')+'" style="max-width:80%"><small class="d-block mb-1 '+(admin?'text-white-50':'text-muted')+'">'+esc(m.sender_type)+' Â· '+esc(m.created_at)+'</small><div>'+esc(m.body).replace(/\n/g,'<br>')+'</div></div>';box.appendChild(el);box.scrollTop=box.scrollHeight}
            function attachmentMarkup(attachments){if(!Array.isArray(attachments)||!attachments.length)return '';return '<div class="d-flex flex-wrap gap-2 mt-2">'+attachments.map(a=>{const url=esc(a?.url||a?.full_url||a?.attachment_full_url||'#'),name=esc(a?.original_name||a?.name||'Attachment'),mime=String(a?.mime_type||'');return mime.startsWith('image/')?'<a href="'+url+'" target="_blank" rel="noopener"><img src="'+url+'" alt="'+name+'" style="width:96px;height:96px;object-fit:cover;border-radius:6px"></a>':'<a href="'+url+'" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">'+name+'</a>'}).join('')+'</div>'}
            function append(m){if(!box||seen.has(String(m.id)))return;seen.add(String(m.id));const admin=m.sender_type==='admin',el=document.createElement('div');el.className='d-flex mb-3 '+(admin?'justify-content-end':'justify-content-start');el.innerHTML='<div class="p-3 rounded '+(admin?'bg-primary text-white':'bg-light')+'" style="max-width:80%"><small class="d-block mb-1 '+(admin?'text-white-50':'text-muted')+'">'+esc(m.sender_type)+' &middot; '+esc(m.created_at)+'</small><div>'+esc(m.body).replace(/\n/g,'<br>')+'</div>'+attachmentMarkup(m.attachments)+'</div>';box.appendChild(el);box.scrollTop=box.scrollHeight}
            async function refresh(){try{const r=await fetch(endpoint,{headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});if(!r.ok)return;const d=await r.json();if(!ready){d.messages.forEach(m=>seen.add(String(m.id)));ready=true;return}d.messages.forEach(append)}catch(_){}}
            if(form)form.addEventListener('submit',async e=>{e.preventDefault();const b=form.querySelector('button[type="submit"]');if(b)b.disabled=true;try{const r=await fetch(form.action,{method:'POST',body:new FormData(form),headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});if(!r.ok)throw 0;const d=await r.json();ready=true;d.messages.forEach(append);form.reset()}catch(_){form.submit()}finally{if(b)b.disabled=false}});
            function toggleOtherDocumentName(){if(!documentType||!otherDocumentNameWrap||!otherDocumentName)return;const isOther=documentType.value==='other';otherDocumentNameWrap.classList.toggle('d-none',!isOther);otherDocumentName.required=isOther;if(!isOther)otherDocumentName.value=''}
            if(documentType){documentType.addEventListener('change',toggleOtherDocumentName);toggleOtherDocumentName()}
            refresh();setInterval(refresh,7000);
        })();
    </script>
@endpush
