<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\DTO\ProxyDto;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $protocol
 * @property string $host
 * @property int $port
 * @property string|null $proxy_key
 * @property string|null $username
 * @property string|null $password
 * @property bool $is_active
 * @property Carbon|null $cooldown_until
 * @property int $fails_count
 * @property int $success_count
 * @property Carbon|null $last_used_at
 * @property string|null $last_error
 * @property int|null $avg_response_time_ms
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> available()
 */
class ProxyServer extends Model
{
    use HasFactory;

    protected $fillable = [
        'proxy_key',
        'protocol',
        'host',
        'port',
        'username',
        'password',
        'is_active',
        'cooldown_until',
        'fails_count',
        'success_count',
        'last_used_at',
        'last_error',
        'avg_response_time_ms',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'cooldown_until' => 'datetime',
            'last_used_at' => 'datetime',
            'port' => 'integer',
            'fails_count' => 'integer',
            'success_count' => 'integer',
            'avg_response_time_ms' => 'integer',
        ];
    }

    /**
     * Scope to find active and non-cooldown proxies.
     *
     * @param  Builder<ProxyServer>  $query
     * @return Builder<ProxyServer>
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(function (Builder $sub) {
                $sub->whereNull('cooldown_until')
                    ->orWhere('cooldown_until', '<=', now());
            });
    }

    public function isCoolingDown(): bool
    {
        return $this->cooldown_until !== null && $this->cooldown_until->isFuture();
    }

    protected static function booted(): void
    {
        static::saving(function (ProxyServer $proxy) {
            if (empty($proxy->proxy_key)) {
                $proxy->proxy_key = static::generateKey(
                    $proxy->protocol ?? 'http',
                    $proxy->host,
                    (int) $proxy->port,
                    $proxy->username,
                    $proxy->password
                );
            }
        });
    }

    /**
     * Генерация уникального детерминированного ключа для прокси-сервера.
     * Позволяет надежно различать покупные прокси с одинаковым host и port, но разными учетными данными.
     */
    public static function generateKey(
        string $protocol,
        string $host,
        int $port,
        ?string $username = null,
        ?string $password = null
    ): string {
        $proto = strtolower(trim($protocol !== '' ? $protocol : 'http'));
        $h = strtolower(trim($host));
        $u = $username !== null ? trim($username) : '';
        $p = $password !== null ? trim($password) : '';

        return hash('sha256', "{$proto}://{$u}:{$p}@{$h}:{$port}");
    }

    public function toDto(): ProxyDto
    {
        return new ProxyDto(
            id: $this->id,
            protocol: $this->protocol,
            host: $this->host,
            port: $this->port,
            username: $this->username,
            password: $this->password,
        );
    }
}
