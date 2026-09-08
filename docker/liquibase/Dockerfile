ARG LIQUIBASE_VERSION=5.0.3
ARG LIQUIBASE_IMAGE_DIGEST=sha256:dfe136c26d39088d87d1bd27f833d6ac5b738a180d7c960b065c7be525725d01

FROM liquibase/liquibase:${LIQUIBASE_VERSION}@${LIQUIBASE_IMAGE_DIGEST} AS liquibase-runtime

USER root

RUN export DEBIAN_FRONTEND=noninteractive \
    && apt-get update \
    && apt-get upgrade -y \
    && rm -rf /var/lib/apt/lists/* /liquibase/bin/lpm

ADD --checksum=sha256:31fbf6f06b2217fb51d5100cee51b22625cc81640da0679b47914e54c1e6377c \
    --chown=liquibase:liquibase \
    https://jdbc.postgresql.org/download/postgresql-42.7.12.jar \
    /liquibase/lib/postgresql.jar

COPY database/changelog /liquibase/changelog

USER liquibase
