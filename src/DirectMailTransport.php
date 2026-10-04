<?php

/*
 * This file is part of the overtrue/laravel-mail-aliyun.
 *
 * (c) overtrue <anzhengchao@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Overtrue\LaravelMailAliyun;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Arr;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\MessageConverter;

/**
 * Class DirectMailTransport
 */
class DirectMailTransport extends AbstractTransport
{
    /**
     * @var ClientInterface
     */
    protected $client;

    /**
     * @var string
     */
    protected $key;

    /**
     * @var string
     */
    protected $secret;

    /**
     * @var array
     */
    protected $options = [];

    /**
     * @var string
     */
    protected $regions = [
        'cn-hangzhou' => [
            'id' => 'cn-hangzhou',
            'url' => 'https://dm.aliyuncs.com',
            'version' => '2015-11-23',
        ],
        'ap-southeast-1' => [
            'id' => 'ap-southeast-1',
            'url' => 'https://dm.ap-southeast-1.aliyuncs.com',
            'version' => '2017-06-22',
        ],
        'ap-southeast-2' => [
            'id' => 'ap-southeast-2',
            'url' => 'https://dm.ap-southeast-2.aliyuncs.com',
            'version' => '2017-06-22',
        ],
    ];

    /**
     * DirectMailTransport constructor.
     */
    public function __construct(ClientInterface $client, string $key, string $secret, array $options = [])
    {
        parent::__construct();

        $this->key = $key;
        $this->secret = $secret;
        $this->client = $client;
        $this->options = $options;
    }

    protected function doSend(SentMessage $message): void
    {
        $original = $message->getOriginalMessage();

        if (! $original instanceof Message) {
            throw new TransportException('This DirectMail transport requires a structured MIME message. Use SMTP for raw messages.');
        }

        $email = MessageConverter::toEmail($original);

        // Do not silently discard unsupported content or expose blind recipients.
        if ($email->getBcc() || $email->getAttachments()) {
            throw new TransportException('This DirectMail transport does not support BCC or attachments. Use SMTP instead.');
        }

        $visibleRecipients = array_map(static function (Address $address) {
            return $address->getAddress();
        }, array_merge($email->getTo(), $email->getCc()));

        foreach ($message->getEnvelope()->getRecipients() as $recipient) {
            if (! in_array($recipient->getAddress(), $visibleRecipients, true)) {
                throw new TransportException('This DirectMail transport cannot expose envelope-only recipients in ToAddress. Use SMTP instead.');
            }
        }

        $regionId = Arr::get($this->options, 'region_id') ?: 'cn-hangzhou';

        if (! isset($this->regions[$regionId])) {
            throw new TransportException('Unsupported DirectMail region: '.$regionId);
        }

        $region = $this->regions[$regionId];

        try {
            $this->client->post($region['url'], [
                'form_params' => $this->payload($email, $region, $message->getEnvelope()),
            ]);
        } catch (GuzzleException $exception) {
            throw new TransportException('Unable to send mail via DirectMail.', 0, $exception);
        }
    }

    public function __toString(): string
    {
        return 'directmail';
    }

    /**
     * Get the HTTP payload for sending the message.
     *
     *
     * @return array
     */
    protected function payload(Email $message, array $region, Envelope $envelope)
    {
        $from = $message->getFrom()[0] ?? $envelope->getSender();

        $parameters = array_filter([
            'AccountName' => Arr::get($this->options, 'from_address') ?: $from->getAddress(),
            'ReplyToAddress' => 'true',
            'AddressType' => Arr::get($this->options, 'address_type', 1),
            'ToAddress' => $this->getTo($envelope),
            'FromAlias' => Arr::get($this->options, 'from_alias') ?: $from->getName(),
            'Subject' => $message->getSubject(),
            'ClickTrace' => Arr::get($this->options, 'click_trace', 0),
            'Format' => 'json',
            'Action' => 'SingleSendMail',
            'Version' => $region['version'],
            'AccessKeyId' => $this->getKey(),
            'Timestamp' => now()->toIso8601ZuluString(),
            'SignatureMethod' => 'HMAC-SHA1',
            'SignatureVersion' => '1.0',
            'SignatureNonce' => \uniqid(),
            'RegionId' => $region['id'],
            'TagName' => $this->getTagName($message),
        ], static function ($value) {
            return $value !== null && $value !== '';
        });

        foreach (['TextBody' => $message->getTextBody(), 'HtmlBody' => $message->getHtmlBody()] as $name => $body) {
            if ($body !== null) {
                if (is_resource($body)) {
                    rewind($body);
                    $body = stream_get_contents($body);
                }

                $parameters[$name] = $body;
            }
        }

        $parameters['Signature'] = $this->makeSignature($parameters);

        return $parameters;
    }

    /**
     * @return string
     */
    protected function makeSignature(array $parameters)
    {
        \ksort($parameters);

        $encoded = [];

        foreach ($parameters as $key => $value) {
            $encoded[] = \sprintf('%s=%s', rawurlencode($key), rawurlencode($value));
        }

        $signString = 'POST&%2F&'.rawurlencode(\implode('&', $encoded));

        return base64_encode(hash_hmac('sha1', $signString, $this->getSecret().'&', true));
    }

    /**
     * Get the "to" payload field for the API request.
     *
     *
     * @return string
     */
    protected function getTo(Envelope $envelope)
    {
        return implode(',', array_map(static function (Address $address) {
            return $address->getAddress();
        }, $envelope->getRecipients()));
    }

    /**
     * @return mixed
     */
    protected function getTransmissionId(ResponseInterface $response)
    {
        return object_get(
            json_decode($response->getBody()->getContents()),
            'RequestId'
        );
    }

    /**
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * @return string
     */
    public function getSecret()
    {
        return $this->secret;
    }

    /**
     * @return string
     */
    public function setKey(string $key)
    {
        return $this->key = $key;
    }

    /**
     * @return string
     */
    public function setSecret(string $secret)
    {
        return $this->secret = $secret;
    }

    /**
     * @return string|null
     */
    protected function getTagName(Email $message)
    {
        return $message->getHeaders()->has('X-Tag-Name') === false ? null : $message->getHeaders()->get('X-Tag-Name')->getBody();
    }
}
