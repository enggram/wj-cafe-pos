#!/bin/bash
# Run the test suite against an isolated in-memory SQLite database.
# This guarantees your MySQL dev/prod data is NEVER touched by tests.
APP_ENV=testing \
DB_CONNECTION=sqlite \
DB_DATABASE=:memory: \
SESSION_DRIVER=array \
CACHE_STORE=array \
php artisan test "$@"
