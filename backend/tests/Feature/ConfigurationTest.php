<?php

namespace Tests\Feature;

use Tests\TestCase;

class ConfigurationTest extends TestCase
{
    public function test_the_three_supported_databases_are_configurable(): void
    {
        $this->assertSame('sqlite', config('database.connections.sqlite.driver'));
        $this->assertSame('mysql', config('database.connections.mysql.driver'));
        $this->assertSame('mariadb', config('database.connections.mariadb.driver'));
        $this->assertSame('pgsql', config('database.connections.pgsql.driver'));
    }

    public function test_the_example_environment_documents_each_database(): void
    {
        $example = file_get_contents(base_path('.env.example'));

        foreach (['sqlite', 'mysql', 'mariadb', 'pgsql'] as $driver) {
            $this->assertStringContainsString("DB_CONNECTION={$driver}", $example);
        }
    }

    public function test_dates_are_handled_in_utc_and_the_interface_language_is_french(): void
    {
        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame('fr', config('app.locale'));
    }
}
