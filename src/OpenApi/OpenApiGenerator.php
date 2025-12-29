<?php

namespace Iquesters\Foundation\OpenApi;

class OpenApiGenerator
{
    public function generate(): array
    {
        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'Iquesters API',
                'version' => 'platform',
            ],
            'paths' => [],
        ];
    }
}