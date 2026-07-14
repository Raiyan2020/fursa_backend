<?php

namespace Tests\Concerns;

use Illuminate\Testing\TestResponse;

trait AssertsDjangoApiEnvelope
{
    protected function assertSuccessEnvelope(TestResponse $response, int $status = 200, ?string $messageEn = null): TestResponse
    {
        $response->assertStatus($status)
            ->assertJsonStructure([
                'status',
                'code',
                'message_en',
                'message_ar',
                'data',
                'meta',
            ])
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('code', $status);

        if ($messageEn !== null) {
            $response->assertJsonPath('message_en', $messageEn);
        }

        return $response;
    }

    protected function assertErrorEnvelope(TestResponse $response, int $status = 400, ?string $messageEn = null): TestResponse
    {
        $response->assertStatus($status)
            ->assertJsonStructure([
                'status',
                'code',
                'message_en',
                'message_ar',
                'data',
                'meta',
            ])
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('code', $status);

        if ($messageEn !== null) {
            $response->assertJsonPath('message_en', $messageEn);
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
}
