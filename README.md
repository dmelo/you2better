You2Better
==========

[![Build Status](https://travis-ci.org/dmelo/you2better.svg)](https://travis-ci.org/dmelo/you2better)

A Web server that provides a easy way to download/stream YouTube audio files.

Dependencies
------------

List of software that must be previously installed:

- make
- pandoc
- php
- python 2.6, 2.7, or 3.2+
- zip

Install
-------

Use the following commands to setup You2Better.

```bash
git clone https://github.com/dmelo/you2better.git
composer install
cd vendor/rg3/youtube-dl
make
sudo make install
./youtube-dl -U
cd -
cp you2better-conf.php.template you2better-conf.php
php -S 0.0.0.0:8888
```

Try to download an audio:

```bash
wget http://localhost:8888/?youtubeid=meT2eqgDjiM -O PomplamooseMusic_Beat_it.m4a
```

Docker
------

You can run the images from Docker Hub
[dmelo/you2better](https://hub.docker.com/r/dmelo/you2better/).

```bash
docker run -p 8888:8888 dmelo/you2better
```

As described on the Install section, download the content using port 8888 of
localhost:

```bash
wget http://localhost:8888/?youtubeid=meT2eqgDjiM -O PomplamooseMusic_Beat_it.m4a
```
