<!--
SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.

SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License
-->

# Install PROD environment

In order to run the application in `prod` mode the `APP_ENV` environment variable must be set to `prod`. You can find 
more information about it [on this page](../usage/environment-variables.md). 

In case of `prod` mode the following configuration files get merged and loaded:
- `config/packages/*.yaml`
- `config/packages/prod/*.yaml`

## Prerequisites

- Install [Composer](https://getcomposer.org/download/)

## Installation

- Update the following environment variables:
```dotenv
APP_ENV=prod
APP_DEBUG=false
```

- Install the dependencies with Composer:
```bash
$ composer install --no-dev --optimize-autoloader
```

- Optimize Composer autoloader:
```bash
$ composer dump-autoload --optimize --no-dev --classmap-authoritative
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
