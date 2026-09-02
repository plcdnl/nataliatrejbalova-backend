#!/bin/sh
# Avvia crond in background: pulizia giornaliera dei log > 30 giorni.
crond -b -L /dev/stdout
