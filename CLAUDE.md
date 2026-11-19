# Version
3

# Coding Rules (Pointer)

This project's rules live in two files in the project root:

- `CODING_RULES.md` — code quality and conventions. BINDING for all code work in
  this repository, always.
- `IMPLEMENTATION_FLOW.md` — the end-to-end flow to follow when planning and
  implementing a change (checks, gates, Definition of Done).

MANDATORY: Before writing or editing ANY code, you MUST Read BOTH files in full
**in the current session**. Do not rely on memory of a previous session, a
summary, or partial reads.

If you are about to make a code change and have not read both files in this
session: STOP, read them, then continue.

An external tool may orchestrate the implementation itself. When a skill or run
states that the implementation is orchestrated, ignore `IMPLEMENTATION_FLOW.md`
entirely — the orchestrator runs those steps as its own phases. `CODING_RULES.md`
stays binding either way.

Next to `CODING_RULES.md`, apply also writes `CODING_RULES_PLAN.md`,
`CODING_RULES_REVIEW.md` and `CODING_RULES_CODE.md`. When an orchestrator's run
instructions name one of these step files, that file replaces `CODING_RULES.md`
for that run; without such an instruction, `CODING_RULES.md` stays binding in full.

Do not inline rules back into this file and do not use `@import` for either file —
they are intentionally referenced, not imported.


# Project Notes

- RTM Statistics frontend: server-rendered PHP page (Twig, no framework) that reads the SQLite
  snapshots of `../remember-the-milk-statistics`. Read-only.
- `contract/` is the `remember-the-milk-statistics-contract` submodule (table schema). Test input
  only; production code never reads it.
- graphify: this project's code directory is `src/` (scan root for `/graphify src --directed`
  and `tools/graphify_update.bat`).

# Project Deviations

- **PDO instead of Cycle ORM** (php/APP.md "No Raw SQL"), approved by the user 2026-11-19. The
  `snapshots` table is owned by the Python producer, and this app only reads it. The SELECT
  column list is `SnapshotRepository::COLUMNS`, and `tests/Unit/ContractTest.php` checks it
  against `contract/schema.json`. That covers the column-drift risk the ORM rule targets.
- **No live DB mirror** (php/APP.md "Required Batch Files"): integration tests build a temporary
  SQLite database from the contract, so `tools/tests.bat` does not pull a database.
- **php-localization placeholders**: the library replaces placeholders one after another with
  `str_ireplace`, so one placeholder must never be a prefix of another (`:page` vs `:pages`).
