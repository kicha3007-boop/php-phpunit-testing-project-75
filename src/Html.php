<?php

namespace Downloader\Html;

use DOMDocument;
use DOMElement;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;

use function Downloader\Names\toResourceFilename;
use function Downloader\Names\toResourcesDirname;

// Какой атрибут у какого тега указывает на ресурс.
const RESOURCE_ATTRIBUTES = ['img' => 'src', 'link' => 'href', 'script' => 'src'];

// Числовые HTML-сущности не-ASCII символов: так libxml сохраняет кириллицу.
const NON_ASCII_ENTITIES = [0x80, 0x10FFFF, 0, 0x1FFFFF];

// Подсказка кодировки для libxml: без неё страница без meta charset читается как ISO-8859-1.
const ENCODING_HINT = '<?xml encoding="UTF-8">';

/**
 * Разбор без побочных эффектов: HTML со ссылками на локальные копии и список ресурсов.
 * Локальный ресурс — с того же хоста, что и страница; чужие не трогаются.
 *
 * @return array{string, array<int, array{url: string, path: string}>}
 */
function prepareHtml(string $html, string $pageUrl): array
{
    $document = loadDocument($html);

    $resources = [];
    foreach (RESOURCE_ATTRIBUTES as $tag => $attribute) {
        foreach ($document->getElementsByTagName($tag) as $element) {
            $resource = localizeResource($element, $attribute, $pageUrl);
            if ($resource !== null) {
                $resources[] = $resource;
            }
        }
    }

    $output = mb_decode_numericentity((string) $document->saveHTML(), NON_ASCII_ENTITIES, 'UTF-8');

    return [rtrim($output, "\n"), $resources];
}

function loadDocument(string $html): DOMDocument
{
    $document = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML(ENCODING_HINT . $html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    // Подсказка кодировки попадает в документ отдельным узлом — убираем его.
    foreach (iterator_to_array($document->childNodes) as $node) {
        if ($node->nodeType === XML_PI_NODE) {
            $document->removeChild($node);
        }
    }

    return $document;
}

/**
 * Переписывает ссылку на локальный ресурс и возвращает его адрес и путь от директории страницы.
 *
 * @return array{url: string, path: string}|null
 */
function localizeResource(DOMElement $element, string $attribute, string $pageUrl): ?array
{
    if (!$element->hasAttribute($attribute)) {
        return null;
    }

    $url = (string) UriResolver::resolve(new Uri($pageUrl), new Uri($element->getAttribute($attribute)));
    if (parse_url($url, PHP_URL_HOST) !== parse_url($pageUrl, PHP_URL_HOST)) {
        return null;
    }

    $path = toResourcesDirname($pageUrl) . '/' . toResourceFilename($url);
    $element->setAttribute($attribute, $path);

    return ['url' => $url, 'path' => $path];
}
