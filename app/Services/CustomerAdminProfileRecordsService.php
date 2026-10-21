<?php

namespace App\Services;

use App\Models\AccountActivationCase;
use App\Models\AccountActivationDocument;
use App\Models\AccountActivationProfileField;
use Illuminate\Support\Facades\Schema;

/** Read-only admin projection; private files stay behind existing authorized routes. */
class CustomerAdminProfileRecordsService
{
    public function forCustomer(int $customerId): array
    {
        $casesReady = Schema::hasTable('account_activation_cases');
        $documentsReady = $casesReady && Schema::hasTable('account_activation_documents');
        $fieldsReady = $casesReady && Schema::hasTable('account_activation_profile_fields');
        $caseIds = $casesReady ? AccountActivationCase::where('subject_type', AccountActivationCase::SUBJECT_CUSTOMER)
            ->where('subject_id', $customerId)->pluck('id') : collect();
        $documents = $documentsReady ? AccountActivationDocument::whereIn('account_activation_case_id', $caseIds)
            ->latest('updated_at')->latest('id')->get()->map(fn ($document) => [
                'id' => $document->id,
                'case_id' => $document->account_activation_case_id,
                'title' => $document->document_type === 'other' ? ($document->custom_name ?: translate('other')) : translate($document->document_type),
                'name' => $document->original_name,
                'number' => $document->document_number,
                'verification_status' => $document->verification_status,
                'created_at' => $document->created_at?->toIso8601String(),
                'updated_at' => $document->updated_at?->toIso8601String(),
                'expires_at' => $document->expires_at?->format('Y-m-d'),
                'preview_url' => in_array($document->mime_type, ['image/jpeg','image/png','image/webp','application/pdf'], true)
                    ? route('admin.activation-center.documents.preview', ['activationCase'=>$document->account_activation_case_id,'document'=>$document->id]) : null,
                'download_url' => route('admin.activation-center.documents.download', ['activationCase'=>$document->account_activation_case_id,'document'=>$document->id]),
            ])->values()->all() : [];
        $fields = $fieldsReady ? AccountActivationProfileField::whereIn('account_activation_case_id', $caseIds)
            ->whereNotIn('field_key', ['password','password_confirmation','remember_token','access_token','otp'])
            ->latest('updated_at')->latest('id')->get()->map(fn ($field) => [
                'id' => $field->id,
                'case_id' => $field->account_activation_case_id,
                'label' => $field->label,
                'value' => $field->value,
                'verified' => $field->is_verified,
                'created_at' => $field->created_at?->toIso8601String(),
                'updated_at' => $field->updated_at?->toIso8601String(),
            ])->values()->all() : [];

        return ['documents_ready'=>$documentsReady,'fields_ready'=>$fieldsReady,'documents'=>$documents,'fields'=>$fields];
    }
}
