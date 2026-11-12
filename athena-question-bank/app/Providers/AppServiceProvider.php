<?php

namespace App\Providers;

use Illuminate\Database\Connection;
use Illuminate\Database\PostgresConnection;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Compatibilidad con Supabase PgBouncer Transaction Pooler (puerto 6543):
        // cuando ATTR_EMULATE_PREPARES está activo, preservar booleanos como 'true'/'false'
        // evitando que Connection::prepareBindings los convierta en enteros (0/1).
        Connection::resolverFor('pgsql', function ($connection, $database, $prefix, $config) {
            return new class($connection, $database, $prefix, $config) extends PostgresConnection {
                public function prepareBindings(array $bindings)
                {
                    $emulating = (bool) ($this->getConfig('options')[\PDO::ATTR_EMULATE_PREPARES] ?? false);
                    if ($emulating) {
                        foreach ($bindings as $key => $value) {
                            if (is_bool($value)) {
                                $bindings[$key] = $value ? 'true' : 'false';
                            }
                        }
                    }

                    return parent::prepareBindings($bindings);
                }
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
