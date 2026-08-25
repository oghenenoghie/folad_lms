#!/bin/bash
# Start command for the scheduler service — runs the Laravel scheduler every minute.
while [ true ]
do
    php artisan schedule:run --verbose --no-interaction &
    sleep 60
done
