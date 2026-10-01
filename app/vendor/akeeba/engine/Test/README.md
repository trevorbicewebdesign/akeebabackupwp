# Akeeba Engine — Test Suite

This directory contains the PHPUnit test suite for Akeeba Engine.

## Directory layout

```
Test/
├── README.md                    ← you are here
├── bootstrap.php                ← PHPUnit bootstrap: loads Composer autoloader, the optional .env file, and defines AKEEBAENGINE
├── .env.sample                  ← sample local environment configuration (copy to .env; .env is git-ignored)
├── AbstractEngineTestCase.php   ← shared base class for tests that need Platform/Factory initialised
├── _data/                       ← fixture files (JSON configuration snapshots, etc.)
│
├── ConfigurationTest.php        ← unit tests: Configuration registry
├── Core/                        ← unit tests: Core classes (Timer, …)
├── Driver/                      ← unit tests: Driver utilities (FixMySQLHostname, Query\Element)
├── Dump/                        ← unit tests: Dump engine adapters (PostgreSQL adapters)
├── Filter/                      ← unit tests: Filter\Base
├── Util/                        ← unit tests: utility classes (Buffer, Collection, Encrypt, …)
│
├── Integration/                 ← integration tests (require external services)
│   ├── Driver/
│   │   ├── AbstractMysqlDriverTestCase.php   ← 32 shared tests for MySQL-compatible drivers
│   │   ├── MysqliTest.php                    ← runs the suite against Driver\Mysqli
│   │   ├── PdomysqlTest.php                  ← runs the suite against Driver\Pdomysql
│   │   └── PostgresqlTest.php                ← 21 tests against Driver\Postgresql (own SQL dialect, own case)
│   ├── Backup/
│   │   └── MisbehavingFileTest.php          ← backs up files that grow, shrink, or disappear mid-backup
│   └── Postproc/
│       ├── AbstractPostprocTestCase.php     ← shared upload/download/delete lifecycle for remote storage engines
│       └── BackblazeTest.php                ← runs the lifecycle against a live BackBlaze B2 bucket
│
└── Stub/                        ← test doubles used by unit tests
    ├── Driver/FixMySQLHostnameStub.php
    ├── Filter/ConcreteFilter.php
    ├── Platform/TestPlatform.php
    ├── Platform/FileSystemTestPlatform.php
    └── Util/HashTraitStub.php
```

---

## Unit tests

Unit tests have no external dependencies and can be run at any time.

### Run all unit tests

```bash
vendor/bin/phpunit
# or, explicitly:
vendor/bin/phpunit --configuration phpunit.xml.dist
```

### Run a single test file

```bash
vendor/bin/phpunit Test/Driver/FixMySQLHostnameTest.php
```

### Run a single test method

```bash
vendor/bin/phpunit --filter testCanFixDefinitions Test/Driver/FixMySQLHostnameTest.php
```

### PHPUnit configuration

`phpunit.xml.dist` at the repository root. It targets the entire `Test/` directory,
so integration tests inside `Test/Integration/` are **also** picked up by the default
run — but they skip themselves automatically when the environment variable that enables
them is absent (`INTEGRATION_DB_HOST` for the MySQL/MariaDB drivers,
`INTEGRATION_PGSQL_HOST` for the PostgreSQL driver, and so on).

### Base class for tests that touch engine internals

Tests that exercise code which calls `Factory` or `Platform` must extend
`Akeeba\Engine\Test\AbstractEngineTestCase` instead of `PHPUnit\Framework\TestCase`.
It wires in a `TestPlatform` stub and resets `Factory` between tests so that static
state does not leak across cases.

---

## Integration tests

Integration tests exercise code against real, external resources (a database server, the
backup engine running as a sub-process, …). They live under `Test/Integration/` and are
**skipped silently** by the unit-test run unless the environment variable that enables them
is set (`INTEGRATION_DB_HOST` for the MySQL/MariaDB drivers, `INTEGRATION_PGSQL_HOST` for
the PostgreSQL driver, `INTEGRATION_BACKUP` for the misbehaving-file backup test).

A separate PHPUnit configuration (`phpunit.integration.xml`) defines two test suites:

| Suite | Targets | Used by |
|-------|---------|---------|
| `integration` | all of `Test/Integration/` | you, when you want to run everything |
| `drivers` | `Test/Integration/Driver/` only | `run-integration-tests.sh` |

The `drivers` suite exists so the runner can iterate over half a dozen database images
**without** dragging the live remote-storage tests (Box, Dropbox, S3, …) along on every
single iteration.

### Local environment configuration (`Test/.env`)

Rather than exporting variables into your shell on every run, you can put them in a
git-ignored `Test/.env` file. `Test/bootstrap.php` loads it automatically before any test
runs. Copy the committed `Test/.env.sample` to `Test/.env` and edit it:

```bash
cp Test/.env.sample Test/.env
```

The format is a minimal dotenv subset: one `KEY=VALUE` per line, `#` comments and blank
lines are ignored, optional surrounding quotes are stripped, and an optional leading
`export ` is allowed. Variables already present in the real environment take precedence,
so you can still override anything on a per-run basis.

### Requirements

- Docker (to manage the ephemeral database containers)
- PHP CLI with the `mysqli`, `pdo_mysql` and `pdo_pgsql` extensions enabled. A driver whose
  extension is missing skips itself rather than failing
- No local service bound to the host ports used (defaults: **13306** for MySQL/MariaDB,
  **15432** for PostgreSQL)

### Run against all configured server versions

```bash
./run-integration-tests.sh
```

The script starts a throw-away container for each configured image, waits for the server to
accept connections, runs the `drivers` suite against it, and force-removes the container —
including if you interrupt the run. Out of the box it covers three families:

