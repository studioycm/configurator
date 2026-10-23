# Reusable skills, research, and validation

Authored 2026-10-21 using Skill Creator and skill-writing guidance. Shared instructions contain general Laravel decisions; [PROJECT_CONTEXT.md](PROJECT_CONTEXT.md) and the findings register carry configurator-only facts, constraints and historical recommendations. No application refactor or third audit is included.

## Installed skills and maintained sources

| Skill | Maintained portable repository source | Shared personal installation | Role |
| --- | --- | --- | --- |
| `laravel-development-architecture` | `.ai/skills/laravel-development-architecture/` | `/Users/studioycm/.codex/skills/laravel-development-architecture/` | New core guidance: responsibility types, native features, class shapes, generation, locations/discovery, input/persistence, UI boundaries and audit workflow |
| `laravel-filament-research` | `.ai/skills/laravel-filament-research/` | `/Users/studioycm/.codex/skills/laravel-filament-research/` | Updated existing research workflow: official/Boost sources, Laravel Daily/Povilas material, Filament Examples MCP/browser evidence, subscription/video provenance and bounded archives |
| `laraveldaily-structure-audit` | `.ai/skills/laraveldaily-structure-audit/` | `/Users/studioycm/.codex/skills/laraveldaily-structure-audit/` | Rebuilt audit adapter: current inventory, responsibilities/callers, evidence classifications, smallest recommendation and stage boundaries |

The project's `.agents/skills` entries are relative links to these repository sources; personal copies make the same guidance available across projects. A clone retains the portable source and project registration without depending on this machine's absolute personal paths. Skills become available according to the client's normal skill discovery; explicitly invoke the named skill in a new task if needed.

The original research skill was found in the personal skill directory and its matching source copy in `/Users/studioycm/Herd/laravel-lens/tooling/skills/laravel-filament-research/`. Its existing source/access procedure was reused. That other checkout was not edited. The former local structure-audit file and prior personal research skill were backed up inside the private archive's `skill-build/backups/`; ordinary Git history also retains the tracked local predecessor.

The repository sources are the maintained distribution for this bundle. After a future edit, validate there, then deliberately sync the same named personal copy and record hashes. Avoid silently editing one copy and assuming the other changed. Existing upstream `laravel-best-practices`, Livewire, Filament and testing skills were composed, not overwritten. No application Composer/npm dependencies changed.

## What the development skill teaches

| Concern | Guidance / benefit |
| --- | --- |
| Service versus Action | Cohesive capability versus operation; plain PHP classes, no required base/interface/method name; examples of typed DTO/calculation/writer/invocation |
| AppServiceProvider | Bindings/lifetimes in `register()`, integrations in `boot()`; focused provider only when wiring earns it |
| Concrete resolution and contracts | Use Laravel constructor resolution without redundant bindings; bind real contracts/primitives/contextual implementations |
| Lifetimes | Transient default; scoped state for a request/job; singleton only with safe process-lived state |
| Discovery | Separate PSR-4 from provider, policy, listener, command, Livewire and Filament panel registration/discovery |
| Commands and locations | Inspect installed `list`/`--help`; use supported native generators with `--no-interaction`; generic Actions/Services/DTOs use `make:class`; follow current namespace roots and component format |
| Validation and rules | HTTP Form Requests, Livewire forms and Filament schemas are different adapters; built-in/custom rules, normalization and write-time invariants have different jobs |
| DTOs/enums/value objects | Explicit meaningful data/value contracts; preserve exact keys/backing values/serialization; avoid mandatory wrappers |
| Eloquent | Keep useful relationships/scopes/casts/mutators; built-ins first; trace bulk writes and lifecycle bypasses; no automatic repository layer |
| Traits/macros/helpers | Narrow host behavior, deliberate global API extension, or topic-specific utility; no blanket prohibition on static methods/helpers/facades |
| Atomic operations | Explicit actor/owner, narrow field ownership, one authoritative writer, transaction/lock order, retries, after-commit/cache/memo semantics |
| Events/jobs/pipelines | Decoupling, genuine deferred work or configurable steps; avoid needless indirection and verify delivery/sync/commit behavior |
| Filament/Livewire | Supported static UI factories, native component lifecycle, hydrated state, current authorization and explicit operation boundaries |
| Refactors/audits | Evidence over thresholds; preserve good existing architecture; verify moves, discovery and serialized consumers; meaningful tests and clear scope |

