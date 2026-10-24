<?php

return [

    /*
     * Queue driver. Only "database" is supported by the current framework.
     */
    'default' => env('QUEUE_CONNECTION', 'database'),

    /*
     * Table used by the database driver.
     */
    'table' => env('QUEUE_TABLE', 'jobs'),

    /*
     * Orphan recovery window, in seconds.
     *
     * A reserved job whose worker died before calling delete() or
     * release() stays reserved forever unless this window lets other
     * workers reclaim it. The framework enforces the invariant:
     *
     *     job_max_duration  <  timeout  <  retry_after
     *
     * With the default timeout of 60 seconds, retry_after should be
     * at least 90 seconds. Set this higher for jobs that routinely
     * run for several minutes.
     */
    'retry_after' => (int) env('QUEUE_RETRY_AFTER', 90),

];