| Family | Default images | Driver classes exercised |
|--------|----------------|--------------------------|
| MySQL | `mysql:8.0`, `mysql:8.4` | `MysqliTest`, `PdomysqlTest` |
| MariaDB | `mariadb:11.4`, `mariadb:12.3` | `MysqliTest`, `PdomysqlTest` |
| PostgreSQL | `postgres:16`, `postgres:17` | `PostgresqlTest` |

### Run a subset

```bash
./run-integration-tests.sh postgres:17     # one specific image
./run-integration-tests.sh mariadb         # every image of one family
./run-integration-tests.sh                 # everything configured
```

The family keywords are `mysql`, `mariadb` and `postgres`. Anything else is taken to be a
literal Docker image name, and its family is inferred from the part before the colon.

### Choosing which server versions to test

The image lists are environment variables, so this is where you add a version to the matrix
or trim it down to a single server to keep a local run quick. Put them in `Test/.env` (the
script reads it, exactly like `bootstrap.php` does) or set them per run. **Quote them** —
they contain spaces:

```bash
INTEGRATION_POSTGRES_IMAGES="postgres:15 postgres:16 postgres:17" ./run-integration-tests.sh postgres
```

| Variable | Default | Meaning |
|----------|---------|---------|
| `INTEGRATION_MYSQL_IMAGES` | `mysql:8.0 mysql:8.4` | Space-separated MySQL images to test |
| `INTEGRATION_MARIADB_IMAGES` | `mariadb:11.4 mariadb:12.3` | Space-separated MariaDB images to test |
| `INTEGRATION_POSTGRES_IMAGES` | `postgres:16 postgres:17` | Space-separated PostgreSQL images to test |
| `INTEGRATION_DB_HOST_PORT` | `13306` | Host port mapped to the MySQL/MariaDB container's 3306 |
| `INTEGRATION_PGSQL_HOST_PORT` | `15432` | Host port mapped to the PostgreSQL container's 5432 |
| `DB_PASSWORD` | `akeebatest` | Root/superuser password seeded into the container |
| `DB_NAME` | `akeebatest` | Database created inside the container |
| `MAX_WAIT_SECONDS` | `120` | How long to wait for a server to accept connections before giving up |

Each iteration is **filtered to its own family's test classes**. That is deliberate rather
than merely tidy: `bootstrap.php` reads `Test/.env` itself, so if you have
`INTEGRATION_DB_HOST` configured there, an unfiltered run would silently point the MySQL
tests at *your own* server while the script was iterating over PostgreSQL images. The
connection variables the script exports take precedence over `Test/.env`, so the tests of the
family being iterated always talk to the container.

### Run against a database you have already started

If you have a server running elsewhere (local install, another container, CI service), set
the connection variables directly and invoke PHPUnit. The two families are independent — set
one group, the other family's tests skip themselves:

```bash
export INTEGRATION_DB_HOST=127.0.0.1     # MySQL / MariaDB
export INTEGRATION_DB_PORT=3306
export INTEGRATION_DB_USER=root
export INTEGRATION_DB_PASSWORD=secret
export INTEGRATION_DB_NAME=akeebatest

vendor/bin/phpunit --configuration phpunit.integration.xml --testsuite drivers
```

### Connection environment variables

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `INTEGRATION_DB_HOST` | **yes**, for MySQL/MariaDB | — | Hostname or IP. Absent ⇒ `MysqliTest` and `PdomysqlTest` skip |
| `INTEGRATION_DB_PORT` | no | `3306` | TCP port |
| `INTEGRATION_DB_USER` | no | `root` | Database user |
| `INTEGRATION_DB_PASSWORD` | no | *(empty)* | Database password |
| `INTEGRATION_DB_NAME` | no | `akeebatest` | Database name (must already exist) |
| `INTEGRATION_PGSQL_HOST` | **yes**, for PostgreSQL | — | Hostname or IP. Absent ⇒ `PostgresqlTest` skips |
| `INTEGRATION_PGSQL_PORT` | no | `5432` | TCP port |
| `INTEGRATION_PGSQL_USER` | no | `postgres` | Database user |
| `INTEGRATION_PGSQL_PASSWORD` | no | *(empty)* | Database password |
| `INTEGRATION_PGSQL_NAME` | no | `akeebatest` | Database name (must already exist) |

### What the MySQL driver integration tests cover

`AbstractMysqlDriverTestCase` contains 32 tests that are run for both `Mysqli`
and `Pdomysql`. The suite uses the table prefix `test_` and creates a working
table `test_enginetest` for the duration of the run.

| Category | Tests |
|----------|-------|
| Connection | `testConnected`, `testDriverType`, `testGetVersion` |
| Result loading | `testLoadResult`, `testLoadAssoc`, `testLoadAssocList`, `testLoadAssocListWithKey`, `testLoadRow`, `testLoadRowList`, `testLoadObject`, `testLoadObjectList`, `testLoadColumn` |
| Data manipulation | `testInsertObject`, `testInsertid`, `testGetAffectedRowsAfterInsert`, `testUpdateObject` |
| Schema introspection | `testGetTableList`, `testGetTableColumns`, `testGetTableCreate`, `testGetTableKeys` |
| Transactions | `testTransactionCommit`, `testTransactionRollback` |
| Escaping / quoting | `testEscape`, `testEscapeNull`, `testEscapeExtra`, `testQuote`, `testQuoteRoundTrip`, `testQuoteName`, `testQuoteNameDotNotation` |
| Numeric fidelity | `testDoublePrecisionIsNotLost` |
| Prefix replacement | `testReplacePrefix`, `testReplacePrefixPreservesStringLiterals` |

### What the PostgreSQL driver integration tests cover

