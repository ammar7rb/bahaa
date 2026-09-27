@php($selectedMedicalIcon = $selectedMedicalIcon ?? null)
<div class="mb-20">
    <label class="form-label fw-semibold">{{ translate('Category_visual') }}</label>
    <div class="d-flex gap-2 mb-3">
        <label class="btn btn-outline-primary flex-fill">
            <input class="form-check-input me-1 category-visual-source" type="radio" name="visual_source" value="library" {{ $selectedMedicalIcon ? 'checked' : '' }}>
            {{ translate('Choose_medical_icon') }}
        </label>
        <label class="btn btn-outline-secondary flex-fill">
            <input class="form-check-input me-1 category-visual-source" type="radio" name="visual_source" value="upload" {{ $selectedMedicalIcon ? '' : 'checked' }}>
            {{ translate('Upload_image') }}
        </label>
    </div>
    <div class="medical-icon-library border rounded p-3" style="max-height: 290px; overflow-y: auto;">
        <div class="row g-2">
            @foreach(config('medical_category_icons', []) as $key => $icon)
                <div class="col-3">
                    <label class="border rounded p-2 w-100 h-100 text-center cursor-pointer">
                        <input class="form-check-input mb-1" type="radio" name="medical_icon" value="{{ $key }}" {{ $selectedMedicalIcon === $key ? 'checked' : '' }}>
                        <span class="d-block fs-24" aria-hidden="true">{{ $icon['glyph'] }}</span>
                        <small class="d-block text-truncate">{{ translate($icon['label']) }}</small>
                    </label>
                </div>
            @endforeach
        </div>
    </div>
    <small class="text-muted d-block mt-2">{{ translate('Choose_from_the_medical_library_or_upload_your_own_category_image') }}</small>
</div>
