ARG POSTGRES_VERSION=18-alpine
ARG POSTGRES_IMAGE_DIGEST=sha256:9a8afca54e7861fd90fab5fdf4c42477a6b1cb7d293595148e674e0a3181de15

FROM postgres:${POSTGRES_VERSION}@${POSTGRES_IMAGE_DIGEST} AS postgres-runtime

RUN apk upgrade --no-cache \
    && rm -f /usr/local/bin/gosu

COPY --chmod=0755 docker/postgres/harden-roles.sh /usr/local/bin/campement-harden-roles
COPY --chmod=0755 docker/postgres/backup.sh /usr/local/bin/campement-backup
