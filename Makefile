install:
	composer install

validate:
	composer validate

lint:
	composer exec --verbose phpcs
	composer exec --verbose phpstan -- analyse --no-progress

lint-fix:
	composer exec --verbose phpcbf

test:
	composer exec --verbose phpunit

test-coverage:
	XDEBUG_MODE=coverage composer exec --verbose phpunit -- --coverage-text

.PHONY: install validate lint lint-fix test test-coverage
