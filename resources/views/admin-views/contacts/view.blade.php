@extends('layouts.admin.app')

@section('title', translate('contact_View'))

@section('content')
    <div class="content container-fluid">
        <div class="container">
            <div class="mb-3">
                <h2 class="h1 mb-0 text-capitalize d-flex align-items-center gap-2">
                    <img width="20" src="{{dynamicAsset(path: 'public/assets/back-end/img/message.png')}}" alt="">
                    {{translate('message_view')}}
                </h2>
            </div>

            <div class="row">
                <div class="col-lg-6">
                    <div class="card mb-3">
                        <div class="card-header d-flex gap-2 flex-wrap align-items-center justify-content-between">
                            <h4 class="mb-0 text-capitalize d-flex gap-2 align-items-center text-capitalize">
                                <i class="fi fi-sr-user"></i>
                                {{translate('user_details')}}
                            </h4>
                            <form action="{{route('admin.contact.update',$contact->id)}}" method="post" id="submit-form">
                                @csrf
                                <div class="d-flex justify-content-end">
                                    @if($contact->seen==0)
                                        <button type="button" class="btn btn-success form-alert" data-id="submit-form" data-message="{{translate('want_check_this_message').'?'}}">
                                            <i class="fi fi-rr-check-circle"></i> {{translate('check')}}
                                        </button>
                                    @else
                                        <button type="button" class="btn btn-info text-capitalize" disabled>
                                            <i class="fi fi-rr-check-circle"></i> {{translate('already_check')}}
                                        </button>
                                    @endif
                                </div>
                            </form>
                        </div>
                        <div class="card-body">
                            <div class="pl-2 d-flex gap-2 align-items-center mb-3">
                                <strong class="">{{$contact->subject}}</strong>
                                @if($contact->seen==1)
                                    <label class="badge text-bg-info badge-info mb-0">{{translate('seen')}}</label>
                                @else
                                    <label class="badge badge-soft-info mb-0 text-capitalize">{{translate('not_seen_yet')}}</label>
                                @endif
                            </div>
                            <table class="table table-sm table-user-information table-borderless mb-0">
                                <tbody>
                                    <tr>
                                        <td>{{translate('name')}}:</td>
                                        <td>{{$contact['name']}}</td>
                                    </tr>
                                    <tr>
                                        <td>{{translate('mobile_no')}}:</td>
                                        <td>{{$contact['mobile_number']}}</td>
                                    </tr>
                                    <tr>
                                        <td>{{translate('email')}}:</td>
                                        <td>{{$contact['email']}}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card mb-3">
                        <div class="card-header justify-content-center">
                            <h4 class="mb-0 text-capitalize text-center">
                                {{translate('message_Log')}}
                            </h4>
                        </div>
                        <div class="card-body d-flex flex-column gap-2">
                            @foreach(\Illuminate\Support\Facades\DB::table('contact_conversation_messages')->where('contact_id', $contact->id)->orderBy('id')->get() as $conversationMessage)
                                <div class="border rounded p-3">
                                    <strong>{{ in_array($conversationMessage->sender, ['admin', 'admin_password']) ? translate('admin') : $contact->name }}</strong>
                                    <p class="mb-0 mt-2" style="white-space:pre-wrap;overflow-wrap:anywhere">{{ \App\Services\ContactPasswordResetService::displayBody($conversationMessage) }}</p>
                                    @foreach(json_decode($conversationMessage->attachments ?? '[]', true) ?: [] as $attachment)
                                        <a class="btn btn-outline-primary btn-sm mt-2" target="_blank" rel="noopener" href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($attachment['path']) }}">{{ $attachment['name'] }}</a>
                                    @endforeach
                                </div>
                            @endforeach
                            <div class="mb-3">
                                <h5 class="badge text-bg-info badge-info rounded mb-3 d-flex">{{ $contact->name }}</h5>
                                <div class="d-flex gap-2 mb-1">
                                    <strong>{{translate('subject')}}: </strong>
                                    <div><strong>{{$contact->subject}}</strong></div>
                                </div>
                                <div class="d-flex gap-2">
                                    <strong>{{translate('message')}}: </strong>
                                    <div>{{$contact->message}}</div>
                                </div>
                            </div>
                            <div>
                                <h5 class="badge text-bg-warning badge-warning rounded mb-3 d-flex">{{translate('admin')}}</h5>
                                @if($contact['reply']!=null)
                                    @php($data=json_decode($contact['reply'],true))
                                    <div class="d-flex gap-2 mb-1">
                                        <strong>{{translate('subject')}}: </strong>
                                        <div><strong>{{$data['subject']}}</strong></div>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <strong>{{translate('message')}}: </strong>
                                        <div>{{$data['body']}}</div>
                                    </div>
                                @elseif(!\Illuminate\Support\Facades\DB::table('contact_conversation_messages')->where('contact_id', $contact->id)->where('sender', 'admin')->exists())
                                    <label class="badge text-white bg-danger">{{translate('no_reply')}}.</label>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-body border-bottom">
                            <h3>{{ translate('admin_reset_account_password') }}</h3>
                            <p class="text-muted">{{ translate('admin_reset_identity_notice') }} <bdi>{{ $contact->email }}</bdi></p>
                            <form method="post" action="{{ route('admin.contact.reset-password', $contact->id) }}" autocomplete="off">
                                @csrf
                                <label for="reset-account-type">{{ translate('account_type') }}</label>
                                <select class="form-control mb-3" id="reset-account-type" name="account_type" required>
                                    <option value="customer">{{ translate('customer') }}</option>
                                    <option value="seller">{{ translate('seller') }}</option>
                                </select>
                                <label for="admin-new-password">{{ translate('new_password') }}</label>
                                <input id="admin-new-password" class="form-control mb-3" type="password" name="password" minlength="8" maxlength="72" autocomplete="new-password" required>
                                <label for="admin-confirm-password">{{ translate('confirm_password') }}</label>
                                <input id="admin-confirm-password" class="form-control mb-3" type="password" name="password_confirmation" minlength="8" maxlength="72" autocomplete="new-password" required>
                                <label class="d-flex gap-2 mb-3"><input type="checkbox" name="identity_verified" value="1" required> {{ translate('admin_identity_verified') }}</label>
                                <button class="btn btn-primary" type="submit">{{ translate('admin_reset_and_send') }}</button>
                            </form>
                        </div>
                        <div class="card-body mt-3 mx-lg-4">
                            <div class="row text-start">
                                <div class="col-12">
                                    <div class="d-flex justify-content-center">
                                        <h3>{{translate('reply_in_conversation')}}</h3>
                                        <?php
                                            $emailServices_smtp = getWebConfig(name: 'mail_config');
                                            if ($emailServices_smtp['status'] == 0) {
                                                $emailServices_smtp = getWebConfig(name: 'mail_config_sendgrid');
                                            }
                                        ?>
                                        @if($emailServices_smtp['status'] != 1)
                                            <label class="badge-soft-info px-1">{{translate('conversation_email_optional_notice')}}</label>
                                        @endif
                                    </div>
                                    <form action="{{route('admin.contact.send-mail', $contact->id)}}" method="post">
                                        @csrf
                                        <div class="form-group mt-2">
                                            <div class="row">
                                                <div class="col-md-12">
                                                    <label class="mb-2">{{translate('subject')}}</label>
                                                    <input class="form-control" name="subject" value="{{ translate('contact_technical_support') }}" readonly>
                                                </div>
                                                <div class="col-md-12 mt-3">
                                                    <label class="mb-2">{{translate('mail_Body')}}</label>
                                                    <textarea class="form-control h-100" name="mail_body"
                                                              placeholder="{{translate('please_send_a_Feedback')}}" required></textarea>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-end pt-3 mt-5">
                                            <button type="submit" class="btn btn-primary">
                                            {{translate('send')}} <i class="fi fi-rr-paper-plane-top"></i>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
