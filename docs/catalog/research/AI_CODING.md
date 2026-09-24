# AI Coding Daily: planning and verification follow-up

> Historical research evidence. Execute the [final plan](../IMPLEMENTATION_PLAN.md) and [guidelines](../IMPLEMENTATION_GUIDELINES.md); their contracts supersede earlier alternatives here. Dates, versions, access and test observations are snapshots, not fresh verification.

Inspected 2026-09-23, following the user's request to include Povilas Korop's AI Coding Daily material. This is source research, not instructions to install the author's tools, copy his rules, or change the accepted catalog scope.

## Sources actually read

| Source | Evidence obtained | Use here |
| --- | --- | --- |
| [My AI Spec-Driven Workflow for Laravel Projects](https://aicodingdaily.com/article/my-ai-spec-driven-workflow-for-laravel-projects-prompts), January 13, 2026, updated January 22 | Complete authenticated article in Chrome; followed its public GitHub repository | Trace the original request through agreed behavior, schema and staged tasks, reviewing each before implementation |
| [My AI Guidelines for Laravel/PHP and Filament](https://aicodingdaily.com/article/my-ai-guidelines-for-laravel-php-filament), January 11, 2026, updated January 23 | Complete authenticated article, including its Laravel and Filament guidance | Verify versions, real relationships and test prerequisites instead of copying remembered framework syntax |
| [LLM Coding Leaderboard methodology](https://aicodingdaily.com/article/llm-coding-leaderboard-my-methodology-and-scoring-formulas), August 21, 2026 | Public article, including examples of behavioral evaluation and evidence grading | Distinguish an implementation that appears complete from one whose actual contract has been exercised |
| [Laravel-New-Project-AI-Spec-Workflow](https://github.com/LaravelDaily/Laravel-New-Project-AI-Spec-Workflow) | README and all three prompt files read through GitHub; pinned review at `5409d2f0f934a0e73ab30620c3cfbe815664cc29` | Check the concrete workflow behind the article, not just its marketing description |

The site's authenticated Premium session was already available. No sign-in request, subscription purchase, raw premium archive, transcript download or new skill import was required. Its video experiments were discovered from titles and descriptions; this report does not claim those premium videos were watched.

## What the source supports

The workflow article separates requirements, user stories, database design and phased implementation. Its linked [database prompt](https://github.com/LaravelDaily/Laravel-New-Project-AI-Spec-Workflow/blob/5409d2f0f934a0e73ab30620c3cfbe815664cc29/prompts/prompt-database-structure.md) and [phase prompt](https://github.com/LaravelDaily/Laravel-New-Project-AI-Spec-Workflow/blob/5409d2f0f934a0e73ab30620c3cfbe815664cc29/prompts/prompt-project-phases.md) make the dependency explicit, including task-specific acceptance tests and checking which tasks are already implemented. Our living decisions, implementation plan and three technical annexes already supply those layers. Keep their references coherent instead of generating a second competing specification.

The repository targets Laravel 12 in its database prompt and proposes controllers by default for noninteractive pages. The article's Filament examples target v4. These are historical examples, not permission to change our installed Laravel 13/Filament 5/Livewire 4 stack or the user's explicit Livewire public-page choice. Its general media-library, enum-directory, test-volume and authorization preferences also remain subordinate to this project. Existing packages are sufficient; no Context7, starter kit or new dependency is introduced by reading the workflow.

The guidelines article usefully warns about model/relationship assumptions and about requiring an extra Pest plugin for the `livewire()` helper. This project's existing `Livewire::test()` avoids that dependency. Its suggestion to authorize a Filament action does not remove the need for authorization in the shared domain operation used by other entry points. The existing plan retains both UI enforcement and protected domain writes. No external guideline file was copied into this repository.

## Verification implications

The methodology article distinguishes behavioral checks from code review and requires evidence for claims about UI behavior. Its examples show that a descriptive test name, a source-level click handler, or many assertions alone cannot prove the promised interaction. Apply that principle proportionally here: verify Back/forward with restored precedence, exercise rejected relationship saves, and demonstrate import failure rollback on MySQL. No benchmark score is assigned to this project.

Its CSV benchmark deliberately accepts valid rows from a mixed file. That is a different contract from our proposed full-file transactional import. Reuse the idea of independent parsing/idempotence/failure fixtures, not its partial-success requirement. Its warning about editing an old migration applies to an existing installation; the user has chosen a new database with a separate migration ledger and matching release. The current database's history is not rewritten.

These observations strengthen the implementation plan's evidence requirements. They do not add user roles, pricing, payments, notifications, a new test framework, a broad technical-debt program, or implementation approval.

## Scope of remaining uncertainty

This pass did not reproduce the author's model comparisons, run his benchmark harness, or watch the premium model-review videos. None is needed to decide the catalog's architecture. Concrete Filament capabilities and repository code are covered in the separate talk/recent-project research; this report supplies the planning and verification connection.
