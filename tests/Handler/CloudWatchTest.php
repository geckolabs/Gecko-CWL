<?php

namespace Maxbanton\Cwh\Test\Handler;


use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Monolog\Logger;
use Monolog\Formatter\LineFormatter;
use Maxbanton\Cwh\Handler\CloudWatch;
use Aws\Result;
use Aws\CloudWatchLogs\Exception\CloudWatchLogsException;
use Aws\CloudWatchLogs\CloudWatchLogsClient;

class CloudWatchTest extends TestCase
{

    /**
     * @var MockObject | CloudWatchLogsClient
     */
    private $clientMock;

    /**
     * @var MockObject | Result
     */
    private $awsResultMock;

    /**
     * @var string
     */
    private $groupName = 'group';

    /**
     * @var string
     */
    private $streamName = 'stream';

    protected function setUp(): void
    {
        $this->clientMock =
            $this
                ->getMockBuilder(CloudWatchLogsClient::class)
                ->setMethods(
                    [
                        'DescribeLogStreams',
                        'CreateLogStream',
                        'PutLogEvents'
                    ]
                )
                ->disableOriginalConstructor()
                ->getMock();
    }

    public function testInitializeCreatesStream()
    {
        $logStreamResult = new Result([
            'logStreams' => [
                [
                    'logStreamName' => $this->streamName,
                    'uploadSequenceToken' => '49559307804604887372466686181995921714853186581450198322'
                ]
            ]
        ]);

        $this
            ->clientMock
            ->expects($this->once())
            ->method('createLogStream')
            ->with([
                'logGroupName' => $this->groupName,
                'logStreamName' => $this->streamName,
            ])
            ->willReturn($logStreamResult);

        $handler = new CloudWatch($this->clientMock, $this->groupName, $this->streamName, 10000, Logger::DEBUG, true);

        $reflection = new \ReflectionClass($handler);
        $reflectionMethod = $reflection->getMethod('initializeStream');
        $reflectionMethod->setAccessible(true);
        $reflectionMethod->invoke($handler);
    }

    public function testInitializeWithMissingGroupAndStream()
    {
        $this
            ->clientMock
            ->expects($this->once())
            ->method('createLogStream')
            ->with([
                'logGroupName' => $this->groupName,
                'logStreamName' => $this->streamName
            ]);

        $handler = $this->getCUT();

        $reflection = new \ReflectionClass($handler);
        $reflectionMethod = $reflection->getMethod('initializeStream');
        $reflectionMethod->setAccessible(true);
        $reflectionMethod->invoke($handler);
    }

    public function testLimitExceeded()
    {
        $this->expectException(\InvalidArgumentException::class);
        (new CloudWatch($this->clientMock, 'a', 'b', 10001));
    }

    public function testSendsOnClose()
    {
        $this->prepareMocks();

        $this
            ->clientMock
            ->expects($this->once())
            ->method('PutLogEvents')
            ->willReturn($this->awsResultMock);

        $handler = $this->getCUT(1);

        $handler->handle($this->getRecord(Logger::DEBUG));

        $handler->close();
    }

    public function testSendsBatches()
    {
        $this->prepareMocks();

        $this
            ->clientMock
            ->expects($this->exactly(2))
            ->method('PutLogEvents')
            ->willReturn($this->awsResultMock);

        $handler = $this->getCUT(3);

        foreach ($this->getMultipleRecords() as $record) {
            $handler->handle($record);
        }

        $handler->close();
    }

    public function testFormatter()
    {
        $handler = $this->getCUT();

        $formatter = $handler->getFormatter();

        $expected = new LineFormatter("%channel%: %level_name%: %message% %context% %extra%", null, false, true);

        $this->assertEquals($expected, $formatter);
    }

    private function prepareMocks()
    {
        $this->awsResultMock =
            $this
                ->getMockBuilder(Result::class)
                ->setMethods(['get'])
                ->disableOriginalConstructor()
                ->getMock();
    }

    public function testSortsEntriesChronologically()
    {
        $this->prepareMocks();

        $this
            ->clientMock
            ->expects($this->once())
            ->method('PutLogEvents')
            ->willReturnCallback(function (array $data) {
                $this->assertStringContainsString('record1', $data['logEvents'][0]['message']);
                $this->assertStringContainsString('record2', $data['logEvents'][1]['message']);
                $this->assertStringContainsString('record3', $data['logEvents'][2]['message']);
                $this->assertStringContainsString('record4', $data['logEvents'][3]['message']);

                return $this->awsResultMock;
            });

        $handler = $this->getCUT(4);

        // created with chronological timestamps:
        $records = [];

        for ($i = 1; $i <= 4; ++$i) {
            $record = $this->getRecord(Logger::INFO, 'record' . $i);
            $record['datetime'] = \DateTime::createFromFormat('U', time() + $i);
            $records[] = $record;
        }

        // but submitted in a different order:
        $handler->handle($records[2]);
        $handler->handle($records[0]);
        $handler->handle($records[3]);
        $handler->handle($records[1]);

        $handler->close();
    }

    private function getCUT($batchSize = 1000)
    {
        return new CloudWatch($this->clientMock, $this->groupName, $this->streamName, $batchSize);
    }

    /**
     * @param int $level
     * @param string $message
     * @param array $context
     * @return array
     */
    private function getRecord($level = Logger::WARNING, $message = 'test', $context = [])
    {
        return [
            'message' => $message,
            'context' => $context,
            'level' => $level,
            'level_name' => Logger::getLevelName($level),
            'channel' => 'test',
            'datetime' => \DateTime::createFromFormat('U.u', sprintf('%.6F', microtime(true))),
            'extra' => [],
        ];
    }

    /**
     * @return array
     */
    private function getMultipleRecords()
    {
        return [
            $this->getRecord(Logger::DEBUG, 'debug message 1'),
            $this->getRecord(Logger::DEBUG, 'debug message 2'),
            $this->getRecord(Logger::INFO, 'information'),
            $this->getRecord(Logger::WARNING, 'warning'),
            $this->getRecord(Logger::ERROR, 'error'),
        ];
    }
}
