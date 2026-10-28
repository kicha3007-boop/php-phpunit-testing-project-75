<?php

namespace Downloader\Names;

/**
 * Адрес без схемы, всё кроме букв и цифр заменено дефисом: ru.hexlet.io/courses → ru-hexlet-io-courses.
 */
function toName(string $url): string
{
    return slugify(addressOf($url));
}

function toPageFilename(string $url): string
{
    return toName($url) . '.html';
}

function toResourcesDirname(string $url): string
{
    return toName($url) . '_files';
}

/**
 * Расширение ресурса сохраняется, у ресурса без расширения — .html.
 */
function toResourceFilename(string $url): string
{
    $address = addressOf($url);
    $extension = pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
    if ($extension === '') {
        return slugify($address) . '.html';
    }

    return slugify(substr($address, 0, -strlen($extension) - 1)) . ".{$extension}";
}

/**
 * Хост и путь без завершающего слеша.
 */
function addressOf(string $url): string
{
    $host = (string) parse_url($url, PHP_URL_HOST);
    $path = (string) parse_url($url, PHP_URL_PATH);

    return rtrim("{$host}{$path}", '/');
}

function slugify(string $text): string
{
    return (string) preg_replace('/[^a-zA-Z0-9]/', '-', $text);
}
