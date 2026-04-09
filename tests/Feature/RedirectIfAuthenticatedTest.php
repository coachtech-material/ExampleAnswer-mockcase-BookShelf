<?php

namespace Tests\Feature;

use App\Http\Middleware\RedirectIfAuthenticated;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class RedirectIfAuthenticatedTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_is_redirected_to_home(): void
    {
        $middleware = new RedirectIfAuthenticated;
        $request = Request::create('/login', 'GET');
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $middleware->handle($request, fn () => response('next'));

        $this->assertEquals(url(RouteServiceProvider::HOME), $response->headers->get('Location'));

        Auth::logout();
    }

    public function test_guest_can_access_route(): void
    {
        $middleware = new RedirectIfAuthenticated;
        $request = Request::create('/login', 'GET');

        $response = $middleware->handle($request, fn () => response('allowed'));

        $this->assertEquals('allowed', $response->getContent());
    }
}
