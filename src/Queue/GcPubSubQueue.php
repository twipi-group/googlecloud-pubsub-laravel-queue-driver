<?php

namespace TwipiGroup\GoogleCloudPubSubLaravelQueueDriver\Queue;

use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Queue;
use Ramsey\Uuid\Uuid;
use TwipiGroup\GoogleCloudPubSubLaravelQueueDriver\Queue\Jobs\GcPubSubJob;
use TwipiGroup\GoogleCloudPubSubPhpAdapter\GcPubSub;

class GcPubSubQueue extends Queue implements QueueContract
{
    /**
     * The Twipi PubSub instance.
     *
     * @var Google\Cloud\PubSub\GcPubSub
     */
    protected $gcpubsub;

    /**
     * The name of the default topic.
     *
     * @var string
     */
    protected $default = 'default';

    /**
     * The suffix of topics.
     *
     * @var string
     */
    protected $topicSuffix = '';

    /**
     * The suffix of subscribers.
     *
     * @var string
     */
    protected $subscriberSuffix = '';

    /**
     * The max attempts of failed job.
     *
     * @var int
     */
    protected $maxTries = 1;

    /**
     * The job retry delay in seconds.
     *
     * @var int
     */
    protected $retryDelay = 0;

    /**
     * Create a new Google Cloud PubSub queue instance.
     *
     * @param  GcPubSub  $GcPubSub
     * @param  array  $config
     * @return void
     */
    public function __construct(GcPubSub $gcpubsub, array $config = [])
    {
        $this->gcpubsub = $gcpubsub;
        $this->default = $config['queue'] ?? $this->default;
        $this->topicSuffix = $config['topic_suffix'] ?? $this->topicSuffix;
        $this->subscriberSuffix = $config['subscriber_suffix'] ?? $this->subscriberSuffix;
        $this->maxTries = $config['max_tries'] ?? $this->maxTries;
        $this->retryDelay = $config['retry_delay'] ?? $this->retryDelay;
    }

    /**
     * Push a new job onto the queue.
     *
     * @param  object|string  $job
     * @param  mixed   $data
     * @param  string|null  $queue
     * @return string
     */
    public function push($job, $data = '', $queue = null)
    {
        return $this->pushRaw($this->createPayload($job, $this->getQueue($queue), $data), $this->getQueue($queue));
    }

    /**
     * Push a raw payload onto the queue.
     *
     * @param  string  $payload
     * @param  string  $queue
     * @param  array   $options
     * @return string
     */
    public function pushRaw($payload, $queue = null, array $options = [])
    {
        if (!$queue) {
            $queue = $this->getQueue($queue);
        }

        $messageIds = $this->gcpubsub->publish($queue, $payload, $options);

        return $messageIds['messageIds'][0];
    }

    /**
     * Create a payload string from the given job and data.
     *
     * @param  string  $job
     * @param  string   $queue
     * @param  mixed   $data
     * @return string
     */
    protected function createPayloadArray($job, $queue, $data = '')
    {
        return array_merge(parent::createPayloadArray($job, $queue, $data), [
            'id' => $this->getRandomId(),
            'attempts' => 1,
            'maxTries' => $this->maxTries,
            'delay' => $this->retryDelay,
        ]);
    }

    /**
     * Get the size of the queue.
     *
     * @param  string  $queue
     *
     * @return int
     */
    public function size($queue = null)
    {
        $size = count(iterator_to_array($this->gcpubsub->getSubscription($this->getSubscriber($queue), $this->getQueue($queue))->pull()));
        return $size;
    }

    /**
     * Push a new job onto the queue after a delay.
     *
     * @param  \DateTimeInterface|\DateInterval|int  $delay
     * @param  string|object  $job
     * @param  mixed   $data
     * @param  string  $queue
     *
     * @return mixed
     */
    public function later($delay, $job, $data = '', $queue = null)
    {
        return $this->pushRaw(
            $this->createPayload($job, $this->getQueue($queue), $data),
            $this->getQueue($queue),
            ['availableAt' => (string) $this->availableAt($delay)]
        );
    }

    /**
     * Pop the next job off of the queue.
     *
     * @param  string  $queue
     * @return \Illuminate\Contracts\Queue\Job|null
     */
    public function pop($queue = null)
    {
        $messages = $this->gcpubsub->consume($this->getSubscriber($queue), $this->getQueue($queue));

        if (empty($messages) || count($messages) > 1) {
            return;
        }

        return new GcPubSubJob(
            $this->container,
            $this,
            $messages[0],
            $this->connectionName,
            $this->getQueue($queue)
        );
    }

    /**
     * Get a random ID string.
     *
     * @return string
     */
    public function getRandomId()
    {
        return (Uuid::uuid4())->toString();
    }

    /**
     * Get the default queue name.
     *
     * @return string
     */
    public function getDefault()
    {
        return $this->default;
    }

    /**
     * Get the topic suffix.
     *
     * @return string
     */
    public function getTopicSuffix()
    {
        return $this->topicSuffix;
    }

    /**
     * Get the subscriber suffix.
     *
     * @return string
     */
    public function getSubscriberSuffix()
    {
        return $this->subscriberSuffix;
    }

    /**
     * Get the pubsub topic or return the default.
     *
     * @param  string|null  $queue
     * @return string
     */
    public function getQueue($queue = null)
    {
        return $this->topicSuffix . ($queue ?: $this->default);
    }

    /**
     * Get the pubsub subscriber name.
     *
     * @param  string|null  $queue
     * @return string
     */
    public function getSubscriber($queue = null)
    {
        return $this->subscriberSuffix . ($queue ?: $this->default);
    }

    /**
     * Get the pubsub client.
     *
     * @return GcPubSub
     */
    public function getClient()
    {
        return $this->gcpubsub;
    }

    /**
     * Get the job max tries.
     *
     * @return int
     */
    public function getMaxTries()
    {
        return $this->maxTries;
    }

    /**
     * Get the retry delay in seconds after each jobs attempts.
     *
     * @return int
     */
    public function getRetryDelay()
    {
        return $this->retryDelay;
    }

    /**
     * Release a message onto the queue.
     *
     * @param array  $payload
     * @param string|null  $queue
     * @param int|1  $attempts
     * @param int|0  $delay
     *
     * @return mixed
     */
    public function release($payload, $queue = null, $attempts = 1, $delay = 0)
    {
        $payload['attempts'] = $attempts;
        $options = ['attempts' => (string) $attempts];

        $options = array_merge([
            'availableAt' => (string) $this->availableAt($delay),
        ], $this->gcpubsub->validateMessageAttributes($options));

        return $this->pushRaw(json_encode($payload), $queue, $options);
    }
}
