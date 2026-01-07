<?php

namespace Iquesters\Foundation\Enums;

class Module
{
    public const VENDOR    = 'iquesters/';
    
    public const FOUNDATION = self::VENDOR . 'foundation';
    public const MASTER_DATA = self::VENDOR . 'masterdata';
    public const USER_INFE = self::VENDOR . 'user-interface';
    public const USER_MGMT = self::VENDOR . 'user-management';
    public const ORGANISATION = self::VENDOR . 'organisation';
    public const PRODUCT   = self::VENDOR . 'product';
    public const SMART_MESSENGER = self::VENDOR . 'smart-messenger';
    public const INTEGRATION = self::VENDOR . 'integration';
}