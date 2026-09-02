#!/bin/sh
# Rimuove i log di Craft più vecchi di 30 giorni dal volume storage condiviso.
# Invocato giornalmente da crond (vedi .coolify/web/crontab).
find /app/storage/logs -type f -mtime +30 -delete 2>/dev/null || true
