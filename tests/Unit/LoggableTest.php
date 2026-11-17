<?php

namespace Iquesters\Foundation\Tests\Unit;

use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Iquesters\Foundation\System\Traits\Loggable;
use Iquesters\Foundation\Tests\TestCase;

class LoggableTest extends TestCase
{
    private array $logged = [];

    protected function setUp(): void
    {
        parent::setUp();

        Event::listen(MessageLogged::class, function (MessageLogged $e) {
            $this->logged[] = [$e->level, $e->message, $e->context];
        });
    }

    public function test_prefixes_class_method_and_line_from_instance_and_static_code(): void
    {
        $subject = new LoggableSubject;

        $instanceLine = $subject->fromInstance();
        $staticLine = LoggableSubject::fromStatic();

        $this->assertSame(['warning', "[LoggableSubject::fromInstance({$instanceLine})] instance message", ['k' => 1]], $this->logged[0]);
        $this->assertSame(['info', "[LoggableSubject::fromStatic({$staticLine})] static message", []], $this->logged[1]);
    }

    public function test_method_start_and_end_report_duration(): void
    {
        LoggableSubject::timed();

        $this->assertStringContainsString('[LoggableSubject::timed(', $this->logged[0][1]);
        $this->assertStringContainsString('👉 STARTED - work', $this->logged[0][1]);
        $this->assertStringContainsString('✅ COMPLETED - work', $this->logged[1][1]);
        $this->assertArrayHasKey('duration_ms', $this->logged[1][2]);
        $this->assertSame('job-1', $this->logged[1][2]['id']);
    }
}

class LoggableSubject
{
    use Loggable;

    public function fromInstance(): int
    {
        $this->logWarning('instance message', ['k' => 1]);
        $line = __LINE__ - 1; // the log call above

        return $line;
    }

    public static function fromStatic(): int
    {
        static::logInfo('static message');
        $line = __LINE__ - 1; // the log call above

        return $line;
    }

    public static function timed(): void
    {
        static::logMethodStart('work');
        static::logMethodEnd('work', ['id' => 'job-1']);
    }
}
