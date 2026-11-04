# Flutter full-suite results — 2026-11-04

## customer

{"success":79}

## vendor

{"success":60,"error":3,"failure":2}

- red activation banner becomes green and remains green after refresh (error)

    Test failed. See exception logs above.
    The test description was: red activation banner becomes green and remains green after refresh
- backend keeps approval internal while seller product starts active (failure)

    Expected: contains '$submittedImageCount < 5'
      Actual: '<?php\n'
                '\n'
                'namespace App\\Http\\Controllers\\RestAPI\\v3\\seller;\n'
                '\n'
                'use App\\Contracts\\Repositories\\AuthorRepositoryInterface;\n'
                'use App\\Contracts\\Repositories\\BrandRepositoryInterface;\n'
                'use App\\Contracts\\Repositories\\DigitalProductAuthorRepositoryInterface;\n'
                'use App\\Contracts\\Repositories\\ProductRepositoryInterface;\n'
                'use App\\Contracts\\Repositories\\PublishingHouseRepositoryInterface;\n'
                'use App\\Contracts\\Repositories\\RestockProductCustomerRepositoryInterface;\n'
                'use App\\Contracts\\Repositories\\RestockProductRepositoryInterface;\n'
                'use App\\Contracts\\Repositories\\StockClearanceProductRepositoryInterface;\n'
                'use App\\Contracts\\Repositories\\StockClearanceSetupRepositoryInterface;\n'
                'use App\\Enums\\WebConfigKey;\n'
                'use App\\Http\\Controllers\\Controller;\n'
                'use App\\Http\\Requests\\API\\v3\\DigitalProductUploadRequest;\n'
                'use App\\Http\\Requests\\API\\v3\\ProductAddRequest;\n'
               
- seller media, generated code, package and optional unit rules are wired (failure)

    Expected: contains 'productImageCount < 5'
      Actual: 'import \'package:sixvalley_vendor_app/features/dashboard/screens/dashboard_screen.dart\';\r\n'
                'import \'package:flutter/foundation.dart\';\r\n'
                'import \'package:flutter/material.dart\';\r\n'
                'import \'package:image_picker/image_picker.dart\';\r\n'
                'import \'package:provider/provider.dart\';\r\n'
                'import \'package:sixvalley_vendor_app/features/addProduct/controllers/add_product_image_controller.dart\';\r\n'
                'import \'package:sixvalley_vendor_app/features/addProduct/controllers/add_product_tax_controller.dart\';\r\n'
                'import \'package:sixvalley_vendor_app/features/addProduct/controllers/digital_product_controller.dart\';\r\n'
                'import \'package:sixvalley_vendor_app/features/addProduct/controllers/variation_controller.dart\';\r\n'
                'import \'package:sixvalley_vendor_app/features/addProduct/domain/models/add_product_model.dart\';\r\n'
                'import \'package:sixvalley_vendor_app/data/model/response/base/api_response.dart\';\r\n'
                'import \'package:sixvalley_vendor_app/features/addProduct/domain/models/attribut
- Arabic delivery form fits narrow dark and light screens and completed state removes submit (error)

    Test failed. See exception logs above.
    The test description was: Arabic delivery form fits narrow dark and light screens and completed state removes submit
- three final-price packages and slot replacement fit Arabic phone and tablet (error)

    Test failed. See exception logs above.
    The test description was: three final-price packages and slot replacement fit Arabic phone and tablet
