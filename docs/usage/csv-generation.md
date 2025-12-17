<!--
SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.

SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License
-->

# CSV Generation Command

It provides a command for generation CSV file with results by delivery ID.

**Note:** Default limit for SQL queries is `1000`.

## Setup

Need setup a storage in `flysystem.yaml`

```
...
csv_results.storage:
    adapter: 'local'
    options:
        directory: '%flysystem.path.csv_results%'
...
```

## Usage

- `bin/console results:generate-csv <deliveryId>` - Generate CSV file with results

**Note:** you can find more details about the command by using `--help`.
