<?php

namespace Downloader\Downloader;

use Downloader\Exceptions\NetworkException;
use Downloader\Exceptions\StorageException;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

use function Downloader\Html\prepareHtml;
use function Downloader\Names\toPageFilename;
use function Downloader\Names\toResourcesDirname;

/**
 * Скачивает страницу с локальными ресурсами в существующую директорию и возвращает путь к странице.
 * $clientClass — класс HTTP-клиента с интерфейсом Guzzle (get()->getBody()->getContents(), request() с sink).
 */
function downloadPage(
    string $url,
    string $outputDir,
    string $clientClass = Client::class,
    LoggerInterface $logger = new NullLogger(),
): string {
    ensureDirectory($outputDir);
    $client = new $clientClass();
    $logger->debug('downloading page', ['url' => $url, 'output' => $outputDir]);

    [$html, $resources] = prepareHtml(fetchPage($client, $url), $url);
    $logger->debug('local resources found', ['count' => count($resources)]);

    if ($resources !== []) {
        makeDirectory(joinPath($outputDir, toResourcesDirname($url)));
    }
    foreach ($resources as ['url' => $resourceUrl, 'path' => $path]) {
        fetchResource($client, $resourceUrl, joinPath($outputDir, $path));
        $logger->debug('resource saved', ['url' => $resourceUrl, 'path' => $path]);
    }

    $pagePath = joinPath($outputDir, toPageFilename($url));
    save($pagePath, $html);
    $logger->info('page saved', ['url' => $url, 'path' => $pagePath]);

    return $pagePath;
}

function joinPath(string ...$parts): string
{
    return implode(DIRECTORY_SEPARATOR, $parts);
}

function ensureDirectory(string $path): void
{
    if (!is_dir($path)) {
        throw new StorageException("Output directory does not exist: {$path}");
    }
}

function fetchPage(object $client, string $url): string
{
    try {
        return $client->get($url)->getBody()->getContents();
    } catch (Throwable $error) {
        throw new NetworkException("Failed to download {$url}: {$error->getMessage()}", 0, $error);
    }
}

function fetchResource(object $client, string $url, string $path): void
{
    try {
        $client->request('GET', $url, ['sink' => $path]);
    } catch (Throwable $error) {
        throw new NetworkException("Failed to download {$url}: {$error->getMessage()}", 0, $error);
    }
}

function makeDirectory(string $path): void
{
    if (!is_dir($path) && !@mkdir($path)) {
        throw new StorageException("Cannot create directory {$path}");
    }
}

function save(string $path, string $content): void
{
    if (@file_put_contents($path, $content) === false) {
        throw new StorageException("Cannot save {$path}");
    }
}
