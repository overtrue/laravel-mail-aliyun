<?php

namespace Overtrue\LaravelMailAliyun\Tests;

use Illuminate\Mail\Mailable;
use Overtrue\LaravelMailAliyun\HasTagName;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Email;

class HasTagNameTest extends TestCase
{
    public function test_it_registers_a_symfony_message_callback(): void
    {
        $mailable = new class extends Mailable
        {
            use HasTagName;
        };
        $this->assertSame($mailable, $mailable->tagName('welcome'));
        $this->assertCount(1, $mailable->callbacks);

        $email = new Email;
        ($mailable->callbacks[0])($email);

        $this->assertSame('welcome', $email->getHeaders()->get('X-Tag-Name')->getBodyAsString());
    }
}
