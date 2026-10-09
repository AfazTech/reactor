<?php

namespace App\Migrations;

use Reactor\Database\Migrations\Migration;
use Reactor\Contracts\DatabaseManagerInterface;

/**
 * Versioned migration for the queue failed_jobs table.
 *
 * The framework's FailedJobRepository creates this table lazily on
 * first failure, but tracking it here keeps the schema consistent
 * across environments and makes the change visible to migrate:status.
 */
class CreateFailedJobsTable extends Migration
{
    public function __construct(DatabaseManagerInterface $db)
    {
        parent::__construct($db);
    }

    public function up(): void
    {
        if (!$this->db->schema()->hasTable('failed_jobs')) {
            $this->db->schema()->create('failed_jobs', function ($table) {
                $table->id();
                $table->string('queue')->index();
                $table->text('payload');
                $table->text('exception');
                $table->integer('failed_at')->index();
            });
        }
    }

    public function down(): void
    {
        $this->db->schema()->dropIfExists('failed_jobs');
    }
}
