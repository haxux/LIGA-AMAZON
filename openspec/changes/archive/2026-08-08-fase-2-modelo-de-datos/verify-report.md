# Verification Report

**Change**: fase-2-modelo-de-datos
**Version**: N/A (no spec version field)
**Mode**: Strict TDD
**Verified against**: branch `fase-2/5-storage-and-verify`, commit `e543654` (tip of stack); working tree clean

## Completeness

| Metric | Value |
|--------|-------|
| Tasks total | 28 |
| Tasks complete | 28 |
| Tasks incomplete | 0 |

All 28 tasks in `tasks.md` are checked `[x]` and independently confirmed against actual code/DB state (not just trusted from the checkbox).

## Build & Tests Execution

**Build**: N/A (no separate build step for this PHP/Laravel change)

**Tests**: 31 passed / 0 failed / 0 skipped -- run independently by this verify phase, not copied from apply-progress.

```text
$ docker compose exec app php artisan test
Tests\Unit\ExampleTest .......................................... 1 passed
Tests\Feature\DatabaseSeederTest ................................ 5 passed
Tests\Feature\DeleteStrategyTest ................................ 2 passed
Tests\Feature\ExampleTest ....................................... 1 passed
Tests\Feature\GameGuardTest ..................................... 5 passed
Tests\Feature\PlayerFactoryTest ................................. 1 passed
Tests\Feature\RelationshipTest .................................. 6 passed
Tests\Feature\SchemaMigrationTest .............................. 10 passed

Tests: 31 passed (52 assertions)
Duration: 52.87s
```

Matches apply-progress.md reported 31/31 exactly -- no drift, no regressions.

**Coverage**: not available -- no xdebug/pcov coverage driver installed in the app container (php -m confirms neither is loaded). Not a failure per strict-tdd-verify rules; skipped cleanly.

## Independent Runtime Re-Verification (beyond the test suite)

Ran `php artisan migrate:fresh --seed --force` against the real dev MySQL DB directly (not trusting apply-progress account), then restored the DB to its documented "migrated, empty" state afterward:

| Check | Result |
|---|---|
| Row counts (seasons/teams/stadiums/players/matchdays/games) | 1/10/10/180/18/90 -- exact match to decisions_locked and proposal Success Criteria |
| Played games (non-null home_score) | 50 (MD1-10 x 5) |
| Unplayed games (null home_score) | 40 (MD11-18 x 5) |
| Equal-team games | 0 |
| Non-null crest_path | 0 |
| Game::create() with equal home/away team, real MySQL connection | ValidationException: "A team cannot play against itself." -- guard confirmed outside SQLite too |
| migrate:rollback --step=6 after fresh-seed | Dropped all 6 tables cleanly, reverse FK order, no FK errors |
| Post-verification DB state | Restored to migrated + empty (same convention apply-progress left it in) |

## Spec Compliance Matrix

### league-data-model

| Requirement | Scenario | Test | Result |
|-------------|----------|------|--------|
| Six domain tables exist | Composite unique rejects duplicate within same season | SchemaMigrationTest::test_duplicate_team_name_within_same_season_is_rejected | COMPLIANT |
| Six domain tables exist | Same team name allowed across seasons | Not directly tested (only implied by unique(season_id,name) schema) | PARTIAL -- constraint verified structurally, cross-season allow-case has no explicit test |
| Six domain tables exist | Stadium strictly 1:1 with team | SchemaMigrationTest::test_duplicate_stadium_for_same_team_is_rejected | COMPLIANT |
| Six domain tables exist (columns) | -- | SchemaMigrationTest (6 hasColumns tests) | COMPLIANT |
| Game scores nullable = not played | Unplayed game has null scores | DatabaseSeederTest::test_fresh_seed_leaves_matchdays_eleven_through_eighteen_unscored | COMPLIANT |
| Game scores nullable = not played | Played game has both scores set | DatabaseSeederTest::test_fresh_seed_scores_matchdays_one_through_ten | COMPLIANT |
| Game home/away teams must differ | Model rejects identical home/away team | GameGuardTest (5 tests: int, string, mixed-type, distinct-succeeds, withoutEvents-bypass) | COMPLIANT |
| Delete strategy protects Game history | Season delete cascades | DeleteStrategyTest::test_deleting_a_season_cascades_to_teams_matchdays_players_stadiums_and_games | COMPLIANT |
| Delete strategy protects Game history | Team delete blocked while referenced by Game | DeleteStrategyTest::test_deleting_a_team_referenced_by_a_game_is_restricted | COMPLIANT |
| Relationships resolve bidirectionally | All 6 relationships, both directions | RelationshipTest (6/6) | COMPLIANT |
| Demo seed produces mixed played/unplayed | Fresh seed, no FK errors, mixed state | DatabaseSeederTest (5/5) + independent migrate:fresh --seed re-run | COMPLIANT |

