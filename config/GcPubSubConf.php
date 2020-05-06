<?php

/**
 * This is an example of queue connection configuration.
 * It will be merged into config/queue.php.
 * You need to set proper values in `.env`.
 */
return [
    'driver' => 'gcpubsub',
    'project_id' => env('PUBSUB_PROJECT_ID', 'google-cloud-project-id'),
    'queue' => env('PUBSUB_QUEUE_DEFAULT', 'default'),
    'topic_suffix' => env('PUBSUB_TOPIC_SUFFIX', ''),
    'subscriber_suffix' => env('PUBSUB_SUBSCRIBER_SUFFIX', ''),
    'max_tries' => env('PUBSUB_JOB_MAX_TRIES', 1),
    'retry_delay' => env('PUBSUB_JOB_RETRY_DELAY', 0),
];
