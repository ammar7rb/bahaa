<?php

namespace Tests\Unit;

use App\Http\Controllers\Customer\AccountPhotoController;
use App\Services\AccountPhotoAccess;
use Illuminate\Support\Facades\Storage;
use ReflectionMethod;
use Tests\TestCase;

class AccountPhotoFilenameTest extends TestCase
{
    public function test_new_photo_names_fit_the_varchar_30_image_columns(): void
    {
        $names = array_map(fn () => AccountPhotoAccess::newFilename(), range(1, 50));

        foreach ($names as $name) {
            $this->assertLessThanOrEqual(30, strlen($name));
            $this->assertMatchesRegularExpression('/\A[a-f0-9]{24}\.webp\z/', $name);
        }
        $this->assertCount(50, array_unique($names));
    }

    public function test_truncated_legacy_names_resolve_to_the_stored_file(): void
    {
        Storage::fake('local');
        $full = '1ed57131-9811-42df-9321-546b1758542f.webp';
        Storage::disk('local')->put('private-account-photos/seller/'.$full, 'x');

        $recover = new ReflectionMethod(AccountPhotoController::class, 'recoverTruncatedName');
        $recover->setAccessible(true);
        $controller = new AccountPhotoController();

        $this->assertSame($full, $recover->invoke($controller, substr($full, 0, 30), 'seller'));
        $this->assertSame('def.png', $recover->invoke($controller, 'def.png', 'seller'));
        $this->assertSame($full, $recover->invoke($controller, $full, 'seller'));
        $this->assertSame('1ed57131-9811-42df-9321-aaaaaa', $recover->invoke($controller, '1ed57131-9811-42df-9321-aaaaaa', 'seller'));
    }
}
