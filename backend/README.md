# Бэкенд-сервис интеграции с Яндекс.Картами (GeoReviews Backend)

REST API сервис на базе **Laravel 12** и **PHP 8.2+**, реализующий отказоустойчивый сбор метаданных, рейтингов и отзывов организаций с публичных страниц Яндекс Карт, ведение истории снимков репутации (снапшотов), динамический пул ротации прокси-серверов и административный контур мониторинга.

---

## 🏛 Архитектура и принципы проектирования

Проект спроектирован по принципам чистой архитектуры с соблюдением **Dependency Inversion Principle (DIP)**:
- **`app/Domain`**: Ядро бизнес-логики, не зависящее от фреймворка и внешних сервисов:
  - `Contracts/`: Интерфейсы парсера (`YandexParserInterface`) и пула ротации (`ProxyRotatorInterface`).
  - `DTO/`: Неизменяемые типизированные объекты передачи данных (`ParsedOrganizationDto`, `ParsedReviewDto`, `ParsedReviewsBatchDto`, `ProxyDto`, `SyncResultDto`).
  - `Exceptions/`: Иерархия доменных исключений (`YandexCaptchaDetectedException`, `YandexMarkupChangedException`, `YandexOrganizationNotFoundException`, `YandexParserException`).
- **`app/Infrastructure`**: Реализация низкоуровневых сервисов и внешних интеграций:
  - `Services/YandexMapsParserService.php`: Парсер HTML-разметки Яндекс Карт (детекция SmartCaptcha/WAF, извлечение JSON из `state-view`, разбор отзывов, следование по редиректам коротких ссылок).
  - `Services/DatabaseProxyRotator.php`: Динамическая ротация прокси по политике LRU (наименее недавно использованный), учет задержки (EMA), автоматический перевод в 30-минутный карантин при детекции капчи и отключение после 15 ошибок подряд.
- **`app/Services`**: Прикладной оркестратор:
  - `OrganizationSyncService.php`: Идемпотентное сохранение организаций и отзывов, управление циклом синхронизации, фиксация снимков изменений.
- **`app/Jobs`**: Асинхронные очереди:
  - `SyncOrganizationReviewsJob.php`: Фоновая задача синхронизации до ~600 отзывов (12 страниц по 50 отзывов) с экспоненциальным повтором (`backoff = [10, 30, 60]`).
- **`app/Support`**: Утилитарные сервисы:
  - `ContentSanitizer.php`: Очистка XSS-векторов, удаление опасных HTML-тегов, фильтрация управляющих символов и валидация безопасных схем URL.
  - `ProxyStringParser.php`: Универсальный парсер адресов прокси (`ip:port`, `ip:port:login:password`, `ip:port@login:password`, `user:password@ip:port`, `socks5://...`).

---

## 🛡 Безопасность и надежность (SRE / Security)

1. **Контроль доступа к API администратора (`EnsureAdminAccess`)**:
   - Авторизация через сессию Laravel Sanctum с флагом `is_admin: true`.
   - Поддержка сервисных API-ключей в заголовках `X-Admin-Key` / `X-API-Key` с защитой от атак по времени через `hash_equals()`.
2. **Сквозная трассировка (`AssignRequestId`)**:
   - Каждому входящему запросу присваивается уникальный `X-Request-ID` (Correlation ID), прокидываемый в контекст логов и возвращаемый в ответах.
3. **Healthcheck & Readiness Probes (`HealthController`)**:
   - `GET /api/health` — быстрая liveness-проверка сервиса.
   - `GET /api/health/ready` (или `?deep=1`) — глубокая диагностика готовности подсистем (задержка БД в миллисекундах, объем очереди задач, доступное дисковое пространство, статус пула прокси).
4. **Защита от перегрузок (Rate Limiting)**:
   - Лимит авторизации: до 5 попыток в минуту.
   - Лимит запуска синхронизаций: до 10 запросов в минуту.

---

## 💻 Консольные команды (Artisan CLI)

Сервис предоставляет набор консольных утилит для DevOps, администрирования и отладки:

