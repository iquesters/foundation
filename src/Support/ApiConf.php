<?php

namespace Iquesters\Foundation\Support;

use Iquesters\Foundation\Support\BaseConf;

/**
 * @deprecated Module APIs are declared in composer.json (extra.platform.api) and
 *             routed by Routing\ModuleApi; nothing reads this any more. Kept so
 *             modules that still declare an `api_conf` property keep loading.
 */
class ApiConf extends BaseConf
{
    protected ?string $identifier = 'api_conf';
    
    protected bool $enabled;
    
    protected string $prefix; // Must be auto generated from module enum - the vendor name  

    /** @var ApiVersion[] */
    protected array $api_versions;

    protected function prepareDefault(BaseConf $default_values) {
        $default_values->enabled = true;

        $api_versions = new ApiVersion();
        $api_versions->prepareDefault($api_versions);
        
        $default_values->api_versions = [
            $api_versions
        ];
    }
}