`PostgresqlTest` is a **standalone case**, not a subclass of the MySQL one: the SQL dialect
differs (`SERIAL` rather than `AUTO_INCREMENT`, double-quoted rather than backtick-quoted
identifiers, `TRUNCATE … RESTART IDENTITY`), and so does the driver's type contract. It
covers the same ground — connection, result loading, writing, schema introspection,
transactions, escaping/quoting — plus `testDoublePrecisionIsNotLost` and
`testDoubleSurvivesDumpRoundTrip`. `getTableCreate()` and `getTableKeys()` are stubs on this
driver, so they are not tested.

Two behaviours differ from MySQL and are asserted as such:

- **Escaping is SQL-standard**, doubling the apostrophe (`it''s a test`), where MySQL
  backslash-escapes it (`it\'s a test`).
- **The driver returns native PHP types** (`int`, `float`, `bool`), where the MySQL drivers
  return strings. This is not an oversight: PDO PostgreSQL parses the value into a native
  double *before* `ATTR_STRINGIFY_FETCHES` would stringify it, so asking it for strings hands
  back an already-truncated value rather than the server's original text. The MySQL drivers
  *can* be asked for the wire text and are (`ATTR_STRINGIFY_FETCHES`). Both are made safe for
  the dump by `Driver\Base::floatToSqlString()`, which renders a float at full round-trip
  precision instead of letting an ordinary string cast truncate it to PHP's `precision` ini
  (14 significant digits, against the 17 a double can need). The two `testDouble*` tests are
  what pin that down — they fail loudly if either driver regresses.

---

## Misbehaving-file backup integration test

`Test/Integration/Backup/MisbehavingFileTest` verifies that the backup engine reacts
correctly when a file changes size — or vanishes — while it is being put into the archive.
It takes a real, files-only backup of the `dev_platform/` directory (which doubles as the
backup "site root") while `dev_platform/makebigfile.php` concurrently misbehaves with a
15 MiB file, `dev_platform/misbehaving.dat`:

| Scenario | What `makebigfile.php` does | Expected engine behaviour |
|----------|-----------------------------|---------------------------|
| `testGrowingFile`     | Grows the file from 15 MiB towards 20 MiB | Backs up the recorded amount of data, warns the file *grew*, finishes **successfully**, and the (partial) archive is extractable |
| `testShrinkingFile`   | Shrinks the file from 15 MiB towards 10 MiB | **Fails** the backup with a *shrunk* error and a *shrunk during backup* warning |
| `testDisappearingFile`| Deletes the file mid-backup | **Fails** the backup with a *went away* error |

The test drives the `dev_platform` CLI (`backup:take --jsonl`) and `makebigfile.php` as
sub-processes. It creates and tears down its own dedicated backup profile, writes archives
to a temporary directory, and cleans up `misbehaving.dat` afterwards.

### Requirements

- `dev_platform` initialised once: `php dev_platform/index.php init`
- A Unix-like OS with `/dev/null` and `/dev/urandom` (Linux, macOS)
- For the `testGrowingFile` extraction check: **Akeeba Kickstart** (its dry-run mode is
  enough). If Kickstart is not found the extraction step is reported as *incomplete*, not
  failed — the rest of the scenario still runs.

### Run it

```bash
INTEGRATION_BACKUP=1 vendor/bin/phpunit --configuration phpunit.integration.xml \
    Test/Integration/Backup/MisbehavingFileTest.php
```

(or set `INTEGRATION_BACKUP=1` in `Test/.env`).

### Environment variables

This test races a background process against the backup engine, so its timing knobs are
configurable. The defaults give a ~4.5 s window and work on a typical developer machine;
tune them if a scenario reports *incomplete* because the window was missed.

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `INTEGRATION_BACKUP` | **yes** | — | Master switch. Truthy (`1`/`true`/`yes`/`on`) enables the test |
| `AKEEBA_DEBUG_BIG_FILE_MULTIPART_DELAY` | no | `300000` | Per-chunk delay (µs) the engine waits while archiving a file. Slows the backup so the engine is still reading the file as it changes. Passed through to the `dev_platform` CLI |
| `BACKUP_MISBEHAVE_DELAY` | no | `80` | `makebigfile.php` delay (ms) between size changes in `grow`/`shrink` mode |
| `BACKUP_NUKE_DELAY` | no | `2000` | `makebigfile.php` delay (ms) before deleting the file in `nuke` mode. Tune so the file disappears *while* the engine is reading it |
| `KICKSTART_PATH` | no | `~/Projects/kickstart/output` | `kickstart.php` / `kickstart_core.php`, or a directory containing one of them, used to test-extract the growing-file archive (CLI dry-run) |

---

## Remote storage provider integration tests

`Test/Integration/Postproc/` covers the remote storage (post-processing) engines under
`engine/Postproc/` — BackBlaze B2, Amazon S3, Dropbox, … — against the live services.

The tests run at the level of the uniform `PostProcInterface` (the same code path the backup
engine uses), not the provider-specific connectors. That is what lets a single blueprint cover
every provider: `AbstractPostprocTestCase` holds all the shared logic, and each provider needs
only a small concrete subclass.

For each provider the lifecycle is:

1. **Upload** a small file (256 KiB, single-shot) and a large file (> 2× the provider's minimum
   chunk size, forcing a real multipart upload), driving the engine's stepped `processPart()` loop
   exactly as a real backup would.
2. **Verify the stored size** independently of the engine (when the provider supports it).
3. **Download** the file back and assert its size *and* SHA-512 checksum match the local original.
4. **Delete** it and confirm it is gone.

Beyond the lifecycle, each provider's concrete test class should also cover the connector's
**informational** methods — anything that fetches account information, lists buckets/drives, or
returns a signed/public download URL (verified by actually downloading through it). These are
provider-specific, so they live in the subclass, not the shared base. Capability-gated calls
(e.g. a bucket-scoped key that cannot list buckets) should self-skip rather than fail.

Each provider self-skips unless all of its credentials are present in the environment, and every
test cleans up its own throw-away objects.

### Failure surfacing (the OAuth2 providers)

