<!--
SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.

SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License
-->

# Delivery execution commands

It provides a command for generation CSV file with results by delivery ID.

**Note:** Default limit for SQL queries is `1000`.

## Usage

- `bin/console delivery-execution:data <deliveryExecutionId>` - Return simple delivery execution data
- `bin/console delivery-execution:data --delivery --with-qti-compiled-delivery <deliveryExecutionId>` - Return extended delivery execution data
- `bin/console delivery-execution:data --delivery --with-qti-compiled-delivery --base64 <deliveryExecutionId>` - Return base64 string for possibility of usage into import command

- `delivery-execution:import <localTenantId> <base64StringFromDataCommand>` - Return base64 string for possibility of usage into import command

**Note:** you can find more details about the command by using `--help`.
