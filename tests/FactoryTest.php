<?php

use Clue\React\Quassel\Factory;
use React\Promise\Deferred;
use Clue\React\Quassel\Io\Protocol;
use React\Promise;

class FactoryTest extends TestCase
{
    public function setUp()
    {
        $this->loop = $this->getMock('React\EventLoop\LoopInterface');
        $this->connector = $this->getMock('React\SocketClient\ConnectorInterface');
        $this->prober = $this->getMockBuilder('Clue\React\Quassel\Io\Prober')->disableOriginalConstructor()->getMock();

        $this->factory = new Factory($this->loop, $this->connector, $this->prober);
    }

    public function testCtorOptionalArgs()
    {
        new Factory($this->loop);
    }

    public function testPassHostnameAndDefaultPortToConnector()
    {
        $deferred = new Deferred();
        $this->connector->expects($this->once())->method('create')->with($this->equalTo('example.com', 4242))->will($this->returnValue($deferred->promise()));
        $this->factory->createClient('example.com');
    }

    public function testPassHostnameAndPortToConnector()
    {
        $deferred = new Deferred();
        $this->connector->expects($this->once())->method('create')->with($this->equalTo('example.com', 1234))->will($this->returnValue($deferred->promise()));
        $this->factory->createClient('example.com:1234');
    }

    public function testInvalidUriWillRejectWithoutConnecting()
    {
        $this->connector->expects($this->never())->method('create');

        $this->expectPromiseReject($this->factory->createClient('///'));
    }

    public function testInvalidSchemeWillRejectWithoutConnecting()
    {
        $this->connector->expects($this->never())->method('create');

        $this->expectPromiseReject($this->factory->createClient('https://example.com:1234/'));
    }

    public function testWillInvokeProberAfterConnecting()
    {
        $stream = $this->getMockBuilder('React\Stream\Stream')->disableOriginalConstructor()->getMock();

        $this->connector->expects($this->once())->method('create')->will($this->returnValue(Promise\resolve($stream)));
        $this->prober->expects($this->once())->method('probe')->with($this->equalTo($stream))->will($this->returnValue(Promise\resolve(Protocol::TYPE_DATASTREAM)));

        $this->expectPromiseResolve($this->factory->createClient('localhost'));
    }

    public function testWillNotInvokeProberIfSchemeIsProtocol()
    {
        $stream = $this->getMockBuilder('React\Stream\Stream')->disableOriginalConstructor()->getMock();

        $this->connector->expects($this->once())->method('create')->will($this->returnValue(Promise\resolve($stream)));
        $this->prober->expects($this->never())->method('probe');

        $this->expectPromiseResolve($this->factory->createClient('legacy://localhost'));
    }
}
