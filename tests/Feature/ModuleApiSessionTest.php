<?php

namespace Iquesters\Foundation\Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Iquesters\Foundation\Tests\TestCase;

/**
 * The modules' JS calls /api/{module}/v1/... with the web session cookie.
 * Module APIs accept it for the app's own pages (Sanctum stateful domains)
 * without the app enabling statefulApi(); other callers still need a token.
 *
 * Each request below carries only the real session cookie from a web login:
 * the in-memory session and auth state are cleared in between.
 */
class ModuleApiSessionTest extends TestCase
{
    private const URL = '/api/foundation/v1/business-entity/list/nothing-here';

    private string $sessionCookie;

    protected function setUp(): void
    {
        parent::setUp();

        config(['session.driver' => 'file']);
        Route::middleware('web')->get('/_test/login/{id}', function (string $id) {
            Auth::guard('web')->loginUsingId($id);

            return 'ok';
        });

        $user = $this->makeUser('super-admin');
        $this->sessionCookie = $this->get("/_test/login/{$user->id}")->assertOk()
            ->getCookie(config('session.cookie'))->getValue();
    }

    /** A fresh request with only the session cookie (and a Referer). */
    private function apiCall(?string $referer, bool $withCookie = true)
    {
        app('session')->forgetDrivers();
        app()->forgetInstance('session.store');
        Auth::forgetGuards();
        // Cookies and credentials set by an earlier call stay on the test case.
        $this->defaultCookies = [];
        $this->withCredentials = false;

        $request = $withCookie ? $this->withCredentials()->withCookie(config('session.cookie'), $this->sessionCookie) : $this;

        return $request->withHeaders(array_filter(['Referer' => $referer]))->getJson(self::URL);
    }

    public function test_a_logged_in_page_can_call_module_apis_with_its_session(): void
    {
        // 404 (unknown business entity), not 401: the session login counted.
        $this->apiCall('http://localhost/business-entity')->assertNotFound();
    }

    public function test_other_origins_and_guests_are_not_authenticated(): void
    {
        $this->apiCall('https://evil.example/')->assertUnauthorized();
        $this->apiCall(null)->assertUnauthorized();
        $this->apiCall('http://localhost/', withCookie: false)->assertUnauthorized();
    }
}
