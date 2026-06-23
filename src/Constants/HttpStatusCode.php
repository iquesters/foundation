<?php

namespace Iquesters\Foundation\Constants;

class HttpStatusCode
{
    const HTTP_CONTINUE = 100;
    const HTTP_SWITCHING_PROTOCOLS = 101;
    const HTTP_PROCESSING = 102;
    const HTTP_EARLY_HINTS = 103;

    const HTTP_OK = 200;
    const HTTP_CREATED = 201;
    const HTTP_ACCEPTED = 202;
    const HTTP_NON_AUTHORITATIVE_INFORMATION = 203;
    const HTTP_NO_CONTENT = 204;
    const HTTP_RESET_CONTENT = 205;
    const HTTP_PARTIAL_CONTENT = 206;
    const HTTP_MULTI_STATUS = 207;
    const HTTP_ALREADY_REPORTED = 208;
    const HTTP_IM_USED = 226;

    const HTTP_MULTIPLE_CHOICES = 300;
    const HTTP_MOVED_PERMANENTLY = 301;
    const HTTP_FOUND = 302;
    const HTTP_SEE_OTHER = 303;
    const HTTP_NOT_MODIFIED = 304;
    const HTTP_USE_PROXY = 305;
    const HTTP_TEMPORARY_REDIRECT = 307;
    const HTTP_PERMANENTLY_REDIRECT = 308;

    const HTTP_BAD_REQUEST = 400;
    const HTTP_UNAUTHORIZED = 401;
    const HTTP_PAYMENT_REQUIRED = 402;
    const HTTP_FORBIDDEN = 403;
    const HTTP_NOT_FOUND = 404;
    const HTTP_METHOD_NOT_ALLOWED = 405;
    const HTTP_NOT_ACCEPTABLE = 406;
    const HTTP_PROXY_AUTHENTICATION_REQUIRED = 407;
    const HTTP_REQUEST_TIMEOUT = 408;
    const HTTP_CONFLICT = 409;
    const HTTP_GONE = 410;
    const HTTP_LENGTH_REQUIRED = 411;
    const HTTP_PRECONDITION_FAILED = 412;
    const HTTP_CONTENT_TOO_LARGE = 413;
    const HTTP_URI_TOO_LONG = 414;
    const HTTP_UNSUPPORTED_MEDIA_TYPE = 415;
    const HTTP_RANGE_NOT_SATISFIABLE = 416;
    const HTTP_EXPECTATION_FAILED = 417;
    const HTTP_I_AM_A_TEAPOT = 418;
    const HTTP_MISDIRECTED_REQUEST = 421;
    const HTTP_UNPROCESSABLE_ENTITY = 422;
    const HTTP_LOCKED = 423;
    const HTTP_FAILED_DEPENDENCY = 424;
    const HTTP_TOO_EARLY = 425;
    const HTTP_UPGRADE_REQUIRED = 426;
    const HTTP_PRECONDITION_REQUIRED = 428;
    const HTTP_TOO_MANY_REQUESTS = 429;
    const HTTP_REQUEST_HEADER_FIELDS_TOO_LARGE = 431;
    const HTTP_UNAVAILABLE_FOR_LEGAL_REASONS = 451;

    const HTTP_INTERNAL_SERVER_ERROR = 500;
    const HTTP_NOT_IMPLEMENTED = 501;
    const HTTP_BAD_GATEWAY = 502;
    const HTTP_SERVICE_UNAVAILABLE = 503;
    const HTTP_GATEWAY_TIMEOUT = 504;
    const HTTP_HTTP_VERSION_NOT_SUPPORTED = 505;
    const HTTP_VARIANT_ALSO_NEGOTIATES = 506;
    const HTTP_INSUFFICIENT_STORAGE = 507;
    const HTTP_LOOP_DETECTED = 508;
    const HTTP_NOT_EXTENDED = 510;
    const HTTP_NETWORK_AUTHENTICATION_REQUIRED = 511;

    public static function isInformational(int $statusCode): bool
    {
        return $statusCode >= self::HTTP_CONTINUE && $statusCode < self::HTTP_OK;
    }

    public static function isSuccess(int $statusCode): bool
    {
        return $statusCode >= self::HTTP_OK && $statusCode < self::HTTP_MULTIPLE_CHOICES;
    }

    public static function isRedirect(int $statusCode): bool
    {
        return $statusCode >= self::HTTP_MULTIPLE_CHOICES && $statusCode < self::HTTP_BAD_REQUEST;
    }

    public static function isClientError(int $statusCode): bool
    {
        return $statusCode >= self::HTTP_BAD_REQUEST && $statusCode < self::HTTP_INTERNAL_SERVER_ERROR;
    }

    public static function isServerError(int $statusCode): bool
    {
        return $statusCode >= self::HTTP_INTERNAL_SERVER_ERROR;
    }

    public static function isError(int $statusCode): bool
    {
        return self::isClientError($statusCode) || self::isServerError($statusCode);
    }

