<?php

// Пакет может стоять зависимостью другого проекта (так его проверяет Хекслет): тогда autoload-dev
// не подключается, поэтому тестовые классы подключаются здесь.
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/FakeClient.php';
