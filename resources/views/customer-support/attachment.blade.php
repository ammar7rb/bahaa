@if(!empty($file['path']))
    @php
        $attachmentUrl = $file['path'];
        $isImage = (bool) preg_match('/\.(jpe?g|png|gif|webp)$/i', parse_url($attachmentUrl, PHP_URL_PATH) ?? '');
    @endphp
    <figure class="support-chat-sent-file">
        @if($isImage)
            <a class="support-chat-image-link" href="{{ $attachmentUrl }}" target="_blank" rel="noopener"><img class="support-chat-sent-image" src="{{ $attachmentUrl }}" alt="صورة مرفقة" loading="lazy"></a>
        @else
            <a class="support-chat-file" href="{{ $attachmentUrl }}" target="_blank" rel="noopener">عرض المرفق</a>
        @endif
        <a class="support-chat-download" href="{{ route('support-ticket.attachments.download',[$conversation->id,$messageId,$attachmentIndex]) }}" download>تنزيل المرفق</a>
    </figure>
@endif
