<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ProxyServer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProxyServer
 */
class ProxyServerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'protocol' => $this->protocol,
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'is_active' => $this->is_active,
            'cooldown_until' => $this->cooldown_until?->toIso8601String(),
            'is_cooling_down' => $this->isCoolingDown(),
            'fails_count' => $this->fails_count,
            'success_count' => $this->success_count,
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'last_error' => $this->last_error,
            'avg_response_time_ms' => $this->avg_response_time_ms,
            'masked_endpoint' => $this->username
                ? "{$this->protocol}://{$this->username}:***@{$this->host}:{$this->port}"
                : "{$this->protocol}://{$this->host}:{$this->port}",
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
