<?php

namespace TwipiGroup\Tests;

use Google\Cloud\PubSub\Message;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Job as JobContract;
use Mockery;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use TwipiGroup\GoogleCloudPubSubLaravelQueueDriver\Queue\GcPubSubQueue;
use TwipiGroup\GoogleCloudPubSubLaravelQueueDriver\Queue\Jobs\GcPubSubJob;

class JobTest extends TestCase
{
    /** @var \Mockery\MockInterface|GcPubSubQueue */
    private $queue;

    /** @var \Mockery\MockInterface|Message */
    private $message;

    /** @var \Mockery\MockInterface|GcPubSubJob */
    private $job;

    /**
     * @var String
     */
    private $payload;

    /**
     * @var int
     */
    private $attempts = 2;

    /**
     * @var String
     */
    private $messageId = '123456789';

    public function setUp(): void
    {
        $this->payload = json_encode([
            'id' => $this->messageId,
            'data' => [
                'test' => 'job',
            ],
            'attempts' => $this->attempts,
        ]);

        $this->container = $this->createMock(Container::class);
        $this->queue = $this->createMock(GcPubSubQueue::class);

        $this->message = $this->getMockBuilder(Message::class)
            ->setConstructorArgs([[], []])
            ->setMethods([
                'data',
                'id',
            ])
            ->getMock();

        $this->message->method('data')
            ->willReturn($this->payload);

        $this->message->method('id')
            ->willReturn($this->messageId);

        $this->job = $this->getMockBuilder(GcPubSubJob::class)
            ->setConstructorArgs([
                $this->container,
                $this->queue,
                $this->message,
                'gcpubsub',
                'default'])
            ->setMethods()
            ->getMock();
    }

    /**
     * Test Job interface
     */
    public function testImplementsJobInterface()
    {
        $reflection = new ReflectionClass(GcPubSubJob::class);
        $this->assertTrue($reflection->implementsInterface(JobContract::class));
    }

    /**
     * Test GetJobId
     */
    public function testGetJobId()
    {
        $this->assertSame($this->job->getJobId(), $this->messageId);
    }

    /**
     * Test getRawBody
     */
    public function testGetRawBody()
    {
        $this->assertEquals($this->job->getRawBody(), $this->payload);
    }

    /**
     * Test Attempts
     */
    public function testAttempts()
    {
        $this->assertSame($this->job->attempts(), $this->attempts);
    }

    /**
     * Test Release
     */
    public function testRelease()
    {
        $delay = 30;

        $this->queue->expects($this->once())
            ->method('release')
            ->with(
                $this->equalTo(json_decode($this->payload, true)),
                $this->equalTo('default'),
                $this->equalTo(($this->attempts + 1)),
                $this->equalTo($delay)
            );

        $this->job->release($delay);
    }
}
