You2Better
==========

A server that povides a easy way to download/stream YouTube audio files.

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

