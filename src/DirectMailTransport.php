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
use Illuminate\Mail\Transport\Transport;
use Illuminate\Support\Arr;
use Psr\Http\Message\ResponseInterface;
use Swift_Mime_SimpleMessage;

/**
 * Class DirectMailTransport
 */
class DirectMailTransport extends Transport
{
    /**
     * @var \GuzzleHttp\ClientInterface
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
        $this->key = $key;
        $this->secret = $secret;
        $this->client = $client;
        $this->options = $options;
    }

    /**
     * Send the given Message.
     *
     * Recipient/sender data will be retrieved from the Message API.
     * The return value is the number of recipients who were accepted for delivery.
     *
     * @param  string[]  $failedRecipients An array of failures by-reference
     * @return int
     */
    public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = null)
    {
        $this->beforeSendPerformed($message);

        $message->setBcc([]);

        $regionId = Arr::get($this->options, 'region_id', 'cn-hangzhou');
        $region = $this->regions[$regionId];

        $this->client->post($region['url'], ['form_params' => $this->payload($message, $region)]);

        $this->sendPerformed($message);

        return $this->numberOfRecipients($message);
    }

    /**
     * Get the HTTP payload for sending the message.
     *
     *
     * @return array
     */
    protected function payload(Swift_Mime_SimpleMessage $message, array $region)
    {
        $parameters = array_filter([
            'AccountName' => Arr::get($this->options, 'from_address', key($message->getFrom())),
            'ReplyToAddress' => 'true',
            'AddressType' => Arr::get($this->options, 'address_type', 1),
            'ToAddress' => $this->getTo($message),
            'FromAlias' => Arr::get($this->options, 'from_alias', current($message->getFrom())),
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
        ]);

        $bodyName = $this->getBodyName($message);
        $parameters[$bodyName] = $message->getBody();

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
    protected function getTo(Swift_Mime_SimpleMessage $message)
    {
        return collect($this->allContacts($message))->map(function ($display, $address) {
            return $display ? $display." <{$address}>" : $address;
        })->values()->implode(',');
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
     * @return array
     */
    protected function allContacts(Swift_Mime_SimpleMessage $message)
    {
        return array_merge(
            (array) $message->getTo(),
            (array) $message->getCc(),
            (array) $message->getBcc()
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
     * @return string
     */
    protected function getBodyName(Swift_Mime_SimpleMessage $message)
    {
        return $message->getBodyContentType() == 'text/plain' ? 'TextBody' : 'HtmlBody';
    }

    /**
     * @return string|null
     */
    protected function getTagName(Swift_Mime_SimpleMessage $message)
    {
        return $message->getHeaders()->has('X-Tag-Name') === false ? null : $message->getHeaders()->get('X-Tag-Name')->getFieldBodyModel();
    }
}
