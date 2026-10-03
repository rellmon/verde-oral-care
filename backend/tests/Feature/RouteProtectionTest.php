<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Dentist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RouteProtectionTest extends TestCase
{
    use RefreshDatabase;

    // Every API route with what protects it, read from the router itself.
    private function apiRoutes(): array
    {
        $router = app('router');
        $routes = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/')) {
                continue;
            }

            $middleware = collect($router->gatherRouteMiddleware($route))->filter(fn ($item) => is_string($item));

            $roleMiddleware = $middleware->first(
                fn ($item) => str_contains($item, 'EnsureUserHasRole:') || str_starts_with($item, 'role:')
            );

            $routes[] = [
                'uri' => $route->uri(),
                'method' => $route->methods()[0],
                'requiresLogin' => $middleware->contains(fn ($item) => str_contains(strtolower($item), 'authenticate')),
                'role' => $roleMiddleware ? substr($roleMiddleware, strrpos($roleMiddleware, ':') + 1) : null,
            ];
        }

        return $routes;
    }

    public function test_only_the_intended_api_routes_are_public(): void
    {
        $public = collect($this->apiRoutes())
            ->reject(fn ($route) => $route['requiresLogin'])
            ->map(fn ($route) => $route['method'].' '.$route['uri'])
            ->sort()
            ->values()
            ->all();

        $this->assertSame([
            'GET api/dentists',
            'GET api/dentists/{dentist}',
            'GET api/dentists/{dentist}/availability',
            'GET api/services',
            'POST api/login',
            'POST api/register',
        ], $public);
    }

    public function test_every_protected_route_refuses_guests_and_the_wrong_roles(): void
    {
        $dentist = Dentist::factory()->create();
        $appointment = Appointment::factory()->for($dentist)->create();

        // Real records, so the route can load them before the role check runs.
        $values = [
            'appointment' => $appointment->id,
            'dentist' => $dentist->id,
            'patient' => $appointment->patient_id,
            'service' => $appointment->service_id,
        ];

        $users = [
            'admin' => User::factory()->admin()->create(),
            'dentist' => $dentist->user,
            'patient' => $appointment->patient->user,
        ];

        $protected = collect($this->apiRoutes())->filter(fn ($route) => $route['requiresLogin']);

        $urlFor = fn (array $route) => '/'.preg_replace_callback('/\{(\w+)\??\}/', function ($match) use ($values, $route) {
            $this->assertArrayHasKey($match[1], $values, "No test value for {{$match[1]}} in {$route['uri']}");

            return $values[$match[1]];
        }, $route['uri']);

        $checks = 0;

        // Guests first, before anyone is logged in.
        foreach ($protected as $route) {
            $status = $this->json($route['method'], $urlFor($route))->getStatusCode();

            $this->assertSame(401, $status, "{$route['method']} {$urlFor($route)} should refuse guests");
            $checks++;
        }

        // Then every role that is not allowed on a role-restricted route.
        foreach ($protected->filter(fn ($route) => $route['role'] !== null) as $route) {
            foreach ($users as $role => $user) {
                if ($role === $route['role']) {
                    continue;
                }

                Sanctum::actingAs($user);
                $status = $this->json($route['method'], $urlFor($route))->getStatusCode();

                $this->assertSame(403, $status, "{$route['method']} {$urlFor($route)} should refuse a {$role}");
                $checks++;
            }
        }

        // Guards against a test that quietly checks nothing.
        $this->assertGreaterThan(60, $checks);
    }
}
