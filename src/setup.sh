#!/bin/bash

composer install
npm install

if [ ! -f .env ]; then
    cp .env.example .env
fi

if [ "$IS_PROD" = "true" ] && [ -f public/hot ]; then
    rm public/hot
fi

APP_KEY=$(grep -E '^APP_KEY=' ".env" | cut -d '=' -f2-)
if [[ -z "$APP_KEY" ]]; then
  php artisan key:generate
fi

composer lint
php artisan storage:link
php artisan migrate
php artisan db:seed
npm run build
php artisan optimize:clear

echo ""
echo "Checking critical environment variables..."

declare -A env_defaults=(
  [MAIL_MAILER]="log"
)

for var in "${!env_defaults[@]}"; do
  current_val=$(grep -E "^$var=" ".env" | cut -d '=' -f2-)
  default_val="${env_defaults[$var]}"

  if [[ -z "$current_val" || "$current_val" == "$default_val" ]]; then
    echo "Warning: $var is not properly set (current: '$current_val')."
    echo "   Project might not function correctly without updating this variable."
  fi
done

echo "Setup complete."
