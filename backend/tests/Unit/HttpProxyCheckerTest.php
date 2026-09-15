<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Infrastructure\Services\HttpProxyChecker;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HttpProxyCheckerTest extends TestCase
{
    private HttpProxyChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checker = new HttpProxyChecker;
    }

    public function test_successful_ping(): void
    {
        Http::fake([
            '*' => Http::response('<html>OK</html>', 200),
        ]);

        $result = $this->checker->ping([
            'protocol' => 'http',
            'host' => '1.2.3.4',
            'port' => 8080,
            'username' => 'user',
            'password' => 'pass',
        ]);

        $this->assertTrue($result->isSuccess);
        $this->assertFalse($result->isCaptcha);
        $this->assertEquals(200, $result->httpStatus);
        $this->assertNull($result->errorMessage);
    }

    public function test_captcha_detection(): void
    {
        Http::fake([
            '*' => Http::response('<html><div class="smartcaptcha"></div></html>', 200),
        ]);

        $result = $this->checker->ping([
            'protocol' => 'http',
            'host' => '1.2.3.4',
            'port' => 8080,
            'username' => null,
            'password' => null,
        ]);

        $this->assertFalse($result->isSuccess);
        $this->assertTrue($result->isCaptcha);
        $this->assertStringContainsString('капча', (string) $result->errorMessage);
    }

    public function test_network_failure(): void
    {
        Http::fake([
            '*' => function () {
                throw new ConnectionException('Connection refused to proxy 1.2.3.4:8080');
            },
        ]);

        $result = $this->checker->ping([
            'protocol' => 'http',
            'host' => '1.2.3.4',
            'port' => 8080,
            'username' => null,
            'password' => null,
        ]);

        $this->assertFalse($result->isSuccess);
        $this->assertFalse($result->isCaptcha);
        $this->assertStringContainsString('Connection refused', (string) $result->errorMessage);
    }

    public function test_ping_many_handles_list(): void
    {
        Http::fake([
            '*' => Http::response('<html>OK</html>', 200),
        ]);

        $results = $this->checker->pingMany([
            [
                'protocol' => 'http',
                'host' => '1.1.1.1',
                'port' => 8080,
                'username' => null,
                'password' => null,
            ],
            [
                'protocol' => 'socks5',
                'host' => '2.2.2.2',
                'port' => 1080,
                'username' => 'u',
                'password' => 'p',
            ],
        ]);

        $this->assertCount(2, $results);
        $this->assertTrue($results[0]->isSuccess);
        $this->assertTrue($results[1]->isSuccess);
    }
}
