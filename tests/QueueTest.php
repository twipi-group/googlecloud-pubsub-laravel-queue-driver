<?php

namespace TwipiGroup\Tests;

use Carbon\Carbon;
use Google\Cloud\PubSub\Message;
use Google\Cloud\PubSub\PubSubClient;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Mockery;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use TwipiGroup\GoogleCloudPubSubLaravelQueueDriver\Queue\GcPubSubQueue;
use TwipiGroup\GoogleCloudPubSubLaravelQueueDriver\Queue\Jobs\GcPubSubJob;
use TwipiGroup\GoogleCloudPubSubPhpAdapter\GcPubSub;

class QueueTest extends TestCase
{
    /** @var \Mockery\MockInterface|GcPubSubQueue */
    public $queue;

    /** @var \Mockery\MockInterface|GcPubSub */
    private $pubsub;

    /** @var \Mockery\MockInterface|PubSubClient */
    private $client;

    /**
     * @var int
     */
    private $expectedPushResult = 123456789;

    /**
     * @var Array
     */
    private $expectedPublishResult = [];

    public function setUp(): void
    {
        $this->expectedPublishResult = [
            'messageIds' => [
                $this->expectedPushResult,
            ],
        ];

        $this->client = $this->createMock(PubSubClient::class);

        $this->pubsub = $this->getMockBuilder(GcPubSub::class)
            ->setConstructorArgs([$this->client])
            ->setMethods([
                'publish',
                'consume',
            ])
            ->getMock();

        $this->queue = $this->getMockBuilder(GcPubSubQueue::class)
            ->setConstructorArgs([$this->pubsub])
            ->setMethods([
                'pushRaw',
                'getQueue',
                'createPayload',
                'getSubscriber',
                'pop',
                'availableAt',
            ])
            ->getMock();
    }

    /**
     * Test Queue interface
     */
    public function testImplementsQueueInterface()
    {
        $reflection = new ReflectionClass(GcPubSubQueue::class);
        $this->assertTrue($reflection->implementsInterface(QueueContract::class));
    }

    /**
     * Test Constructor with empty config
     */
    public function testConstructWithEmptyConfig()
    {
        $queue = new GcPubSubQueue($this->pubsub);

        $this->assertSame($queue->getClient(), $this->pubsub);
        $this->assertSame($queue->getDefault(), 'default');
        $this->assertEmpty($queue->getTopicSuffix());
        $this->assertEmpty($queue->getSubscriberSuffix());
        $this->assertSame($queue->getMaxTries(), 1);
        $this->assertSame($queue->getRetryDelay(), 0);
    }

    /**
     * Test Constructor with non empty config
     */
    public function testConstructWithConfig()
    {
        $config = $this->getConfig();
        $queue = new GcPubSubQueue($this->pubsub, $config);

        $this->assertSame($queue->getClient(), $this->pubsub);
        $this->assertSame($queue->getDefault(), $config['queue']);
        $this->assertSame($queue->getTopicSuffix(), $config['topic_suffix']);
        $this->assertSame($queue->getSubscriberSuffix(), $config['subscriber_suffix']);
        $this->assertSame($queue->getMaxTries(), $config['max_tries']);
        $this->assertSame($queue->getRetryDelay(), $config['retry_delay']);
    }

    /**
     * Test PubSub client
     */
    public function testPubSubClient()
    {
        $this->assertTrue($this->pubsub instanceof GcPubSub);
    }

    /**
     * Test Push
     */
    public function testPush()
    {
        $job = 'test';
        $data = ['example' => 'first'];
        $queueName = '';

        $this->queue->expects($this->once())
            ->method('pushRaw')
            ->with($this->callback(function ($payload) use ($job, $data) {
                $decoded = json_decode($payload, true);
                return $decoded['data'] === $data
                && $decoded['job'] === $job
                && $decoded['maxTries'] === $this->queue->getMaxTries();
            }), $this->equalTo('default'))
            ->willReturn($this->expectedPushResult);

        $this->queue->expects($this->exactly(2))
            ->method('getQueue')
            ->with($this->equalTo($queueName))
            ->willReturn('default');

        $this->queue->expects($this->once())
            ->method('createPayload')
            ->with($this->equalTo($job), $this->equalTo('default'), $this->equalTo($data))
            ->willReturn(json_encode([
                'displayName' => $job,
                'job' => $job,

                'delay' => null,
                'timeout' => null,
                'data' => $data,
                'id' => 'randomId',
                'attempts' => 1,
                'maxTries' => 1,
            ]));

        $this->assertEquals($this->expectedPushResult, $this->queue->push($job, $data, $queueName));
    }

