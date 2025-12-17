<!--
SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.

SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License
-->

# Install DEV environment with Docker

In order to run the application in `dev` mode the `APP_ENV` environment variable must be set to `dev`. You can find
more information about it [on this page](../usage/environment-variables.md).

In case of `dev` mode the following configuration files get merged and loaded:
- `config/packages/*.yaml`
- `config/packages/dev/*.yaml`

## Prerequisites

- Install [Composer](https://getcomposer.org/download/)
- Install Docker and Docker Compose
[[OSX](https://docs.docker.com/docker-for-mac/install/)]
[[Ubuntu](https://docs.docker.com/install/linux/docker-ce/ubuntu/)]
[[Windows](https://docs.docker.com/docker-for-windows/install/)]

## Installation

- Install the dependencies with Composer:
```bash
$ composer install
```

- Generate JWT public and private keys with `openssl`. For example to generate them with `pass:test` passphrase:
```bash
$ cd config/jwt
$ openssl genrsa -passout pass:test -out private.pem 2048
$ openssl rsa -in private.pem -passin pass:test -pubout -out public.pem
```
**Note:** Don't forget to update the environment variables in `.env`.

- Generate OAuth2 public and private keys, following the
[official documentation](https://oauth2.thephpleague.com/installation/). For example to generate them with `pass:test`
passphrase:
```bash
$ cd config/oauth2
$ openssl genrsa -passout pass:test -out private.key 2048
$ openssl rsa -in private.key -passin pass:test -pubout -out public.key
```
**Note #1:** Be sure that the key files have `600` or `660` permissions.

**Note #2:** Don't forget to update the environment variables in `.env`.

## Run

Start the Docker services by running the following command:
```bash
$ docker-compose up -d
```

## Run workers

Please check the available Messenger commands [here](../usage/messenger.md). You need to run it in `tao_deliver_be_phpfpm`
container, e.g.:

```bash
$ docker exec -it tao_deliver_be_phpfpm bin/console messenger:consume <transport> -vvv
```

## Exposed Docker services

| Service                        | Description                                                | URL                   |            |
|--------------------------------|------------------------------------------------------------|-----------------------|------------|
| `tao_deliver_be_nginx`         | Web server                                                 | http://localhost:8001 |            |
| `tao_deliver_be_redis`         | Redis                                                      | http://localhost:6379 |            |
| `tao_deliver_be_elasticsearch` | Elasticsearch                                              | http://localhost:9200 |            |
| `tao_deliver_be_kibana`        | Kibana                                                     | http://localhost:5601 |            |
