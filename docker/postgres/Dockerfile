ARG POSTGRES_VERSION=18-alpine
ARG POSTGRES_IMAGE_DIGEST=sha256:9a8afca54e7861fd90fab5fdf4c42477a6b1cb7d293595148e674e0a3181de15
ARG AGE_PACKAGE_VERSION=1.3.1-r5

FROM postgres:${POSTGRES_VERSION}@${POSTGRES_IMAGE_DIGEST} AS postgres-runtime

RUN apk upgrade --no-cache \
    && rm -f /usr/local/bin/gosu

COPY --chmod=0755 docker/postgres/harden-roles.sh /usr/local/bin/campement-harden-roles

FROM postgres-runtime AS backup-runtime

ARG AGE_PACKAGE_VERSION

USER root

RUN apk add --no-cache "age=${AGE_PACKAGE_VERSION}"

COPY --chmod=0755 docker/postgres/backup.sh /usr/local/bin/campement-backup
COPY --chmod=0755 docker/postgres/verify-backup.sh /usr/local/bin/campement-verify-backup

USER postgres

ENTRYPOINT ["/usr/local/bin/campement-backup"]