The [architecture research basis](../../../../.ai/skills/laravel-development-architecture/references/research-basis.md) contains the source/access ledger. The [research source map](../../../../.ai/skills/laravel-filament-research/references/architecture-source-map.md) provides targeted discovery leads. These are focused living guidelines, not a claim of learning every framework API or completing every course.

## Fresh research and qualifications

Official Laravel 13 documentation, Boost results, installed framework v13.35 source and Artisan help were inspected. Current examples were checked for PHP 8.4 compatibility. Livewire 4 / Filament 5 docs and Filament's explicit static-factory guidance informed UI boundaries.

Laravel Daily's March 2026 Laravel 13 structure course was researched through public bodies and authenticated lesson access. Full authenticated DTO/helpers/traits/reuse/anti-extraction bodies were inspected; targeted pipeline/job/event/module prose and example structure/links were also inspected. The source ledger distinguishes those levels. The first three examples of the 2024 Services article were readable; the remainder and some complete Filament Examples project implementations remained gated. No complete subscription library or video/transcript review is claimed.

Filament Examples MCP returned actual source under `v4/forms/wizard-with-summary-step`; a query mentioning service transitions supplied no useful transition implementation. Public wizard and construction-management snippets/descriptions provided complementary design evidence. Neither a 4/5 badge nor tool availability establishes full project access or v5 compatibility.

The research resolved several traps explicitly:

- Some Laravel 13 `BindWhen` closure-attribute syntax requires PHP 8.5 despite the attribute class existing under PHP 8.4.
- Container construction/supported invocation resolves dependencies; an ordinary direct `handle()` call does not inject missing arguments.
- `make:scope` generates a global scope class; local scopes are model methods. Existing supported syntax does not require mechanical conversion.
- Events are synchronous unless listeners are queued; tutorial background-work wording does not override framework behavior.
- `validated()` does not generally coerce input strings into a typed DTO. Casts/mutators do not cover every builder/raw write.
- Course advice to extract for reuse and advice to avoid speculative extraction must be reconciled through actual complexity/benefit. Fixed counts and line thresholds are heuristics.

Reusable files contain original synthesis and links, not copied subscription lessons or licensed project trees.

## Validation performed

1. **Baseline:** an independent agent considered three synthetic scenarios before the new bundle was authored. It already made sound distinctions around static factories, narrow patches, concrete resolution, transaction ownership and historical evidence. This is not evidence that the new skill caused improvement.
2. **Forward decision review:** a fresh agent read the bundle and handled six scenarios: import/form full-state coupling; shared inventory operation/container/PHP floor; correcting historical claims and archives; unnecessary DTO/scope/binding modernization; discovery/serialized effects of moves; and Livewire hydration/authorization. All produced sound recommendations in that simulation. These were simulated decisions, not executed application tests or a statistical effectiveness study.
3. **Independent integrity review:** checked instructions, local references/portable copies, archive claims/checksums and boundary wording. It found one overstatement about casts/mutators covering every Eloquent write; the guidance was corrected to describe model-assignment versus bulk/raw paths. The original review is preserved privately, with the correction recorded separately.
4. **Packaging checks:** the official Skill Creator `quick_validate.py` passed for all three skills. YAML metadata/default prompts, local skill/document links, relative project registrations and repository/personal file equality were checked. The system/bundled Python lacked PyYAML; a temporary PyYAML 6.0.3 install enabled the validator, without changing application dependencies.
5. **Archive checks:** independent QA verified exact answer bytes, canonical exported projections, boundaries and all ten historical checksum entries. Unavailable/truncated history is documented rather than reconstructed.

Detailed forward/integrity outputs, backups and installed-file hashes are stored privately under `/Users/studioycm/.codex/research/laravel-architecture/2026-10-21/skill-build/`. The historical `MANIFEST.json` is unchanged by later skill-build records. No PHP application files were edited, so application Pest/Pint/FilaCheck execution was not required for this documentation/skill change. No runtime, browser acceptance, concurrency proof or application test pass is claimed.

The later third scan must verify the actual current application with these skills. The [kickoff prompt](THIRD_SCAN_KICKOFF.md) preserves findings-first review and approval before a detailed phased implementation plan.
