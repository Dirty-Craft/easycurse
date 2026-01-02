#!/bin/sh

rm /etc/nginx/conf.d/ssl.conf
if [ "${DOMAIN:-localhost}" != "localhost" ]; then
  nginx
  certbot certonly -d ${DOMAIN} --email admin@${DOMAIN} --agree-tos --non-interactive --webroot -w /var/www/public
  envsubst '${DOMAIN}' < /etc/nginx/conf.d/ssl.conf.template > /etc/nginx/conf.d/ssl.conf
  nginx -s stop
fi

nginx -g "daemon off;"
