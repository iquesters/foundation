<?php

namespace Iquesters\Foundation\Config;

use Iquesters\Foundation\Support\BaseConf;
use Iquesters\Foundation\Support\ApiConf;
use Iquesters\Foundation\Enums\Module;

class FoundationConf extends BaseConf
{
    protected ?string $identifier = Module::FOUNDATION;

    protected ApiConf $api_conf;

    protected function prepareDefault(BaseConf $default_values)
    {   
        $default_values->api_conf = new ApiConf();
        $default_values->api_conf->prefix = 'foundation'; // Must be auto generated from module enum - the vendor name  
        $default_values->api_conf->prepareDefault($default_values->api_conf);
    }
}