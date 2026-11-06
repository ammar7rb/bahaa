@foreach(['floor'=>'الدور','unit_number'=>'رقم الشقة / المكتب','company_details'=>'بيانات الشركة / المكتب'] as $field=>$label)
<div class="mb-3"><label for="address-{{ $field }}">{{ $label }}</label><input class="form-control" id="address-{{ $field }}" name="{{ $field }}" type="text" maxlength="{{ $field === 'company_details' ? 500 : 100 }}" value="{{ old($field, data_get($addressDetails ?? null,$field)) }}"></div>
@endforeach
