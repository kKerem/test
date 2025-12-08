#!/bin/sh
set -e

echo "Starting PHP server on port ${PORT:-8000}..."
echo "Working directory: $(pwd)"
echo "Public directory exists: $(test -d public && echo 'yes' || echo 'no')"
echo "index.php exists: $(test -f public/index.php && echo 'yes' || echo 'no')"

php -S 0.0.0.0:${PORT:-8000} -t public
