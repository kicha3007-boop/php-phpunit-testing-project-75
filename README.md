# Тестирование загрузчика страниц (PHP)

[![hexlet-check](https://github.com/kicha3007-boop/php-phpunit-testing-project-75/actions/workflows/hexlet-check.yml/badge.svg)](https://github.com/kicha3007-boop/php-phpunit-testing-project-75/actions)
[![CI](https://github.com/kicha3007-boop/php-phpunit-testing-project-75/actions/workflows/ci.yml/badge.svg)](https://github.com/kicha3007-boop/php-phpunit-testing-project-75/actions/workflows/ci.yml)

`page-loader` — утилита и библиотека: скачивает страницу вместе с локальными ресурсами (картинки, стили,
скрипты, ссылки `link` с того же хоста) и переписывает ссылки на скачанные файлы, чтобы страница открывалась
без сети. Главное в проекте — тесты: HTTP-клиент подменяется, каждый тест работает в своей временной директории.

## Установка

Нужны PHP 8.3+ (расширения dom, mbstring) и Composer.

```bash
git clone https://github.com/kicha3007-boop/php-phpunit-testing-project-75.git
cd php-phpunit-testing-project-75
make install   # зависимости
make test      # тесты (PHPUnit)
make lint      # PHP_CodeSniffer (PSR-12) и PHPStan
```

Без локального PHP — в контейнере:

```bash
docker run --rm -v "$PWD:/app" -w /app composer:2 sh -c "composer install && make lint test"
```

## Использование

```bash
bin/page-loader --output /var/tmp https://ru.hexlet.io/courses
Page was downloaded as '/var/tmp/ru-hexlet-io-courses.html'

bin/page-loader --debug https://ru.hexlet.io/courses   # отладочный лог (Monolog) в stderr
```

- `-o, --output <dir>` — куда сохранять, директория должна существовать (по умолчанию текущая);
- страница: `ru-hexlet-io-courses.html`, ресурсы: `ru-hexlet-io-courses_files/ru-hexlet-io-assets-application.css` —
  адрес без схемы, всё кроме букв и цифр заменено на `-`, расширение сохраняется (у страниц — `.html`);
- ресурсы с других хостов не скачиваются и их ссылки не меняются;
- ошибки (ответ не 2xx, сеть, нет директории, нет прав) — сообщение в stderr и код выхода `1`.

Как библиотека:

```php
use function Downloader\Downloader\downloadPage;

$path = downloadPage('https://ru.hexlet.io/courses', '/var/tmp');
```

Третий аргумент — класс HTTP-клиента с интерфейсом Guzzle (по умолчанию `GuzzleHttp\Client`), четвёртый — PSR-3 логгер.
Ошибки: `Downloader\Exceptions\NetworkException` и `StorageException`, обе наследуют `PageLoaderException`.

## Устройство

- `src/Downloader.php` — пайплайн: проверить директорию, скачать страницу, подготовить ресурсы, сохранить;
- `src/Html.php` — разбор HTML (DOMDocument) без побочных эффектов: локальные ресурсы и новые ссылки;
- `src/Names.php` — имена файлов и директории из адреса;
- `bin/page-loader` — командная часть (docopt): аргументы, вывод, коды возврата.

Тесты: `tests/HtmlTest.php` — разбор и имена без сети, `tests/DownloaderTest.php` — загрузка с фейковым
клиентом (`tests/FakeClient.php`) и ошибки сети и файловой системы. Фикстуры — `tests/fixtures/`.
