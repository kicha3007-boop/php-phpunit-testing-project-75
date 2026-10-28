<?php

namespace Downloader\Tests;

use PHPUnit\Framework\TestCase;

use function Downloader\Html\prepareHtml;
use function Downloader\Names\toPageFilename;
use function Downloader\Names\toResourceFilename;
use function Downloader\Names\toResourcesDirname;

class HtmlTest extends TestCase
{
    private const PAGE_URL = 'https://ru.hexlet.io/courses';

    public function testRewritesOnlyLocalLinks(): void
    {
        [$html, $resources] = prepareHtml(self::readFixture('page.html'), self::PAGE_URL);

        $this->assertSame(self::readFixture('expected.html'), $html);
        $this->assertStringContainsString('https://cdn2.hexlet.io/assets/menu.css', $html);
        $this->assertStringContainsString('https://js.stripe.com/v3/', $html);
        $this->assertEqualsCanonicalizing(
            [
                'https://ru.hexlet.io/assets/professions/php.png',
                'https://ru.hexlet.io/assets/application.css',
                'https://ru.hexlet.io/courses',
                'https://ru.hexlet.io/packs/js/runtime.js',
            ],
            array_column($resources, 'url'),
        );
    }

    public function testPageWithoutResources(): void
    {
        [$html, $resources] = prepareHtml('<p>текст &amp; ссылка</p>', self::PAGE_URL);

        $this->assertSame([], $resources);
        $this->assertStringContainsString('текст &amp; ссылка', $html);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function resourceNameProvider(): array
    {
        return [
            'картинка' => ['https://ru.hexlet.io/assets/app.min.css', 'ru-hexlet-io-assets-app-min.css'],
            'параметры запроса' => ['https://ru.hexlet.io/photo.png?v=2', 'ru-hexlet-io-photo.png'],
            'страница без расширения' => ['https://ru.hexlet.io/courses', 'ru-hexlet-io-courses.html'],
        ];
    }

    /**
     * @dataProvider resourceNameProvider
     */
    public function testResourceFilename(string $url, string $expected): void
    {
        $this->assertSame($expected, toResourceFilename($url));
    }

    public function testPageNames(): void
    {
        $this->assertSame('ru-hexlet-io-courses.html', toPageFilename('https://ru.hexlet.io/courses/'));
        $this->assertSame('ru-hexlet-io-courses_files', toResourcesDirname(self::PAGE_URL));
    }

    private static function readFixture(string $name): string
    {
        return (string) file_get_contents(__DIR__ . '/fixtures/' . $name);
    }
}
