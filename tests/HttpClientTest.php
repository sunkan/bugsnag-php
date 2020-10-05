<?php

namespace Bugsnag\Tests;

use Bugsnag\Configuration;
use Bugsnag\HttpClient;
use Bugsnag\Report;
use Bugsnag\Request\RequestInterface;
use Exception;
use GuzzleHttp\Client;
use Http\Discovery\Psr17FactoryDiscovery;

class HttpClientTest extends TestCase
{
    protected $config;
    protected $guzzle;
    protected $http;

    protected function setUp()
    {
        $this->config = new Configuration('6015a72ff14038114c3d12623dfb018f');

        $this->guzzle = $this->getMockBuilder(Client::class)
                             ->setMethods([self::getGuzzleMethod()])
                             ->getMock();

        $this->http = new HttpClient(
            $this->config,
            $this->guzzle,
            Psr17FactoryDiscovery::findRequestFactory(),
            Psr17FactoryDiscovery::findStreamFactory()
        );
    }

    private static function getInvocationParameters($invocation)
    {
        if (is_callable([$invocation, 'getParameters'])) {
            return $invocation->getParameters();
        }

        return $invocation->parameters;
    }

    public function testHttpClient()
    {
        // Expect request to be called
        $this->guzzle->expects($spy = $this->any())->method(self::getGuzzleMethod());

        // Add a report to the http and deliver it
        $this->http->queue(Report::fromNamedError($this->config, 'Name')->setMetaData(['foo' => 'bar']));
        $this->http->send();

        $this->assertCount(1, $invocations = $spy->getInvocations());
        /** @var \Psr\Http\Message\RequestInterface[] $params */
        $params = self::getInvocationParameters($invocations[0]);
        $this->assertCount(self::getGuzzleExpectedParamCount(), $params);
        $this->assertSame($this->config->getNotifyEndpoint(), $params[0]->getUri()->__toString());
        $body = json_decode($params[0]->getBody()->__toString(), true);
        $this->assertSame([], $body['events'][0]['user']);
        $this->assertSame(['foo' => 'bar'], $body['events'][0]['metaData']);
        $this->assertSame('6015a72ff14038114c3d12623dfb018f', $body['apiKey']);
        $this->assertSame('4.0', $body['events'][0]['payloadVersion']);

        $headers = $params[0]->getHeaders();
        $this->assertSame('6015a72ff14038114c3d12623dfb018f', $headers['Bugsnag-Api-Key'][0]);
        $this->assertArrayHasKey('Bugsnag-Sent-At', $headers);
        $this->assertSame('4.0', $headers['Bugsnag-Payload-Version'][0]);
    }

    public function testHttpClientMultipleSend()
    {
        // Expect request to be called
        $this->guzzle->expects($spy = $this->any())->method(self::getGuzzleMethod());

        // Add a report to the http and deliver it
        $this->http->queue(Report::fromNamedError($this->config, 'Name')->setMetaData(['foo' => 'bar']));
        $this->http->send();

        // Make sure these do nothing
        $this->http->send();
        $this->http->send();

        // Check we only sent once
        $this->assertCount(1, $invocations = $spy->getInvocations());
    }

    public function testMassiveMetaDataHttpClient()
    {
        // Expect request to be called
        $this->guzzle->expects($spy = $this->any())->method(self::getGuzzleMethod());

        // Add a report to the http and deliver it
        $this->http->queue(Report::fromNamedError($this->config, 'Name')->setMetaData(['foo' => str_repeat('A', 1500000)]));
        $this->http->send();

        $this->assertCount(1, $invocations = $spy->getInvocations());
        $params = self::getInvocationParameters($invocations[0]);
        $this->assertCount(self::getGuzzleExpectedParamCount(), $params);
        $this->assertSame($this->config->getNotifyEndpoint(), $params[0]->getUri()->__toString());

        $body = json_decode($params[0]->getBody()->__toString(), true);
        $this->assertSame([], $body['events'][0]['user']);
        $this->assertArrayNotHasKey('metaData', $body['events'][0]);
        $this->assertSame('6015a72ff14038114c3d12623dfb018f', $body['apiKey']);
        $this->assertSame('4.0', $body['events'][0]['payloadVersion']);

        $headers = $params[0]->getHeaders();
        $this->assertSame('6015a72ff14038114c3d12623dfb018f', $headers['Bugsnag-Api-Key'][0]);
        $this->assertArrayHasKey('Bugsnag-Sent-At', $headers);
        $this->assertSame('4.0', $headers['Bugsnag-Payload-Version'][0]);
    }

