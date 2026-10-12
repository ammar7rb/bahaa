<?php

namespace Tests\Unit;

use Symfony\Component\Process\Process;
use Tests\TestCase;

class CustomerPackageBladeSyntaxTest extends TestCase
{
    public function test_customer_package_and_cart_templates_compile_to_valid_php(): void
    {
        foreach (['admin-views/customer/monthly-packages.blade.php', 'customer-subscription/packages.blade.php', 'customer-subscription/profile-overview.blade.php', 'customer-subscription/payment-dialogs.blade.php', 'web-views/users-profile/partials/purchasing-limit-strip.blade.php'] as $view) {
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
    }
}
