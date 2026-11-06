<div class="card card-body">
    <div class="mb-20">
        <h2 class="mb-1">{{ translate('Basic_Setup') }}</h2>
    </div>
    <div class="row gy-4">
        <div class="col-md-4">
            @include("vendor-views.product.add._product-thumbnail")
        </div>
        <div class="col-md-8">
            @include("vendor-views.product.add._additional-images")
        </div>
        <div class="col-12">
            @include("vendor-views.product.add._title-description", ['fieldPart' => 'name'])
        </div>
    </div>
</div>
