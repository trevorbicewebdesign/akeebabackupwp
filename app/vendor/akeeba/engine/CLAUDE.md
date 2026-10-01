# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Akeeba Engine is a PHP site backup library. It runs a backup as a *stepped* process, spread over
multiple HTTP requests, serializing and deserializing its own state between steps — most of the
engine's design follows from that constraint.

## Commands

```bash
# Unit tests (phpunit.xml already covers Test/)
vendor/bin/phpunit

# Database driver integration tests — spins up a Docker container per configured server
# image. Optional argument: one image (postgres:17) or a family (mysql|mariadb|postgres).
./run-integration-tests.sh

# Remote storage integration tests (needs live credentials in Test/.env)
vendor/bin/phpunit --configuration phpunit.integration.xml --testsuite integration
```

## Code Conventions

- All PHP files in `engine/` must have the `defined('AKEEBAENGINE') || die();` guard after the namespace declaration
- All files carry a GPL-3.0-or-later license header block
- No `declare(strict_types=1)` in engine code, even though `rector.php` itself uses it
- `#[\AllowDynamicProperties]` is used on classes that need dynamic properties
- Engine code must run on PHP 7.4, even though Rector targets PHP 8.4

## Architecture

Non-obvious wiring only; the rest is readable from `engine/`:

- **`Factory`** is the only entry point to engine components — never instantiate them directly.
  It also owns serialization/deserialization of engine state between backup steps.
- **`Base\Part`** drives every component through
  `STATE_INIT → STATE_PREPARED → STATE_RUNNING → STATE_POSTRUN → STATE_FINISHED` (or `STATE_ERROR`)
  via `tick()`. One `tick()` must fit inside a single PHP execution step — that is why work is
  chunked the way it is.
- Pluggable engines (`Archiver/`, `Dump/`, `Scan/`, `Postproc/`) are selected by configuration key,
  not by class reference — e.g. `akeeba.advanced.archiver_engine`.
- **`Configuration`** uses dot-notation keys; defaults come from the numbered JSON files in `engine/Core/`.
