<?php

namespace Tests\Feature;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

class PostmanCollectionCoverageTest extends TestCase
{
    public function test_collection_covers_every_api_route_with_valid_body_modes(): void
    {
        $rootJson = file_get_contents(base_path('Fursa_API.postman_collection.json'));
        $docsJson = file_get_contents(base_path('docs/postman/Fursa_API.postman_collection.json'));

        $this->assertSame($docsJson, $rootJson, 'Root and docs Postman collections must stay identical.');

        $collection = json_decode($rootJson, true, flags: JSON_THROW_ON_ERROR);
        $requests = $this->flattenRequests($collection['item'] ?? []);

        foreach ($requests as $request) {
            $mode = $request['body']['mode'] ?? null;
            $this->assertContains($mode, [null, 'formdata', 'raw']);

            if (in_array($request['method'], ['GET', 'HEAD'], true)) {
                $this->assertNull($mode, "{$request['method']} {$request['path']} must have no body.");
            }
        }

        $missing = [];
        foreach (RouteFacade::getRoutes() as $route) {
            if (! $route instanceof Route || ! str_starts_with($route->uri(), 'api/')) {
                continue;
            }

            $methods = array_values(array_diff($route->methods(), ['HEAD', 'OPTIONS']));
            if ($methods === []) {
                continue;
            }

            $path = $this->normalizePath($route->uri());
            $covered = collect($requests)->contains(
                fn (array $request) => $request['path'] === $path && in_array($request['method'], $methods, true)
            );

            if (! $covered) {
                $missing[] = implode('|', $methods).' '.$route->uri();
            }
        }

        $this->assertSame([], $missing, 'Postman is missing API route definitions: '.implode(', ', $missing));
    }

    private function flattenRequests(array $items): array
    {
        $requests = [];

        foreach ($items as $item) {
            if (isset($item['request'])) {
                $raw = $item['request']['url']['raw'] ?? '';
                $path = preg_replace('/^\{\{base_url\}\}/', 'api/', $raw);
                $path = explode('?', $path, 2)[0];

                $requests[] = [
                    'name' => $item['name'] ?? '',
                    'method' => $item['request']['method'] ?? '',
                    'path' => $this->normalizePath($path),
                    'body' => $item['request']['body'] ?? [],
                ];
            }

            if (isset($item['item'])) {
                array_push($requests, ...$this->flattenRequests($item['item']));
            }
        }

        return $requests;
    }

    private function normalizePath(string $path): string
    {
        $path = rtrim($path, '/');
        $path = preg_replace('/\{\{[^}]+\}\}/', '{}', $path);

        return preg_replace('/\{[^}]+\}/', '{}', $path);
    }
}
