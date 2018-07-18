<h1 align="center">Laravel mail aliyun</h1>

<p align="center">:e-mail: <a href="https://help.aliyun.com/product/29412.html">Aliyun DrirectMail</a> Transport for Laravel Application.</p>

## Installing

```shell
$ composer require overtrue/laravel-mail-aliyun -vvv
```

## Configuration

> API documention: https://help.aliyun.com/document_detail/29435.html

```php
// config/services.php
    
    'directmail' => [
        'key' => env('ALIYUN_ACCESS_KEY_ID'),
        'address_type' => 1, 
        'from_alias' => null,  
        'click_trace' => 0, 
        'version' => '2015-11-23',
        'region_id' => null,
    ],
```

## Usage

Set default mail driver:

```env
//.env
MAIL_DRIVER=directmail
```

Please reference the official doc: [Laravel Sending mail](https://laravel.com/docs/5.6/mail#sending-mail)

## License

MIT
