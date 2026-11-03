ARG POSTGRES_VERSION=18-alpine
ARG POSTGRES_IMAGE_DIGEST=sha256:10a5c69a83035ab5239f5620d708520e8b28f5aa1f2b20143e6024532edadfb5
ARG AGE_PACKAGE_VERSION=1.3.2-r1

FROM postgres:${POSTGRES_VERSION}@${POSTGRES_IMAGE_DIGEST} AS postgres-runtime

ARG OS_PACKAGE_REFRESH=manual

RUN test -n "$OS_PACKAGE_REFRESH" \
    && apk upgrade --no-cache \
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
