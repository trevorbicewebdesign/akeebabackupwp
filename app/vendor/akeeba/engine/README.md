# Akeeba Engine

The backup engine for Akeeba Backup and Akeeba Solo

## Constants

**`AKEEBA_BACKUP_ORIGIN`**

Overrides the backup origin (e.g. `backend`, `cli`, etc)

**`AKEEBA_CHUNK`**

Overrides the `engine.archiver.common.chunk_size` setting, i.e. how many bytes to read when processing large files.

**`AKEEBA_NO_PROXY_AWARE`**

Set to true to skip the proxy auto-detection

**`AKEEBA_CACERT_PEM`**

Overrides the location of the `cacert.pem` file if the platform-specific one cannot be found.

**`AKEEBA_PRO`**
**`AKEEBABACKUP_PRO`**

Set to 1 or true to tell the engine to look for configuration files in the `Config/Pro` directory (instead of just the `Config` directory).

**`AKEEBA_VERSION`**
**`AKEEBABACKUP_VERSION`**

Sets the reported Akeeba Backup version. Also used by the `[VERSION]` variable.

**`AKEEBA_SERVERKEY`**

Settings encryption key. Normally read from the platform-specific key file, e.g. `serverkey.php`.

**`_AKEEBA_COMPRESSION_THRESHOLD`**

Overrides the `engine.archiver.common.big_file_threshold` setting, i.e. the size in bytes at which point a file is considered "big".

**`_AKEEBA_DIRECTORY_READ_CHUNK`**

Overrides the `engine.archiver.zip.cd_glue_chunk_size` setting, i.e. the chunk size in bytes for processing the ZIP files' Central Directory records.

**`_AKEEBA_IS_WINDOWS`**

Are we running under Microsoft Windows?

## Magic files and constants for testing

**File `[SITE_ROOT]/.akeeba_engine_automated_tests_error`**

Makes the file packing step throw an exception.

**Constant `AKEEBA_BACKUP_TESTING_STEP_THROTTLING`**

How many μsec to sleep before executing each step. This is used to throttle the backup, simulating slow servers.