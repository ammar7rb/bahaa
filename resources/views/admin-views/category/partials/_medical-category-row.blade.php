<tr>
    <td><span style="padding-inline-start: {{ $level * 24 }}px" class="d-inline-block">{{ $level ? '↳ ' : '' }}{{ $category->name }}</span><br><small class="text-muted" style="padding-inline-start: {{ $level * 24 }}px">#{{ $category->id }}</small></td>
    <td>{{ $level === 0 ? translate('main_category') : ($level === 1 ? translate('sub_category') : translate('sub_sub_category')) }}</td>
    <td>{{ $category->product_count ?? 0 }}</td>
    <td><form method="post" action="{{ route('admin.category.medical-priority') }}" class="d-flex gap-2">@csrf<input type="hidden" name="id" value="{{ $category->id }}"><input class="form-control form-control-sm" style="max-width:90px" type="number" min="0" name="priority" value="{{ $category->priority ?? 0 }}"><button class="btn btn-sm btn-outline-primary" type="submit">{{ translate('save') }}</button></form></td>
    <td><form method="post" action="{{ route('admin.category.medical-status') }}">@csrf<input type="hidden" name="id" value="{{ $category->id }}"><input type="hidden" name="is_active" value="{{ $category->is_active ? 0 : 1 }}"><button class="btn btn-sm {{ $category->is_active ? 'btn-success' : 'btn-outline-secondary' }}" type="submit">{{ $category->is_active ? translate('active') : translate('inactive') }}</button></form></td>
</tr>
@foreach($category->childes as $child)
    @include('admin-views.category.partials._medical-category-row', ['category' => $child, 'level' => $level + 1])
@endforeach
