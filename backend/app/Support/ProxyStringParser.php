<?php

declare(strict_types=1);

namespace App\Support;

class ProxyStringParser
{
    /**
     * Parse various proxy connection string formats into structured components.
     *
     * Supported formats:
     * - ip:port (e.g. 192.168.1.1:8080)
     * - protocol://ip:port (e.g. socks5://192.168.1.1:1080)
     * - ip:port:login:password (e.g. 192.168.1.1:8080:user:pass)
     * - ip:port@login:password (e.g. 192.168.1.1:8080@user:pass)
     * - login:password@ip:port (e.g. user:pass@192.168.1.1:8080)
     * - protocol://login:password@ip:port (standard RFC 3986 URI)
     * - protocol://ip:port:login:password
     * - protocol://ip:port@login:password
     * - login:password:ip:port
     *
     * @return array{protocol: string, host: string, port: int, username: string|null, password: string|null}|null
     */
    public static function parse(string $input): ?array
    {
        $input = trim($input);
        if ($input === '') {
            return null;
        }

        // 1. Extract protocol if present (e.g. http://, socks5://, https://)
        $protocol = 'http';
        if (preg_match('/^([a-zA-Z0-9_+]+):\/\/(.*)$/', $input, $matches)) {
            $protocol = strtolower($matches[1]);
            $input = $matches[2];
        }

        // 2. Handle '@' notation
        if (str_contains($input, '@')) {
            // Check reverse notation: host:port@user:password
            if (preg_match('/^([a-zA-Z0-9.-]+):(\d{1,5})@(.+)$/', $input, $matches)) {
                $host = $matches[1];
                $port = (int) $matches[2];
                $auth = $matches[3];
                [$user, $pass] = str_contains($auth, ':') ? explode(':', $auth, 2) : [$auth, null];

                return self::formatResult($protocol, $host, $port, $user, $pass);
            }

            // Standard notation: user:password@host:port
            if (preg_match('/^(.+)@([a-zA-Z0-9.-]+):(\d{1,5})$/', $input, $matches)) {
                $auth = $matches[1];
                $host = $matches[2];
                $port = (int) $matches[3];
                [$user, $pass] = str_contains($auth, ':') ? explode(':', $auth, 2) : [$auth, null];

                return self::formatResult($protocol, $host, $port, $user, $pass);
            }
        }

        // 3. Handle colon-delimited parts
        $parts = explode(':', $input);

        // 4+ parts: host:port:user:password OR user:password:host:port
        if (count($parts) >= 4) {
            // Case A: host:port:user:password
            if (is_numeric($parts[1]) && (int) $parts[1] >= 1 && (int) $parts[1] <= 65535) {
                $host = $parts[0];
                $port = (int) $parts[1];
                $user = $parts[2];
                $pass = implode(':', array_slice($parts, 3));

                return self::formatResult($protocol, $host, $port, $user, $pass);
            }

            // Case B: user:password:host:port
            $lastIndex = count($parts) - 1;
            if (is_numeric($parts[$lastIndex]) && (int) $parts[$lastIndex] >= 1 && (int) $parts[$lastIndex] <= 65535) {
                $port = (int) $parts[$lastIndex];
                $host = $parts[$lastIndex - 1];
                $user = $parts[0];
                $pass = implode(':', array_slice($parts, 1, $lastIndex - 1));

                return self::formatResult($protocol, $host, $port, $user, $pass);
            }
        }

        // 3 parts: host:port:user
        if (count($parts) === 3 && is_numeric($parts[1]) && (int) $parts[1] >= 1 && (int) $parts[1] <= 65535) {
            return self::formatResult($protocol, $parts[0], (int) $parts[1], $parts[2], null);
        }

        // 2 parts: host:port
        if (count($parts) === 2 && is_numeric($parts[1]) && (int) $parts[1] >= 1 && (int) $parts[1] <= 65535) {
            return self::formatResult($protocol, $parts[0], (int) $parts[1], null, null);
        }

        // Fallback: parse_url
        $parsed = parse_url("{$protocol}://{$input}");
        if ($parsed !== false && ! empty($parsed['host']) && ! empty($parsed['port'])) {
            return self::formatResult(
                $protocol,
                (string) $parsed['host'],
                (int) $parsed['port'],
                isset($parsed['user']) ? (string) $parsed['user'] : null,
                isset($parsed['pass']) ? (string) $parsed['pass'] : null
            );
        }

        return null;
    }

    /**
     * Validate and structure final proxy array.
     *
     * @return array{protocol: string, host: string, port: int, username: string|null, password: string|null}|null
     */
    private static function formatResult(string $protocol, string $host, int $port, ?string $user, ?string $pass): ?array
    {
        $host = trim($host);
        if ($host === '' || $port < 1 || $port > 65535) {
            return null;
        }

        return [
            'protocol' => $protocol !== '' ? $protocol : 'http',
            'host' => $host,
            'port' => $port,
            'username' => ($user !== null && trim($user) !== '') ? trim($user) : null,
            'password' => ($pass !== null && trim($pass) !== '') ? trim($pass) : null,
        ];
    }
}
