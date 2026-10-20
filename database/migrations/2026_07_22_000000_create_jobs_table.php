<?php

namespace App\Migrations;

use Reactor\Database\Migrations\Migration;
use Reactor\Contracts\DatabaseManagerInterface;

class CreateJobsTable extends Migration
{
    public function __construct(DatabaseManagerInterface $db)
    {
        parent::__construct($db);
    }

    public function up(): void
    {
        if (!$this->db->schema()->hasTable('jobs')) {
            $this->db->schema()->create('jobs', function ($table) {
                $table->id();
                $table->string('queue');
                $table->text('payload');
                $table->tinyInteger('attempts')->default(0);
                $table->integer('reserved_at')->nullable();
                $table->integer('available_at');
                $table->integer('created_at');

                // DatabaseQueue::popFromQueue() selects by
                // `WHERE queue = ? AND available_at <= ?`, so a composite
                // index on (queue, available_at) lets SQLite/MySQL satisfy
                // both predicates from one B-Tree. Two standalone indexes
                // on the same columns would force the planner to choose
                // only one of them.
                $table->index(['queue', 'available_at']);
            });
        }
    }

    public function down(): void
    {
        $this->db->schema()->dropIfExists('jobs');
    }
}