### public-file-storage

| Requirement | Scenario | Test | Result |
|-------------|----------|------|--------|
| Public disk writable for crest uploads | File written under crests/ | Manual verification (design.md explicitly excludes from PHPUnit/SQLite) -- probe-write documented in tasks.md 5.2, crests/.gitignore presence confirmed by this verify phase | COMPLIANT (manual, per design own carve-out) |
| Files retrievable over HTTP at /storage/ | Symlink serves file | Manual curl probe documented in tasks.md 5.2/5.4; storage:link functionality re-confirmed by this verify phase (is_link() returns true) | COMPLIANT (manual) |
| Symlink failure falls back to nginx alias | -- | Not exercised -- symlink worked, so this scenario was never triggered in this environment. Fallback code is committed-but-inert per design D4. | UNTESTED (by design -- the failure mode did not occur here; fallback exists as inert code, not verified live) |
| Serving mechanism documented | Mechanism recorded | design.md Open Questions (resolved), tasks.md Phase 5, apply-progress.md -- all state "symlink" | COMPLIANT |

**Compliance summary**: 13/15 scenarios fully compliant, 2 partial/untested by design (cross-season team name allow-case has no explicit test; nginx-alias fallback path was never exercised because the symlink did not fail in this environment -- both are low-risk, neither blocks archive).

## Correctness (Static Evidence)

| Requirement | Status | Notes |
|------------|--------|-------|
| 6 migrations (columns, constraints) | Implemented | Verified by direct file read against design.md/exploration.md column tables -- exact match, including unsignedTinyInteger(shirt_number), unsignedSmallInteger(founded_year/number), unsignedInteger(capacity/home_score/away_score) |
| FK delete strategy (cascade vs restrict) | Implemented | cascadeOnDelete() on Team/Player/Stadium/Matchday/Game-to-Matchday; restrictOnDelete() on both games.home_team_id/away_team_id -- matches D1 exactly |
| home_team_id <> away_team_id guard | Implemented | Game::booted() saving hook, int-cast comparison, ValidationException -- matches D2 code sample verbatim |
| 6 Eloquent models, #[Fillable] + casts() | Implemented | All 6 models use attribute-based #[Fillable], method-based casts(), matching app/Models/User.php convention; relationship wiring matches design table exactly (including explicit-FK homeGames/awayGames, homeTeam/awayTeam) |
| 6 factories | Implemented | Curated names/cities (no raw Faker for domain fields per proposal), PlayerFactory uses Sequence for shirt_number, GameFactory::played() state |
| DatabaseSeeder circle-method double round-robin | Implemented | 9 first-leg + 9 second-leg rounds x 5 pairings = 18 matchdays x 5 games = 90; distinct teams guaranteed by construction; WithoutModelEvents + docblock noting D3 |
| Storage: crests/.gitignore + parent fix | Implemented | storage/app/public/.gitignore now star / !.gitignore / !crests/; git check-ignore -v on the new file returns non-zero exit (not ignored) -- re-verified independently |
| storage:link functional | Implemented | is_link('/var/www/html/public/storage') returns true, re-checked independently in this verify run |
| No .env*/docker-compose.yml touched | Confirmed | git diff --stat docker-compose.yml empty; git log --name-only across the full 5-branch range shows zero touches to .env* or docker-compose.yml; the MySQL-vs-SQLite test-isolation fix lives entirely in tests/TestCase.php + phpunit.xml, as documented |
| docker/nginx/default.conf untouched (fallback not needed) | Confirmed | git diff --stat docker/nginx/default.conf empty |

