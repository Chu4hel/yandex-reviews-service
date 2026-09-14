<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\ProxyServerResource;
use App\Models\ProxyServer;
use App\Support\ProxyStringParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProxyServerController extends Controller
{
    /**
     * Список всех прокси-серверов в пуле ротации.
     */
    public function index(): AnonymousResourceCollection
    {
        $proxies = ProxyServer::orderBy('id', 'desc')->get();

        return ProxyServerResource::collection($proxies);
    }

    /**
     * Добавление одного или нескольких прокси-серверов в пул.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'proxies' => ['nullable', 'array'],
            'proxies.*' => ['required', 'string'],
            'proxy' => ['nullable', 'string'],
        ]);

        $rawList = [];
        if (! empty($validated['proxy'])) {
            $rawList[] = (string) $validated['proxy'];
        }
        if (! empty($validated['proxies']) && is_array($validated['proxies'])) {
            foreach ($validated['proxies'] as $item) {
                if (is_string($item) && trim($item) !== '') {
                    $rawList[] = trim($item);
                }
            }
        }

        if (empty($rawList)) {
            return response()->json([
                'message' => 'Не переданы адреса прокси для добавления.',
            ], 422);
        }

        $added = [];
        foreach ($rawList as $raw) {
            $parsed = $this->parseProxyString($raw);
            if (! $parsed) {
                continue;
            }

            $proxy = ProxyServer::updateOrCreate(
                ['host' => $parsed['host'], 'port' => $parsed['port']],
                [
                    'protocol' => $parsed['protocol'],
                    'username' => $parsed['username'],
                    'password' => $parsed['password'],
                    'is_active' => true,
                    'cooldown_until' => null,
                    'fails_count' => 0,
                ]
            );

            $added[] = $proxy;
        }

        return response()->json([
            'message' => 'Прокси-серверы успешно добавлены в пул ротации.',
            'count' => count($added),
            'proxies' => ProxyServerResource::collection($added),
        ], 201);
    }

    /**
     * Переключение статуса активности прокси-сервера.
     */
    public function toggle(ProxyServer $proxy): ProxyServerResource
    {
        $proxy->update([
            'is_active' => ! $proxy->is_active,
            'cooldown_until' => null, // сброс карантина при ручном переключении
        ]);

        return new ProxyServerResource($proxy);
    }

    /**
     * Удаление прокси-сервера из пула ротации.
     */
    public function destroy(ProxyServer $proxy): JsonResponse
    {
        $proxy->delete();

        return response()->json([
            'message' => 'Прокси-сервер успешно удален из пула.',
        ]);
    }

    /**
     * @return array{protocol: string, host: string, port: int, username: string|null, password: string|null}|null
     */
    private function parseProxyString(string $input): ?array
    {
        return ProxyStringParser::parse($input);
    }
}
