<h1 align="center">Laravel mail aliyun</h1>

<p align="center">:e-mail: <a href="https://help.aliyun.com/product/29412.html">Aliyun DrirectMail</a> Transport for Laravel Application.</p>

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
    ],
```

AccessKeyID 和 AccessKeySecret 由阿里云官方颁发给用户的 AccessKey 信息（可以通过阿里云控制台[用户信息管理](https://usercenter.console.aliyun.com/?spm=a2c4g.11186623.2.17.12f2461dHSyXbw#/manage/ak)中查看和管理）.

## Attention  

Please make sure your timestamp is UTC type , if not ,please use function ```date_default_timezone_set('UTC')``` before you use ```date()``` function 

## Usage

Set default mail driver and configuration:

*.env*
```bash
MAIL_DRIVER=directmail

ALIYUN_ACCESS_KEY_ID=  #AccessKeyID
ALIYUN_ACCESS_KEY_SECRET= #AccessKeySecret
```

Please reference the official doc: [Laravel Sending mail](https://laravel.com/docs/5.6/mail#sending-mail)

## License

MIT