## Coherence (Design)

| Decision | Followed? | Notes |
|----------|-----------|-------|
| D1 -- FK delete strategy | Yes | Cascade/restrict split matches exactly |
| D2 -- Eloquent guard, not raw SQL CHECK | Yes | Game::booted() saving hook, int-cast, ValidationException |
| D3 -- WithoutModelEvents in seeder | Yes | Trait present, docblock explains the guard is muted, distinct teams guaranteed by circle-method construction |
| D4 -- Storage: symlink-first, nginx alias fallback | Yes | Symlink worked; alias committed-but-inert in design/tasks docs, default.conf correctly left untouched since not needed |
| D5 -- Test DB divergence (SQLite tests, MySQL runtime) | Yes | tests/TestCase.php forces SQLite :memory: regardless of container env vars; independently confirmed real dev MySQL DB was untouched by the test run (row counts stayed 0 before/after php artisan test) |
| D6 -- Circle-method fixture generation | Yes | Algorithm matches the documented "fixed team + rotating array" circle method; produces exactly 90 games, no self-pairings |

## TDD Compliance

| Check | Result | Details |
|-------|--------|---------|
| TDD Evidence reported | Partial | tasks.md documents narrative RED-to-GREEN per task (1.1-4.3, each explicitly marked "Must fail" for RED, "Confirm X.X green" for GREEN). apply-progress.md provides a standardized TDD Cycle Evidence table (RED/GREEN/TRIANGULATE/SAFETY NET/REFACTOR columns) only for Phase 5-7 (correctly N/A there -- no new tests). No such table exists for tasks 1.1-4.3, where the actual TDD work happened. Neither of the two apply-progress.md versions found in git history (def98ff, e543654) contains it for those phases. |
| All tasks have tests | Yes | 6/6 expected test files present (SchemaMigrationTest, DeleteStrategyTest, GameGuardTest, PlayerFactoryTest, RelationshipTest, DatabaseSeederTest), each mapped 1:1 to a design.md Testing Strategy row |
| RED confirmed (tests exist) | Yes | All 6 test files verified present by direct read, content matches tasks.md per-row description |
| GREEN confirmed (tests pass) | Yes | 31/31 pass on this verify phase own independent php artisan test run |
| Triangulation adequate | Yes | Test-case counts per file match design.md Testing Strategy table row-for-row: 10 (rows 1-2), 2 (rows 3-4), 6 (row 5), 5 (rows 6-7), 1 (row 8), 5 (row 9) |
| Safety Net for modified files | Yes | Only pre-existing files modified: tests/TestCase.php (infra fix, documented, does not touch .env*), storage/app/public/.gitignore (one-line addition), phpunit.xml (env force flag). None caused regressions -- full suite green |

**TDD Compliance**: 5/6 checks fully passed, 1 partial (documentation-format gap -- see WARNING below; the substance of TDD was followed and independently verifiable via source + test-count cross-reference, only the standardized table artifact is missing for phases 1-4)

---

### Test Layer Distribution

| Layer | Tests | Files | Tools |
|-------|-------|-------|-------|
| Unit | 1 | 1 | PHPUnit (stock Laravel ExampleTest) |
| Integration | 30 | 6 | PHPUnit + RefreshDatabase against SQLite :memory: |
| E2E | 0 | 0 | not installed |
| Total | 31 | 7 | |

Storage serving (HTTP /storage/... retrieval) is verified manually (curl), not as an automated test -- this is an explicit, documented design.md carve-out ("not automatable in SQLite/PHPUnit"), not a gap.

