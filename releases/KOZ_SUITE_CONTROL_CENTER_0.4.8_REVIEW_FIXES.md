# KOZ Suite Control Center 0.4.8: виправлення за рев'ю WordPress.org

- **Плагін:** `koz-suite-control-center` (автор ramirkz, Tony Kozyriev).
- **Попередня версія:** 0.4.7. Код на uafree.org збігався з GitHub `ramirkoz/ua-free-plugins` (main) байт у байт, відрізнявся лише readme.txt.
- **ZIP для рев'ю:** `koz-suite-control-center-0.4.8-wordpress-review.zip`.
- **Резервна копія 0.4.7:** `koz-suite-control-center-0.4.7-backup.zip` (код із GitHub, ідентичний встановленому).
- **Дата:** 26.11.2026.

Slug, архітектура, меню, каталог плагінів, експорт JSON і сканування не змінювалися. Нових функцій немає.

## 1. Зауваження WordPress.org і виправлення

### 1.1 Changing Active Plugins

**Було.** `KOZSUITECC_Dashboard::activate()` викликав `deactivate_legacy_plugin()`. Той через `deactivate_plugins()` вимикав `ua-free-analytics-dashboard` і `ua-free-suite-control-center`.

**Стало:**
- метод `deactivate_legacy_plugin()` видалено;
- `register_activation_hook` більше не реєструється, бо активації нічого робити;
- у плагіні немає жодного виклику `deactivate_plugins()`, `activate_plugin()` чи змін опції `active_plugins`.

Якщо старий пакет UA FREE досі активний, Control Center лише показує це в блоці «Потребує уваги» («A legacy package is still active. Finish the migration before removing it.»). Рішення лишається за адміністратором.

**Файли:** `includes/class-kozsuitecc-dashboard.php`, `koz-suite-control-center.php`.

### 1.2 Interference with admin_footer

**Було.** На рівні файлу виконувалося `remove_action( 'admin_footer', … )` для `UAFree_Admin_Support_Panel_V2::render` і `UAFree_Admin_Support_Footer::render`.

**Стало:**
- обидва виклики видалено;
- Control Center лише додає власний `admin_footer`-обробник;
- обробник рендерить панель тільки для `manage_options` і тільки на екранах `kozsuitecc-suite`, `kozsuitecc-control-center` і `koz-suite-control-center`;
- повторний рендер блокує `$rendered`;
- якщо інший модуль KOZ надає спільну панель (`KOZ_Admin_Support_Panel_V1`), власна панель не виводиться, щоб не було дублікатів.

**Файл:** `includes/class-kozsuitecc-admin-support-panel.php`.

## 2. Legacy telemetry (додатковий аудит)

| Що | Власник | Рішення |
|---|---|---|
| `uafree_suite_support_settings`, `uafree_suite_install_id`, `uafree_suite_heartbeat_token`, `uafree_suite_heartbeat_token_expiry` | старі пакети `ua-free-analytics-dashboard` і `ua-free-suite-control-center` (вони ж видаляють їх у своєму `uninstall.php`) | Control Center більше їх не видаляє |
| CRON `uafree_suite_daily_heartbeat` | ті самі старі пакети | Control Center більше не знімає подію ні під час активації, ні під час деактивації |
| `kozsuitecc_migration_version`, `kozsuitecontrolcenter_migration_version` | Control Center | більше не записуються; лише прибираються в `uninstall.php` разом із плагіном |

**Видалено:**
- `remove_legacy_telemetry()` і її виклик на кожному `admin_init`;
- `unschedule_legacy_heartbeat()`;
- `deactivate()` і `register_deactivation_hook`.

Своїх даних, які треба мігрувати, у плагіна немає: він не зберігає налаштувань.

**Файли:** `includes/class-kozsuitecc-dashboard.php`, `koz-suite-control-center.php`.

## 3. Панель підтримки

