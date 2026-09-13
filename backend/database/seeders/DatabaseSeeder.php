<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\OrganizationSnapshot;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Seed user for authentication
        $user = User::firstOrCreate(
            ['email' => 'admin@georeviews.local'],
            [
                'name' => 'Администратор',
                'password' => Hash::make('password'),
            ]
        );

        // 2. Seed a demonstration organization
        $org = Organization::firstOrCreate(
            ['yandex_org_id' => '67037665858'],
            [
                'name' => 'Додо Пицца',
                'url' => 'https://yandex.ru/maps/org/dodo_pizza/67037665858/reviews/',
                'address' => 'Москва, улица Большая Полянка, 30',
                'rating' => 4.90,
                'ratings_count' => 2774,
                'reviews_count' => 1990,
                'sync_status' => 'completed',
                'sync_progress' => 100,
                'last_synced_at' => now(),
            ]
        );

        // 3. Seed initial snapshots
        OrganizationSnapshot::firstOrCreate(
            ['organization_id' => $org->id],
            [
                'rating_before' => 4.85,
                'rating_after' => 4.90,
                'ratings_count_before' => 2650,
                'ratings_count_after' => 2774,
                'reviews_count_before' => 1900,
                'reviews_count_after' => 1990,
                'new_reviews_added' => 90,
                'updated_reviews_count' => 10,
                'snapshot_at' => now()->subDay(),
            ]
        );

        // 4. Seed sample reviews if empty
        if ($org->reviews()->count() === 0) {
            $sampleReviews = [
                [
                    'author_name' => 'Александр Смирнов',
                    'author_level' => 'Знаток города 8 уровня',
                    'rating' => 5,
                    'text' => 'Отличная пиццерия! Всегда чисто, уютно, быстро готовят. Додстеры горячие и с хрустящей корочкой. Персонал очень вежливый и приветливый.',
                    'published_at' => now()->subHours(3),
                    'business_response_text' => 'Александр, спасибо большое за высокую оценку и теплые слова! Всегда рады видеть вас!',
                    'business_response_at' => now()->subHours(2),
                ],
                [
                    'author_name' => 'Елена В.',
                    'author_level' => 'Знаток города 4 уровня',
                    'rating' => 5,
                    'text' => 'Заказывали доставку в офис. Привезли за 25 минут, пицца горячая, сыр тянется. Морс из черной смородины натуральный и вкусный.',
                    'published_at' => now()->subDays(1),
                    'business_response_text' => 'Елена, спасибо за отзыв! Очень рады, что обед в офисе удался на славу :)',
                    'business_response_at' => now()->subDays(1)->addHours(1),
                ],
                [
                    'author_name' => 'Михаил К.',
                    'author_level' => 'Знаток города 12 уровня',
                    'rating' => 4,
                    'text' => 'Хорошее место у метро Полянка. Бывает людно в обеденное время, но столики освобождаются быстро. Кофе приличный.',
                    'published_at' => now()->subDays(2),
                    'business_response_text' => null,
                    'business_response_at' => null,
                ],
                [
                    'author_name' => 'Ольга Николаева',
                    'author_level' => 'Знаток города 6 уровня',
                    'rating' => 5,
                    'text' => 'Любимая пицца Пепперони Фреш! Регулярно заходим сюда с детьми по выходным. Детям очень нравится игровая зона.',
                    'published_at' => now()->subDays(3),
                    'business_response_text' => 'Ольга, спасибо за доверие всей семьей! Будем ждать вас снова в гости!',
                    'business_response_at' => now()->subDays(3)->addHours(2),
                ],
                [
                    'author_name' => 'Дмитрий',
                    'author_level' => 'Знаток города 3 уровня',
                    'rating' => 5,
                    'text' => 'Удобное приложение, отслеживание приготовления по камере на кухне — классная фишка. Заказ выдали ровно за 10 минут.',
                    'published_at' => now()->subDays(4),
                    'business_response_text' => null,
                    'business_response_at' => null,
                ],
            ];

            foreach ($sampleReviews as $i => $rev) {
                Review::create(array_merge($rev, [
                    'organization_id' => $org->id,
                    'yandex_review_id' => 'sample_seed_rev_'.($i + 1),
                ]));
            }
        }
    }
}