---

### Changed File Coverage

Coverage analysis skipped -- no coverage tool (xdebug/pcov) detected in the app container.

---

### Assertion Quality

No violations found across all 6 change-authored test files (SchemaMigrationTest, DeleteStrategyTest, GameGuardTest, PlayerFactoryTest, RelationshipTest, DatabaseSeederTest). Notably:
- DatabaseSeederTest's "no equal-team game" and "no crest_path" tests each carry an explicit precondition count assertion (assertSame(90, Game::count()), assertSame(10, Team::count())) guarding against the vacuous-pass-on-empty-collection failure mode -- this was caught and fixed during apply per the project's own assertion-quality rules, and independently confirmed still present and correct.
- GameGuardTest triangulates the int-cast guard across int/int, string/string, and mixed int/string comparisons -- genuine variance, not repeated identical assertions.
- No tautologies, no assertion-free tests, no ghost loops over possibly-empty collections, no CSS/implementation-detail coupling (none applicable -- backend-only change), no mock-heavy tests (no mocks used; all tests hit a real SQLite DB through Eloquent).

**Assertion quality**: All assertions verify real behavior

---

### Quality Metrics

**Linter**: No errors -- vendor/bin/pint --test run against all changed files (models, migrations, factories, seeder, changed test files) -> PASS ... 31 files
**Type Checker**: Not available -- no PHPStan/Larastan installed in this project

## Issues Found

**CRITICAL**: None

**WARNING**:
1. apply-progress.md lacks the standardized "TDD Cycle Evidence" table (RED/GREEN/TRIANGULATE/SAFETY NET columns per strict-tdd-verify.md) for tasks 1.1-4.3 -- the phases where actual RED-to-GREEN TDD work happened. tasks.md documents the same information narratively per task, and this verify phase independently reconstructed and confirmed RED/GREEN/TRIANGULATE evidence by cross-referencing test file contents against design.md Testing Strategy table (see TDD Compliance section above) -- no substantive TDD violation was found, but the reporting artifact itself does not meet the strict-tdd format contract. Non-blocking for archive; worth a note for future phases to populate the table format directly during apply, not just narratively in tasks.md.
2. public-file-storage spec scenario "Symlink failure falls back to nginx alias" was never exercised -- the symlink worked on the first attempt in this environment, so the nginx alias code path is committed but has zero live verification. This is an accepted, explicitly-documented risk (design D4 own "divergence note"), not a defect, but it means the fallback is unverified until/unless a future environment actually hits the NTFS bind-mount failure.
3. Spec scenario "Same team name allowed across different seasons" (league-data-model) has no explicit covering test -- only the rejecting case ((season_id, name) unique, same-season duplicate) is tested. The allow-case is implied by the constraint shape (composite unique, not a plain unique on name) but is not independently asserted.

**SUGGESTION**:
1. Test layer distribution is 100% integration-style (DB-backed RefreshDatabase tests) plus one stock unit test -- appropriate for this schema/relationship-heavy change, no action needed, noted for completeness per strict-tdd-verify informational reporting requirement.

## Verdict

**PASS WITH WARNINGS**

All 28 tasks are genuinely complete and match the code on disk. All 6 migrations, 6 models, 6 factories, and the seeder match design.md exactly, including the FK cascade/restrict split and the home_team_id <> away_team_id guard. The full 31-test suite was re-run independently in this verify phase (not trusted from the apply report) and passed cleanly; the seed algorithm exact row counts (1/10/10/180/18/90, 50 played/40 unplayed, 0 equal-team games, 0 crest paths) and the model guard were additionally re-verified against the real dev MySQL database (not just SQLite tests), then the DB was restored to its documented migrated-empty state. Storage plumbing (crests/.gitignore, the parent .gitignore fix, storage:link) is functionally confirmed. No .env* or docker-compose.yml files were touched by any of the 5 branches. The three WARNINGs above are process/coverage gaps, not implementation defects, and do not block archiving this change.
