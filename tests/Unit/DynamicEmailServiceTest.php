<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Mail\DynamicEmailService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DynamicEmailServiceTest extends TestCase
{
    #[Test]
    public function it_renders_django_forgot_password_placeholders(): void
    {
        $user = new User([
            'first_name' => 'Ahmed',
            'last_name' => 'Arafa',
            'email' => 'ahmed@example.com',
        ]);

        $template = <<<'HTML'
<p>Hi {{ user.first_name }},</p>
{% if method == 'OTP' %}
<p>You requested to reset your password.</p>
<p> Use the below OTP code to reset your password:</p>
<div class="otp">{{ otp_code }}</div>
{% else %}
<p>You requested to reset your password. Click the button below link to set a new password:</p>
<a href="{{ reset_password_link }}" class="btn">Reset Password</a>
{% endif %}
<p>This {{method|lower}} is valid for {{ expiry_time }} minutes.</p>
HTML;

        $rendered = DynamicEmailService::render(
            $template,
            DynamicEmailService::buildContext($user, [
                'otp_code' => '160072',
                'method' => 'OTP',
                'expiry_time' => 30,
            ])
        );

        $this->assertStringContainsString('Hi Ahmed,', $rendered);
        $this->assertStringContainsString('160072', $rendered);
        $this->assertStringContainsString('This otp is valid for 30 minutes.', $rendered);
        $this->assertStringNotContainsString('{{', $rendered);
        $this->assertStringNotContainsString('{% ', $rendered);
        $this->assertStringNotContainsString('Reset Password', $rendered);
    }

    #[Test]
    public function it_renders_laravel_flat_placeholders(): void
    {
        $user = new User(['first_name' => 'Sara', 'email' => 'sara@example.com']);

        $rendered = DynamicEmailService::render(
            'Hi {{first_name}}, OTP {{otp}} expires in {{expiry_minutes}} minutes.',
            DynamicEmailService::buildContext($user, [
                'otp' => '999111',
                'expiry_minutes' => 30,
            ])
        );

        $this->assertSame('Hi Sara, OTP 999111 expires in 30 minutes.', $rendered);
    }

    #[Test]
    public function it_applies_default_filter(): void
    {
        $rendered = DynamicEmailService::render(
            'Hello {{ user.first_name|default:"User" }}',
            ['user' => ['first_name' => '']]
        );

        $this->assertSame('Hello User', $rendered);
    }
}
