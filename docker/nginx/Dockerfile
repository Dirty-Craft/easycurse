FROM nginx:alpine

RUN apk update
RUN apk add --no-cache certbot certbot certbot-nginx openssl

COPY ./docker/nginx/init.sh /init.sh

RUN chmod +x /init.sh

CMD ["/init.sh"]
