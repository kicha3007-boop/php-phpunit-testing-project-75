<?php

namespace Downloader\Tests;

/**
 * Подмена HTTP-клиента с интерфейсом Guzzle: downloadPage() создаёт клиент по имени класса,
 * поэтому ответы задаются статически перед каждым тестом.
 */
class FakeClient
{
    /** @var array<string, string> адрес → содержимое */
    public static array $responses = [];

    /** @var array<string, string> адрес → сообщение ошибки */
    public static array $errors = [];

    /** @var array<int, string> запрошенные адреса по порядку */
    public static array $requested = [];

    private string $content = '';

    public static function reset(): void
    {
        self::$responses = [];
        self::$errors = [];
        self::$requested = [];
    }

    public function get(string $url): self
    {
        $this->content = $this->respond($url);
        return $this;
    }

    public function getBody(): self
    {
        return $this;
    }

    public function getContents(): string
    {
        return $this->content;
    }

    /**
     * @param array{sink: string} $options
     */
    public function request(string $method, string $url, array $options): void
    {
        $content = $this->respond($url);
        if (@file_put_contents($options['sink'], $content) === false) {
            throw new \RuntimeException("Unable to write to {$options['sink']}");
        }
    }

    private function respond(string $url): string
    {
        self::$requested[] = $url;
        if (array_key_exists($url, self::$errors)) {
            throw new \RuntimeException(self::$errors[$url]);
        }
        if (!array_key_exists($url, self::$responses)) {
            throw new \RuntimeException("Unexpected request: {$url}");
        }

        return self::$responses[$url];
    }
}
