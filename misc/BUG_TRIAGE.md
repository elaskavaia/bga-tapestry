# Bug Triage - project config and state

Game-specific configuration and running state for the `game-bug-triage` skill. The skill itself is
game-independent; everything project-specific lives here. Read this file at the start of every
triage run.

## Game reference

- **BGA game id:** 1446 (Tapestry)
- **Bug list:** https://boardgamearena.com/bugs?game=1446
- **Single report:** https://boardgamearena.com/bug?id=<REPORT_ID>
- **Studio error log:** https://studio.boardgamearena.com/studioissues?game=tapestry - studio short
  name is `tapestry`, not the numeric id above. Add `&tableId=<TABLE_ID>` for one table,
  `&id=<ERROR_ID>` for one error, `&projects=frontend|backend` to filter. (`taptest` is the renamed
  test copy produced by `misc/rename.sh`, not the production project - never triage against it.)
- **Designer:** Jamey Stegmaier designed Tapestry, and his playtest feedback is transcribed in
  [TODO.md](TODO.md). He does not really file bug reports, so unlike some sibling projects there is
  no standing "hands off the designer's reports" rule here. If one ever does appear under his name,
  treat it as a design ruling rather than a player claim and check with Victoria first.

## Rules sources (for `rules`-type reports)

Check a reporter's claim against these, in order, before assuming the code is wrong:

1. [RULES.md](RULES.md) - the base game rulebook (`TapestryRules_r13.pdf`) plus the per-space
   reference guide tables (`Tap_ReferenceGuide_r11.pdf`). You must read this every run.
2. [RULES_AUTOMA.md](RULES_AUTOMA.md) - the solo rules (`Automa_TapRules_r8.pdf`): Automa and
   Shadow Empire behaviour, the decision deck, hex tiebreaker, difficulty levels. Read this for any
   report on a solo table (the report page states the player count beside the dump info).
3. [FORMAL_RULES.txt](FORMAL_RULES.txt) - Victoria's rules interpretation: effect ordering,
   void/mandatory/optional semantics, advance/regress definitions. This is the tie-breaker for
   anything the rulebook leaves ambiguous, and it also records open questions to the designer. It
   is an interpretation, not a printed rule - cite it as such.
4. [TODO.md](TODO.md) - carries an unanswered "Questions for Jamey (orig dev)" block plus Jamey
   Stegmaier's own playtest feedback. Some entries are marked FIXED inline; do not assume the
   unmarked ones are still open without checking.

The converted rules cover the base game and solo play only. Expansion content (Plans & Ploys, Arts &
Architecture, Fantasies & Futures) and the Civilization Adjustments have no converted rules doc yet -
for a report about those, say so and record it as an open question rather than guessing.

Source PDFs live outside the repo in `~/Develop/bga/bga-assets/Tapestry/`. Convert with a tool
(`pdftotext`), never by reading the PDF directly.

## Deploy model

Process: **creating a git tag = deployed.** Tag name is the BGA version string, e.g.
`v260731-2000`. To decide if a fix is live:

- Find the fix commit: `git log --oneline -i --grep='#<reportid>'`. Commit convention here is
  `Bug #<id>: "<title>"` (7 such commits predate this file); sibling projects use `BGA #<id>`. The
  case-insensitive `#<id>` grep catches both.
- Is it in the latest tag: `git merge-base --is-ancestor <commit> $(git describe --tags --abbrev=0)
  && echo LIVE`.
- List tags containing it: `git tag --contains <commit> --sort=creatordate | head`.

In the latest deployed tag -> **Fixed**. Committed/tagged but that tag not deployed -> **Waiting for
deploy**. When unsure whether a tag is deployed, ask.

**Tagging only started 2026-08-26**, back-filled from Victoria's recollection: `v260731-2000` on
5ac2007 is the last deployed release. Anything after it on `main` is undeployed. There is no tag
history before that, so a fix commit older than 5ac2007 cannot be dated by tag - such a commit
predates the last deploy and is therefore live.

The local `prod` branch is stale (tip 2025-09-05, far behind `main`) and is NOT a deploy marker.
Ignore it.

## Investigation and tests

- Tests live under `modules/tests/`, PHPUnit, bootstrapped by `modules/_autoload.php`.
- Single method:
  `APP_GAMEMODULE_PATH=~/git/bga-sharedcode/misc/ phpunit --bootstrap modules/_autoload.php --filter <method> modules/tests/<File>.php`
- The investigation agent runs **only its own test**, never `npm run tests` or `npm run predeploy`.
- A reproducing test must be **green**, asserting the current buggy behavior, with a comment pinning
  it for `Bug #<id>` (flip the assertion when the fix lands) - see the skill's CONFIRMED note.
- Tests that touch civilization data must call `doAdjustMaterial($players, $variant)` first, since
  material is rewritten in place per player count and per Civilization Adjustments option.
- Framework behaviour comes from stubs in `~/git/bga-sharedcode/misc/php/stubs/`, which are more
  forgiving than production - a green test at a framework boundary is not proof.

## Bug triage last checked

**2026-08-26 18:40 EDT** - only triage BGA reports created/updated after this time; bump this line
to `date` at run start after each run.

(First run has no real history behind this marker: the status sweeps - Waiting for deploy, and Open
- are what actually cover the backlog. Expect the first pass to be large.)

## Tracked bugs

Internal record of triaged reports (root cause, fix, test) - never put this detail in a public bug
comment. Empty: no report has been triaged through this workflow yet.

Pre-existing bug notes that are NOT BGA reports live in [TODO.md](TODO.md) (Jamey's playtest list,
the Coal Baron / Utilitarians notes). Leave them there; only reports with a BGA id belong here.

## Triage run log

Short log of each run: date, reports touched, outcome.

- **2026-08-26** - Setup only, no triage run. Created this file and [RULES.md](RULES.md) (converted
  and [RULES_AUTOMA.md](RULES_AUTOMA.md) (converted from the rulebook, reference guide and Automa
  PDFs with `pdftotext`). Bootstrapped the deploy model by
  back-tagging `v260731-2000` on 5ac2007, the last commit before the 2026-08-25 work burst; 20
  commits on `main` are undeployed as of today. Game id 1446 and studio short name `tapestry` both
  confirmed by Victoria.