- **PayPal** прибрано: блок, посилання, адресу й текст «PayPal and crypto donations…». Тепер текст такий: «Crypto donations support maintenance, testing and new free releases.»
- **Криптоадреси** звірено з KOZ Site Bridge 0.6.0:
  - BTC: `bc1q4dn8e7sz2866g7qplqtshh98j54tvuau5ghuuk`. Було `…qp1qt…`, а цифра «1» у bech32 після префікса недопустима, тож адреса була некоректна;
  - ETH / USDC: `0x3aE3b23A7BD94b8a65A7E8Ca205A4e29BEF7c229`, без змін;
  - USDT TRC-20: `TYSgyk7K3XB4WPHprf5w8ZodFAfxFfDdbF`. Було `TYsGyK7K3XB4NPHprf5w8ZodFafxFfDdbP`.
- **Окремі напрями** підтримки розробника і фонду UA FREE збережено.
- **Переклади.** Тексти панелі додано в усі 10 мовних словників (`assets/i18n/*.json`), переклади взято з Site Bridge 0.6.0. Раніше панель показувалася лише англійською.
- **JS** тепер бере підписи «Copy», «Copied» і «Address copied.» із локалізованого об'єкта, а не з жорстко прописаного англійського тексту.
- **Версія ресурсів** панелі тепер `KOZSUITECC_VERSION` замість `1.0.0`, щоб браузер не тримав старий JS.
- **Перевірка залишків.** У PHP, JS, CSS і JSON не лишилося PayPal і старих адрес (перевірено grep).

**Файли:**
- `includes/class-kozsuitecc-admin-support-panel.php`;
- `assets/kozsuitecc-admin-support-panel.js`;
- `assets/i18n/*.json`.

## 4. Аудит безпеки та відповідності

- **Активація і деактивація інших плагінів:** немає.
- **Сторонні хуки:** `remove_action` і `remove_filter` відсутні. `remove_submenu_page` прибирає лише власну сторінку-сумісність `koz-suite-control-center`.
- **Nonce і права доступу:**
  - експорт JSON: `manage_options` + `check_admin_referer`;
  - сканування: `manage_options` (екран) + `wp_verify_nonce`;
  - вибір періоду: `wp_verify_nonce`.
- **Escaping:** увесь вивід екранується. Додатково виправлено неекранований клас статусу (`esc_attr`).
- **Sanitization:** `$_GET` обробляються через `wp_unslash`, `sanitize_text_field` і `absint`, період обмежено білим списком 7/30/90.
- **Публічне сканування:** `wp_safe_remote_get` лише до сторінок цього сайту, без форм. Секретні значення не виводяться.
- **Опції, CRON, таблиці:** плагін нічого не планує і не створює. `uninstall.php` видаляє лише власні опції.

Інших випадків втручання у сторонні плагіни не знайдено.

## 5. Версія і документація

- **`koz-suite-control-center.php`:** `Version: 0.4.8`, `KOZSUITECC_VERSION` = 0.4.8.
- **`readme.txt`:** Stable tag 0.4.8, запис 0.4.8 у Changelog, новий розділ Upgrade Notice.
  - Розділ «Part of the KOZ Suite» і всі 9 посилань на опубліковані плагіни збережено.
  - Описи функцій не змінено.
- **`CHANGELOG.md`:** запис 0.4.8. Заголовок файлу перенесено нагору, бо 0.4.7 стояв над ним.

## 6. Plugin Check

На uafree.org для 0.4.8, з усіма категоріями (general, plugin_repo, security, performance, accessibility) і без ШІ-аналізу: **«Перевірки завершено. Помилок не знайдено».**

## 7. Перевірка на uafree.org (26.11.2026)

**Перед оновленням зафіксовано:**
- 24 плагіни, усі активні;
- старих пакетів UA FREE на сайті немає;
- опції: `kozsuitecc_migration_version`, `uafree_suite_hub_db_version`, `uafree_suite_hub_packages`, `uafree_suite_hub_page_id`, `uafree_suite_hub_settings`.

