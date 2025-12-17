<!--
SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.

SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License
-->

# Symfony Messenger

The [Messenger](https://symfony.com/doc/current/components/messenger.html) component helps send and receive messages via
message queues.

## Console commands

- `bin/console messenger:setup-transports` - Initialize all the transports
- `bin/console messenger:consume <transport name>` - Consumes all the messages for the given transport
- `bin/console messenger:failed:remove` - remove all the messages from the failed transport
- `bin/console messenger:failed:retry` - retry all the messages in the failed transport
- `bin/console messenger:failed:show` - display all the messages from the failed transport

**Note:** you can find more details about each commands by using `--help`.

## Transports

You can find all the available transports in `config/packages/messenger.yaml`.
