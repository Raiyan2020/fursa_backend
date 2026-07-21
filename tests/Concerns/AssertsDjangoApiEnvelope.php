<?php

namespace Tests\Concerns;

use Illuminate\Testing\TestResponse;

trait AssertsDjangoApiEnvelope
{
    protected function assertSuccessEnvelope(TestResponse $response, int $status = 200, ?string $messageEn = null): TestResponse
    {
        $response->assertStatus($status)
            ->assertJsonStructure([
                'key',
                'msg',
                'code',
                'response_status' => ['error', 'validation_errors'],
                'data',
            ])
            ->assertJsonPath('key', 'success')
            ->assertJsonPath('code', $status)
            ->assertJsonPath('response_status.error', false);

        if ($messageEn !== null) {
            $response->assertJsonPath('msg', $messageEn);
        }

        return $response;
    }

    protected function assertErrorEnvelope(TestResponse $response, int $status = 400, ?string $messageEn = null): TestResponse
    {
        $response->assertStatus($status)
            ->assertJsonStructure([
                'key',
                'msg',
                'code',
                'response_status' => ['error', 'validation_errors'],
                'data',
            ])
            ->assertJsonPath('key', 'fail')
            ->assertJsonPath('code', $status)
            ->assertJsonPath('response_status.error', true);

        if ($messageEn !== null) {
            $response->assertJsonPath('msg', $messageEn);
        }

        return $response;
    }

    protected function assertPaginationMeta(TestResponse $response): TestResponse
    {
        $response->assertJsonStructure([
            'meta' => [
                'pagination' => ['page', 'limit', 'total', 'total_pages'],
                'timestamp',
            ],
        ]);

        return $response;
    }

    protected function assertNoServerException(TestResponse $response): TestResponse
    {
        $this->assertLessThan(
            500,
            $response->getStatusCode(),
            "Endpoint returned {$response->getStatusCode()}:\n".$response->getContent()
        );

        return $response;
    }
}
