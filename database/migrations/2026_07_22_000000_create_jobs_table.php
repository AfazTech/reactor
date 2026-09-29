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
                $table->string('queue')->index();
                $table->text('payload');
                $table->tinyInteger('attempts')->default(0);
                $table->integer('reserved_at')->nullable();
                $table->integer('available_at')->index();
                $table->integer('created_at');
            });
        }
    }

    public function down(): void
    {
        $this->db->schema()->dropIfExists('jobs');
    }
}
