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
use Swift_Mime_SimpleMessage;

/**
 * Class Transport.
 *
 * @author overtrue <i@overtrue.me>
 */
class DirectMailTransport extends Transport
{
    /**
     * Guzzle client instance.
     *
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
    protected $regons = [
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
     *
     * @param \GuzzleHttp\ClientInterface $client
     * @param string                      $key
     * @param string                      $secret
     * @param array                       $options
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
     * @param Swift_Mime_SimpleMessage $message
     * @param string[]                 $failedRecipients An array of failures by-reference
     *
     * @return int
     */
    public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = null)
    {
        $this->beforeSendPerformed($message);

        $message->setBcc([]);

        $regionId = \array_get($this->options, 'region_id', 'cn-hangzhou');
        $region = $this->regons[$regionId];

        $this->client->post($region['url'], ['form_params' => $this->payload($message, $region)]);

        $this->sendPerformed($message);

        return $this->numberOfRecipients($message);
    }

    /**
     * Get the HTTP payload for sending the message.
     *
     * @param \Swift_Mime_SimpleMessage $message
     * @param array                     $region
     *
     * @return array
     */
    protected function payload(Swift_Mime_SimpleMessage $message, array $region)
    {
        date_default_timezone_set('UTC');
        $parameters = array_filter([
            'AccountName' => array_get($this->options, 'from_address', \config('mail.from.address', key($message->getFrom()))),
            'ReplyToAddress' => 'true',
            'AddressType' => array_get($this->options, 'address_type', 1),
            'ToAddress' => $this->getTo($message),
            'FromAlias' => array_get($this->options, 'from_alias'),
            'Subject' => $message->getSubject(),
            'HtmlBody' => $message->getBody(),
            'ClickTrace' => array_get($this->options, 'click_trace', 0),
            'Format' => 'json',
            'Action' => 'SingleSendMail',
            'Version' => $region['version'],
            'AccessKeyId' => $this->getKey(),
            'Timestamp' => date('Y-m-d\TH:i:s\Z'),
            'SignatureMethod' => 'HMAC-SHA1',
            'SignatureVersion' => '1.0',
            'SignatureNonce' => \uniqid(),
            'RegionId' => $region['id'],
        ]);

        $parameters['Signature'] = $this->makeSignature($parameters);

        return $parameters;
    }

    /**
     * @param array $parameters
     *
     * @return string
     */
    protected function makeSignature(array $parameters)
    {
        \ksort($parameters);

        $encoded = [];

        foreach ($parameters as $key => $value) {
            $encoded[] = \sprintf('%s=%s', rawurlencode($key), rawurlencode($value));
        }

        $signString = 'POST&%2F&'.rawurlencode(\join('&', $encoded));

        return base64_encode(hash_hmac('sha1', $signString, $this->getSecret().'&', true));
    }

    /**
     * Get the "to" payload field for the API request.
     *
     * @param \Swift_Mime_SimpleMessage $message
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
     * Get the transmission ID from the response.
     *
     * @param \GuzzleHttp\Psr7\Response $response
     *
     * @return string
     */
    protected function getTransmissionId($response)
    {
        return object_get(
            json_decode($response->getBody()->getContents()), 'RequestId'
        );
    }

    /**
     * Get all of the contacts for the message.
     *
     * @param \Swift_Mime_SimpleMessage $message
     *
     * @return array
     */
    protected function allContacts(Swift_Mime_SimpleMessage $message)
    {
        return array_merge(
            (array) $message->getTo(), (array) $message->getCc(), (array) $message->getBcc()
        );
    }

    /**
     * Get the API key being used by the transport.
     *
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * Get the API key being used by the transport.
     *
     * @return string
     */
    public function getSecret()
    {
        return $this->secret;
    }

    /**
     * Set the API key being used by the transport.
     *
     * @param string $key
     *
     * @return string
     */
    public function setKey(string $key)
    {
        return $this->key = $key;
    }

    /**
     * Get the API key being used by the transport.
     *
     * @return string
     */
    public function setSecret(string $secret)
    {
        return $this->secret = $secret;
    }
}
