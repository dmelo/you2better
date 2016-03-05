FROM fedora:23

MAINTAINER Diogo Oliveira de Melo <dmelo87@gmail.com>

ENTRYPOINT ["php", "-S", "0.0.0.0:8888"]

EXPOSE 8888

ADD ./ /you2better
WORKDIR /you2better

# install packages
RUN ./docker-setup.sh

