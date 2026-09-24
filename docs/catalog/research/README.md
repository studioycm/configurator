# Catalog/configurator research index

> Historical research evidence. Execute the [final plan](../IMPLEMENTATION_PLAN.md) and [guidelines](../IMPLEMENTATION_GUIDELINES.md); their contracts supersede earlier alternatives here. Dates, versions, access and test observations are snapshots, not fresh verification.

Date: 2026-09-23. Workspace: `/Users/studioycm/Herd/configurator`.

Authority: [the living plan](../DECISIONS.md) records user decisions. These research notes provide evidence and technical proposals; examples do not override the requested behavior.

## Requested workflow and status

1. Research Laravel Daily, Filament Examples and official version-matched documentation with multiple agents; inspect real browser content and working source patterns. **Completed for this planning checkpoint: channel title/description scans, all thirteen Laracon demo topics, related publisher sources, four recent/relevant project source reviews and AI Coding Daily's planning workflow. Spoken-content and access limits are recorded below.**
2. Close material gaps through targeted follow-up checks and consolidate findings in documents. **Completed: source/lifecycle second pass, root review and peer consistency check.**
3. Inventory potentially useful PodText skills, compare them with the skills already here, and let the user choose. **[Inventory and selection complete](SKILL_PROVENANCE.md): UX Design Thinking and Laravel Simplifier imported. User chose official testing guidance; the installed Boost `testing-best-practices` replaced upstream's retired standalone Pest skill. No PodText Pest import.**
4. Apply the chosen/relevant Laravel and Filament skills to evaluate the plan and current code, then write the final implementation plan. **[Implementation proposal ready for review](../IMPLEMENTATION_PLAN.md), with reconciled core/engine, public and admin annexes. Implementation and database rehearsal remain future work.**

No application implementation, dependency change, database write, push or deployment is part of this research.

## Evidence documents

