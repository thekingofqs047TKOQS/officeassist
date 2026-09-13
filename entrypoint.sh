#!/bin/bash
# entrypoint.sh — makes Apache listen on the port Render assigns dynamically

set -e

# Render sets $PORT; default to 80 if not set (e.g. local testing)
PORT="${PORT:-80}"

sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/:80>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf

exec apache2-foreground
