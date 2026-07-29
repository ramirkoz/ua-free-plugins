# UA FREE Site Bridge 0.4.5

**Статус:** кандидат на незалежне review  
**Режим:** read-only  
**Автоматичні зміни сайту:** відсутні

## Що робить

- показує стан WordPress, PHP, бази, теми й плагінів;
- показує публічні сторінки, меню та внутрішні посилання;
- читає тільки privacy-safe журнал 404 Guard;
- перевіряє `robots.txt`, sitemap, `llms.txt`, AI manifest і статус 404;
- перевіряє лише дозволені публічні шляхи сайту як звичайний браузер, Googlebot або AdsBot;
- показує redirect chain та вибрані заголовки Cloudflare/сервера.

## Що прибрано порівняно з 0.3

- постійний журнал API;
- збереження User-Agent;
- endpoint `/api-log`;
- повний текст сторінок;
- шляхи файлів плагінів;
- назви cron hooks;
- довільні URL для HTTP-перевірок.

## Підключення

1. `UA FREE → Site Bridge`.
2. Створити або перевипустити API-ключ.
3. Імпортувати OpenAPI URL у приватний GPT Action.
4. Authentication: API key, custom header `X-UAFree-Key`.
5. Перевірити `pingSiteBridge`.

API-ключ не потрібно й не можна надсилати в чат.

## Обмеження 0.4.5

- HTTP probe не приймає query string або довільні шляхи.
- WordPress operational endpoints заблоковані.
- Rate limit використовує атомарний MySQL advisory lock.

## Захист 0.4.5

- кожен redirect повторно перевіряється за тим самим allowlist;
- `/links` не виконує запити до шляхів поза allowlist;
- після 120 запитів лічильник більше не пише у базу;
- лічильники мають реальне автоматичне завершення;
- додані focused tests для трьох знайдених blocker-сценаріїв.

## Пакування 0.4.5

- production ZIP не містить `tests/`;
- executable test harness зберігається окремо;
- збірка автоматично завершується FAIL, якщо тестовий PHP потрапляє в deployable archive.

## OpenAPI 0.4.5

- виправлено помилку `object schema missing properties`;
- компонент `BridgeResponse` тепер має явні optional properties;
- endpoint-и, ключі та функціонал не змінені.