| Document | Responsibility |
| --- | --- |
| [Livewire catalog research](LIVEWIRE.md) | Public navigation, filters/SubGroup reconciliation, URL/Back state, pagination, rendering and query boundaries |
| [Filament admin research](FILAMENT.md) | Matrix authoring, rule editor, ordering, shared definitions, import lifecycle, admin form layout and deferred tabs |
| [Engine/data research](ENGINE_AND_DATA.md) | Actual legacy behavior and gaps, typed engine boundary, normalization/upserts, JSON/MySQL storage and fresh-database transition |
| [YouTube discovery extension](YOUTUBE.md) | Laravel Daily and Filament Daily upload discovery for 2025 and the past year, mapped to deeper publisher examples |
| [Povilas's Filament capability talk](FILAMENT_CAPABILITIES.md) | All thirteen timestamped demo topics, original tutorials/repositories, selected actual source and current Filament API checks |
| [Recent FilamentExamples projects](RECENT_PROJECTS.md) | Four authenticated project pages/directories and sixteen targeted repository files, with pinned implementation references, lock versions and adaptation limits |
| [AI Coding Daily follow-up](AI_CODING.md) | Authenticated planning guidance, its pinned workflow repository and evidence-based verification implications |

The root and peer reviews reconciled findings across these boundaries and the three implementation annexes. References to nonexistent application policies and to O9 as a planning blocker were corrected. The import transaction boundary is now an explicit proposal, and public/admin settings, SubGroup property vocabulary, test paths and staged-save behavior agree.

## Source and access standards

- Installed target verified with Boost on this date: PHP 8.4, Laravel 13.33.0, Filament 5.8.4, Livewire 4.4.6; MySQL 8.0.46 was inspected locally.
- Laravel Daily is authenticated in Chrome. Filament Examples authenticated successfully through the existing GitHub session. Browser access is available; it is not being treated as a blocker.
- Filament Examples MCP is available and returns named source-file examples. This is recorded separately from authenticated tutorial reading, installed package-source verification and executed browser behavior.
- Older Laravel Daily/Filament examples provide ideas and must be adapted to the installed majors. Per the user's correction, video research requires **titles and descriptions**; a title-only card is just a candidate. A description is discovery evidence, not proof that its implementation was inspected or tested.
- PodText's local research index is a discovery aid. Reverify applicable claims against this project. Distilled notes and links may be reused; browser profiles, cookies, credentials and raw subscription archives are not being copied.
- Store concise findings, source links, verified mechanisms, version differences and explicit limitations. Do not duplicate full premium tutorials or unrelated application code.
- The Laracon recordings had no available transcript/captions through the inspected paths. Both descriptions and all thirteen demonstration frames were inspected, followed by original sources; this is not a claim of complete spoken-content review. The Livewire 4 kit's publisher page was accessible, while its separate repository invitation remains pending. Neither limitation prevents the reviewed implementation proposal.

## Item 10 clarification

The assistant raised this because the POC engine contains a `configuration_code` condition input. It was not a client request. The source field in the old admin can technically accept that input, but the current local rules do not use it; the detailed audit records code paths, test coverage and the read-only usage check.

“Change selections” meant that an active restriction can make an option illegal, after which the agreed fallback selects the valid default/first option, or reports an incomplete configuration if no option is legal. It did not refer to changing Product records or saved visitor configurations.

For example, checking an individual selected material option is already covered by ordinary selection conditions. Checking the entire output string to restrict choices that generate that same string adds a feedback dependency. There is no demonstrated requirement for that extra behavior. Assess it as a legacy branch rather than silently adding it to the rebuild. The future asset mapper is a separate consumer of the generated result and remains deferred.

Item 11-A is recorded: plan a fresh MySQL database, retain required account/non-domain data, build clean domain migrations, import the catalog and archive the old database/files/code. No new database or migration rehearsal has been run.

## Research completion criteria

- Each requested workstream has specific source evidence and an implementation implication, including things the examples do not solve.
- Evidence distinguishes public discovery filters from configurator disabled/hidden outcomes.
- The shared public/admin engine contract, rule validation, identity normalization and persistence boundaries are evaluated together rather than independently.
- Version-specific APIs are checked using Boost/installed source; older examples are marked as adaptations.
- Unresolved content or business choices are listed without reopening accepted decisions.
- Research does not claim that a custom engine/matrix/importer has been implemented, production-tested or proved by watching a demo.

## Cross-workstream conclusions

| Finding | Consequence for the implementation plan | Evidence |
| --- | --- | --- |
| Framework fit is established, domain behavior still needs implementation | A Livewire discovery page can reuse Filament controls; Filament can host the mapping matrix. Neither supplies the required reconciliation/mapping semantics automatically. | Livewire report sections 2/7; admin report sections 3/4 |
| Discovery depends on selection order | Persist values and precedence together. The browser produced 36 versus 9 matches after the same next choice from identical prior visible selections applied in different orders. Prove one coherent Back step when one action changes several fields. | Livewire report sections 3–5 |
| One result must drive both configuration UIs | The running app uses PHP/Livewire; the separate PHP manifest helper has no application caller found. Replace split behavior with one settled result consumed by public configuration and admin preview. | Engine report sections 2–4 |
| Rule authoring needs domain validation | Validate allowlisted typed inputs, group depth, option membership and dependencies. Filament QueryBuilder's oversized-tree behavior is inappropriate for persisted configurator rules. | Admin report section 5; engine report sections 3/4 |
| Reference checks must precede relationship writes | In ordinary Filament record editing, relationship saves can occur before `mutateFormDataBeforeSave`. Defaults/mapping checks must run before any write, with transactional domain persistence covering all entry points. | Admin report section 6 |
| Deferred rendering is distinct from hidden/inapplicable attributes | The 5.8 schema mechanism can reduce initial markup, but does not imply absent schema/state or skipped relationships. Add unopened-tab save checks before adopting it for association editing. | Admin report section 7 |
| Import identity has two traps | Normalize before record lookup; MySQL upsert can conflict on any unique key rather than only Laravel's requested legacy key. Use explicit legacy identity and conflict handling, preserving source-owned blanks and app-only JSON keys. | Engine report section 6; admin report section 8 |
| Fresh DB is selected, operational proof remains | Specify clean domain migrations and retained-data transfer with matching application code. Rehearse on MySQL; current SQLite tests cannot verify case-sensitive codes, JSON semantics or cutover. | Engine report sections 5/7 |
| Custom grids and resource composition fit the chosen stack | Retain normal resources for shared definitions, with a staged custom matrix and saved-definition Preview. Read actual write methods: a grid's Save button does not prove that the complete grid is validated or saved atomically. | Capability report; recent-project report sections 1–3 |
| Custom records and scoped services have concrete lifecycle limits | A bare Collection is not automatically sliced by a pagination setting; request-scoped memoization does not span independent lazy-widget requests. Keep the public Eloquent paginator and one prepared evaluation result per adapter. | Recent-project report section 4; installed-source/API checks |

These conclusions support the accepted plan. They do not add saved configurations, version publishing, custom behavior, a parts subsystem, a generic EAV field store or a new authorization hierarchy.

## Reviewable plan and remaining execution evidence

The [implementation plan](../IMPLEMENTATION_PLAN.md) now specifies the delivery sequence and three technical annexes: [core/import/engine](../contracts/ENGINE_AND_DATA.md), [public discovery](../contracts/PUBLIC_CATALOG.md) and [Filament management](../contracts/FILAMENT_ADMIN.md). They include the exact 67-column map, proposed schema/resource names, typed operators, matrix state/persistence, mutation authorization preserving current panel eligibility and the fresh migration-file partition. These are reviewable technical proposals under the accepted user decisions.

The initial 501-row import proposal is CLI-first, full-file preflight followed by one transactional apply. Any future admin adapter calls that same operation. This remains a proposed implementation choice; no queued partial-success importer has been adopted accidentally from an example.

Required future proofs are explicit: coherent URL Back/forward including malformed/stale state; preset precedence; Settings saves preserving unvisited associations; whole-matrix failure without partial writes or lost input; and real MySQL constraints, JSON queries and import rollback. The fresh-database plan partitions the 36 existing migration files into 17 retained infrastructure/package files and 19 replaced domain files. No schema creation, transfer, browser test of a new screen or operational rehearsal has occurred.

The source does not identify a parent Group; that is a missing content input, not a reason to invent a legacy ID. Product codes, raw part slots and ambiguous source values remain preserved under the agreed normalization/import policy.

The next review can start with M1's visible catalog foundation and the explicitly labelled technical defaults. No additional subscription login, full course reread, new UI framework or dependency is required to reach this planning checkpoint.
