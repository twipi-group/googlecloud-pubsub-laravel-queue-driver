<?php

namespace TwipiGroup\GoogleCloudPubSubLaravelQueueDriver;

use Illuminate\Queue\QueueManager;
use Illuminate\Support\ServiceProvider;
use TwipiGroup\GoogleCloudPubSubLaravelQueueDriver\Queue\Connectors\GcPubSubConnector;

class GcServiceProviderQueue extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/GcPubSubConf.php', 'queue.connections.gcpubsub'
        );
    }

    /**
     * Register the application's event listeners.
     *
     * @return void
     */
    public function boot(): void
    {
        /** @var QueueManager $queue */
        $queue = $this->app['queue'];

        $queue->addConnector('gcpubsub', function () {
            return new GcPubSubConnector;
        });
    }
}
