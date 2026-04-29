<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\RoleMiddleware;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    public function test_it_allows_users_with_matching_enum_roles(): void
    {
        $request = Request::create('/api/v1/company/internships');
        $request->setUserResolver(fn () => new User([
            'role' => UserRole::COMPANY,
        ]));

        $response = (new RoleMiddleware())->handle(
            $request,
            fn () => new Response('', 204),
            'company',
        );

        $this->assertSame(204, $response->getStatusCode());
    }
}
