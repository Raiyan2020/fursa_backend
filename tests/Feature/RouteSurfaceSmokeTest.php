<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Tests\Concerns\AssertsDjangoApiEnvelope;
use Tests\Support\CreatesDomainFixtures;
use Tests\TestCase;

/**
 * Executes every registered API/admin method at least once. Domain flow tests
 * cover successful mutations; this suite guarantees that the remaining route
 * surface resolves through middleware/binding without an unhandled exception.
 */
class RouteSurfaceSmokeTest extends TestCase
{
    use AssertsDjangoApiEnvelope;
    use CreatesDomainFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_api_route_surface_never_throws_server_exception(): void
    {
        foreach ($this->routesWithPrefix('api/') as $route) {
            foreach ($this->requestMethods($route) as $method) {
                $uri = $this->materializeUri($route->uri());
                $response = $this->withHeaders([
                    'Accept' => 'application/json',
                    'Lang' => 'en',
                ])->call($method, '/'.$uri);

                $this->assertNoServerException($response);
            }
        }
    }

    public function test_admin_route_surface_never_throws_for_guest_or_admin(): void
    {
        $routes = $this->routesWithPrefix('dashboard');

        foreach ($routes as $route) {
            foreach ($this->requestMethods($route) as $method) {
                $response = $this->call($method, '/'.$this->materializeUri($route->uri()));
                $this->assertNoServerException($response);
            }
        }

        $this->actingAs($this->adminActor(), 'admin');

        foreach ($routes as $route) {
            if ($route->uri() === 'dashboard/login') {
                continue;
            }

            foreach ($this->requestMethods($route) as $method) {
                $response = $this->call($method, '/'.$this->materializeUri($route->uri()));
                $this->assertNoServerException($response);
            }
        }
    }

    public function test_authenticated_api_route_surface_never_throws_for_supported_actors(): void
    {
        [, $volunteerToken] = $this->createVolunteerActor();
        [, $organizationToken] = $this->createOrganizationActor();

        $routes = $this->routesWithPrefix('api/')
            ->filter(fn (Route $route) => in_array('auth:api', $route->gatherMiddleware(), true));

        foreach ([$volunteerToken, $organizationToken] as $token) {
            foreach ($routes as $route) {
                foreach ($this->requestMethods($route) as $method) {
                    $response = $this->call(
                        $method,
                        '/'.$this->materializeUri($route->uri()),
                        [],
                        [],
                        [],
                        [
                            'HTTP_ACCEPT' => 'application/json',
                            'HTTP_LANG' => 'en',
                            'HTTP_AUTHORIZATION' => 'Token '.$token,
                        ]
                    );

                    $this->assertNoServerException($response);
                }
            }
        }
    }

    public function test_route_method_and_uri_pairs_are_unique(): void
    {
        $pairs = collect(['api/', 'dashboard'])
            ->flatMap(fn (string $prefix) => $this->routesWithPrefix($prefix))
            ->flatMap(fn (Route $route) => collect($this->requestMethods($route))
                ->map(fn (string $method) => $method.' '.$route->uri()));

        $duplicates = $pairs->duplicates()->values()->all();

        $this->assertSame([], $duplicates, 'Duplicate routes make handlers unreachable: '.implode(', ', $duplicates));
    }

    /**
     * @return Collection<int, Route>
     */
    protected function routesWithPrefix(string $prefix): Collection
    {
        return collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn (Route $route) => str_starts_with($route->uri(), $prefix))
            ->values();
    }

    /**
     * @return list<string>
     */
    protected function requestMethods(Route $route): array
    {
        return array_values(array_filter(
            $route->methods(),
            fn (string $method) => $method !== 'HEAD'
        ));
    }

    protected function materializeUri(string $uri): string
    {
        return (string) preg_replace_callback('/\{([^}]+)\}/', function (array $match) {
            $parameter = rtrim($match[1], '?');

            return in_array($parameter, ['uuid', 'slug', 'choice_type'], true)
                ? 'missing-test-value'
                : '999999';
        }, $uri);
    }
}