    /**
     * Test Pushraw
     */
    public function testPushRaw()
    {
        $payload = json_encode([
            'job' => 'test',
            'data' => ['example' => 'first'],
        ]);

        $this->pubsub->expects($this->once())
            ->method('publish')
            ->with($this->equalTo('default'),
                $this->callback(function ($publish) use ($payload) {
                    return $publish === $payload;
                }))
            ->willReturn($this->expectedPublishResult);

        $queue = new GcPubSubQueue($this->pubsub);
        $this->assertEquals($this->expectedPushResult, $queue->pushRaw($payload));
    }

    /**
     * Test Pushraw with wrong attributes
     */
    public function testPushRawWithWrongAttributes()
    {
        $payload = json_encode([
            'job' => 'test',
            'data' => ['example' => 'pushraw'],
        ]);

        $attributes = [
            'successtest' => 'ok',
            'wrongtest' => [
                'foo' => 'bar',
            ],
            1 => 'not string',
        ];

        $this->expectException(\UnexpectedValueException::class);

        $this->pubsub->expects($this->once())
            ->method('publish')
            ->with($this->equalTo('default'),
                $this->callback(function ($publish) use ($payload) {
                    return $publish === $payload;
                }), $this->equalTo($attributes))
            ->will($this->throwException(new \UnexpectedValueException()));

        $queue = new GcPubSubQueue($this->pubsub);
        $queue->pushRaw($payload, '', $attributes);
    }

    /**
     * Test Later
     */
    public function testLater()
    {
        $job = 'test';
        $data = ['example' => 'later'];
        $queueName = '';
        $delaySeconds = 30;
        $delayTimestamp = Carbon::now()->addRealSeconds($delaySeconds)->getTimestamp();

        $this->queue->expects($this->once())
            ->method('availableAt')
            ->willReturn($delayTimestamp);

        $this->queue->expects($this->exactly(2))
            ->method('getQueue')
            ->with($this->equalTo($queueName))
            ->willReturn('default');

        $this->queue->expects($this->once())
            ->method('createPayload')
            ->with($this->equalTo($job), $this->equalTo('default'), $this->equalTo($data))
            ->willReturn(json_encode([
                'displayName' => $job,
                'job' => $job,
                'delay' => null,
                'timeout' => null,
                'data' => $data,
                'id' => 'randomId',
                'attempts' => 1,
                'maxTries' => 1,
            ]));

        $this->queue->expects($this->once())
            ->method('pushRaw')
            ->with($this->callback(function ($payload) use ($job, $data) {
                $decoded = json_decode($payload, true);
                return $decoded['data'] === $data
                && $decoded['job'] === $job
                && $decoded['maxTries'] === $this->queue->getMaxTries();
            }),
                $this->equalTo('default'),
                $this->callback(function ($attributes) use ($delayTimestamp) {
                    if (!is_array($attributes)) {
                        return false;
                    }
                    foreach ($attributes as $key => $attribute) {
                        if (!is_string($attribute) || !is_string($key)) {
                            return false;
                        }
                    }
                    if (!isset($attributes['availableAt']) || $attributes['availableAt'] !== (string) $delayTimestamp) {
                        return false;
                    }

                    return true;
                }))
            ->willReturn($this->expectedPushResult);

        $this->assertEquals($this->expectedPushResult, $this->queue->later($delaySeconds, $job, $data));
    }

    /**
     * Test Pop available job
     */
    public function testPop()
    {
        /** @var \Mockery\MockInterface|Message */
        $message = $this->createMock(Message::class);

        /** @var \Mockery\MockInterface|Container */
        $container = $this->createMock(Container::class);

        $this->pubsub->expects($this->once())
            ->method('consume')
            ->with($this->equalTo('default'), $this->equalTo('default'))
            ->willReturn([$message]);

        $queue = new GcPubSubQueue($this->pubsub);
        $queue->setContainer($container);
        $queue->setConnectionName('gcpubsub');

        $this->assertTrue($queue->pop() instanceof GcPubSubJob);
    }