    public static function defaultMessage(int $statusCode): string
    {
        return match ($statusCode) {
            self::HTTP_CONTINUE => 'Continue',
            self::HTTP_SWITCHING_PROTOCOLS => 'Switching protocols',
            self::HTTP_PROCESSING => 'Processing',
            self::HTTP_EARLY_HINTS => 'Early hints',
            self::HTTP_OK => 'Request successful',
            self::HTTP_CREATED => 'Resource created successfully',
            self::HTTP_ACCEPTED => 'Request accepted for processing',
            self::HTTP_NON_AUTHORITATIVE_INFORMATION => 'Non-authoritative information',
            self::HTTP_NO_CONTENT => 'No content',
            self::HTTP_RESET_CONTENT => 'Reset content',
            self::HTTP_PARTIAL_CONTENT => 'Partial content',
            self::HTTP_MULTI_STATUS => 'Multi-status',
            self::HTTP_ALREADY_REPORTED => 'Already reported',
            self::HTTP_IM_USED => 'IM used',
            self::HTTP_MULTIPLE_CHOICES => 'Multiple choices',
            self::HTTP_MOVED_PERMANENTLY => 'Resource moved permanently',
            self::HTTP_FOUND => 'Resource found at new location',
            self::HTTP_SEE_OTHER => 'See other resource',
            self::HTTP_NOT_MODIFIED => 'Resource not modified',
            self::HTTP_USE_PROXY => 'Use proxy',
            self::HTTP_TEMPORARY_REDIRECT => 'Temporary redirect',
            self::HTTP_PERMANENTLY_REDIRECT => 'Permanent redirect',
            self::HTTP_BAD_REQUEST => 'Bad request',
            self::HTTP_UNAUTHORIZED => 'Unauthorized',
            self::HTTP_PAYMENT_REQUIRED => 'Payment required',
            self::HTTP_FORBIDDEN => 'Forbidden',
            self::HTTP_NOT_FOUND => 'Resource not found',
            self::HTTP_METHOD_NOT_ALLOWED => 'Method not allowed',
            self::HTTP_NOT_ACCEPTABLE => 'Not acceptable',
            self::HTTP_PROXY_AUTHENTICATION_REQUIRED => 'Proxy authentication required',
            self::HTTP_REQUEST_TIMEOUT => 'Request timeout',
            self::HTTP_CONFLICT => 'Conflict',
            self::HTTP_GONE => 'Resource gone',
            self::HTTP_LENGTH_REQUIRED => 'Length required',
            self::HTTP_PRECONDITION_FAILED => 'Precondition failed',
            self::HTTP_CONTENT_TOO_LARGE => 'Content too large',
            self::HTTP_URI_TOO_LONG => 'URI too long',
            self::HTTP_UNSUPPORTED_MEDIA_TYPE => 'Unsupported media type',
            self::HTTP_RANGE_NOT_SATISFIABLE => 'Range not satisfiable',
            self::HTTP_EXPECTATION_FAILED => 'Expectation failed',
            self::HTTP_I_AM_A_TEAPOT => "I'm a teapot",
            self::HTTP_MISDIRECTED_REQUEST => 'Misdirected request',
            self::HTTP_UNPROCESSABLE_ENTITY => 'Validation failed',
            self::HTTP_LOCKED => 'Resource locked',
            self::HTTP_FAILED_DEPENDENCY => 'Failed dependency',
            self::HTTP_TOO_EARLY => 'Too early',
            self::HTTP_UPGRADE_REQUIRED => 'Upgrade required',
            self::HTTP_PRECONDITION_REQUIRED => 'Precondition required',
            self::HTTP_TOO_MANY_REQUESTS => 'Too many requests',
            self::HTTP_REQUEST_HEADER_FIELDS_TOO_LARGE => 'Request header fields too large',
            self::HTTP_UNAVAILABLE_FOR_LEGAL_REASONS => 'Unavailable for legal reasons',
            self::HTTP_INTERNAL_SERVER_ERROR => 'Internal server error',
            self::HTTP_NOT_IMPLEMENTED => 'Not implemented',
            self::HTTP_BAD_GATEWAY => 'Bad gateway',
            self::HTTP_SERVICE_UNAVAILABLE => 'Service unavailable',
            self::HTTP_GATEWAY_TIMEOUT => 'Gateway timeout',
            self::HTTP_HTTP_VERSION_NOT_SUPPORTED => 'HTTP version not supported',
            self::HTTP_VARIANT_ALSO_NEGOTIATES => 'Variant also negotiates',
            self::HTTP_INSUFFICIENT_STORAGE => 'Insufficient storage',
            self::HTTP_LOOP_DETECTED => 'Loop detected',
            self::HTTP_NOT_EXTENDED => 'Not extended',
            self::HTTP_NETWORK_AUTHENTICATION_REQUIRED => 'Network authentication required',
            default => 'Request processed',
        };
    }
}
