FROM ubuntu:22.04

LABEL dockerfile.version="v1.2" dockerfile.release-date="2023-01-04"

# Set up ENVs that will be utilized in compose file.
ENV TZ Etc/UTC

ENV ITFLOW_NAME ITFlow

ENV ITFLOW_URL demo.itflow.org

ENV ITFLOW_PORT 8080

ENV ITFLOW_REPO github.com/itflow-org/itflow

ENV ITFLOW_REPO_BRANCH master

# apache2 log levels: emerg, alert, crit, error, warn, notice, info, debug
ENV ITFLOW_LOG_LEVEL warn

ENV ITFLOW_DB_HOST itflow-db

ENV ITFLOW_DB_PASS null

# Set timezone from TZ ENV
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# PREREQS: php php-intl php-mysqli php-imap php-curl libapache2-mod-php mariadb-server git -y
# Upgrade, then install prereqs.
RUN apt-get update && apt-get upgrade -y && apt-get clean 

# ITFlow Requirements
RUN apt-get install -y \
    git\
    apache2\
    php

# Ubuntu quality of life installs
RUN apt-get install -y \
    vim\
    cron\ 
    dnsutils\
    iputils-ping

# Install & enable php extensions
RUN apt-get install -y \ 
    php-intl\
    php-mysqli\
    php-curl\
    php-imap

RUN apt-get install -y \
    libapache2-mod-php\
    libapache2-mod-md

# Enable md apache mod
RUN a2enmod md

# Set the work dir to the git repo.
WORKDIR /var/www/html

# Entrypoint
# On every run of the docker file, perform an entrypoint that verifies the container is good to go.
COPY entrypoint.sh /usr/bin/

RUN chmod +x /usr/bin/entrypoint.sh

# forward request and error logs to docker log collector
RUN ln -sf /dev/stdout /var/log/apache2/access.log && ln -sf /dev/stderr /var/log/apache2/error.log

ENTRYPOINT [ "entrypoint.sh" ]

# Expose the apache port
EXPOSE $ITFLOW_PORT

# Start the httpd service and have logs appear in stdout
CMD [ "apache2ctl", "-D", "FOREGROUND" ]