    /**
     * Test Pop unavailable job
     */
    public function testPopWithoutJob()
    {
        $this->pubsub->expects($this->once())
            ->method('consume')
            ->with($this->equalTo('default'), $this->equalTo('default'))
            ->willReturn([]);

        $queue = new GcPubSubQueue($this->pubsub);
        $this->assertTrue(is_null($queue->pop()));
    }

    /**
     * Test getRandomId
     */
    public function testRandomId()
    {
        $queue = new GcPubSubQueue($this->pubsub);
        $randomId = $queue->getRandomId();
        $this->assertSame(1, preg_match('/[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}/', $randomId));
        $this->assertStringMatchesFormat('%s', $randomId);
    }

    /**
     * Test Default
     */
    public function testDefault()
    {
        $queue = new GcPubSubQueue($this->pubsub);
        $this->assertSame('default', $queue->getDefault());
    }

    /**
     * Test getSubscriber
     */
    public function testSubscriber()
    {
        $queue = new GcPubSubQueue($this->pubsub);
        $this->assertSame('default', $queue->getSubscriber());
    }

    /**
     * Test getClient
     */
    public function testClient()
    {
        $queue = new GcPubSubQueue($this->pubsub);
        $this->assertSame($this->pubsub, $queue->getClient());
    }

    /**
     * Test getMaxTries
     */
    public function testMaxTries()
    {
        $queue = new GcPubSubQueue($this->pubsub);
        $this->assertSame(1, $queue->getMaxTries());
    }

    /**
     * Test getRetryDelay
     */
    public function testRetryDelay()
    {
        $queue = new GcPubSubQueue($this->pubsub);
        $this->assertSame(0, $queue->getRetryDelay());
    }

    /**
     * Test getTopicSuffix
     */
    public function testTopicSuffix()
    {
        $queue = new GcPubSubQueue($this->pubsub);
        $this->assertSame('', $queue->getTopicSuffix());
    }

    /**
     * Test getSubscriberSuffix
     */
    public function testSubscriberSuffix()
    {
        $queue = new GcPubSubQueue($this->pubsub);
        $this->assertSame('', $queue->getSubscriberSuffix());
    }

    /**
     * Test Release with delay
     */
    public function testReleaseWithDelay()
    {
        $job = 'test';
        $data = ['example' => 'later'];
        $payloadToRelease = [
            'displayName' => $job,
            'job' => $job,
            'delay' => null,
            'timeout' => null,
            'data' => $data,
            'id' => 'randomId',
            'attempts' => 1,
            'maxTries' => 1,
        ];
        $queueName = 'default';
        $delaySeconds = 30;
        $attempts = 2;
        $delayTimestamp = Carbon::now()->addRealSeconds($delaySeconds)->getTimestamp();

        $this->queue->expects($this->once())
            ->method('availableAt')
            ->willReturn($delayTimestamp);

        $this->queue->expects($this->once())
            ->method('pushRaw')
            ->with($this->callback(function ($payload) use ($job, $data) {
                $decoded = json_decode($payload, true);
                return $decoded['data'] === $data
                && $decoded['job'] === $job
                && $decoded['maxTries'] === $this->queue->getMaxTries();
            }),
                $this->equalTo('default'),
                $this->callback(function ($attributes) use ($delayTimestamp, $attempts) {
                    if (!is_array($attributes)) {
                        return false;
                    }
                    foreach ($attributes as $key => $attribute) {
                        if (!is_string($attribute) || !is_string($key)) {
                            return false;
                        }
                    }
                    if (!isset($attributes['availableAt'])
                        || !isset($attributes['attempts'])
                        || $attributes['availableAt'] !== (string) $delayTimestamp
                        || $attributes['attempts'] !== (string) $attempts) {
                        return false;
                    }

                    return true;
                }))
            ->willReturn($this->expectedPushResult);

        $this->assertEquals($this->expectedPushResult, $this->queue->release($payloadToRelease, $queueName, $attempts, $delaySeconds));
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
            'topic_suffix' => 'mytopicsuffix',
            'subscriber_suffix' => 'mysubscribersuffix',
            'max_tries' => 3,
            'retry_delay' => 10,
        ];
    }
}
