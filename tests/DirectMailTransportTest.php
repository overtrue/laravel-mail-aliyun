<?php

namespace Overtrue\LaravelMailAliyun\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Overtrue\LaravelMailAliyun\DirectMailTransport;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

class DirectMailTransportTest extends TestCase
{
    private $history = [];

    private function transport(array $options = [], array $responses = []): DirectMailTransport
    {
        $handler = HandlerStack::create(new MockHandler($responses ?: [new Response(200, [], '{"RequestId":"test"}')]));
        $handler->push(Middleware::history($this->history));

        return new DirectMailTransport(new Client(['handler' => $handler]), 'test-key', 'test-secret', $options);
    }

    private function email(): Email
    {
        return (new Email)->from(new Address('from@example.com', 'Sender'))->to('to@example.com')->subject('Test')->text('Hello');
    }

    private function payload(): array
    {
        parse_str((string) $this->history[0]['request']->getBody(), $payload);

        return $payload;
    }

    public function test_it_sends_a_symfony_email_and_signs_the_payload(): void
    {
        $transport = $this->transport(['address_type' => 0]);
        $email = $this->email()->cc('copy@example.com')->html('<p>Hello</p>');
        $email->getHeaders()->addTextHeader('X-Tag-Name', '欢迎');

        $sent = $transport->send($email);
        $payload = $this->payload();

        $this->assertInstanceOf(SentMessage::class, $sent);
        $this->assertSame('directmail', (string) $transport);
        $this->assertSame('https://dm.aliyuncs.com', (string) $this->history[0]['request']->getUri());
        $this->assertSame('from@example.com', $payload['AccountName']);
        $this->assertSame('Sender', $payload['FromAlias']);
        $this->assertSame('to@example.com,copy@example.com', $payload['ToAddress']);
        $this->assertSame('Hello', $payload['TextBody']);
        $this->assertSame('<p>Hello</p>', $payload['HtmlBody']);
        $this->assertSame('欢迎', $payload['TagName']);
        $this->assertSame('0', $payload['AddressType']);
        $this->assertSame('0', $payload['ClickTrace']);

        $signature = $payload['Signature'];
        unset($payload['Signature']);
        ksort($payload);
        $query = http_build_query($payload, '', '&', PHP_QUERY_RFC3986);
        $this->assertSame(base64_encode(hash_hmac('sha1', 'POST&%2F&'.rawurlencode($query), 'test-secret&', true)), $signature);
    }

    public function test_it_respects_explicit_envelope_recipients_and_configured_sender(): void
    {
        $transport = $this->transport(['from_address' => 'configured@example.com', 'from_alias' => 'Configured', 'region_id' => 'ap-southeast-1']);
        $transport->send($this->email()->addTo('override@example.com'), new Envelope(new Address('bounce@example.com'), [new Address('override@example.com')]));
        $payload = $this->payload();

        $this->assertSame('override@example.com', $payload['ToAddress']);
        $this->assertSame('configured@example.com', $payload['AccountName']);
        $this->assertSame('Configured', $payload['FromAlias']);
        $this->assertSame('2017-06-22', $payload['Version']);
        $this->assertSame('https://dm.ap-southeast-1.aliyuncs.com', (string) $this->history[0]['request']->getUri());
    }

    public function test_envelope_sender_does_not_override_the_aliyun_from_account(): void
    {
        $this->transport()->send($this->email(), new Envelope(new Address('bounce@example.com'), [new Address('to@example.com')]));

        $this->assertSame('from@example.com', $this->payload()['AccountName']);
        $this->assertSame('Sender', $this->payload()['FromAlias']);
    }

    public function test_null_configuration_uses_defaults_and_text_only_is_preserved(): void
    {
        $this->transport(['region_id' => null, 'from_address' => null, 'from_alias' => null])->send($this->email()->text('0'));
        $payload = $this->payload();

        $this->assertSame('from@example.com', $payload['AccountName']);
        $this->assertSame('cn-hangzhou', $payload['RegionId']);
        $this->assertSame('0', $payload['TextBody']);
        $this->assertArrayNotHasKey('HtmlBody', $payload);
        $this->assertArrayNotHasKey('TagName', $payload);
    }

    public function test_html_only_is_preserved(): void
    {
        $this->transport()->send($this->email()->text(null)->html('<b>Hello</b>'));
        $this->assertSame('<b>Hello</b>', $this->payload()['HtmlBody']);
        $this->assertArrayNotHasKey('TextBody', $this->payload());
    }

    public function test_resource_bodies_are_sent_as_content(): void
    {
        $body = fopen('php://temp', 'r+');
        fwrite($body, 'Stream body');
        rewind($body);

        try {
            $this->transport()->send($this->email()->text($body));
            $this->assertSame('Stream body', $this->payload()['TextBody']);
        } finally {
            fclose($body);
        }
    }

    public function test_bcc_is_rejected_without_leaking_or_mutating_the_message(): void
    {
        $email = $this->email()->bcc('private@example.com');

        try {
            $this->transport()->send($email);
            $this->fail('Expected a transport exception.');
        } catch (TransportException $exception) {
            $this->assertSame([], $this->history);
            $this->assertSame('private@example.com', $email->getBcc()[0]->getAddress());
        }
    }

    public function test_envelope_only_recipients_are_not_exposed_as_visible_recipients(): void
    {
        try {
            $this->transport()->send($this->email(), new Envelope(new Address('from@example.com'), [new Address('private@example.com')]));
            $this->fail('Expected a transport exception.');
        } catch (TransportException $exception) {
            $this->assertSame([], $this->history);
            $this->assertStringContainsString('envelope-only recipients', $exception->getMessage());
        }
    }

    public function test_raw_messages_are_rejected_with_a_descriptive_transport_exception(): void
    {
        try {
            $this->transport()->send(new RawMessage('raw message'), new Envelope(new Address('from@example.com'), [new Address('to@example.com')]));
            $this->fail('Expected a transport exception.');
        } catch (TransportException $exception) {
            $this->assertSame([], $this->history);
            $this->assertStringContainsString('structured MIME message', $exception->getMessage());
        }
    }

    public function test_attachments_are_rejected_before_sending(): void
    {
        $this->expectException(TransportException::class);
        $this->transport()->send($this->email()->attach('contents', 'example.txt'));
    }

    public function test_invalid_regions_raise_a_transport_exception(): void
    {
        $this->expectException(TransportException::class);
        $this->transport(['region_id' => 'invalid'])->send($this->email());
    }

    public function test_http_failures_raise_a_symfony_transport_exception(): void
    {
        $this->expectException(TransportException::class);
        $this->transport([], [new Response(400, [], '{"Code":"InvalidToAddress"}')])->send($this->email());
    }

    public function test_connection_failures_raise_a_symfony_transport_exception(): void
    {
        $this->expectException(TransportException::class);
        $this->transport([], [new ConnectException('Unavailable', new Request('POST', 'https://dm.aliyuncs.com'))])->send($this->email());
    }
}
