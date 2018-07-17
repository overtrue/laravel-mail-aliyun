<?php

/*
 * This file is part of the overtrue/laravel-mail-aliyun.
 *
 * (c) overtrue <anzhengchao@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Overtrue\LaravelMailAliyun;

use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;

/**
 * Class DirectMailServiceProvider.
 *
 * @author overtrue <i@overtrue.me>
 */
class DirectMailServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register()
    {
        $this->app['swift.transport']->extend('directmail', function () {
            $config = $this->app['config']->get('services.directmail', []);

            return new DirectMailTransport(new Client($config), $config['key'], $config);
        });
    }
}
