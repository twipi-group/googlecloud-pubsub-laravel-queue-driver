<?php

namespace TwipiGroup\Tests;

use Illuminate\Queue\Connectors\ConnectorInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use TwipiGroup\GoogleCloudPubSubLaravelQueueDriver\Queue\Connectors\GcPubSubConnector;
use TwipiGroup\GoogleCloudPubSubLaravelQueueDriver\Queue\GcPubSubQueue;

class ConnectorTest extends TestCase
{
    /**
     * Test Connector interface
     */
    public function testImplementsConnectorInterface()
    {
        $reflection = new ReflectionClass(GcPubSubConnector::class);
        $this->assertTrue($reflection->implementsInterface(ConnectorInterface::class));
    }

    /**
     * Test Connect
     */
    public function testConnect()
    {
        $connector = new GcPubSubConnector;
        $config = $this->getConfig();
        $queue = $connector->connect($config);

        $this->assertTrue($queue instanceof GcPubSubQueue);
    }

    /**
     * Test Connect with empty google cloud project id
     */
    public function testConnectWithWrongProjectId()
    {
        $connector = new GcPubSubConnector;
        $config = $this->getConfig();
        $config['project_id'] = '';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The Google PubSub project id is missing');

        $connector->connect($config);
    }

    /**
     * Get fake config
     */
    private function getConfig()
    {
        return [
            'driver' => 'gcpubsub',
            'project_id' => 'google-cloud-project-id',
            'queue' => 'default',
            'topic_suffix' => '',
            'subscriber_suffix' => '',
            'max_tries' => 1,
            'retry_delay' => 0,
        ];
    }
}
