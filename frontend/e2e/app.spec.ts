import { test, expect } from '@playwright/test'

test.describe('GeoReviews E2E User Flows', () => {
  test('redirects unauthenticated user to /login and displays quick login button', async ({ page }) => {
    await page.goto('/')

    await expect(page).toHaveURL(/.*\/login/)
    await expect(page.locator('h1')).toContainText('GeoReviews')
    await expect(page.locator('h2')).toContainText('Авторизация')

    const autofillBtn = page.locator('button:has-text("admin@georeviews.local / password")')
    await expect(autofillBtn).toBeVisible()

    await autofillBtn.click()

    const emailInput = page.locator('input[type="email"]')
    const passwordInput = page.locator('input[type="password"]')

    await expect(emailInput).toHaveValue('admin@georeviews.local')
    await expect(passwordInput).toHaveValue('password')
  })

  test('successful login navigates to /organizations dashboard', async ({ page }) => {
    // Mock authentication and organizations API
    await page.route('**/api/auth/login', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          token: 'mock_token_12345',
          user: {
            id: 1,
            name: 'Администратор Тест',
            email: 'admin@georeviews.local',
            is_admin: true,
          },
        }),
      })
    })

    await page.route('**/api/organizations', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [
            {
              id: 1,
              yandex_org_id: '67037665858',
              name: 'Додо Пицца Полянка',
              url: 'https://yandex.ru/maps/org/dodo_pizza/67037665858/reviews/',
              address: 'ул. Большая Полянка, 30',
              rating: 4.9,
              ratings_count: 1250,
              reviews_count: 520,
              sync_status: 'completed',
              sync_progress: 100,
              last_synced_at: '2026-09-14T08:00:00Z',
              last_sync_error: null,
            },
          ],
        }),
      })
    })

    await page.goto('/login')
    await page.click('button:has-text("admin@georeviews.local / password")')
    await page.click('button:has-text("Войти в систему")')

    await expect(page).toHaveURL(/.*\/organizations/)
    await expect(page.locator('text=Додо Пицца Полянка')).toBeVisible()
    await expect(page.locator('text=Администратор Тест')).toBeVisible()
    await expect(page.locator('a:has-text("Админ-панель")')).toBeVisible()
  })

  test('admin can access /admin and view proxy management & metrics', async ({ page }) => {
    // Set authenticated admin session in localStorage
    await page.addInitScript(() => {
      localStorage.setItem('auth_token', 'mock_admin_token')
      localStorage.setItem(
        'auth_user',
        JSON.stringify({
          id: 1,
          name: 'Администратор',
          email: 'admin@georeviews.local',
          is_admin: true,
        })
      )
    })

    await page.route('**/api/admin/settings', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          environment: {
            php_version: '8.2.20',
            laravel_version: '12.1.0',
            server_time: '2026-09-14T09:00:00Z',
            queue_driver: 'database',
          },
          database_metrics: {
            organizations_count: 3,
            reviews_count: 450,
            snapshots_count: 8,
          },
          proxy_pool_metrics: {
            total_proxies: 2,
            available_for_use: 2,
            active_proxies: 2,
            cooling_down_proxies: 0,
            avg_latency_ms: 320,
          },
          queue_metrics: {
            pending_jobs: 0,
            failed_jobs: 0,
          },
        }),
      })
    })

    await page.route('**/api/admin/proxies', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [
            {
              id: 1,
              protocol: 'http',
              host: '185.123.45.67',
              port: 8080,
              username: 'user1',
              is_active: true,
              cooldown_until: null,
              is_cooling_down: false,
              fails_count: 0,
              success_count: 42,
              last_used_at: '2026-09-14T08:55:00Z',
              last_error: null,
              avg_response_time_ms: 280,
              masked_endpoint: 'http://user1:***@185.123.45.67:8080',
              created_at: '2026-09-14T00:00:00Z',
            },
          ],
        }),
      })
    })

    await page.goto('/admin')

    await expect(page.locator('h2')).toContainText('Административная панель')
    await expect(page.locator('text=185.123.45.67')).toBeVisible()
    await expect(page.locator('button:has-text("Добавить прокси")')).toBeVisible()

    // Test opening Add Proxy modal
    await page.click('button:has-text("Добавить прокси")')
    await expect(page.locator('textarea')).toBeVisible()
  })

  test('organization detail page shows reviews, export button and trend chart', async ({ page }) => {
    await page.addInitScript(() => {
      localStorage.setItem('auth_token', 'mock_admin_token')
      localStorage.setItem(
        'auth_user',
        JSON.stringify({
          id: 1,
          name: 'Администратор',
          email: 'admin@georeviews.local',
          is_admin: true,
        })
      )
    })

    await page.route('**/api/organizations/1', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: {
            id: 1,
            yandex_org_id: '67037665858',
            name: 'Додо Пицца',
            url: 'https://yandex.ru/maps/org/dodo_pizza/67037665858/reviews/',
            address: 'ул. Большая Полянка, 30',
            rating: 4.8,
            ratings_count: 1200,
            reviews_count: 500,
            sync_status: 'completed',
            sync_progress: 100,
            last_synced_at: '2026-09-14T08:00:00Z',
          },
        }),
      })
    })

    await page.route('**/api/organizations/1/reviews*', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [
            {
              id: 10,
              yandex_review_id: 'rev_test_1',
              author_name: 'Елена Васильева',
              author_avatar_url: null,
              author_level: 'Знаток города 5 уровня',
              rating: 5,
              text: 'Быстрая доставка, пицца горячая и очень вкусная!',
              published_at: '2026-09-14T07:30:00Z',
              business_response_text: 'Елена, спасибо за теплые слова!',
              business_response_at: '2026-09-14T07:45:00Z',
            },
          ],
          meta: {
            current_page: 1,
            last_page: 1,
            per_page: 50,
            total: 1,
          },
          organization: {
            id: 1,
            name: 'Додо Пицца',
          },
        }),
      })
    })

    await page.route('**/api/organizations/1/snapshots', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [
            {
              id: 1,
              organization_id: 1,
              rating_before: 4.5,
              rating_after: 4.8,
              ratings_count_before: 1000,
              ratings_count_after: 1200,
              reviews_count_before: 400,
              reviews_count_after: 500,
              new_reviews_added: 100,
              updated_reviews_count: 0,
              snapshot_at: '2026-09-14T08:00:00Z',
              created_at: '2026-09-14T08:00:00Z',
            },
          ],
        }),
      })
    })

    await page.goto('/organizations/1')

    await expect(page.locator('text=Додо Пицца')).toBeVisible()
    await expect(page.locator('text=Елена Васильева')).toBeVisible()

    // Verify Export to CSV button is visible
    const exportBtn = page.locator('button:has-text("Экспорт в CSV")')
    await expect(exportBtn).toBeVisible()

    // Switch to Snapshots & Analytics Tab
    const snapshotsTab = page.locator('button:has-text("История изменений")')
    await snapshotsTab.click()

    // Verify ReputationTrendChart rendered
    await expect(page.locator('text=Тренд рейтинга')).toBeVisible()
    await expect(page.locator('text=+100').first()).toBeVisible()
    await expect(page.locator('svg').first()).toBeVisible()
  })
})
