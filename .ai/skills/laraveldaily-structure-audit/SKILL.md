---
name: laraveldaily-structure-audit
description: Use when the user requests a Laravel project structure audit, architecture review, responsibility-boundary review, helper/service/trait classification, namespace/location assessment, or overengineering review, including Livewire and Filament applications. Use for requested audit scope, not an automatic whole-project sweep during ordinary development.
---

# Laravel structure and responsibility audit

Produce evidence-backed recommendations for the inspected application. This adapter extends the Laravel Daily-inspired structure audit with native Laravel, Livewire and Filament boundary checks. It is locally maintained guidance, not an assertion that Laravel Daily endorses every rule.

## Load the right guidance

Use the shared `laravel-development-architecture` skill and its audit workflow, responsibility matrix, container/discovery, input/persistence and relevant UI references. Use `laravel-best-practices` for affected Laravel details, `livewire-development`/`filament-development` for those frameworks and `testing-best-practices` for coverage recommendations. Research unresolved design alternatives through `laravel-filament-research`.

Discover those skills from the active catalog or standard skill locations; do not assume another project's paths. If a companion is missing, disclose the gap and follow the minimum workflow below using installed-version Boost/official docs. Do not invent its instructions or stop all useful read-only work. A separate security/performance/API audit runs only when relevant to the requested scope.

## Minimum workflow

1. **Scope/snapshot:** read AGENTS and applicable path rules; record timestamp, checkout, HEAD/branch, dirty state, PHP/package versions, requested areas and exclusions. A historical audit is a lead, not current evidence. Respect audit-only authorization.
2. **Inventory:** enumerate in-scope PHP, routes/bootstrap/config, Blade/JS, tests and CI where relevant. Record coverage/gaps. Search for helper-like behavior regardless of class suffix. Do not rely only on controllers or file size.
3. **Trace:** inspect candidate methods, callers, DI/bindings/discovery, input/actor/tenant, reads/writes, serialized outputs, state/lifetime and external effects. Identify authoritative writers and competing transaction/lock paths.
4. **Check native alternatives:** use installed-version Boost docs, locked source for disputed signatures and Filament Examples actual code where relevant. Confirm source date/version/access; course title or premium preview alone cannot establish implementation advice.
5. **Challenge:** compare retain/move/rename/extract/narrow-update alternatives. Prove the responsibility problem and expected benefit. Keep valid static UI factories, simple CRUD, coherent model methods, focused Services, existing compiler/writer/DTO contracts and legitimate inheritance.
6. **Report:** stable IDs, evidence/status/impact, exact current paths/methods/verified lines, smallest recommendations, proposed target paths/FQCNs where supportable, contracts/dependencies and meaningful tests. Separate defects, supported risks, unresolved decisions and optional organization.
7. **Review boundary:** finish findings first when requested. Detailed phases or implementation follow only within the user's authorized stage. Do not run generators, rename classes, delete code/tests, install dependencies or change provider wiring during a read-only audit.

## Required review questions

| Area | Questions |
| --- | --- |
| Helpers/utilities | Is it pure transformation, UI configuration, query, domain operation, or hidden writer? Is there a better existing owner? |
| Actions/services | Is the capability cohesive? Does extraction remove UI/transport coupling while preserving all callers' rules? Is one authoritative writer retained? |
| Validation/DTOs/rules | Are transport validation, normalization, authorization and write-time invariants distinct? Are missing/null/empty/unchanged semantics preserved? |
| Eloquent | Do relationships/scopes/casts fit the stored model? Do custom casts roundtrip correctly? Can bulk/quiet writes bypass required hooks? |
| Providers/container | Are registration/boot responsibilities correct? Are concrete bindings unnecessary? Are scoped/singleton state and actual invocation safe? |
| Traits/macros/inheritance | Is one host behavior shared with explicit requirements? Are globals, collisions and lifecycle deliberate? Is the abstraction smaller than its cost? |
| Livewire/Filament | Are UI state/configuration separate from shared writes? Are authorization/hydration/relationship hooks preserved? Are copied internals version-checked? |
| Location/namespace | Is PSR-4 correct? What policy/event/command/provider/component/panel discovery or serialized references change? Is the move useful beyond preference? |
| Concurrency/effects | Who owns transaction, lock order, revisions, retries, external effects, after-commit work and cache/memo invalidation? |
| Verification | Do tests prove real behavior/failure modes? Are database/browser/CI gaps explicit? Is test execution isolated from non-test runtime state? |

## Evidence discipline

Do not make every string an enum, every validated array a DTO, every class an interface, every query a repository, every repeated line a trait, or every static factory a Service. Length and occurrence counts are inspection heuristics. Laravel supports inline validation, facades, `app()` and many folder conventions; assess their actual consequences.

No direct callers found does not prove unused; a test-only method does not make all other class methods legacy. Different lock orders establish a risk until reproduced. A source-level inconsistency is not automatically a runtime defect. A namespace match is not discovery verification. Never call an unrun proposed test passed.

For repeated audits, reconcile prior IDs as retained/narrowed/superseded/resolved/unverified and preserve original answers separately. If an archive is requested, keep repository-safe findings and private raw outputs separate, with provenance/hashes/truncation limits; exclude hidden reasoning, secrets and irrelevant private content.

Do not create documentation without the user's request or repository workflow requiring it. Do not commit/push merely because an audit is finished.
