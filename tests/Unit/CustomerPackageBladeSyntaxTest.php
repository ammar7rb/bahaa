<?php

namespace Tests\Unit;

use Symfony\Component\Process\Process;
use Tests\TestCase;

class CustomerPackageBladeSyntaxTest extends TestCase
{
    public function test_customer_package_and_cart_templates_compile_to_valid_php(): void
    {
        foreach (['customer-account/notifications.blade.php','customer-account/sidebar-communication.blade.php','customer-support/index.blade.php','admin-views/customer/monthly-packages.blade.php', 'customer-subscription/packages.blade.php', 'customer-subscription/profile-overview.blade.php', 'customer-subscription/payment-dialogs.blade.php', 'web-views/users-profile/partials/purchasing-limit-strip.blade.php'] as $view) {
            $compiled = app('blade.compiler')->compileString(file_get_contents(resource_path('views/'.$view)));
            $temporary = tempnam(sys_get_temp_dir(), 'customer-blade-');
            try {
                file_put_contents($temporary, $compiled);
                $process = new Process([PHP_BINARY, '-l', $temporary]);
                $process->run();
                $this->assertTrue($process->isSuccessful(), $view.': '.$process->getErrorOutput().$process->getOutput());
            } finally {
                unlink($temporary);
            }
        }
        foreach (['theme_sigma_care/theme-views/partials/_order-summery.blade.php','theme_sigma_care/theme-views/layouts/app.blade.php','theme_aster/theme-views/layouts/app.blade.php','default/layouts/front-end/app.blade.php'] as $view) {
            $compiled = app('blade.compiler')->compileString(file_get_contents(resource_path('themes/'.$view)));
            $temporary = tempnam(sys_get_temp_dir(), 'credit-blade-');
            try {
                file_put_contents($temporary,$compiled);
                $process = new Process([PHP_BINARY,'-l',$temporary]); $process->run();
                $this->assertTrue($process->isSuccessful(),$view.': '.$process->getOutput());
            } finally { unlink($temporary); }
        }
    }
}
