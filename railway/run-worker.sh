#!/bin/bash
# Start command for the queue worker service — runs continuously.
php artisan queue:work --sleep=3 --tries=3 --max-time=3600
