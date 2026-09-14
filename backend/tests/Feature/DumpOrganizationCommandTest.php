<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DumpOrganizationCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dumps_organization_data_to_json_file(): void
    {
        $org = Organization::create([
            'name' => 'Додо Пицца Тест',
            'url' => 'https://yandex.ru/maps/org/dodo/555/reviews/',
            'yandex_org_id' => '555',
            'sync_status' => 'synced',
            'rating' => 4.9,
            'reviews_count' => 1,
        ]);

        Review::create([
            'organization_id' => $org->id,
            'yandex_review_id' => 'rev-test-1',
            'author_name' => 'Алексей Смирнов',
            'rating' => 5,
            'text' => 'Отличная пицца и быстрая доставка!',
            'published_at' => now(),
        ]);

        $tempFile = str_replace('\\', '/', storage_path('framework/testing/test_org_dump_'.uniqid().'.json'));

        $this->artisan("reviews:dump {$org->id} --output={$tempFile}")
            ->expectsOutputToContain('Дамп успешно создан')
            ->assertSuccessful();

        $this->assertFileExists($tempFile);
        $content = File::get($tempFile);
        $data = json_decode($content, true);

        $this->assertIsArray($data);
        $this->assertEquals('1.0', $data['dump_version']);
        $this->assertEquals('Додо Пицца Тест', $data['organization']['name']);
        $this->assertEquals(1, $data['reviews_count']);
        $this->assertEquals('Алексей Смирнов', $data['reviews'][0]['author_name']);

        // Clean up
        if (File::exists($tempFile)) {
            File::delete($tempFile);
        }
    }

    public function test_fails_when_organization_not_found(): void
    {
        $this->artisan('reviews:dump 999999')
            ->expectsOutputToContain('Организация с ID 999999 не найдена.')
            ->assertFailed();
    }
}
