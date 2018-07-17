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
     * @var array
     */
    protected $options = [];

    /**
     * @var string
     */
    protected $url = 'https://dm.aliyuncs.com/?Action=SingleSendMail';

    /**
     * Create a new SparkPost transport instance.
     *
     * @param \GuzzleHttp\ClientInterface $client
     * @param string                      $key
     * @param array                       $options
     */
    public function __construct(ClientInterface $client, $key, $options = [])
    {
        $this->key = $key;
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

        $to = $this->getTo($message);

        $message->setBcc([]);

        $this->client->post($this->url, $this->payload($message, $to));

        $this->sendPerformed($message);

        return $this->numberOfRecipients($message);
    }

    /**
     * Get the HTTP payload for sending the Mailgun message.
     *
     * @param \Swift_Mime_SimpleMessage $message
     * @param string                    $to
     *
     * @return array
     */
    protected function payload(Swift_Mime_SimpleMessage $message, $to)
    {
        $parameters = [
            'form_params' => [
                'AccountName' => $message->getFrom(),
                'ReplyToAddress' => true,
                'AddressType' => array_get($this->options, 'address_type', 1),
                'ToAddress' => $this->getTo(),
                'FromAlias' => array_get($this->options, 'from_alias'),
                'Subject' => $message->getSubject(),
                'HtmlBody' => $message->getBody(),
                'ClickTrace' => array_get($this->options, 'click_trace', 0),
                'Format' => 'json',
                'Version' => array_get($this->options, 'version', '2015-11-23'),
                'AccessKeyId' => $this->getKey(),
                'Timestamp' => date('Y-m-d\TH:i:s\Z'),
                'SignatureMethod' => 'HMAC-SHA1',
                'SignatureVersion' => '1.0',
                'SignatureNonce' => \uniqid(),
                'RegionId' => \array_get($this->options, 'region_id'),
            ],
        ];

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

        $signString = rawurlencode('POST&/&'.http_build_query($parameters, null, '&', PHP_QUERY_RFC3986));

        return base64_encode(hash_hmac('sha1', $signString, $this->getKey(), true));
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
     * Set the API key being used by the transport.
     *
     * @param string $key
     *
     * @return string
     */
    public function setKey($key)
    {
        return $this->key = $key;
    }
}
