THREADS_COUNT=${1:-3}
START_TIME=$(date +%H:%M:%S)
START_TIMESTAMP=$(date +%s)

echo "Scraping started at $THREADS_COUNT symfony-processes(post list). The process may take up to 24 hours";
echo "Start date: $START_TIME";

# --restart - начинаем парсинг сначала, не продолжаем
docker compose exec backend php bin/console post:get-list --threads="$THREADS_COUNT" --env=prod --no-debug #--restart

END_TIMESTAMP=$(date +%s)
DURATION_SECONDS=$((END_TIMESTAMP - START_TIMESTAMP))

HOURS=$((DURATION_SECONDS / 3600))
MINUTES=$(((DURATION_SECONDS % 3600) / 60))
SECONDS=$((DURATION_SECONDS % 60))

DURATION_FORMATTED=$(printf "%02d:%02d:%02d" $HOURS $MINUTES $SECONDS)

echo "Done! Duration time: $DURATION_FORMATTED"