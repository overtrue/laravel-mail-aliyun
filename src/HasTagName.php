<?php


namespace Overtrue\LaravelMailAliyun;

use Swift_Mime_SimpleMessage;

/**
 * Trait HasTagName
 */
trait HasTagName
{
    /**
     * The callbacks for the message.
     *
     * @var array
     */
    public $callbacks = [];

    /**
     * @param $tagName
     *
     * @return \Closure
     */
    protected function getMailableCallback($tagName)
    {
        return function (Swift_Mime_SimpleMessage $message) use ($tagName) {
            $message->getHeaders()->addTextHeader('X-Tag-Name', $tagName);
        };
    }

    /**
     * @param $tagName
     *
     * @return $this
     */
    public function tagName($tagName)
    {
        $this->callbacks['X-Tag-Name'] = $this->getMailableCallback($tagName);
        return $this;
    }
}