    public function testMassiveUserHttpClient()
    {
        // Setup error_log mocking
        $log = $this->getFunctionMock('Bugsnag', 'error_log');
        $log->expects($this->once())->with($this->equalTo('Bugsnag Warning: Payload too large'));

        // Expect request to be called
        $this->guzzle->expects($spy = $this->any())->method(self::getGuzzleMethod());

        // Add a report to the http and deliver it
        $this->http->queue(Report::fromNamedError($this->config, 'Name')->setUser(['foo' => str_repeat('A', 1500000)]));
        $this->http->send();

        $this->assertCount(0, $spy->getInvocations());
    }

    public function testPartialHttpClient()
    {
        // Setup error_log mocking
        $log = $this->getFunctionMock('Bugsnag', 'error_log');
        $log->expects($this->once())->with($this->equalTo('Bugsnag Warning: Payload too large'));

        // Expect request to be called
        $this->guzzle->expects($spy = $this->any())->method(self::getGuzzleMethod());

        // Add two errors to the http and deliver them
        $this->http->queue(Report::fromNamedError($this->config, 'Name')->setUser(['foo' => str_repeat('A', 1500000)]));
        $this->http->queue(Report::fromNamedError($this->config, 'Name')->setUser(['foo' => 'bar']));
        $this->http->send();

        $this->assertCount(1, $invocations = $spy->getInvocations());
        $params = self::getInvocationParameters($invocations[0]);
        $this->assertCount(self::getGuzzleExpectedParamCount(), $params);
        $this->assertSame($this->config->getNotifyEndpoint(), $params[0]->getUri()->__toString());

        $body = json_decode($params[0]->getBody()->__toString(), true);
        $this->assertSame(['foo' => 'bar'], $body['events'][0]['user']);
        $this->assertSame([], $body['events'][0]['metaData']);
        $this->assertSame('6015a72ff14038114c3d12623dfb018f', $body['apiKey']);
        $this->assertSame('4.0', $body['events'][0]['payloadVersion']);

        $headers = $params[0]->getHeaders();
        $this->assertSame('6015a72ff14038114c3d12623dfb018f', $headers['Bugsnag-Api-Key'][0]);
        $this->assertArrayHasKey('Bugsnag-Sent-At', $headers);
        $this->assertSame('4.0', $headers['Bugsnag-Payload-Version'][0]);
    }

    public function testHttpClientFails()
    {
        // Setup error_log mocking
        $log = $this->getFunctionMock('Bugsnag', 'error_log');
        $log->expects($this->once())->with($this->equalTo('Bugsnag Warning: Couldn\'t notify. Guzzle exception thrown!'));

        // Expect request to be called
        $this->guzzle->method(self::getGuzzleMethod())->will($this->throwException(new Exception('Guzzle exception thrown!')));

        // Add a report to the http and deliver it
        $this->http->queue(Report::fromNamedError($this->config, 'Name')->setMetaData(['foo' => 'bar']));
        $this->http->send();
    }

    private function getGuzzleExpectedParamCount()
    {
        return 1;
    }

    private function getGuzzlePostUriParam(array $params)
    {
        return $params[self::getGuzzleMethod() === 'request' ? 1 : 0];
    }

    private function getGuzzlePostOptionsParam(array $params)
    {
        return $params[self::getGuzzleMethod() === 'request' ? 2 : 1];
    }
}
