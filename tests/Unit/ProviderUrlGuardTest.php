<?php

namespace Tests\Unit;

use App\Services\Providers\ProviderUrlGuard;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ProviderUrlGuardTest extends TestCase
{
    public function test_private_ip_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ProviderUrlGuard())->validate('http://127.0.0.1:8080');
    }

    public function test_embedded_url_credentials_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ProviderUrlGuard())->validate('https://user:password@example.com');
    }

    public function test_username_without_password_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ProviderUrlGuard())->validate('https://user@example.com');
    }

    public function test_password_without_username_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ProviderUrlGuard())->validate('https://:password@example.com');
    }

    public function test_public_ip_is_allowed(): void
    {
        (new ProviderUrlGuard())->validate('https://8.8.8.8');
        $this->addToAssertionCount(1);
    }
}
