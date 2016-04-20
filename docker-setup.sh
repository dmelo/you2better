#!/bin/bash

dnf install -y \
    git \
    make \
    pandoc \
    php \
    python \
    zip

php -r "readfile('https://getcomposer.org/installer');" > composer-setup.php
php composer-setup.php
./composer.phar install
cp you2better-conf.php.template you2better-conf.php
chmod 755 index.php
mkdir log/

cd vendor/rg3/youtube-dl/
make
make install

cd -
