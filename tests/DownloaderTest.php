<?php

namespace Downloader\Tests;

use Downloader\Exceptions\NetworkException;
use Downloader\Exceptions\StorageException;
use PHPUnit\Framework\TestCase;

use function Downloader\Downloader\downloadPage;

class DownloaderTest extends TestCase
{
    private const PAGE_URL = 'https://ru.hexlet.io/courses';
    private const RESOURCES_DIR = 'ru-hexlet-io-courses_files';

    /** Ресурсы страницы: адрес → [фикстура, имя файла]. */
    private const RESOURCES = [
        'https://ru.hexlet.io/assets/professions/php.png' => ['php.png', 'ru-hexlet-io-assets-professions-php.png'],
        'https://ru.hexlet.io/assets/application.css' => ['application.css', 'ru-hexlet-io-assets-application.css'],
        'https://ru.hexlet.io/packs/js/runtime.js' => ['runtime.js', 'ru-hexlet-io-packs-js-runtime.js'],
        self::PAGE_URL => ['page.html', 'ru-hexlet-io-courses.html'],
    ];

    private string $outputDir;

    protected function setUp(): void
    {
        $this->outputDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('page-loader-', true);
        mkdir($this->outputDir);

        FakeClient::reset();
        foreach (self::RESOURCES as $url => [$fixture]) {
            FakeClient::$responses[$url] = self::readFixture($fixture);
        }
    }

    protected function tearDown(): void
    {
        self::removeDirectory($this->outputDir);
    }

    public function testDownloadsPageWithLocalResources(): void
    {
        $path = downloadPage(self::PAGE_URL, $this->outputDir, FakeClient::class);

        $this->assertSame($this->outputDir . DIRECTORY_SEPARATOR . 'ru-hexlet-io-courses.html', $path);
        $this->assertSame(self::readFixture('expected.html'), file_get_contents($path));

        $saved = scandir($this->outputDir . DIRECTORY_SEPARATOR . self::RESOURCES_DIR);
        $this->assertEqualsCanonicalizing(
            array_column(self::RESOURCES, 1),
            array_values(array_diff((array) $saved, ['.', '..'])),
        );
        foreach (self::RESOURCES as [$fixture, $filename]) {
            $actual = file_get_contents($this->outputDir . '/' . self::RESOURCES_DIR . '/' . $filename);
            $this->assertSame(self::readFixture($fixture), $actual, $filename);
        }
    }

    public function testExternalResourcesAreNotRequested(): void
    {
        downloadPage(self::PAGE_URL, $this->outputDir, FakeClient::class);

        $hosts = array_unique(array_map(fn($url) => parse_url($url, PHP_URL_HOST), FakeClient::$requested));
        $this->assertSame(['ru.hexlet.io'], array_values($hosts));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function pageErrorProvider(): array
    {
        return [
            'нет соединения' => ['Could not resolve host'],
            'ответ 404' => ['Client error: `GET https://ru.hexlet.io/courses` resulted in a `404 Not Found` response'],
            'ответ 500' => ['Server error: `GET https://ru.hexlet.io/courses` resulted in a `500` response'],
        ];
    }

    /**
     * @dataProvider pageErrorProvider
     */
    public function testPageErrorLeavesOutputEmpty(string $message): void
    {
        FakeClient::$errors[self::PAGE_URL] = $message;

        try {
            downloadPage(self::PAGE_URL, $this->outputDir, FakeClient::class);
            $this->fail('NetworkException expected');
        } catch (NetworkException $error) {
            $this->assertStringContainsString(self::PAGE_URL, $error->getMessage());
            $this->assertStringContainsString($message, $error->getMessage());
        }
        $this->assertSame(['.', '..'], scandir($this->outputDir));
    }

    public function testResourceError(): void
    {
        FakeClient::$errors['https://ru.hexlet.io/assets/application.css'] = 'Not Found';

        $this->expectException(NetworkException::class);
        $this->expectExceptionMessage('Failed to download https://ru.hexlet.io/assets/application.css');

        downloadPage(self::PAGE_URL, $this->outputDir, FakeClient::class);
    }

    public function testMissingOutputDirectoryIsNotCreated(): void
    {
        $missing = $this->outputDir . DIRECTORY_SEPARATOR . 'missing';

        try {
            downloadPage(self::PAGE_URL, $missing, FakeClient::class);
            $this->fail('StorageException expected');
        } catch (StorageException $error) {
            $this->assertStringContainsString('does not exist', $error->getMessage());
        }
        $this->assertDirectoryDoesNotExist($missing);
        $this->assertSame([], FakeClient::$requested, 'до проверки директории сеть не трогается');
    }

    public function testOutputIsAFile(): void
    {
        $file = $this->outputDir . DIRECTORY_SEPARATOR . 'file.txt';
        touch($file);

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('does not exist');

        downloadPage(self::PAGE_URL, $file, FakeClient::class);
    }

    public function testResourcesDirectoryBlockedByFile(): void
    {
        touch($this->outputDir . DIRECTORY_SEPARATOR . self::RESOURCES_DIR);

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('Cannot create directory');

        downloadPage(self::PAGE_URL, $this->outputDir, FakeClient::class);
    }

    public function testDirectoryWithoutWritePermission(): void
    {
        if (DIRECTORY_SEPARATOR === '\\' || (function_exists('posix_geteuid') && posix_geteuid() === 0)) {
            $this->markTestSkipped('права на запись не ограничивают root и Windows');
        }
        chmod($this->outputDir, 0555);

        try {
            $this->expectException(StorageException::class);
            downloadPage(self::PAGE_URL, $this->outputDir, FakeClient::class);
        } finally {
            chmod($this->outputDir, 0755);
        }
    }

    private static function readFixture(string $name): string
    {
        return (string) file_get_contents(__DIR__ . '/fixtures/' . $name);
    }

    private static function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (array_diff((array) scandir($path), ['.', '..']) as $entry) {
            $entryPath = $path . DIRECTORY_SEPARATOR . $entry;
            is_dir($entryPath) ? self::removeDirectory($entryPath) : unlink($entryPath);
        }
        rmdir($path);
    }
}
