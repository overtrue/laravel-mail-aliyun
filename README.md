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
MAIL_DRIVER=directmail

ALIYUN_ACCESS_KEY_ID=  #AccessKeyID
ALIYUN_ACCESS_KEY_SECRET= #AccessKeySecret
ALIYUN_REGION_ID= #RegionID: cn-hangzhou, ap-southeast-1, ap-southeast-2
ALIYUN_FROM_ADDRESS= #FromAddress
ALIYUN_FROM_ALIAS= #FromAlias
```

*TagName*
```php
use Overtrue\LaravelMailAliyun\HasTagName;
class VerifyMail extend Mailable{
    use HasTagName;
    public function build()
    {
        $this->tagName('alreadyDefinedTag');
        return $this->text('mails.verify');
    }
}
```

Please reference the official doc: [Laravel Sending mail](https://laravel.com/docs/5.6/mail#sending-mail)

## :heart: Sponsor me 

If you like the work I do and want to support it, [you know what to do :heart:](https://github.com/sponsors/overtrue)

如果你喜欢我的项目并想支持它，[点击这里 :heart:](https://github.com/sponsors/overtrue)

## PHP 扩展包开发

> 想知道如何从零开始构建 PHP 扩展包？
>
> 请关注我的实战课程，我会在此课程中分享一些扩展开发经验 —— [《PHP 扩展包实战教程 - 从入门到发布》](https://learnku.com/courses/creating-package)

## License

MIT
