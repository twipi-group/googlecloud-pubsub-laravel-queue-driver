<?php

namespace TwipiGroup\GoogleCloudPubSubLaravelQueueDriver\Queue\Jobs;

use Google\Cloud\PubSub\Message;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\Jobs\Job;
use TwipiGroup\GoogleCloudPubSubLaravelQueueDriver\Queue\GcPubSubQueue;

class GcPubSubJob extends Job implements JobContract
{
    /**
     * The Google PubSub queue instance.
     *
     * @var TwipiGroup\GoogleCloudPubSubLaravelQueueDriver\Queue\GcPubSubQueue
     */
    protected $gcpubsubqueue;

    /**
     * The container.
     *
     * @var \Illuminate\Container\Container $container
     */
    protected $container;

    /**
     * The Google PubSub message.
     *
     * @var Google\Cloud\PubSub\Message
     */
    protected $message;

    /**
     * The connection name.
     *
     * @var string
     */
    protected $connectionName;

    /**
     * The queue name.
     *
     * @var string
     */
    protected $queue;

    /**
     * Create a new message instance.
     *
     * @param \Illuminate\Container\Container  $container
     * @param TwipiGroup\GoogleCloudPubSubLaravelQueueDriver\Queue\GcPubSubQueue  $gcpubsubqueue
     * @param \Google\Cloud\PubSub\Message  $message
     * @param string  $connectionName
     * @param string  $queue
     */
    public function __construct(Container $container, GcPubSubQueue $gcpubsubqueue, Message $message, string $connectionName, string $queue)
    {
        $this->container = $container;
        $this->gcpubsubqueue = $gcpubsubqueue;
        $this->message = $message;
        $this->connectionName = $connectionName;
        $this->queue = $queue;
    }

    /**
     * Get the message identifier.
     *
     * @return string
     */
    public function getJobId()
    {
        return $this->message->id();
    }

    /**
     * Get the raw body string for the message.
     *
     * @return string
     */
    public function getRawBody()
    {
        return $this->message->data();
    }

    /**
     * Get the number of times the message has been attempted.
     *
     * @return int
     */
    public function attempts()
    {
        return $this->payload()['attempts'] ?? 1;
    }

    /**
     * Release the message back into the queue.
     *
     * @param  int   $delay
     * @return void
     */
    public function release($delay = 0)
    {
        parent::release($delay);

        $attempts = $this->attempts() + 1;

        $this->gcpubsubqueue->release(
            $this->payload(),
            $this->queue,
            $attempts,
            $delay
        );
    }
}
