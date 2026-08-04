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
        $this->assertStringStartsWith('http', $url);
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

    public function test_getimg_strips_uploader_style_storage_prefix(): void
    {
        $url = getimg('/storage/banners/home-hero-placeholder.jpg');

        $this->assertSame(
            rtrim((string) config('fursa.backend_host'), '/').'/storage/banners/home-hero-placeholder.jpg',
            $url
        );
        $this->assertStringNotContainsString('/storage/storage/', $url);
    }

    public function test_getimg_strips_repeated_storage_prefixes(): void
    {
        $url = getimg('storage/storage/banners/home-hero-placeholder.jpg');

        $this->assertStringEndsWith('/storage/banners/home-hero-placeholder.jpg', $url);
        $this->assertStringNotContainsString('/storage/storage/', $url);
    }

    public function test_getimg_returns_external_urls_unchanged(): void
    {
        $external = 'https://cdn.example.com/image.jpg';

        $this->assertSame($external, getimg($external));
    }

    public function test_opportunity_cover_image_prefers_announcement(): void
    {
        $after = (object) ['image' => 'after.jpg', 'is_after_completed' => true, 'is_deleted' => false];
        $announcement = (object) ['image' => 'card.jpg', 'is_after_completed' => false, 'is_deleted' => false];

        $cover = opportunity_cover_image([$after, $announcement]);

        $this->assertSame('card.jpg', $cover->image);
        $this->assertCount(1, opportunity_card_images([$after, $announcement]));
        $this->assertSame('card.jpg', opportunity_card_images([$after, $announcement])->first()->image);
    }

    public function test_opportunity_cover_image_falls_back_when_no_announcement(): void
    {
        $after = (object) ['image' => 'after.jpg', 'is_after_completed' => true, 'is_deleted' => false];

        $cover = opportunity_cover_image([$after]);

        $this->assertSame('after.jpg', $cover->image);
    }
}
