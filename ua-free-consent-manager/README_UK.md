# UA FREE Consent Manager 0.1.2

## Призначення

Плагін керує згодою відвідувача та не дозволяє зареєстрованим необов’язковим WordPress-скриптам виконуватися до відповідної згоди. Зареєстровані handles завжди вилучаються із server-rendered HTML і завантажуються клієнтським loader лише після перевірки cookie, тому full-page cache не може випадково віддати consented scripts іншому відвідувачу.

## Категорії

- `necessary` — завжди активна;
- `analytics` — вимкнена за замовчуванням;
- `advertising` — вимкнена за замовчуванням;
- `external_media` — вимкнена за замовчуванням.

## Публічні функції

```php
uafree_consent_manager_get_status(): array;
uafree_consent_manager_is_allowed( string $category ): bool;
uafree_consent_manager_register_integration( array $integration ): bool;
uafree_consent_manager_get_integrations(): array;
```

Дозволені поля integration:

- `id`;
- `name`;
- `category`;
- `script_handles`.

API навмисно не приймає URL скрипта, PHP callback або довільний JavaScript.

## Зберігання

Cookie `uafree_consent` містить лише:

- `schema_version`;
- consent booleans;
- `policy_version`;
- `updated_at`.

IP, User-Agent, email, WordPress user ID та persistent consent log не зберігаються.

## Обмеження кандидата

- WordPress runtime ще не перевірений;
- multisite не перевірений;
- GA4, Google Ads, Consent Mode v2 та зовнішні media adapters не входять у 0.1.2;
- відкликання згоди не видаляє сторонні cookies, але блокує майбутнє завантаження зареєстрованих optional scripts після reload.

## Виправлення 0.1.2

- банер ініціалізується після появи DOM;
- для нового відвідувача банер видимий уже на серверному HTML;
- cookie, категорії та API інтеграцій не змінені.
