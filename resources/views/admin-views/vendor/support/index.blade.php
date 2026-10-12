@extends('layouts.admin.app')
@section('title', translate('vendor_support_conversations'))
@section('content')
<div class="content container-fluid">
    <h1 class="h2 mb-3">{{ translate('vendor_support_conversations') }}</h1>
    <div class="card mb-4"><div class="card-body">
        <h2 class="h4">{{ translate('vendor_open_conversation') }}</h2>
        <p class="text-muted">{{ translate('vendor_admin_conversation_hint') }}</p>
        @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
        <form method="post" action="{{ route('admin.vendor-support.store') }}">@csrf
            <div class="row"><div class="col-md-6"><label for="support-seller">{{ translate('vendor') }}</label><select class="form-control" id="support-seller" name="seller_id" required><option value="">{{ translate('select') }}</option>@foreach($sellers as $seller)<option value="{{ $seller->id }}" @selected(old('seller_id') == $seller->id)>{{ $seller->administrative_reference }} — {{ $seller->shop?->name ?: $seller->f_name.' '.$seller->l_name }}</option>@endforeach</select></div>
            <div class="col-md-6"><label for="support-subject">{{ translate('subject') }}</label><input class="form-control" id="support-subject" name="subject" maxlength="191" value="{{ old('subject') }}" required></div></div>
            <label class="mt-3" for="support-body">{{ translate('message') }}</label><textarea class="form-control" id="support-body" name="body" maxlength="10000" rows="4" required>{{ old('body') }}</textarea><button class="btn btn-primary mt-3">{{ translate('vendor_open_conversation') }}</button>
        </form>
    </div></div>
    <div class="card"><div class="table-responsive"><table class="table"><thead><tr><th>{{ translate('vendor') }}</th><th>{{ translate('subject') }}</th><th>{{ translate('status') }}</th><th>{{ translate('last_update') }}</th></tr></thead><tbody>
    @forelse($threads as $thread)<tr><td>{{ $thread->seller?->administrative_reference }} — {{ $thread->seller?->shop?->name }}</td><td><a href="{{ route('admin.vendor-support.show', $thread) }}">{{ $thread->subject }}</a></td><td>{{ translate($thread->status) }}</td><td>{{ $thread->updated_at->format('Y-m-d H:i') }}</td></tr>@empty<tr><td colspan="4">{{ translate('vendor_no_conversations') }}</td></tr>@endforelse
    </tbody></table></div><div class="card-body">{{ $threads->links() }}</div></div>
</div>
@endsection
