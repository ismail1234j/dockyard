#!/bin/sh
# if the socket is mounted at runtime, open it up for www-data
if [ -S /var/run/docker.sock ]; then
  chmod 666 /var/run/docker.sock
fi
# pass through to Apache
exec "$@"
