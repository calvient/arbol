<?php

namespace Calvient\Arbol\Tests;

use Calvient\Arbol\ArbolServiceProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // Run package migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function getPackageProviders($app)
    {
        return [
            ArbolServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Use SQLite for testing
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Set up a simple User model for testing
        $app['config']->set('arbol.user_model', TestUser::class);
        $app['config']->set('arbol.series_path', __DIR__.'/Series');
    }

    protected function defineDatabaseMigrations()
    {
        // Create users table for testing
        $this->app['db']->connection()->getSchemaBuilder()->create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->integer('client_id')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        // Create arbol_reports table
        $this->app['db']->connection()->getSchemaBuilder()->create('arbol_reports', function ($table) {
            $table->id();
            $table->timestamps();
            $table->string('name');
            $table->text('description')->nullable();
            $table->bigInteger('author_id')->unsigned()->index();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->json('user_ids')->nullable();
        });

        // Create arbol_sections table
        $this->app['db']->connection()->getSchemaBuilder()->create('arbol_sections', function ($table) {
            $table->id();
            $table->timestamps();
            $table->bigInteger('arbol_report_id')->unsigned()->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('series');
            $table->string('slice')->nullable();
            $table->string('xaxis_slice')->nullable();
            $table->string('aggregator')->nullable()->default('Default');
            $table->string('percentage_mode')->nullable();
            $table->json('filters')->nullable();
            $table->string('format')->default('table');
            $table->integer('sequence')->default(0);
        });
    }
}

/**
 * Simple test user model for testing purposes
 */
class TestUser extends Model implements Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): mixed
    {
        return $this->id;
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        return $this->password ?? '';
    }

    public function getRememberToken(): ?string
    {
        return $this->remember_token;
    }

    public function setRememberToken($value): void
    {
        $this->remember_token = $value;
    }

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }
}
