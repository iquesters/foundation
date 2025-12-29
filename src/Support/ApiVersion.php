<?php

namespace Iquesters\Foundation\Support;

class ApiVersion extends BaseConf
{
    protected ?string $identifier = 'api_version';
    
    protected string $version;
    protected string $file_name;
    
    protected function prepareDefault(BaseConf $default_values) {
        $default_values->version = "v1";
        $default_values->file_name = "routes/api.v1.php";
    }
}