#!/bin/sh
set -e

# Render injects PORT and requires the service to listen on it; default to
# 80 for plain `docker run` / local testing where PORT isn't set.
PORT="${PORT:-80}"

sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/:80>/:${PORT}>/" /etc/apache2/sites-enabled/000-default.conf

# Managed-MySQL CA cert (e.g. Aiven), passed as base64 in a plain env var
# rather than a platform "Secret File" — some sandboxed container runtimes
# (observed on Render's free tier) mount secret files with group ownership
# that even a root-uid process inside the container can't read, regardless
# of file permission bits. Decoding into a file this process creates itself
# sidesteps that entirely.
if [ -n "$RB_DB_SSL_CA_B64" ]; then
    mkdir -p /tmp/certs
    echo "$RB_DB_SSL_CA_B64" | base64 -d > /tmp/certs/aiven-ca.pem
    export RB_DB_SSL_CA=/tmp/certs/aiven-ca.pem
fi

exec "$@"
