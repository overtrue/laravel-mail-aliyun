<h1 align="center">Laravel mail aliyun</h1>

<p align="center">:e-mail: <a href="https://help.aliyun.com/product/29412.html">Aliyun DrirectMail</a> Transport for Laravel Application.</p>

[![Sponsor me](https://github.com/overtrue/overtrue/blob/master/sponsor-me-button-s.svg?raw=true)](https://github.com/sponsors/overtrue)

## Installing

```shell
$ composer require overtrue/laravel-mail-aliyun -vvv
```

## Configuration

> API documention: https://help.aliyun.com/document_detail/29435.html

*config/services.php*
```php
    'directmail' => [
        'key' => env('ALIYUN_ACCESS_KEY_ID'),
        'secret' => env('ALIYUN_ACCESS_KEY_SECRET'),
        'region_id' => env('ALIYUN_REGION_ID'),
        'from_address' => env('ALIYUN_FROM_ADDRESS'),
        'from_alias' => env('ALIYUN_FROM_ALIAS'),
    ],
```

AccessKeyID 和 AccessKeySecret 由阿里云官方颁发给用户的 AccessKey 信息（可以通过阿里云控制台[用户信息管理](https://usercenter.console.aliyun.com/?spm=a2c4g.11186623.2.17.12f2461dHSyXbw#/manage/ak)中查看和管理）.

## Usage

Set default mail driver and configuration:

*.env*
```bash
MAIL_MAILER=directmail

ALIYUN_ACCESS_KEY_ID=  #AccessKeyID
ALIYUN_ACCESS_KEY_SECRET= #AccessKeySecret
ALIYUN_REGION_ID= #RegionID: cn-hangzhou, ap-southeast-1, ap-southeast-2
ALIYUN_FROM_ADDRESS= #FromAddress
ALIYUN_FROM_ALIAS= #FromAlias
```

Add a mailer in `config/mail.php`:

```php
'mailers' => [
    'directmail' => ['transport' => 'directmail'],
],
```

This version supports Laravel 13 and PHP 8.3+ using Symfony Mailer. To and CC recipients are
sent through the API's `ToAddress` field; the API does not preserve a separate CC
header. This transport rejects BCC, envelope-only recipients absent from To/CC headers,
and attachments rather than silently dropping them or exposing blind recipients. Use SMTP when these features are needed.
Custom message callbacks should use `withSymfonyMessage()` and Symfony's `Email`.

*TagName*
```php
use Overtrue\LaravelMailAliyun\HasTagName;
class VerifyMail extends Mailable{
    use HasTagName;
    public function build()
    {
        $this->tagName('alreadyDefinedTag');
        return $this->text('mails.verify');
    }
}
```

Please reference the official doc: [Laravel Sending mail](https://laravel.com/docs/13.x/mail#sending-mail)

## :heart: Sponsor me 

[![Sponsor me](https://github.com/overtrue/overtrue/blob/master/sponsor-me.svg?raw=true)](https://github.com/sponsors/overtrue)

如果你喜欢我的项目并想支持它，[点击这里 :heart:](https://github.com/sponsors/overtrue)


## Project supported by JetBrains

Many thanks to Jetbrains for kindly providing a license for me to work on this and other open-source projects.

[![](https://resources.jetbrains.com/storage/products/company/brand/logos/jb_beam.svg)](https://www.jetbrains.com/?from=https://github.com/overtrue)

## License

MIT

## Tests

```shell
composer install
composer test
```

The test suite uses a mocked HTTP handler and does not send real email.
