FROM 1maa/php-dev:8.3 AS builder

WORKDIR /tmp/faucet

COPY bin bin
COPY src src
COPY views views
COPY web web
COPY autoload.php .
COPY composer.json .
COPY composer.lock .

RUN composer install --no-dev --classmap-authoritative \
 && chown -R nobody:nobody /tmp/faucet


FROM 1maa/php:8.3 AS final

ENV FAUCET_DEBUG=0
ENV FAUCET_REDIS_ENDPOINT=redis:6379
ENV FAUCET_REDIS_PREFIX="faucet:"
ENV FAUCET_BITCOIN_RPC_ENDPOINT="http://knots:38332"
ENV FAUCET_BITCOIN_RPC_USER=knots
ENV FAUCET_BITCOIN_RPC_PASS=knots
ENV FAUCET_NAME="Your Signet Faucet"
ENV FAUCET_MIN_ONE_TIME_BTC=0.001
ENV FAUCET_MAX_ONE_TIME_BTC=5.0
ENV FAUCET_USER_SESSION_TTL=3600
ENV FAUCET_GLOBAL_SESSION_TTL=3600
ENV FAUCET_USE_CAPTCHA=0
ENV FAUCET_USE_BATCHING=0
ENV FAUCET_USER_SESSION_MAX_BTC=20.0
ENV FAUCET_GLOBAL_SESSION_MAX_BTC=150.0

ENV PHP_CLI_SERVER_WORKERS=8

COPY --from=builder /tmp/faucet /var/www/faucet

WORKDIR /var/www/faucet

USER nobody

EXPOSE 8080

CMD ["php", "-d", "variables_order=EGPCS", "-S", "0.0.0.0:8080", "-t", "web"]
