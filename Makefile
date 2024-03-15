PORT ?= 8081
start:
	PHP_CLI_SERVER_WORKERS=5 php -S 0.0.0.0:$(PORT) -t public

setup:
	composer install

lint:
	composer exec --verbose phpcs -- --standard=PSR12 public src

validate:
	composer validate