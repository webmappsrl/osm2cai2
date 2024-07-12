#!/bin/bash
set -e

source ./.env

CONTAINER_NAME="php81_$APP_NAME"
SCREEN_NAME="horizon_prod_$APP_NAME"


echo "CONTAINER_NAME: $CONTAINER_NAME"
echo "SCREEN_NAME: $SCREEN_NAME"

if screen -list | grep -q "$SCREEN_NAME"; then
  echo "termino Horizon. Eventuali jobs in esecuzione verranno terminati prima di proseguere..."
  docker exec "$CONTAINER_NAME" php artisan horizon:terminate

  while docker exec "$CONTAINER_NAME" php artisan horizon:status | grep -q 'running'; do
    echo "Attendere che Horizon termini..."
    sleep 5
  done

  screen -S "$SCREEN_NAME" -X quit
  echo "Horizon terminato."
fi

screen -dmS "$SCREEN_NAME" docker exec "$CONTAINER_NAME" php artisan horizon
echo "Horizon avviato in una nuova sessione screen."
