THREADS_COUNT=${1:-30}

echo "Scraping started at $THREADS_COUNT symfony-processes(post list). The process may take up to 24 hours";

docker compose exec backend php bin/console post:get-list --threads="$THREADS_COUNT" --env=prod --no-debug

echo "Done!"