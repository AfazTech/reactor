<?php

namespace App\Migrations;

use Reactor\Database\Migrations\Migration;
use Reactor\Contracts\DatabaseManagerInterface;

class CreateUsersTable extends Migration
{
    public function __construct(DatabaseManagerInterface $db)
    {
        parent::__construct($db);
    }

    public function up(): void
    {
        if (!$this->db->schema()->hasTable('users')) {
            $this->db->schema()->create('users', function ($table) {
                $table->increments('id');
                $table->bigInteger('user_id')->unique();
                $table->string('username', 64)->nullable();
                $table->string('first_name', 64)->nullable();
                $table->string('last_name', 64)->nullable();
                $table->boolean('status')->default(true);
                $table->string('step', 255)->nullable();
                $table->json('temp')->nullable();
                // Default language mirrors config('app.default_language')
                // which itself defaults to 'en' when DEFAULT_LANGUAGE is
                // not set in .env.
                $table->string('language', 10)->default('en');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        $this->db->schema()->dropIfExists('users');
    }
}
