<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ProxyStringParser;
use PHPUnit\Framework\TestCase;

class ProxyStringParserTest extends TestCase
{
    public function test_parses_ip_port(): void
    {
        $res = ProxyStringParser::parse('192.168.1.1:8080');

        $this->assertNotNull($res);
        $this->assertSame('http', $res['protocol']);
        $this->assertSame('192.168.1.1', $res['host']);
        $this->assertSame(8080, $res['port']);
        $this->assertNull($res['username']);
        $this->assertNull($res['password']);
    }

    public function test_parses_ip_port_with_protocol(): void
    {
        $res = ProxyStringParser::parse('socks5://10.0.0.1:1080');

        $this->assertNotNull($res);
        $this->assertSame('socks5', $res['protocol']);
        $this->assertSame('10.0.0.1', $res['host']);
        $this->assertSame(1080, $res['port']);
        $this->assertNull($res['username']);
        $this->assertNull($res['password']);
    }

    public function test_parses_ip_port_login_password_colon_format(): void
    {
        $res = ProxyStringParser::parse('192.168.1.100:8000:myuser:secret123');

        $this->assertNotNull($res);
        $this->assertSame('http', $res['protocol']);
        $this->assertSame('192.168.1.100', $res['host']);
        $this->assertSame(8000, $res['port']);
        $this->assertSame('myuser', $res['username']);
        $this->assertSame('secret123', $res['password']);
    }

    public function test_parses_ip_port_at_login_password_format(): void
    {
        $res = ProxyStringParser::parse('192.168.1.100:8000@myuser:secret123');

        $this->assertNotNull($res);
        $this->assertSame('http', $res['protocol']);
        $this->assertSame('192.168.1.100', $res['host']);
        $this->assertSame(8000, $res['port']);
        $this->assertSame('myuser', $res['username']);
        $this->assertSame('secret123', $res['password']);
    }

    public function test_parses_standard_uri_auth_format(): void
    {
        $res = ProxyStringParser::parse('http://proxyuser:proxypass@proxy.provider.com:3128');

        $this->assertNotNull($res);
        $this->assertSame('http', $res['protocol']);
        $this->assertSame('proxy.provider.com', $res['host']);
        $this->assertSame(3128, $res['port']);
        $this->assertSame('proxyuser', $res['username']);
        $this->assertSame('proxypass', $res['password']);
    }

    public function test_parses_login_password_at_host_port_without_protocol(): void
    {
        $res = ProxyStringParser::parse('proxyuser:proxypass@proxy.provider.com:3128');

        $this->assertNotNull($res);
        $this->assertSame('http', $res['protocol']);
        $this->assertSame('proxy.provider.com', $res['host']);
        $this->assertSame(3128, $res['port']);
        $this->assertSame('proxyuser', $res['username']);
        $this->assertSame('proxypass', $res['password']);
    }

    public function test_parses_socks5_with_ip_port_login_password(): void
    {
        $res = ProxyStringParser::parse('socks5://185.220.101.5:1080:agent007:p@ssw0rd!');

        $this->assertNotNull($res);
        $this->assertSame('socks5', $res['protocol']);
        $this->assertSame('185.220.101.5', $res['host']);
        $this->assertSame(1080, $res['port']);
        $this->assertSame('agent007', $res['username']);
        $this->assertSame('p@ssw0rd!', $res['password']);
    }

    public function test_parses_password_with_colons(): void
    {
        $res = ProxyStringParser::parse('192.168.1.50:8080:admin:complex:pass:word');

        $this->assertNotNull($res);
        $this->assertSame('192.168.1.50', $res['host']);
        $this->assertSame(8080, $res['port']);
        $this->assertSame('admin', $res['username']);
        $this->assertSame('complex:pass:word', $res['password']);
    }

    public function test_returns_null_on_invalid_strings(): void
    {
        $this->assertNull(ProxyStringParser::parse(''));
        $this->assertNull(ProxyStringParser::parse('not-a-proxy'));
        $this->assertNull(ProxyStringParser::parse('192.168.1.1:999999')); // invalid port
    }
}
