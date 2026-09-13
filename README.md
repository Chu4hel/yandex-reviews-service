# Сервис интеграции с Яндекс.Картами (GeoReviews)

Упрощённая модель реального сервиса для работы с отзывами и данными бизнеса на картах (Яндекс.Карты, 2ГИС). Проект подготовлен как решение тестового задания.

Стек проекта:
- **Backend / API**: Laravel 12 (PHP 8.2+), REST API, SQLite / MySQL, Eloquent, Queues.
- **Frontend / SPA**: Vue 3 (Composition API, `<script setup>`), TypeScript (строгая типизация без `any`), Pinia, Vue Router, Tailwind CSS v4, Lucide Icons.

---

## Архитектура и принципы проектирования

Проект спроектирован по принципам чистой архитектуры и **Dependency Inversion Principle (DIP)**:
1. **Domain (Домен)** — бизнес-логика (модели организаций, отзывов, парсинга, метрик рейтинга) не зависит от внешних фреймворков и способов доставки (HTTP, CLI).
2. **Services / Use Cases (Службы)** — сценарии взаимодействия: парсинг карточки Яндекс.Карт, синхронизация отзывов, расчет аналитики и среднего рейтинга.
3. **Infrastructure (Инфраструктура)** — клиенты HTTP-парсинга, доступ к БД, внешние адаптеры.
4. **API / Controllers (Презентация Backend)** — валидация входных данных (Form Requests), возврат стандартизированных JSON Resources.
5. **Frontend SPA** — модульный интерфейс на Vue 3, типизированные API-сервисы (Axios), управление состоянием (Pinia).

---

## Структура репозитория

```text
yandex-reviews-service/
├── backend/                  # Laravel 12 REST API
│   ├── app/
│   │   ├── Http/Controllers/ # REST API Контроллеры
│   │   ├── Models/           # Eloquent модели
│   │   └── Services/         # Сервисы бизнес-логики и парсинга
│   ├── config/               # Конфигурация (CORS, Sanctum, DB)
│   ├── database/             # Миграции, сидеры, database.sqlite
│   ├── routes/
│   │   └── api.php           # API эндпоинты (/api/health, /api/organizations...)
│   └── tests/                # Feature и Unit тесты (PHPUnit)
├── frontend/                 # Vue 3 Single Page Application
│   ├── src/
│   │   ├── components/       # Переиспользуемые Vue-компоненты
│   │   ├── views/            # Страницы приложения
│   │   ├── router/           # Маршрутизация (Vue Router)
│   │   ├── stores/           # Pinia хранилища состояния
│   │   ├── services/         # HTTP-клиенты к API (Axios)
│   │   ├── types/            # TypeScript интерфейсы и типы (без any)
│   │   └── style.css         # Стилизация Tailwind CSS v4
│   └── vite.config.ts        # Конфигурация Vite с proxy на Laravel API
├── package.json              # Скрипты оркестрации разработки (concurrently)
└── README.md
```

---

## Системные требования

- **PHP**: >= 8.2 с расширениями `pdo_sqlite` (или `pdo_mysql`), `curl`, `mbstring`, `dom`
- **Composer**: >= 2.5
- **Node.js**: >= 18.x
- **npm**: >= 9.x

---

## Быстрый старт и запуск окружения

### 1. Установка всех зависимостей

В корне проекта:
```bash
# Установка dev-зависимостей корня (concurrently)
npm install

# Установка зависимостей бэкенда и фронтенда одной командой
npm run setup
```

Или по отдельности:
```bash
# Бэкенд
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate

# Фронтенд
cd ../frontend
npm install
```

---

### 2. Запуск проекта в режиме разработки

Для одновременного запуска бэкенда (`php artisan serve`) и фронтенда (`vite`) из корня проекта:
```bash
npm run dev
```

Приложение будет доступно по адресам:
- **Frontend SPA**: [http://localhost:5173](http://localhost:5173)
- **Backend API**: [http://127.0.0.1:8000/api](http://127.0.0.1:8000/api)
- **Health Check**: [http://127.0.0.1:8000/api/health](http://127.0.0.1:8000/api/health)

---

### 3. Запуск тестов и проверка сборки

**Тесты бэкенда**:
```bash
cd backend
php artisan test
```

**Сборка фронтенда и проверка типов**:
```bash
cd frontend
npm run build
```