```bash
# Добавление прокси в пул ротации (поддерживаются форматы с аутентификацией)
php artisan proxy:add http://user:pass@192.168.1.1:8080
php artisan proxy:add 192.168.1.1:8080:user:pass
php artisan proxy:add 192.168.1.1:8080@user:pass
php artisan proxy:add socks5://user:pass@185.123.45.67:1080

# Просмотр текущего состояния пула прокси (статусы, карантин, статистика)
php artisan proxy:list

# Выгрузка полного изолированного JSON-слепка организации (с отзывами и снимками)
php artisan reviews:dump 1 --output=storage/app/dumps/org_1.json --limit-reviews=100

# Автоматическая ротация устаревших снимков репутации (поддержка dry-run)
php artisan reviews:prune-snapshots --days=90 --dry-run
php artisan reviews:prune-snapshots --days=90
```

---

## 📡 Основные маршруты REST API

| Метод | Эндпоинт | Доступ | Описание |
|---|---|---|---|
| `GET` | `/api/health` | Публичный | Быстрая liveness-проверка сервиса |
| `GET` | `/api/health/ready` | Публичный | Глубокая readiness-диагностика подсистем |
| `POST` | `/api/auth/login` | Публичный | Вход в систему (выпуск Bearer токена) |
| `GET` | `/api/auth/user` | Sanctum | Данные текущего пользователя и права |
| `POST` | `/api/auth/logout` | Sanctum | Выход из системы (отзыв токенов) |
| `GET` | `/api/organizations` | Sanctum | Список всех подключенных организаций |
| `POST` | `/api/organizations` | Sanctum | Подключение карточки по ссылке или ID |
| `GET` | `/api/organizations/{id}` | Sanctum | Детальная информация об организации |
| `GET` | `/api/organizations/{id}/status` | Sanctum | Статус и процент фоновой синхронизации |
| `POST` | `/api/organizations/{id}/sync` | Sanctum | Запуск повторной синхронизации |
| `GET` | `/api/organizations/{id}/reviews` | Sanctum | Список отзывов (по 50 на стр., фильтры, сортировка) |
| `GET` | `/api/organizations/{id}/snapshots` | Sanctum | История снимков рейтинга и притока отзывов |
| `GET` | `/api/organizations/{id}/export` | Sanctum | Экспорт отзывов в CSV (Excel UTF-8 BOM) |
| `GET` | `/api/admin/settings` | Admin / API Key | Системные метрики (БД, очереди, прокси) |
| `GET` | `/api/admin/proxies` | Admin / API Key | Список прокси-серверов в пуле |
| `POST` | `/api/admin/proxies` | Admin / API Key | Пакетное добавление прокси |
| `POST` | `/api/admin/proxies/{id}/toggle` | Admin / API Key | Включение / отключение прокси |
| `DELETE` | `/api/admin/proxies/{id}` | Admin / API Key | Удаление прокси из пула |

---

## 🛠 Запуск и разработка

### Локальная установка:

```bash
# 1. Установка PHP зависимостей
composer install

# 2. Настройка переменных окружения
cp .env.example .env
php artisan key:generate

# 3. Применение миграций и запуск сидов (создает демо-данные и учетную запись)
touch database/database.sqlite
php artisan migrate --seed

# 4. Запуск локального сервера разработки
php artisan serve
```

### Запуск фонового воркера очередей:

```bash
php artisan queue:work --tries=3 --timeout=120
```

---

## 🧪 Контроль качества и тестирование

В проекте настроен строгий автоматический контроль качества:

```bash
# Статический анализ максимального уровня строгости (PHPStan Level 8)
composer phpstan

# Проверка и форматирование кода по стандарту PSR-12 (Laravel Pint)
composer format:test
composer format

# Запуск полного набора юнит- и функциональных тестов (PHPUnit)
php artisan test
```

> **Статус проверок:**
> - PHPStan: Level 8 — **0 ошибок** на 44 файлах.
> - PHPUnit: **72 теста пройдены** (443 assertions).