`BoxTest` and `Dropbox2Test` also assert that a *broken* configuration is reported in terms the user can act on. This is
easy to get wrong, because the akeeba.com refresh relay reports every one of these failures as an error payload carrying
an **HTTP 200** status — so nothing throws on its own, and a connector which does not notice that no access token came
back will happily keep using the dead one. Box did exactly that: a lapsed authorisation surfaced as a bare
`Unexpected HTTP status 401` from whatever call happened to run next, telling the user nothing.

Each provider therefore covers three rejections — an invalid refresh token, an absent Download ID, and an invalid one
(what a lapsed subscription looks like) — and asserts the resulting exception names both the cause and the remedy. A
fourth test covers the engine's own guard, which refuses to build a connector at all when no Download ID is configured.

These tests always use a **deliberately invalid refresh token**. They must never send the real one: a *successful*
refresh would spend it (Box rotates on every use) and strand the rest of the suite.

When adding a provider, **document which connector methods are *not* covered** (in a coverage-map
docblock on the test class, as `BackblazeTest` does) so the uncovered surface is an explicit,
reviewable decision rather than an accident.

### Amazon S3 (live AWS)

Set `AWS_S3_ACCESS_KEY`, `AWS_S3_SECRET_KEY` and `AWS_S3_BUCKET` (see `Test/.env.sample`) and run:

```bash
vendor/bin/phpunit Test/Integration/Postproc/Amazons3Test.php
```

| Variable | Required | Description |
|----------|----------|-------------|
| `AWS_S3_ACCESS_KEY` | **yes** | AWS access key ID. Must allow put/get/delete and multipart on the bucket |
| `AWS_S3_SECRET_KEY` | **yes** | AWS secret access key paired with the access key |
| `AWS_S3_BUCKET` | **yes** | Bucket **name**. Objects are stored under the `akeeba-engine-test` prefix |
| `AWS_S3_REGION` | no | Bucket region (default `us-east-1`). **Must** match the bucket — v4 signatures are region-scoped |
| `AWS_S3_SIGNATURE` | no | Signature method, `v4` (default) or `v2` |

Unlike Google Storage, the Amazon S3 engine performs genuine multipart uploads, so the large-file test asserts a
real multi-step (multipart) upload. The default `bucket-owner-full-control` ACL is used, which AWS still accepts
even on buckets with ACLs disabled (Object Ownership = Bucket owner enforced).

### Amazon S3 against Minio (Docker)

