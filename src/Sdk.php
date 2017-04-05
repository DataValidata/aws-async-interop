<?php


namespace DataValidata\AWSAsyncInterop;


use Amp\Loop;
use Aws\Sdk as AwsSdk;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Handler\CurlMultiHandler;
use GuzzleHttp\HandlerStack;

class Sdk
{
    public function getSdk($config = [])
    {
        $config['http_handler'] = $this->default_http_handler();
        return new AwsSdk($config);
    }

    /**
     * @return Client
     */
    private function getGuzzleClient()
    {
        $this->attachPromiseQueueToEventLoop();
        return new Client(
            [
                'handler' => HandlerStack::create($this->getCurlMultiHandler())
            ]
        );
    }

    private function attachPromiseQueueToEventLoop()
    {
        $queue = \GuzzleHttp\Promise\queue();
        Loop::repeat(0, $this->getBoundClosure($queue, 'run'));
    }

    /**
     * @return CurlMultiHandler
     */
    private function getCurlMultiHandler()
    {
        $handler = new CurlMultiHandler();
        Loop::repeat(0, $this->getBoundClosure($handler, 'tick'));
        return $handler;
    }

    private function getBoundClosure($object, $methodName)
    {
        $closure = function () use ($methodName) {
            call_user_func([$this, $methodName]);
        };
        return \Closure::bind($closure, $object, $object);
    }

    /**
     * Creates a default HTTP handler based on the available clients.
     *
     * @return callable
     */
    private function default_http_handler()
    {
        $version = (string) ClientInterface::VERSION;
        if ($version[0] === '5') {
            return new \Aws\Handler\GuzzleV5\GuzzleHandler($this->getGuzzleClient());
        } elseif ($version[0] === '6') {
            return new \Aws\Handler\GuzzleV6\GuzzleHandler($this->getGuzzleClient());
        } else {
            throw new \RuntimeException('Unknown Guzzle version: ' . $version);
        }
    }
}