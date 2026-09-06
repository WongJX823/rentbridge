#!/bin/sh
set -e

# Render injects PORT and requires the service to listen on it; default to
# 80 for plain `docker run` / local testing where PORT isn't set.
PORT="${PORT:-80}"

sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/:80>/:${PORT}>/" /etc/apache2/sites-enabled/000-default.conf

exec "$@"
