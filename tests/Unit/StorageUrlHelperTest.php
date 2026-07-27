<?php

namespace Tests\Unit;

use Tests\TestCase;

class StorageUrlHelperTest extends TestCase
{
    public function test_getimg_strips_legacy_public_prefix(): void
    {
        $url = getimg('public/opportunity_images/uuid/cropped-image.jpg');

        $this->assertStringEndsWith('/storage/opportunity_images/uuid/cropped-image.jpg', $url);
        $this->assertStringNotContainsString('/storage/public/', $url);
    }

    public function test_getimg_handles_relative_disk_paths(): void
    {
        $url = getimg('banners/home-hero-placeholder.jpg');

        $this->assertStringEndsWith('/storage/banners/home-hero-placeholder.jpg', $url);
    }

    public function test_getimg_handles_storage_prefixed_paths(): void
    {
        $url = getimg('/storage/public/profile_pics/avatar.jpg');

        $this->assertStringEndsWith('/storage/profile_pics/avatar.jpg', $url);
    }

    public function test_getimg_returns_external_urls_unchanged(): void
    {
        $external = 'https://cdn.example.com/image.jpg';

        $this->assertSame($external, getimg($external));
    }
}
