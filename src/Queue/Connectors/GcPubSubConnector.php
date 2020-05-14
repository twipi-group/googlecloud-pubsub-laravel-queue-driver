<?php

namespace TwipiGroup\GoogleCloudPubSubLaravelQueueDriver\Queue\Connectors;

use Google\Cloud\PubSub\PubSubClient;
use Illuminate\Queue\Connectors\ConnectorInterface;
use InvalidArgumentException;
use TwipiGroup\GoogleCloudPubSubLaravelQueueDriver\Queue\GcPubSubQueue;
use TwipiGroup\GoogleCloudPubSubPhpAdapter\GcPubSub;

class GcPubSubConnector implements ConnectorInterface
{
    /**
     * Establish a queue connection.
     *
     * @param array $config
     *
     * @return GcPubSubQueue
     */
    public function connect(array $config): GcPubSubQueue
    {
        if (!isset($config['project_id']) || !$config['project_id']) {
            throw new InvalidArgumentException('The Google PubSub project id is missing');
        }

        $client = new PubSubClient([
            'projectId' => $config['project_id'],
        ]);
        $pubsub = new GcPubSub($client);
        $pubsub->setMaxMessages(1);

        if (env('APP_DEBUG', false) === true) {
            $pubsub->setDebug(true);
        }

        return new GcPubSubQueue(
            $pubsub,
            $config
        );
    }
}