| # | Перевірка | Результат |
|---|---|---|
| 1 | Оновлення 0.4.7 → 0.4.8 через «Замінити поточну версію завантаженою» | «Плагін успішно оновлений», фатальних помилок немає |
| 2 | Головне меню KOZ Suite (`kozsuitecc-suite`) | 200, «12 / 12 плагінів активно», 12 карток |
| 3 | Control Center (`kozsuitecc-control-center`) | 200, «Усе активно», 12 / 12, неактивних 0, відсутніх 0 |
| 4 | Визначення встановлених і активних KOZ-плагінів | 12 / 12, версії відповідають списку плагінів |
| 5 | Кнопки «Відкрити» в 12 модулях | Усі 12 сторінок відкриваються без помилки доступу чи PHP-повідомлень |
| 6 | Експорт JSON | 200, файл `koz-suite-control-center-30-days.json`, версія 0.4.8, 12/12, `external_requests: false` |
| 7 | Сканування публічних сторінок | Перевірено 3 сторінки, знайдено 0 |
| 8 | Панель підтримки | На сайті активний KOZ Suite Hub зі спільною панеллю, тож Control Center свою не виводить: на екрані одна панель, без PayPal, BTC новий |
| 9 | Стан плагінів після оновлення | 24 / 24 активні, як і до оновлення |
| 10 | Опції | Той самий набір, нічого не видалено |
| 11 | `debug.log` | Нових записів немає, остання подія 2024 року і не стосується KOZ. На сайті `WP_DEBUG` вимкнено, `WP_DEBUG_LOG` увімкнено |
| 12 | Plugin Check | Помилок не знайдено |

## 8. Чиста інсталяція з WP_DEBUG=true

**Не виконувалася.** Тестових сайтів WordPress, окрім uafree.org, немає, а локальні сервери на комп'ютері розробника за домовленістю не запускаються. На uafree.org `WP_DEBUG` вимкнено, і для тесту його не вмикали, щоб не змінювати робочий сайт фонду.

Що це означає: notice і deprecated-повідомлення PHP 8.4 на uafree.org не відображаються і не пишуться в лог. Фатальних помилок немає.

## 9. Обмеження та невирішене

- **Окрему панель Control Center не видно на uafree.org**, бо її заміняє спільна панель KOZ Suite Hub. Вигляд без Hub перевірено лише за кодом. Hub не вимикали, щоб не змінювати сайт.
- **Сценарій зі старими пакетами UA FREE на сайті не тестувався**, бо їх там немає. За кодом Control Center їх не чіпає, а лише показує в «Потребує уваги».
- **Чужі footer-панелі** `UAFree_Admin_Support_Panel_V2` і `UAFree_Admin_Support_Footer` на сайті відсутні. Тепер вони рендерилися б поряд із панеллю Control Center, бо Control Center їх більше не прибирає. Для рев'ю це прийнятно.
- **Стара пряма адреса** `admin.php?page=koz-suite-control-center` дає 403 «немає доступу». Так було і в 0.4.7, бо код меню не змінювався. Причина: сторінка реєструється і одразу прибирається з меню, після чого WordPress її не пускає. Для рев'ю це не зауваження, тож не виправлялося.
- **`/wp-content/debug.log` на uafree.org доступний публічно** (HTTP 200). Це не стосується плагіна, але варто закрити доступ на рівні сервера або Cloudflare.

## 10. Статус

**READY** для повторного рев'ю: обидва зауваження WordPress.org усунено, аналогічних випадків не лишилося, Plugin Check чистий, функції на uafree.org працюють.

Застереження для рев'ю: перевірки на чистій інсталяції з `WP_DEBUG=true` не було (розділ 8).

ZIP у WordPress.org не завантажувався, SVN-реліз не публікувався.
