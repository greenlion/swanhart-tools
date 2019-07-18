FROM mysql:5.6

ENV DEBIAN_FRONTEND=noninteractive

ADD . flexviews

RUN apt-get update && apt-get install -y \
    php \
    php-mysqli \
    php-pear

WORKDIR flexviews/consumer

CMD php setup_flexcdc.php --force && php run_consumer.php
