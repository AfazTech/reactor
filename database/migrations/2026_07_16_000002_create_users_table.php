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

                // Panel statistics query these columns as date ranges,
                // so a plain B-Tree index is used directly. A whereDate()
                // query would wrap the column in a function and bypass
                // the index, which is why PanelStatsHandler uses
                // whereBetween() instead.
                $table->index('created_at');
                $table->index('last_interaction_at');

                // Distribution of users by preferred language, used by
                // the admin panel and any future localised broadcast.
                $table->index('language');

                // Composite index for "list active users ordered by
                // last interaction". The standalone last_interaction_at
                // index above is still required for queries that filter
                // by date alone (the panel "interacted today" counter),
                // because the composite cannot serve a range on its
                // second column when the first column is not filtered.
                $table->index(['status', 'last_interaction_at']);
            });
        }
    }

    public function down(): void
    {
        $this->db->schema()->dropIfExists('users');
    }
}
