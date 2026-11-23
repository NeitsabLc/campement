ARG LIQUIBASE_VERSION=5.0.3
ARG LIQUIBASE_IMAGE_DIGEST=sha256:dfe136c26d39088d87d1bd27f833d6ac5b738a180d7c960b065c7be525725d01

FROM liquibase/liquibase:${LIQUIBASE_VERSION}@${LIQUIBASE_IMAGE_DIGEST} AS liquibase-runtime

ARG OS_PACKAGE_REFRESH=manual

USER root

RUN test -n "$OS_PACKAGE_REFRESH" \
    && export DEBIAN_FRONTEND=noninteractive \
    && apt-get update \
    && apt-get upgrade -y \
    && rm -rf /var/lib/apt/lists/* /liquibase/bin/lpm

ADD --checksum=sha256:9776fca62dfc84f312d5c1aff9791f6f3cf56d518a8eca4a3a8dca7a95ef8881 \
    --chown=liquibase:liquibase \
    https://jdbc.postgresql.org/download/postgresql-42.7.15.jar \
    /liquibase/lib/postgresql.jar

COPY database/changelog /liquibase/changelog

USER liquibase
