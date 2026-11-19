<?php

namespace Iquesters\Foundation\Support;

/**
 * Foundation's own public/ files (e.g. the business entity builder's JS and
 * CSS): served at /module-assets/foundation/{path}, or from
 * public/vendor/foundation once published (see ModuleAssets).
 */
final class Assets extends ModuleAssets
{
    protected static function slug(): string
    {
        return 'foundation';
    }
}