This proves the Amazon S3 engine works against an S3-compatible service reached through a **custom endpoint** with
path-style addressing, and needs **no cloud credentials** — it spins up a throw-away [Minio](https://min.io/)
server in Docker, runs the full multipart lifecycle against it, and removes the container afterwards. It is the
only Amazon S3 test that exercises the genuine multipart path without live AWS credentials.

It is **opt-in**: it self-skips unless `MINIO_TEST` is truthy **and** Docker is installed and running.

```bash
MINIO_TEST=1 vendor/bin/phpunit Test/Integration/Postproc/Amazons3MinioTest.php
```

| Variable | Required | Description |
|----------|----------|-------------|
| `MINIO_TEST` | **yes** | Master opt-in switch. Set to `1`/`true`/`yes`/`on` to enable. Docker must also be available |
| `MINIO_IMAGE` | no | Docker image to run (default `minio/minio:latest`) |
| `MINIO_ROOT_USER` | no | Minio root access key (default `minioadmin`) |
| `MINIO_ROOT_PASSWORD` | no | Minio root secret key (default `minioadmin`) |
| `MINIO_BUCKET` | no | Bucket name to create and use (default `akeeba-engine-test-bucket`) |

The container is started on a random loopback port, the bucket is created through a PUT-bucket S3 request (no
`mc`/shell dependency), and everything is torn down in `tearDownAfterClass()`. If Docker is missing or the daemon
is down, the whole suite reports skipped with a clear reason.

### WebDAV (Docker)

This proves the WebDAV engine works against a real WebDAV server, and needs **no external account** — it spins up a
throw-away [`rclone serve webdav`](https://rclone.org/commands/rclone_serve_webdav/) server in Docker, runs the full
lifecycle against it, and removes the container afterwards. The engine creates the remote directory itself (MKCOL), so
that path is exercised rather than bypassed.

The default `rclone/rclone` image is **multi-architecture** (linux/amd64 **and** linux/arm64), so it runs natively on
Apple Silicon with no Rosetta/QEMU emulation — important since macOS is dropping Rosetta.

It is **opt-in**: it self-skips unless `WEBDAV_TEST` is truthy **and** Docker is installed and running.

```bash
WEBDAV_TEST=1 vendor/bin/phpunit Test/Integration/Postproc/WebdavTest.php
```

| Variable | Required | Description |
|----------|----------|-------------|
| `WEBDAV_TEST` | **yes** | Master opt-in switch. Set to `1`/`true`/`yes`/`on` to enable. Docker must also be available |
| `WEBDAV_IMAGE` | no | Docker image to run (default `rclone/rclone:latest`). An override must run `serve webdav` from the same CLI flags |
| `WEBDAV_USERNAME` | no | WebDAV username the container is configured with (default `akeeba`) |
| `WEBDAV_PASSWORD` | no | WebDAV password the container is configured with (default `test-password`) |

The WebDAV engine uploads each file in a single PUT (no multipart), so the large-file test verifies a multi-megabyte
single PUT byte-for-byte but does not assert a multi-step upload. The server speaks Basic auth over plain HTTP on a
loopback-only port, which is safe for a throw-away local container. If Docker is missing or the daemon is down, the
whole suite reports skipped with a clear reason.

### OpenStack Swift (Docker)

This proves the Swift engine works against a real OpenStack [Keystone](https://docs.openstack.org/keystone/) identity
service and a real [Swift](https://docs.openstack.org/swift/) object store, and needs **no external account** — it spins
up a throw-away [`jeantil/openstack-keystone-swift`](https://github.com/jeantil/openstack-swift-keystone-docker) server
in Docker, runs the full lifecycle against it, and removes the container afterwards. The image bundles both services and
serves Keystone Identity **v2 and v3**, so the single test exercises both of the connector's authentication code paths;
the upload/download/delete lifecycle itself runs over Keystone v3.

The default image is published for **linux/amd64 only**, so on Apple Silicon it runs under Docker's emulation — slower,
but functional. (No multi-architecture Keystone+Swift image is readily available; if you have one, point `SWIFT_IMAGE`
at it.) If the container cannot start at all, the suite skips with a clear reason instead of failing.

It is **opt-in**: it self-skips unless `SWIFT_TEST` is truthy **and** Docker is installed and running.

```bash
SWIFT_TEST=1 vendor/bin/phpunit Test/Integration/Postproc/SwiftTest.php
```

| Variable | Required | Description |
|----------|----------|-------------|
| `SWIFT_TEST` | **yes** | Master opt-in switch. Set to `1`/`true`/`yes`/`on` to enable. Docker must also be available |
| `SWIFT_IMAGE` | no | Docker image to run (default `jeantil/openstack-keystone-swift:pike`). An override must serve Keystone v2/v3 on 35357 and Swift on 8080 |
| `SWIFT_USERNAME` | no | OpenStack username the image is seeded with (default `demo`) |
| `SWIFT_PASSWORD` | no | OpenStack password the image is seeded with (default `demo`) |
| `SWIFT_PROJECT` | no | Project (tenant) name the demo user belongs to (default `test`) |
| `SWIFT_DOMAIN` | no | Keystone domain (default `Default`) |
| `SWIFT_CONTAINER` | no | Container name the test creates and uses (default `akeeba-engine-test`) |
| `SWIFT_RESELLER_PREFIX` | no | Swift account prefix, used only if the Keystone catalog has no object-store endpoint (default `KEY_`) |

The Swift engine uploads each file in a single PUT (no multipart), so the large-file test verifies a multi-megabyte
single PUT byte-for-byte but does not assert a multi-step upload. The test authenticates against Keystone, discovers the
demo project's ID (needed both as the connector's tenant ID and as the Swift account in the storage URL), creates the
container, and tears everything down afterwards. Everything speaks plain HTTP on loopback-only ports, which is safe for a
throw-away local container.

### FTP, native and over cURL (Docker)

This proves the two FTP engines — native (PHP `ext/ftp`, `FtpTest`) and cURL (`FtpcurlTest`) — work against a real FTP
server, and needs **no external account** — it builds a minimal `alpine:3.20` + [`pure-ftpd`](https://www.pureftpd.org/)
image on the fly from `Test/_docker/pure-ftpd`, runs the full upload/download/delete lifecycle against it, and removes the
container afterwards. The image is multi-architecture, so it runs natively on Apple Silicon (no emulation).

Both tests are **opt-in**: they self-skip unless `FTP_TEST` is truthy **and** Docker is installed and running. `FtpTest`
additionally skips when the PHP `ftp` extension is missing; `FtpcurlTest` additionally skips when cURL has no FTP support.

```bash
FTP_TEST=1 vendor/bin/phpunit Test/Integration/Postproc/FtpTest.php
FTP_TEST=1 vendor/bin/phpunit Test/Integration/Postproc/FtpcurlTest.php
```

| Variable | Required | Description |
|----------|----------|-------------|
| `FTP_TEST` | **yes** | Master opt-in switch. Set to `1`/`true`/`yes`/`on` to enable. Docker must also be available |
| `FTP_IMAGE` | no | Docker image to run. Default: built locally from `Test/_docker/pure-ftpd`. An override must honour `PUBLICHOST`/`FTP_USER_NAME`/`FTP_USER_PASS`/`FTP_USER_HOME`/`FTP_PASSIVE_PORTS` and publish ports 30000-30009 |
| `FTP_USERNAME` | no | FTP username the container is configured with (default `akeeba`) |
| `FTP_PASSWORD` | no | FTP password the container is configured with (default `test-password`) |

The FTP engines upload each file in a single `STOR` (no multipart), so the large-file test verifies a multi-megabyte
transfer byte-for-byte but does not assert a multi-step upload. The test picks a free host control port in the
**4000-4999** range (probed with shell tools, never by opening a socket from PHP, since the host may forbid that) and
publishes **both** the control port and the passive data ports (30000-30009) on `127.0.0.1`. Publishing them on the same
interface is essential: otherwise Docker forwards them through different internal networks, the passive data connection
reaches pure-ftpd from a different source IP than the control connection, and pure-ftpd's anti-bounce check silently
drops every transfer. If Docker is missing or the daemon is down, the suite skips with a clear reason.

### SFTP, native and over cURL (Docker)

This proves the two SFTP engines — native (PHP `ext/ssh2`, `SftpTest`) and cURL (`SftpcurlTest`) — work against a real
SFTP server, and needs **no external account** — it runs the stock
[`jmcombs/sftp`](https://hub.docker.com/r/jmcombs/sftp) image (a multi-architecture drop-in fork of
[`atmoz/sftp`](https://hub.docker.com/r/atmoz/sftp), so it runs natively on Apple Silicon), runs the full
upload/download/delete lifecycle against it, and removes the container afterwards.

Both tests are **opt-in**: they self-skip unless `SFTP_TEST` is truthy **and** Docker is installed and running.
`SftpTest` additionally skips when the PHP `ssh2` extension is missing; `SftpcurlTest` additionally skips when cURL has
no SFTP support (libcurl built without libssh2/libssh).

```bash
SFTP_TEST=1 vendor/bin/phpunit Test/Integration/Postproc/SftpTest.php
SFTP_TEST=1 vendor/bin/phpunit Test/Integration/Postproc/SftpcurlTest.php
```

| Variable | Required | Description |
|----------|----------|-------------|
| `SFTP_TEST` | **yes** | Master opt-in switch. Set to `1`/`true`/`yes`/`on` to enable. Docker must also be available |
| `SFTP_IMAGE` | no | Docker image to run (default `jmcombs/sftp:latest`). An override must take a `<user>:<pass>:::<dir>` command argument and chroot the user with a writable `<dir>` sub-directory (e.g. `atmoz/sftp`, which is amd64-only) |
| `SFTP_USERNAME` | no | SFTP username the container is configured with (default `akeeba`) |
| `SFTP_PASSWORD` | no | SFTP password the container is configured with (default `test-password`) |

The SFTP engines upload each file in a single transfer (no multipart), so the large-file test verifies a multi-megabyte
transfer byte-for-byte but does not assert a multi-step upload. They also do not support `downloadToBrowser()` (there is
no credentials-in-URL scheme a browser could follow for SFTP). SFTP is a single TCP connection, so — unlike FTP — there
are no passive ports: the test picks a free host port in the **4000-4999** range (probed with shell tools, never by
opening a socket from PHP, since the host may forbid that) and publishes only the SSH port on `127.0.0.1`. atmoz/sftp
chroots the virtual user to its home and only lets it write inside a `/upload` sub-directory it creates, so the engine's
remote directory is placed there (`/upload/akeeba-engine-test`) and the engine creates it itself, exercising its mkdir
path. If Docker is missing or the daemon is down, the suite skips with a clear reason.

### OVH Object Storage (live OVH)

OVH Object Storage is OpenStack [Swift](https://docs.openstack.org/swift/) behind OVH's public
[Keystone](https://docs.openstack.org/keystone/) **v3** identity service. The OVH connector hard-codes OVH's
authentication endpoint (`https://auth.cloud.ovh.net`) and the `Default` domain, so — unlike the generic Swift test —
there is no local stand-in: this test can only run against a real OVH project and is gated on live credentials, like the
live-AWS Amazon S3 test.

Create an OpenStack user in the OVH Public Cloud panel (*Project Management → Users & Roles*) to obtain the OpenStack
username/password and the Project ID, then set `OVH_PROJECTID`, `OVH_USERNAME`, `OVH_PASSWORD` and `OVH_CONTAINERURL`
(see `Test/.env.sample`) and run:

```bash
vendor/bin/phpunit Test/Integration/Postproc/OvhTest.php
```

| Variable | Required | Description |
|----------|----------|-------------|
| `OVH_PROJECTID` | **yes** | OVH OpenStack Project (tenant) ID |
| `OVH_USERNAME` | **yes** | OVH OpenStack username |
| `OVH_PASSWORD` | **yes** | OVH OpenStack password |
| `OVH_CONTAINERURL` | **yes** | Full storage URL of an **existing** container, e.g. `https://storage.gra.cloud.ovh.net/v1/AUTH_xx…/my-container`. The engine never creates the container; objects are stored under the `akeeba-engine-test` pseudo-folder |

OVH (OpenStack Swift) uploads each object in a single PUT, so the large-file test verifies a large single-shot upload
rather than a multipart one.

### BackBlaze B2

Set `BACKBLAZE_ID`, `BACKBLAZE_KEY` and `BACKBLAZE_BUCKET` (see `Test/.env.sample`) and run:

```bash
vendor/bin/phpunit Test/Integration/Postproc/BackblazeTest.php
```

| Variable | Required | Description |
|----------|----------|-------------|
| `BACKBLAZE_ID` | **yes** | Application Key ID |
| `BACKBLAZE_KEY` | **yes** | Application Key secret. Must allow read, write and delete on the bucket |
| `BACKBLAZE_BUCKET` | **yes** | Bucket **name** (not its ID). Objects are stored under the `akeeba-engine-test` prefix |

B2 puts the API version in the URL path, and the connector pins every call to the version in
`Backblaze::apiVersion`. Two further test classes cover application keys whose `allowed` information is shaped
differently from the unrestricted key above. Both self-skip when their variables are unset, and both need keys you have
to create by hand — an unrestricted key cannot stand in for either, because it is precisely the restriction that
triggers the code paths they cover.

#### Bucket-restricted key

`BackblazeRestrictedTest` is the regression test for the v4 authorization bug where a least-privilege key could not
resolve its own bucket. `getBucketId()` reads the bucket ID straight out of the key's `allowed` information, and only
falls back to `b2_list_buckets` if that misses. A key with no `listBuckets` capability has no fallback, so a miss is
fatal — which is what makes the bug visible here and invisible in `BackblazeTest`.

```bash
vendor/bin/phpunit Test/Integration/Postproc/BackblazeRestrictedTest.php
```

| Variable | Required | Description |
|----------|----------|-------------|
| `BACKBLAZE_RESTRICTED_ID` | **yes** | Key ID of a key restricted to **exactly one** bucket |
| `BACKBLAZE_RESTRICTED_KEY` | **yes** | Its secret. Needs `writeFiles`, `readFiles` and `deleteFiles`, and must **not** have `listBuckets` |
| `BACKBLAZE_RESTRICTED_BUCKET` | **yes** | The bucket **name** that key is locked to |

#### Multi-bucket key

`BackblazeMultiBucketTest` is the regression test for the v4 multi-bucket key bug. A key may now be restricted to
several buckets, which the API reports as an `allowed.buckets[]` array; the code used to compare only against the first
entry, so any *other* allowed bucket failed to resolve and fell through to `b2_list_buckets` — which since API v2
refuses an unfiltered listing for a restricted key. The test drives its whole lifecycle against **BUCKET2**: targeting
BUCKET1 would pass even with the bug in place.

**This test mints its own key.** You cannot supply one: the B2 web console offers "all buckets" or exactly *one* bucket,
so a multi-bucket key can only be created through the `b2_create_key` API (v4 takes a plural `bucketIds` array; the
singular `bucketId` was removed). The test creates a short-lived key from your `BACKBLAZE_ID` / `BACKBLAZE_KEY`, scoped
to two buckets your account already has, and deletes it on teardown. It also carries a ten-minute
`validDurationInSeconds`, so B2 expires it by itself if the run is killed before teardown. The key lives in memory only
— never written to disk, never logged — and no buckets are created or destroyed.

Because it creates a real credential on a real account it is **opt-in and off by default**:

```bash
BACKBLAZE_MULTIBUCKET_TEST=1 vendor/bin/phpunit Test/Integration/Postproc/BackblazeMultiBucketTest.php
```

| Variable | Required | Description |
|----------|----------|-------------|
| `BACKBLAZE_MULTIBUCKET_TEST` | **yes** | Opt in (`1`, `true`, `yes`, `on`). Without it the test skips |
| `BACKBLAZE_ID` / `BACKBLAZE_KEY` | **yes** | Reused from above. Must have the `writeKeys` and `deleteKeys` capabilities |
| `BACKBLAZE_MULTIBUCKET_BUCKET1` | no | Pin the first bucket the minted key may access. Defaults to `BACKBLAZE_BUCKET` |
| `BACKBLAZE_MULTIBUCKET_BUCKET2` | no | Pin the second — the one the test uploads to. Defaults to any other bucket in the account |

The account needs at least two buckets; the test skips with an explanatory message if it has fewer, or if the key lacks
`writeKeys`/`deleteKeys`.

### Box

Set `BOX_ACCESS_TOKEN`, `BOX_REFRESH_TOKEN` and `BOX_DLID` (see `Test/.env.sample`) and run:

```bash
vendor/bin/phpunit Test/Integration/Postproc/BoxTest.php
```

| Variable | Required | Description |
|----------|----------|-------------|
| `BOX_ACCESS_TOKEN` | **yes** | Box OAuth2 access token (short-lived ~60 min). Must allow read, write and delete |
| `BOX_REFRESH_TOKEN` | **yes** | Box OAuth2 refresh token (used to renew the access token) |
| `BOX_DLID` | **yes** | akeeba.com Download ID, used to relay OAuth2 token refresh through akeeba.com |

Objects are stored in the `akeeba-engine-test` folder at the account root. Box uploads in a single shot (no
multipart), so the large-file test verifies a large single-shot upload.

#### Box rewrites your `Test/.env`, on purpose

Box's tokens behave unlike any other provider here, and the test has to play along. The access token lasts about an
hour, and the refresh token is **single-use**: every refresh mints a replacement and kills the one just used (they also
lapse after 60 days of disuse). A refresh is therefore not free — it *spends* a credential.

So `BoxTest` keeps exactly one live pair, shared by the engine's connector and the test's own verification connector,
and **writes whatever pair it ends on back into `Test/.env`**. Without that the suite would eat its own credentials: the
first refresh spends what is in the file, the replacement dies with the process, and every later run starts from a token
Box has already invalidated. (That is precisely the state the test was found in — every Box test failing with an opaque
HTTP 401.) If you keep your tokens in the real environment rather than in `Test/.env`, nothing is written and you must
carry the rotation across runs yourself.

#### Minting a fresh Box token pair

When `BOX_REFRESH_TOKEN` has expired or been spent, no code change can revive it — you have to re-authorise. Open the
akeeba.com OAuth2 relay in a browser, pointing `callback` at somewhere you can read the result back from:

```
https://www.akeeba.com/oauth2/box.php?callback=<url-encoded return URL>&dlid=<your Download ID>
```

It redirects to Box's consent screen and, on approval, back to your `callback` with the freshly minted tokens. Put them
in `Test/.env` as `BOX_ACCESS_TOKEN` and `BOX_REFRESH_TOKEN`. Without a valid `dlid` the relay answers `402`.

### RackSpace CloudFiles

Set `CLOUDFILES_USERNAME`, `CLOUDFILES_APIKEY` and `CLOUDFILES_CONTAINER` (see `Test/.env.sample`) and run:

```bash
vendor/bin/phpunit Test/Integration/Postproc/CloudfilesTest.php
```

| Variable | Required | Description |
|----------|----------|-------------|
| `CLOUDFILES_USERNAME` | **yes** | RackSpace Cloud account username |
| `CLOUDFILES_APIKEY` | **yes** | RackSpace Cloud API key (Account Settings → API Key), not the password |
| `CLOUDFILES_CONTAINER` | **yes** | Container **name**; it must already exist. Objects are stored under the `akeeba-engine-test` prefix |

The region and storage endpoint are auto-detected from the authentication response, so no region variable is
required. CloudFiles (OpenStack SWIFT) uploads each object in a single PUT, so the large-file test is a large
single-shot upload rather than a multipart one.

### Dropbox

Set `DROPBOX_DLID` plus at least one of `DROPBOX_ACCESS_TOKEN` / `DROPBOX_REFRESH_TOKEN` (see `Test/.env.sample`) and run:

```bash
vendor/bin/phpunit Test/Integration/Postproc/Dropbox2Test.php
```

| Variable | Required | Description |
|----------|----------|-------------|
| `DROPBOX_ACCESS_TOKEN` | one of the two token vars | Short-lived OAuth2 access token. Needs files read/write, metadata, sharing and account_info scopes |
| `DROPBOX_REFRESH_TOKEN` | one of the two token vars | Long-lived refresh token (recommended; required for the token-refresh test) |
| `DROPBOX_DLID` | **yes** | akeeba.com Download ID. The engine will not run without it |
| `DROPBOX_TEAM` | no | Set to `1` for a Dropbox Business team-space root; defaults to the personal namespace |

Objects are stored in the `akeeba-engine-test` folder.

### Google Drive

Set `GOOGLEDRIVE_ACCESS_TOKEN`, `GOOGLEDRIVE_REFRESH_TOKEN` and `GOOGLEDRIVE_DLID` (see `Test/.env.sample`) and run:

```bash
vendor/bin/phpunit Test/Integration/Postproc/GoogledriveTest.php
```

| Variable | Required | Description |
|----------|----------|-------------|
| `GOOGLEDRIVE_ACCESS_TOKEN` | **yes** | OAuth2 access token (auto-refreshed if stale). Needs the full `drive` scope |
| `GOOGLEDRIVE_REFRESH_TOKEN` | **yes** | OAuth2 refresh token |
| `GOOGLEDRIVE_DLID` | **yes** | akeeba.com Download ID, used to relay OAuth2 token refresh through akeeba.com |
| `GOOGLEDRIVE_TEAM_DRIVE` | no | Shared (Team) Drive ID; empty = personal Drive |

Objects are stored in the `akeeba-engine-test` folder. The first folder-creating run is slower (Google Drive needs
a short delay before a new folder is resolvable by name).

### Google Storage

Set `GOOGLESTORAGE_ACCESS_KEY`, `GOOGLESTORAGE_SECRET_KEY` and `GOOGLESTORAGE_BUCKET` (see `Test/.env.sample`) and run:

```bash
vendor/bin/phpunit Test/Integration/Postproc/GooglestorageTest.php
```

| Variable | Required | Description |
|----------|----------|-------------|
| `GOOGLESTORAGE_ACCESS_KEY` | **yes** | HMAC **interoperability** access key (Cloud Console → Cloud Storage → Settings → Interoperability) |
| `GOOGLESTORAGE_SECRET_KEY` | **yes** | HMAC interoperability secret paired with the access key |
| `GOOGLESTORAGE_BUCKET` | **yes** | Bucket **name** (not a URL). Objects are stored under the `akeeba-engine-test` prefix |
| `GOOGLESTORAGE_USESSL` | no | Set to `0` to talk HTTP instead of HTTPS (debugging only) |

This is the legacy S3-interoperability engine (HMAC keys), not the JSON/service-account engine. It hard-disables
multipart, so the large-file test is a large single-shot upload.

### OneDrive for Business

Set `ONEDRIVEBUSINESS_ACCESS_TOKEN`, `ONEDRIVEBUSINESS_REFRESH_TOKEN` and `ONEDRIVEBUSINESS_DLID` (see `Test/.env.sample`) and run:

```bash
vendor/bin/phpunit Test/Integration/Postproc/OnedrivebusinessTest.php
```

| Variable | Required | Description |
|----------|----------|-------------|
| `ONEDRIVEBUSINESS_ACCESS_TOKEN` | **yes** | Microsoft Graph OAuth2 access token (auto-refreshed if stale) |
| `ONEDRIVEBUSINESS_REFRESH_TOKEN` | **yes** | OAuth2 refresh token |
| `ONEDRIVEBUSINESS_DLID` | **yes** | akeeba.com Download ID, used to relay OAuth2 token refresh through akeeba.com |
| `ONEDRIVEBUSINESS_DRIVE` | no | Target Drive ID (OneDrive for Business or SharePoint library); empty = personal/default Drive |
| `ONEDRIVEBUSINESS_TEST_REFRESH` | no | Set to `1` to also run the (destructive) token-refresh test, which may rotate the refresh token |

Objects are stored under the `akeeba-engine-test` prefix. The legacy `OneDrive` and `OneDriveApp` engines are
deprecated and are not tested.

### Adding another provider

Create `Test/Integration/Postproc/<Provider>Test.php` extending `AbstractPostprocTestCase` and
implement its hooks: `getEngineSlug()`, `isProviderConfigured()`, `getSkipMessage()`,
`configureProvider()` (set the `engine.postproc.<slug>.*` keys), `getMinimumPartSize()`, and
`getRemoteSize()` (return `null` to skip the independent size check). Then add concrete tests for
the connector's account-info / listing / signed-URL methods, and a coverage-map docblock listing
what is *not* covered. `uploadTemporaryObject()` on the base gives you a remote object to test
against, and `registerRemoteObjectForCleanup()` registers objects you create directly through the
connector so teardown deletes them. If the engine never segments large uploads (single-shot only),
override `supportsMultipart()` to return `false` so the large-file test skips the multi-step
assertion. If the engine reads the akeeba.com Download ID from the platform (the OAuth providers do,
via the `update_dlid` option), seed it in `configureProvider()` with
`$this->platform()->setConfigurationOption('update_dlid', …)`. PHPUnit discovers the new class
automatically.

---

## Writing new tests

### Unit test

1. Create `Test/<Subsystem>/YourClassTest.php` in the namespace
   `Akeeba\Engine\Test\<Subsystem>`.
2. Extend `PHPUnit\Framework\TestCase` (or `AbstractEngineTestCase` if Factory/Platform
   is needed).
3. Name every test method `test*` and add it to the default run — no extra
   configuration needed; PHPUnit discovers it automatically.

### Data provider

Place the provider in a separate `*Provider.php` file in the same directory.
Reference it with the `@dataProvider` annotation:

```php
/**
 * @dataProvider \Akeeba\Engine\Test\MySubsystem\MyProvider::myProvider()
 */
public function testSomething($input, $expected): void { … }
```

### Integration test for a new service

1. Create `Test/Integration/<Subsystem>/AbstractYourServiceTestCase.php` with
   the shared test logic. Guard the class with a `setUpBeforeClass` that calls
   `$this->markTestSkipped()` when the relevant `INTEGRATION_*` environment
   variable is absent.
2. Add concrete subclasses for each driver or backend variant, implementing the
   factory method that instantiates the class under test.
3. PHPUnit discovers the new tests automatically via `phpunit.integration.xml`.
   No changes to that file are required unless you need a separate test suite.
