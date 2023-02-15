<?php

namespace Overtrue\LaravelMailAliyun;

use Illuminate\Mail\Mailable;
use Swift_Mime_SimpleMessage;

/**
 * Trait HasTagName
 *
 * Can be used by mailable to set tag name
 */
trait HasTagName
{
    /**
     * @param  string  $tagName
     * @return \Closure
     */
    protected function getMailableCallback($tagName)
    {
        return function (Swift_Mime_SimpleMessage $message) use ($tagName) {
            $message->getHeaders()->addTextHeader('X-Tag-Name', $tagName);
        };
    }

    /**
     * @param  string  $tagName
     * @return $this
     */
    public function tagName($tagName)
    {
        if ($this instanceof Mailable) {
            $this->withSwiftMessage($this->getMailableCallback($tagName));
        }

        return $this;
    }
}
