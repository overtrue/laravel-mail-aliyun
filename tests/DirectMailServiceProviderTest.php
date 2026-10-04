<?php

namespace Overtrue\LaravelMailAliyun\Tests;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Mail\SentMessage;
use Orchestra\Testbench\TestCase;
use Overtrue\LaravelMailAliyun\DirectMailServiceProvider;
use Overtrue\LaravelMailAliyun\DirectMailTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;

class DirectMailServiceProviderTest extends TestCase
{
    private $history = [];

    protected function getPackageProviders($app)
    {
        return [DirectMailServiceProvider::class];
    }

    protected function defineEnvironment($app)
    {
        $handler = HandlerStack::create(new MockHandler([new Response(200, [], '{"RequestId":"test"}')]));
        $handler->push(Middleware::history($this->history));

        $app['config']->set('services.directmail', ['key' => 'test-key', 'secret' => 'test-secret', 'handler' => $handler]);
        $app['config']->set('mail.default', 'directmail');
        $app['config']->set('mail.mailers.directmail', ['transport' => 'directmail']);
        $app['config']->set('mail.from', ['address' => 'from@example.com', 'name' => 'Sender']);
    }

    public function test_it_registers_a_symfony_transport_with_laravel(): void
    {
        $transport = $this->app['mail.manager']->mailer()->getSymfonyTransport();

        $this->assertInstanceOf(DirectMailTransport::class, $transport);
        $this->assertInstanceOf(TransportInterface::class, $transport);
        $this->assertSame('test-key', $transport->getKey());
        $this->assertSame('test-secret', $transport->getSecret());
    }

    public function test_it_sends_through_laravels_mailer_without_network_requests(): void
    {
        $sent = $this->app['mail.manager']->raw('Hello from Laravel', function ($message) {
            $message->to('to@example.com')->subject('Integration test');
        });

        $this->assertInstanceOf(SentMessage::class, $sent);
        $this->assertCount(1, $this->history);
        parse_str((string) $this->history[0]['request']->getBody(), $payload);
        $this->assertSame('to@example.com', $payload['ToAddress']);
        $this->assertSame('from@example.com', $payload['AccountName']);
        $this->assertSame('Hello from Laravel', $payload['TextBody']);
    }
}
