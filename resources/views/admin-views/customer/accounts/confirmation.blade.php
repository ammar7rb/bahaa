<input type="hidden" name="customer_id" value="{{ $customer->id }}">
<input type="hidden" name="request_key" value="{{ \Illuminate\Support\Str::uuid() }}">
<label>سبب العملية {{ ($page==='list')?'«اختياري»':'' }}<textarea class="form-control" name="reason" rows="2" maxlength="2000" {{ $page!=='list' ? 'required' : '' }}></textarea></label>
<label>اكتب رقم الحساب للتأكيد: <bdi>{{ $customer->administrative_reference }}</bdi><input type="text" name="confirmation" class="form-control" autocomplete="off" required dir="ltr"></label>
