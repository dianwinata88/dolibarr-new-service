#!/bin/sh
set -e

if [ "$1" = 'frankenphp' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then
	# Install the project dependencies if the source is mounted as a volume
	# and vendor/ is missing or outdated
	if [ ! -f vendor/autoload.php ] || [ composer.json -nt vendor/autoload.php ]; then
		composer install --prefer-dist --no-progress --no-interaction
	fi

	if grep -q ^DATABASE_URL= .env 2>/dev/null; then
		echo "Waiting for database to be ready..."
		ATTEMPTS_LEFT=20
		until [ $ATTEMPTS_LEFT -le 0 ] || bin/console dbal:run-sql -q "SELECT 1" >/dev/null 2>&1; do
			sleep 1
			ATTEMPTS_LEFT=$((ATTEMPTS_LEFT - 1))
		done
		if [ $ATTEMPTS_LEFT -le 0 ]; then
			echo "The database is not up or not reachable"
		else
			echo "The database is now ready and reachable"
		fi
	fi

	mkdir -p var/cache var/log
	setfacl -R -m u:www-data:rwX -m u:"$(whoami)":rwX var 2>/dev/null || true
	setfacl -dR -m u:www-data:rwX -m u:"$(whoami)":rwX var 2>/dev/null || true
fi

exec docker-php-entrypoint "$@"
