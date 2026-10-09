#!/usr/bin/env bash
cd "$(dirname "$0")"
exec php -S "${HOST:-127.0.0.1}:${PORT:-8765}" -t public
