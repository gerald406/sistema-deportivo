<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Las pruebas usan RefreshDatabase, que vacia la base: solo pueden
     * correr sobre una base *_test (phpunit.xml). Si la configuracion
     * esta cacheada (config:cache) phpunit.xml no se aplica y esto evita
     * que se borre la base de trabajo.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $database = (string) config('database.connections.' . config('database.default') . '.database');

        if (! str_ends_with($database, '_test')) {
            $this->fail("Las pruebas solo corren sobre una base *_test (configurada: {$database}).");
        }
    }
}
