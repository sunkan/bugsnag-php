<?php

namespace Bugsnag\Tests\phpt\Utilities;

use BadMethodCallException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A Guzzle 6 compatible implementation of ClientInterface for use in PHPT tests.
 *
 * This should never be used directly; use 'FakeGuzzle' instead!
 */
class FakeGuzzle6 implements ClientInterface, \Psr\Http\Client\ClientInterface
{
    public function request($method, $uri, array $options = [])
    {
        reportRequest($method, $uri, $options);

        return new Response();
    }

    public function send(RequestInterface $request, array $options = [])
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented!');
    }

    public function sendAsync(RequestInterface $request, array $options = [])
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented!');
    }

    public function requestAsync($method, $uri, array $options = [])
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented!');
    }

    public function getConfig($option = null)
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented!');
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $body = json_decode($request->getBody()->__toString(), true);
        reportRequest($request->getMethod(), $request->getUri()->__toString(), ['json' => $body]);

        return new Response();
    }
}
