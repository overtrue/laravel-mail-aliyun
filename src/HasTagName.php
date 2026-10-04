<?php

namespace Overtrue\LaravelMailAliyun;

use Illuminate\Mail\Mailable;
use Symfony\Component\Mime\Email;

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
        return function (Email $message) use ($tagName) {
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
            $this->withSymfonyMessage($this->getMailableCallback($tagName));
        }

        return $this;
    }
}
