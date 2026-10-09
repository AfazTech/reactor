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
                // Activity flag: 0 = inactive / blocked, 1 = active.
                $table->boolean('status')->default(false);
                $table->string('step', 255)->nullable();
                $table->json('temp')->nullable();
                // No default: an empty (NULL) value signals that the user
                // has not chosen a language yet. StartHandler detects this
                // and shows the language selection screen.
                $table->string('language', 10)->nullable();
                // Timestamp of the most recent interaction with the bot
                // (message, callback, block/unblock event, ...).
                $table->dateTime('last_interaction_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        $this->db->schema()->dropIfExists('users');
    }
}
