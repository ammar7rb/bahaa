<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testApplicationContainerBootsInTestingEnvironment(): void
    {
        $this->assertTrue($this->app->bound('router'));
        $this->assertTrue($this->app->environment('testing'));
        $this->assertNotEmpty($this->app->version());
    }
}
