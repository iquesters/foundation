<?php

namespace Iquesters\Foundation;

use Iquesters\Foundation\System\Providers\BaseServiceProvider;
use Iquesters\Foundation\System\Package\PackageInfo;
use Iquesters\Foundation\Package\FoundationPackageInfo;

class FoundationServiceProvider extends BaseServiceProvider
{
    protected function packageInfo(): PackageInfo
    {
        return new FoundationPackageInfo();
    }


    // protected function middlewares(): array
    // {
    //     return [
    //         'validate.platform.version' => ValidatePlatformVersion::class,
    //         'request-middleware'        => RequestMiddleware::class,
    //         'response-middleware'       => ResponseMiddleware::class,
    //     ];
    // }

    // protected function serviceProviders(): array
    // {
    //     return [
    //         ApiRouteServiceProvider::class,
    //     ];
    // }

}