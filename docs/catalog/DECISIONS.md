# Catalog and Configurator — Decisions and History

**Updated:** 2026-09-23

**Project:** `/Users/studioycm/Herd/configurator`

**Conversation:** Refactor catalog configurators (`01a0cba9-28dd-7a03-9ec7-0deac3ee16db`)

**Purpose:** Preserve user decisions, corrections and historical evidence. Start a new implementation session with [docs/README](../README.md), the [execution plan](IMPLEMENTATION_PLAN.md) and [guidelines](IMPLEMENTATION_GUIDELINES.md). This record explains authority and provenance; its older workstream skeleton is not a second execution checklist.

**Quick navigation:** [Current status](#1-where-we-are-now) · [Engine decisions](#6-engine-decision-register) · [Corrections](#8-corrections-that-supersede-earlier-suggestions) · [Open choices](#9-open-choices-and-design-work) · [Inputs and defaults](#91-next-review-proposed-details-and-missing-inputs) · [Historical workstreams](#101-workstream-skeleton) · [Historical milestones](#102-proposed-delivery-sequence) · [Completed work](#11-completed-work-and-evidence) · [Maintenance](#14-maintaining-this-plan)

## 1. Where we are now

**Research and technical evaluation are complete; the [implementation handoff](IMPLEMENTATION_PLAN.md) is ready for a new session.** The user selected the fresh-database approach (11-A), and the plan preserves early visible catalog delivery. Channel titles/descriptions, the supplied Laracon demonstration topics, recent FilamentExamples repositories and AI Coding Daily's workflow have been reviewed with explicit evidence limits. UX Design Thinking and Laravel Simplifier are installed. For Pest, use the already-installed official Boost successor, not PodText's customized copy. **The rebuild and database rehearsal have not begun.** Section 9 identifies technical defaults and remaining inputs; section 10 preserves historical workstreams. The execution checklist is now T01–T13 in the handoff.

| Workstream | Current status | Evidence / next deliverable |
| --- | --- | --- |
| Source files, legacy data, current models/engine | Reviewed | Sections 3 and 11 |
| Real browser interaction with both client sketches and the existing app | Completed for the reviewed flows | Section 11; this does not mean the sketches are production-ready |
| Product storage approach | Confirmed | Core columns + three JSON collections |
| Public filter/result behavior | Confirmed | Immediate product cards; configurable pagination; default 10 per page |
| Core engine decisions | Confirmed, including latest corrections | Section 6; exceptions and remaining structural details are explicit |
| Follow-up decision batches | 10 choices settled; item 10 was an explanation request and its assessment is complete | Fresh database selected in 11-A; whole-output conditions are not an accepted new requirement; section 9.2 |
| Example research and final implementation planning | Research and cross-annex evaluation complete; technical proposal ready for review; selected skills settled | [Research index](research/README.md) · [YouTube extension](research/YOUTUBE.md) · [Skill provenance/import](research/SKILL_PROVENANCE.md) · [Implementation proposal](IMPLEMENTATION_PLAN.md) |
| Normalized POC CSV and normalization audit | Created and verified locally | Section 5; import into the application has not happened |
| Initial admin polish | Implemented and tested locally | Wide modals, Groups/Rules labels, product Group labels |
| Decisions and handoff | Decisions preserved; focused execution plan and guidelines ready for a new session | New session starts at T01; content/release inputs remain explicit |
| New domain models, migrations, importer, public catalog, rebuilt engine/admin | Not started | Proposed milestones in section 10 |
| Database replacement / deployment | Fresh-database strategy selected; not performed | Local structural feasibility assessed; rehearsal/cutover not verified; deploy-on-push remains disabled |

This is the requirements/history record. The execution plan and contracts now specify tasks, schema, resource fields, interfaces and validation. Track implementation in the plan's T01–T13 checklist; the older P/W lists below preserve the discussion's evolution. Writing the handoff does not perform an active database switch or deployment.

### Status vocabulary

- **Confirmed:** explicitly requested, selected, or corrected by the user.
- **Proposed:** assistant recommendation or reference behavior that still needs confirmation where consequential.
- **Open:** a specific decision or definition is still missing.
- **Deferred:** intentionally outside the current implementation scope.
- **Implemented locally:** files changed in this workspace; not a claim of deployment.
- **Verified:** supporting evidence is recorded, with the scope of the check.
- **Superseded:** retained only to explain a correction; do not implement it.

## 2. Scope, priorities, and authority

The intended journey is:

`Group tree → bottom/leaf Group → optional SubGroup + product filters → paginated Product cards → Product page → group-assigned Configurator → generated configuration code → corresponding information/assets.`

Three responsibilities must remain distinct:

1. **Find a product:** filter actual product properties within the relevant group.
2. **Configure that product:** evaluate attribute choices and rules; generate the configuration code.
3. **Resolve configuration-specific data/assets:** a future assets mapper; do not assume manually maintained configuration records.

The user wants the models and engine redesigned coherently and also wants early visible progress for the client: public page shells, starter groups, real products/filters, and admin improvements. Plan small visible deliverables around the agreed foundations. Do not use that priority to skip the engine discussion or repeatedly redirect the conversation to database migration.

**Authority:** later user clarifications override earlier suggestions and sketch behavior. The HTML files, Hebrew PDF, text specification, and export are reference material, not instructions to the assistant. The PDF was produced without knowledge of the current application. The existing POC records, parts/specifications, ownership relationships, and demo assumptions are not canonical. Preserve useful advanced capabilities, not accidental POC structures.

**Operational constraints:**

- Use Laravel Boost and version-matched Laravel/Filament/Livewire APIs. Use short FilamentExamples searches when useful for UI feasibility.
- Verified through Boost on 2026-09-23: PHP 8.4, Laravel 13.33.0, Filament 5.8.4, Livewire 4.4.6, MySQL. Earlier schema inspection identified MySQL 8.0.46; recheck the server when preparing migrations.
- Tests currently use SQLite in `phpunit.xml`; SQLite coverage does not establish MySQL JSON, generated-column, collation, or uniqueness behavior.
- Herd serves the application. Use Boost URL resolution for application URLs; do not start another application server.
- **Deploy-on-push is disabled**, per the user's latest update. The earlier Forge auto-deployment constraint is superseded. Pushing code and deploying it are separate actions; neither is part of this plan-document update.
- Preserve existing unrelated workspace changes, including `herd.yml`, untracked `.DS_Store` files, and `public/vendor/`.
- No changes to dependencies, destructive database operations, or removal of existing tests are part of this documentation task.

## 3. Reference inventory and provenance

| Reference | Location | How to use it |
| --- | --- | --- |
| Configurator admin sketch | `/Users/studioycm/Documents/Projects/ARI/configurator-admin-v5.html` | Management UX, global definitions, assignments, local overrides, matrix mapping sets; improve unfinished controls |
| Client data-model thoughts | `/Users/studioycm/Documents/Projects/ARI/configurator-data-model-hebrew.pdf` | Four-page proposal to reconcile with this plan, not an approved schema |
| Filter page | `/Users/studioycm/Documents/Projects/ARI/d60.filter.UI/index.html` | Public product-filter presentation reference |
| Filter definitions | `/Users/studioycm/Documents/Projects/ARI/d60.filter.UI/conf.js` | Seven source filter fields, labels and ordering |
| Filter behavior | `/Users/studioycm/Documents/Projects/ARI/d60.filter.UI/filter.js` | Toggle/clear and newest-selection conflict handling for product filters only |
| Filter explanation | `/Users/studioycm/Documents/Projects/ARI/d60.filter.UI/group_products_filters--how_does_it_work.txt` | Intended filter interaction; apply later pagination corrections |
| JavaScript product data | `/Users/studioycm/Documents/Projects/ARI/d60.filter.UI/data.js` | Legacy data reference; comparison with workbook found no record differences |
| Workbook | `/Users/studioycm/Documents/Projects/ARI/d60.filter.UI/d060.groups_2144.xlsx` | Original import evidence; leave unchanged |
| Hosted filter sketch | [d60-filter.test](https://d60-filter.test/) | User-provided browser-accessible copy, tested this session |
| Hosted admin sketch | [configurator-admin.html](https://d60-filter.test/configurator-admin.html) | User-provided browser-accessible copy, tested this session |

The earlier local `file://` browser block and admin login prerequisite were resolved by the user hosting the references and signing in. Browser access is not a current blocker.

### Conversation checkpoints

These checkpoints identify the source of decisions without retaining the entire transcript:

- **R1 — Initial request:** group/product/configurator separation, model/engine refactor, client references, future assets mapper, Livewire public catalog.
- **R2 — First detailed clarification:** each export row is a Product; legacy IDs; `SubGroup` and `Attribute`; group assignment; advanced abilities; subgroup hiding; first/default options; visible progress; consider a fresh database.
- **R3 — Storage/browser clarification:** accept core columns + JSON; normalized CSV POC; label tab `Custom`; `s60` was a mistake; physical `name` discussion. Deploy-on-push was enabled at that point; R10 supersedes that status.
- **R4 — Hosted references:** browser interaction explicitly requested and completed on the supplied HTTP(S) pages.
- **R5 — Result pagination clarification:** remove single-result constraint; accept immediate paginated cards, initial page size 10, optional visitor page-size control.
- **R6 — Missing selections:** accept the recommended behavior provisionally, with admin diagnostics and a centrally changeable policy.
- **R7 — Answers to 16 engine decisions:** accept 1–3; choose 4-A, 5-A if simple otherwise B, 7-A, 8-A, 9-B, 11-A, 13-A, 14-B, 15-B for now, 16-A; explicitly correct mapping presentation, code alphabet, priority, and saved-configuration assumptions.
- **R8 — Final context correction:** accept the proposed meaning of `All`; Territory and Application are **not multiple**. Both are single-select.
- **R9 — Living plan request:** consolidate the full conversation and maintain this document progressively.
- **R10 — Plan skeleton and discussion checkpoint:** explicitly include filters UI, engine changes, data changes, catalog foundation/first usable public flow (Core models, Import, Public pages, Admin management), and database transition/fresh-database feasibility. Deploy-on-push is now disabled. Continue discussing missing plan details before implementation.
- **R11 — First follow-up batch:** discuss three decision groups at a time. User selected code order managed by the configurator itself (no global ordering layer), independent explicit defaults (2-A), and partial mappings with complete sets (3-A). O3/O4/O6 are settled; eight groups remain.
- **R12 — Second follow-up batch:** user selected drag-to-reorder rule priority (4-A), sharing a configurator across groups (5-A), and source-wins reimports for imported fields (6-A). The import must create Groups, Products and the three Product JSON collections, preserving legacy product/group identities and resolving their relationships to newly generated internal IDs. O5/O7/O8 are settled at the policy level; five groups remain.
- **R13 — Exact source identity clarification:** source `id` is the Product legacy ID; source `group_id` is the legacy group reference used to find/create a Group with that `legacy_id`; source `group_name` supplies the new Group's name. This clarifies O8 and does not answer pending items 7–9.
- **R14 — Third follow-up batch:** user selected an Eloquent `name` accessor with the physical database column deferred (7-B), keeping `product_code` for admin labels and `product_name` for the product page; SubGroups act as filter presets with newest-choice reconciliation (8-A); removals require explicit repairs to references/defaults (9-A). Nine groups are settled; O9 and O1 remain.
- **R15 — Research and final planning request:** user selected 11-A (fresh MySQL database with clean domain migrations and retained accounts/non-domain data), asked why item 10 mentions whole-code conditions and changing selections, and requested several agents to research Laravel Daily and Filament Examples using browsers/MCP and save research documents. After that, inventory PodText skills for the user to choose, then evaluate the plan and current code using Laravel/Filament skills and build the final implementation plan. Do not treat the item-10 question as agreement to a new feature.
- **R16 — YouTube discovery extension:** additionally review the Laravel Daily and Filament Daily channels' Videos sections for the past year and 2025, using relevant uploads to discover what to search on LaravelDaily.com and FilamentExamples.com. Cover January 2025 through the research date. The user clarified that **titles and descriptions** must be inspected; title-only cards are discovery candidates, not researched leads. Verify selected patterns against current versions.
- **R17 — Skill choice and Pest clarification:** user selected UX Design Thinking and Laravel Simplifier (the other two proposed skills) for import. Both were copied independently into this project and registered; no package changed. PodText's Pest skill explicitly targets Pest 5 and is a rendered upstream skill with a local parallel-testing restriction and explanatory note. Explain that provenance without importing Pest until selected. Use existing testing guidance for planning meanwhile.
- **R18 — Deeper source research and official testing guidance:** continue research using the supplied [Laracon livestream](https://www.youtube.com/watch?v=vii6P0vJhTw), especially Povilas Korop's advanced Filament examples and related LaravelDaily, FilamentExamples and AICodingDaily material, with particular attention to recent FilamentExamples projects and their GitHub source. The user prefers original package/Boost testing guidance and explicitly excludes PodText's customized Pest skill. Upstream verification found that Pest retired the standalone skill on 2026-08-25 in favor of the already-installed Boost `testing-best-practices`; use that official successor.
- **R19 — Final handoff:** move the root planning/research documents into `docs/`, review them all and create focused implementation instructions/guidelines for a new session. All sixteen documents were moved, links rebased, research alternatives reconciled with the final contracts, and an authoritative task plan/start page/guidelines prepared. This session remains documentation-only.

## 4. Confirmed catalog and management direction

### 4.1 Vocabulary and ownership

| Concept | Target meaning | Status / boundary |
| --- | --- | --- |
| `Group` | Actual node in the catalog hierarchy | Confirmed vocabulary; rename misleading `CatalogGroup` terminology coherently |
| `SubGroup` | Optional named preset of product-property values inside a group page | Confirmed spelling; distinct from actual child Group nodes |
| `Product` | One exported product-code row, with fixed product facts | Working target name; earlier `ProductProfile` alternative does not imply a separate profile layer |
| Product properties | Real imported product facts used for filtering and display | Separate from configurable attributes |
| `Configurator` | Configurable definition reached through the product's group | Replace misleading `ConfigProfile` terminology and product-owned POC assumptions |
| `Attribute` | A configurable dimension with selectable options | Confirmed term; participates in a configurator rather than product-property storage |
| `Option` | A canonical attribute–value meaning with a unique code | Confirmed code ownership in E11; technical associations specified in the data/engine contract |
| Shared `Value` | Reusable business value, such as Stainless Steel 316 | Shared value concept accepted through E11; Value is the final plan's model name |
| Configuration result | Current evaluated selections, code, completeness, and diagnostics | Runtime result; not manually authored configuration records |

A leaf group has one assigned configurator when configured, and assignments can be managed from the configurator. Several leaf groups may share the same configurator; edits affect every assigned group. Duplicate the definition when a group needs independently maintained behavior. Assignments are explicit at leaf level, with parent groups used for navigation and no ancestor inheritance. While unassigned, a group's products remain visible with their product information but without a configurator; flag the missing assignment in admin.

Configurator-specific inclusion, option ordering/defaults, and local display overrides must remain distinct from canonical option/code identity. The first included option becomes the stored initial default; the administrator can choose another using an explicit default control. Subsequent option reordering does not change the stored default, including when the administrator has never manually overridden it.

Each configurator manages its own configuration-code order. There is no global default order, copying or inheritance layer. Code order remains separate from UI display order; reordering visible controls does not automatically change code order.

### 4.2 Public group filters and SubGroups

- Reach a bottom/leaf group through the real group tree, then show its product filters and results.
- Configure filter property keys, labels and ordering at group level; product storage alone does not decide which properties a group exposes.
- Preserve the filter reference's interaction: one selected value per ordinary field, no automatic selections, clicking a selected value clears it, and incompatible values can be clicked so the newest selection wins and conflicting earlier selections are reconciled.
- This behavior is **only for product discovery filters**. It must not be reused for disabled configurator options.
- A SubGroup has a label, a chosen product property, and one or more preset allowed values. It is optional and configured in group settings.
- Visitors can switch SubGroups or reset them.
- A SubGroup is a filter preset, not a fixed navigation boundary. When the newest ordinary filter choice cannot coexist with that preset, clear the conflicting SubGroup, explain the change and restore the corresponding field's ordinary visibility. The actual leaf Group remains the scope. Preserve still-compatible choices under the reference reconciliation behavior.
- Hide the corresponding property filter when the selected SubGroup supplies only one allowed value. Keep it available for a multi-value preset, such as Working Pressure 10 or 16.
- Provide a SubGroup setting to force-hide that field's filter.
- SubGroup presets do not duplicate Product records. Their constraints and reset behavior must be tested with the ordinary filters.
- Show product cards with useful comparison facts **immediately**, with a count and pagination; initial page size **10**.
- Admin/code settings determine page size and whether visitors may change it. A page size of 1 means one card per page, not a requirement to narrow the result set to one product.
- Do not implement a hardcoded single-result threshold, single-result-only NEXT flow, or mandatory result-count threshold.
- Working page defaults accompanying 8-A: cards show product name/code, pressure and connection facts; initially sort by product code; show filter-value counts; keep SubGroup/filter/page state in the URL so Back restores it. These are catalog-discovery controls, not session persistence for configurator context.
- "Clear filters" clears ordinary filters while preserving the selected SubGroup. "Reset all" clears both. Reconcile filter visibility/results and reset pagination when criteria change.

### 4.3 Configurator management and deferred UI

- Preserve the sketch's approachable matrix UI for mapping source-option sets to allowed target-option sets.
- Partial mapping coverage is allowed. An unmapped source adds no restriction from that rule; other applicable rules still apply. Every saved mapping set must include at least one source and one target option, and a source cannot belong to two sets within the same rule.
- Intentionally empty target sets are outside the selected mapping editor behavior. Empty intersections between independently valid rules still follow E01; authoring validation does not eliminate or redefine that runtime case.
- Preserve exclusion capability as a separate advanced, normally less prominent rule type.
- Visibility conditions may use selections, product properties, territory/application, and supported engine inputs; they are not restricted to product properties.
- Preserve existing useful abilities: reactive recalculation, context conditions, allowed restrictions, hide/disable actions, defaults, label/display-value overrides, hints/help, and preview feedback.
- Preview & Test must evaluate the same engine and policies as the public configurator.
- Manage rule priority by dragging rules into order, highest priority at the top. Maintain priority values automatically. Keep the ordinary condition editor as an AND list with optional one-level AND/OR groups; no unrestricted deep nesting in the initial scope.
- Defer stages/items as a separate system. Ordinary attributes and rules drive the current configurator.
- Add only a placeholder tab titled **Custom**. Do not implement custom behaviors, pop-ups, texts, or arbitrary custom sections in this phase.
- Existing demo tabs and data provide visual references. Product-dependent tabs can begin with honest placeholders where the future mapper is required.
- Remove or reorganize irrelevant admin resources as part of the agreed redesign; do not infer permission to delete their data or tests.
- Removing an option used as a default or in a mapping requires explicit repair before saving: show references, choose a replacement default or correct affected mappings. Prevent deletion of a shared definition while it remains referenced. Do not silently replace defaults or remove dependent rules as a side effect of removal.
- Distinguish those structural edit errors from potentially conflicting but valid rules. Empty-intersection scenarios continue to use the agreed admin diagnostics and E01 runtime behavior; do not reject every possible conflicting combination or imply exhaustive validation.

## 5. Product data and normalization

### 5.1 Confirmed storage direction

Use **core columns + JSON**, not a one-to-one copy of the complete export schema or a generic field/value system for the initial build.

| Storage | Intended contents | Detail status |
| --- | --- | --- |
| Core columns | New internal identity, legacy product ID, legacy group reference, resolved internal group reference, `product_code`, product-page label and essential lifecycle/display fields | Identity/relationship direction confirmed; exact field types, lengths and indexes require the schema plan |
| `properties` JSON | Product facts used for filtering and display | Confirmed direction |
| `parts` JSON | Source part entries, retaining their numbered diagram positions | Confirmed interim storage; future reusable Part model deferred |
| `extra_data` JSON | Remaining imported columns whose business meaning is unsettled | Confirmed direction; retain source information |

`product_name` is for the public product page. Admin record labels use `product_code`. **7-B is confirmed:** expose `name` as an Eloquent accessor returning `product_code`; defer a physical/generated `name` database column. Configure Filament titles and SQL search/sort to use `product_code` explicitly. The accessor does not create a SQL column, so database `where`, `orderBy` or `pluck` must not target a nonexistent `name`. No `title` alias or separately editable admin name is requested.

The importer creates Groups and Products with new internal primary keys, preserves their legacy IDs, resolves legacy relationships to internal foreign keys, and fills all three Product JSON fields. Import **D060, legacy group 2144**, and its exported products. The starter parent's final label/source identity still needs to be supplied through import metadata or additional source data; do not fabricate a legacy ID or parent relationship.

### 5.2 Source facts established during this session

- Workbook: 501 product records, 67 column positions; worksheet and `data.js` matched. Source IDs and product codes are unique.
- The normalized source header includes `id`, `group_id` and `group_name`; it has no parent-group ID column. Preserve `sub_group` as source data until its mapping is reviewed; its presence does not establish a parent/child Group relationship or automatically define the dynamic SubGroup settings.
- Declared filters: `Working_Pressure`, `Valve_Type`, `Connection_Type`, `Connection_Size`, `Automatic_Type`, `Automatic_Config`, `Discharge_Outlet_Type`.
- `Part1` through `Part28` are ordered diagram slots, not stable reusable part identities. Earlier inspection found six meanings at Part1 and `Kin_Seat` at positions 1–7.
- Parts contain material descriptions embedded in text. Entity-decoded angle brackets are literal data and must remain escaped when rendered; blanket tag stripping would lose information.
- Preserve ambiguous auxiliary columns, units and terminology. `Addition_to_Product_Code` is not proven redundant; empty `with_s50c` is not established as false. Column 48 has an empty header and empty values.

### 5.3 Created local POC artifacts

- [Normalization script](../../storage/app/imports/ari/normalize_export.py)
- [Normalized CSV](../../storage/app/imports/ari/d060.groups_2144.normalized.csv)
- [Per-change audit](../../storage/app/imports/ari/d060.groups_2144.normalized.audit.json)

Applied policy: decode HTML entities once, normalize Unicode NFC, trim surrounding whitespace, and convert the known blank1/blank2/blank3 ZWNJ-only placeholders to empty. Preserve identifier spelling, leading zeros, original columns, units, and uncertain direction markers.

Verified results: **501 rows, 67 columns, 7,557 changed cells**, unchanged unique IDs/product codes, and 259 leading-zero values preserved. CSV roundtrip and normalization idempotence passed. Four expected collision groups: one Part2 whitespace variant and the three blank/ZWNJ variants. No identifier collisions. There are 115 preserved direction-marker cells awaiting business clarification.

Source SHA-256: `e48f7863066bc804f522193e6f814f86afff247ab79e8948c13f488923a1c58d`

Normalized CSV SHA-256: `5a8adbd4439bb1f020ab625d84fa6fde3a62ce67fbd74f94cf2b2e3b19758f72`

The source workbook is unchanged. These artifacts live under Git-ignored `storage/app/`; they are not a deployed importer or application data. The importer will carry the same tested normalization into the confirmed import behavior below. Exact proposed source-column mapping is recorded in the core technical annex; unapproved synonym conversions and ambiguous source meanings remain preserved.

### 5.4 Confirmed import identity and update behavior

Create or update groups first, then resolve product relationships and import Product core fields plus `properties`, `parts` and `extra_data`. The [core technical annex](contracts/ENGINE_AND_DATA.md) now proposes exact fields, types and the exhaustive 67-column map; these are reviewable implementation details, not an implemented schema.

| Source | Target / treatment |
| --- | --- |
| Source `id` | Product `legacy_id`; this is the legacy product identifier. Allocate a new internal Product `id` on first import and retain it on reimport. |
| Source `group_id` | Group `legacy_id`; find or create one Group per distinct legacy group ID. Allocate the Group's own new internal `id` on first import and retain it on reimport. |
| Source `group_name` | Group `name`; use it to name the Group created from the source `group_id`. Reuse the Group by legacy identity across product rows, rather than creating one Group per product or matching only by name. |
| Product source `group_id` | Preserve the source reference as Product `legacy_group_id`; look up Group by its `legacy_id` and set Product `group_id` to that Group's internal `id` |
| Remaining mapped product columns | Populate core fields and the three JSON fields according to the reviewed mapping; retain numbered part positions and uncertain extra columns |
| Parent hierarchy, where supplied separately | Resolve legacy parent references to internal Group `parent_id` values; the provided sample alone does not define this hierarchy |

Internal relationships use the new application IDs. A legacy group reference is import/provenance data, not a value to copy directly into the new internal foreign-key column. Unknown or conflicting group identities must be reported rather than silently attaching a product to an unrelated record.

**Reimport policy — 6-A:** source values win for the fields owned by the import, including intentional blanks. Application-only fields remain unchanged. Products absent from a later file remain unchanged and are reported; absence alone does not archive or delete them. Reuse existing internal IDs and records through legacy identity matching. Preserve imported and application-only JSON keys according to the field mapping rather than indiscriminately replacing unrelated data. The core annex specifies the proposed exact map and rejects an unexpected/missing positional column before applying this known source format; no new synonym/semantic conversions have been approved.

## 6. Engine decision register

The E01–E16 numbering follows the bulk engine discussion. These are business rules for the rebuild; the present POC implementation is not evidence that they are already implemented.

| ID | Decision | Confirmed behavior / qualification |
| --- | --- | --- |
| E00 | Missing selections | A condition requiring a missing selection does not match; absence does not satisfy `!=`. Dependent rules requiring it stay inactive, while other applicable rules continue. The target remains selectable under the remaining restrictions. Missing required selections make the result incomplete. Add admin diagnostics and keep this policy centrally changeable. Ordinary initialization/fallback should supply selections whenever legal options exist. |
| E01 | Empty intersection / no legal options | Keep the visitor's latest change, clear the affected selection, explain the conflict, mark the configuration incomplete, and withhold a valid final code. Do not silently relax a rule or revert the visitor's change. |
| E02 | Invalid selected option, alternatives available | Preserve a still-valid selection. Otherwise select the designated default if allowed, then the first allowed option in the configured option order. Briefly identify automatic changes. |
| E03 | Chains and cycles | Recalculate the complete affected chain. Reject circular dependencies initially and explain the cycle in admin validation. Visual order and rule priority do not replace dependency analysis. |
| E04 | Hidden attribute | Hidden means inapplicable: no active selection, outgoing selection-dependent rule contribution, or code segment. It is exempt from completion while inapplicable. This concerns hiding the entire attribute, not merely hiding one option. |
| E05 | Attribute becomes applicable again | Prefer restoring its previous selection if still valid; otherwise normal fallback. User allowed always-default behavior if restoration proves complex. Proposed implementation keeps prior choices only in current interaction state; it does not require persistent saved configurations. Do not deliberately leave an applicable attribute unanswered when a legal option exists. |
| E06 | Hidden/disabled outcomes and mapping presentation | Mapping-set restrictions disable options outside the allowed result. Separate rule outcomes can explicitly hide or disable options. Disabled configurator options cannot be clicked to clear upstream choices. The product-filter conflict-clearing interaction is not applicable here. |
| E07 | Required attributes | Every applicable attribute requires a valid selection. Optional unanswered attributes are not part of this design. The exceptional incomplete case E01 remains possible when no legal choice exists. An explicit `Without` option is a genuine coded selection. |
| E08 | Priority / order | Admin drags rules into order, highest priority at the top; stored priority values are maintained automatically. Conflicting scalar display outcomes use that priority; any equal-priority conflicts from invalid/imported data still produce diagnostics. Allowed-option restrictions combine by intersection, with exclusions subtracting. No implicit highest-priority restriction replacement has been approved. |
| E09 | Condition groups | AND by default; ordinary editor uses a simple AND list with optional one-level AND/OR groups. Unrestricted deep nesting is outside the initial scope. Exact supported operators remain part of the engine contract. |
| E10 | Territory / Application | Both are **single-select**, default **All**, and editable by the visitor. All leaves that dimension unrestricted rather than expanding to every territory/application-specific restriction. A specific choice participates in matching conditions; groups use AND/OR and active restrictions intersect. Session persistence is deferred. |
| E11 | Code identity | A code belongs to the canonical Attribute + Value meaning. Body Material + 316 and Seat Material + 316 have different codes. Several configurators can reference the same canonical option without creating duplicate code records. |
| E12 | Code sequence | Each configurator manages its own code order, separately from UI display order. Reordering visible controls must not automatically change the code. Latest user choice removes the previously proposed global ordering/copy/inheritance layer. |
| E13 | Code presentation | Product code is displayed separately; configuration code appears beneath it. Generate the ordered option-code string as in the existing demo, retaining the current hyphen-separated presentation unless later changed. No product-code prefix was selected. |
| E14 | Code allocation | **Manual entry**, exactly two ASCII characters from `A-Z`, `a-z`, `0-9`, globally unique with case-sensitive comparison. Preserve case and leading zeros. `Aa`, `aa`, and `00` are valid distinct examples. There are 3,844 possible strings. Automatic allocation and uppercase-only normalization are superseded. |
| E15 | Definition changes / visitor persistence | For now, use current definitions and apply edits immediately rather than pinning visitors to a published version. No manual configuration inventory and no visitor save/load feature. Configurator history/version snapshots and future RFQ/order metadata are deferred pending the client. |
| E16 | Policy scope | Central engine defaults with selected configurator-level overrides. Do not scatter the same policy across individual rules, admin preview, and public UI. Exposing every policy as a visible settings control immediately is not required. |

### 6.1 Priority is not the same as dependency order

Use one documented interpretation in editor, preview, and runtime:

- **Constraint composition:** active allowed sets intersect; advanced exclusions subtract. Running a restriction earlier or later does not silently make an illegal option legal.
- **Presentation conflicts:** the applicable rule higher in the admin order wins a conflicting scalar outcome. The ordinary reorder UI maintains distinct positions; any conflicting equal priorities from invalid/imported data still produce a diagnostic. Apply the confirmed structural-validation boundary and specify the exact diagnostic/presentation behavior in the engine contract.
- **Dependency resolution:** changes propagate through attribute dependencies before returning a settled result. Report cycles instead of relying on the order of rows in the admin.

An explicit restriction-override or stop-processing action would be a new capability, not an automatic consequence of adding priority.

### 6.2 Diagnostics and completeness

Admin validation/Preview & Test should explain the missing or inapplicable source, inactive rules, affected targets, empty intersections, automatic fallback, and final completeness. Show the contributing rules where possible. Detectable structural errors can be validated before use; do not claim every possible combination has been exhaustively proven safe.

All applicable attributes are normally selected automatically. “No unanswered attributes” does not permit inventing a selection when the allowed set is empty. That is the explicitly accepted incomplete/conflict state. Returning from that state must re-evaluate restrictions and use E02 rather than retaining an invalid selection.

`All` is an explicit unrestricted context state, not a missing input and not a multi-selection. Territory All + Application Industry leaves territory unrestricted while application conditions can affect the result.

### 6.3 Engine structure carried into the final contract

The detailed DTOs, typed conditions and evaluation order are specified in the data/engine contract. This summary preserves the original intent:

1. Load one definition and trusted Product/context inputs.
2. Resolve applicability, matching conditions, restrictions and presentation outcomes with the agreed priority semantics.
3. Preserve valid selections, apply fallback/restoration, and propagate dependencies until the acyclic evaluation is settled.
4. Return resolved selections, allowed/disabled/hidden state, presentation overrides, completeness, code, and diagnostics as one result.
5. Use that same result in Preview & Test and the public page. Avoid a second simplified evaluator that behaves differently.

O9's assessment is complete: whole assembled-code feedback has no demonstrated use and is omitted from the initial technical design. Selected-option-code conditions remain included. Product/context-only rules must not require a selected option trigger. Do not reopen the old feedback branch as a prerequisite to implementing the agreed engine.

## 7. Explicitly deferred work

- A separate configurator stages/items system.
- Custom functionality beyond the **Custom** tab placeholder.
- Final parts/specification modeling and reusable Parts identity; preserve source data in JSON meanwhile.
- The assets mapper's matching rules, storage, and authoring UI. Configuration-dependent content must not be faked by taking the first stored configuration.
- RFQ/order workflows and persisted visitor configurations associated with those workflows.
- Configurator version history/snapshots and version metadata for historical RFQs/orders, pending the client. A version reference alone cannot reproduce history unless its definition is retained.
- Session persistence for Territory/Application and other cross-visit restoration behavior.
- A physical/generated Product `name` column; use the chosen accessor and explicit `product_code` admin/query configuration for now.
- Any new restriction-override action, unrestricted nested condition editor, or cycle-supporting solver beyond the chosen initial scope.

## 8. Corrections that supersede earlier suggestions

| Earlier statement / assumption | Current decision |
| --- | --- |
| `s60` starter child group | Source is **D060 / 2144**; `s60` was a typo |
| `GroupSubgroup`, `ConfigurationAttribute` as preferred vocabulary | Use **SubGroup**, **Attribute** |
| `Custom items` tab | **Custom** only |
| Every engine step needs a stage/item model | Stages/items deferred; use attributes and rules |
| Filter must reach exactly one product | Immediate paginated cards, default 10/page; page size is a setting |
| A minimum/maximum match-count threshold is required before results appear | No threshold in the agreed starting behavior |
| Visibility can use product properties only | It can use supported selections, product data, and context inputs |
| Configurator disabled options behave like clickable incompatible filter values | They do not; the two interaction models are separate |
| Hidden attribute may continue contributing its choice/code | E04: inapplicable, no active contribution |
| Applicable attributes may be optional/unanswered | E07: every applicable attribute requires a valid selection |
| Territory/Application are multiple choice | Latest correction: **single-select** |
| All expands to all specific context restrictions | All means unrestricted for that dimension |
| Uppercase-only or automatically suggested codes | Manual, case-sensitive **A–Z, a–z, 0–9** |
| Configuration records/save-load/version publishing are current requirements | Runtime results now; RFQ/history decisions deferred |
| Fresh database has already been created or proved by rehearsal | 11-A selects the fresh-database strategy; creation, transfer, rehearsal and cutover have not happened |
| Whole-code selection feedback is a client requirement | It is a legacy engine capability raised by the assistant; explain its actual use before adding any requirement. Item 10 is not answered A/B |
| Product needs a physical/generated `name` column now | Defer that column; `name` is an accessor for `product_code`, used for admin labels; `product_name` is for the product page |
| Global code order with copying or inheritance | Latest choice: code order is managed entirely by each configurator |
| Reordering options changes the initial default until manually overridden | Store the initial default independently; subsequent reordering never changes it |
| Mapping requires full source coverage or permits intentional empty target sets | Partial coverage is allowed; each saved set has source and target options; duplicate source membership within a rule is rejected |
| Admin must enter rule priority numbers | Drag to reorder; higher rows have higher priority |
| Each group must maintain a separate configurator copy | Several leaf groups may share a configurator; assignments are explicit |
| Legacy source IDs become the new primary/foreign keys directly | Allocate internal IDs, retain legacy identities and resolve legacy references to internal foreign keys |
| Initial groups/products/JSON fields will be manually populated separately from import | The importer creates and populates them from the source and explicit import metadata |
| SubGroup remains fixed when the newest filter conflicts with it | Clear the conflicting preset, restore normal field visibility and explain the change; remain within the actual Group |
| Removing referenced options automatically repairs or discards their dependents | Require explicit repairs and prevent deletion of shared definitions that remain in use |
| Sketch bugs or inactive controls limit the intended feature set | Improve them; preserve the desired ease of use |
| Forge automatically deploys every push | Latest user update: **deploy-on-push is disabled** |
| A completed research/implementation proposal automatically starts the rebuild | Review the proposed technical plan and select the implementation milestone; code, import and database switching remain separate work |

## 9. Open choices and design work

Do not reopen settled choices just because their implementation has not started. Ten of the eleven follow-up groups are resolved (section 9.2). Item 10/O9 was an explanation and assessment of a legacy capability, not an assumed new business requirement; its research is complete below. O10 remains deferred and is excluded from that count. The [implementation plan](IMPLEMENTATION_PLAN.md) and three annexes now supply proposed schema, import, engine and UI contracts. Review of those proposals, missing content and future execution evidence are distinct from unresolved business policy.

| ID | Topic | Current position / proposed next treatment | Needed before |
| --- | --- | --- | --- |
| O9 | Legacy whole-code condition assessment | [Research completed](research/ENGINE_AND_DATA.md): the PHP branch is technically enterable through the free-text source field, but zero of the five local stored rules use it and no tests cover it. Changing selections meant normal fallback after invalidation, not Product edits or saved configurations. The question has been explained; recommend no new whole-code feedback behavior without a concrete use case. This recommendation is not an A/B user answer. Selected-option code, product/context conditions and agreed outcomes remain in scope. | Carry the evidence and scope distinction into the final engine plan |
| O10 | Future code edits/history | Versioning remains deferred. Code retirement/reassignment and historical references need a policy before persistent RFQ or asset mappings rely on them. Do not present code immutability/retirement policy as already approved. | Durable configuration/asset/RFQ features |

Authorization uses explicit resource/domain checks through the planned `manage-catalog` gate, preserving the existing `User::canAccessPanel()` admission intent. Inspection found no existing policy classes to reuse. No new role hierarchy or permission model has been selected.

### 9.1 Next review: proposed details and missing inputs

The database strategy is settled and O9 has been explained from code, stored rules and tests. Review the proposed technical contracts below without restarting the decision questionnaire. Keep confirmed choices as the starting point; initial content and deployment evidence remain separate inputs.

| Review area | Specification now available / remaining input | Related items |
| --- | --- | --- |
| Filters and first public flow | [Public annex](contracts/PUBLIC_CATALOG.md): exact routes/state, preset reconciliation, counts, cards and pagination. Review technical defaults; supply parent-group metadata and any initial SubGroups. URL/Back behavior still requires implementation proof. | Resolved O11; W1, W4 |
| Configurator and engine | [Core annex](contracts/ENGINE_AND_DATA.md): model/DTO names, typed sources/operators, dependency evaluation and diagnostics. O9 evidence supports omitting whole-output feedback; actual initial definitions/context choices remain content inputs. | O9; W2 |
| Product data and imports | Core annex: all 67 columns mapped once into core inputs and three JSON buckets, exact identity/blank handling and full-file preflight/atomic apply proposal. Review proposed field capacities and import entry point. | Resolved O2/O8; W3 |
| Admin management | [Admin annex](contracts/FILAMENT_ADMIN.md): resource/tab names, staged matrix saves, authorization and explicit repair. Review initial import-first/read-only Product presentation and duplicate defaults; no new publishing/history system. | Resolved O12; W2, W4 |
| Database transition | [Implementation plan, section 7](IMPLEMENTATION_PLAN.md): 17 retained and 19 replaced migration files, data-transfer boundaries and MySQL rehearsal/rollback. Deployment inventory and a successful rehearsal remain future evidence. | Resolved O1; W5 |

The assistant has drafted class boundaries, query/index choices, field types, migration dependencies and test cases. They do not all require separate user decisions. Bring back material changes to behavior, data retention, scope or the editing experience; proposed technical defaults remain clearly labelled in the annexes.

Parts/specification redesign, asset-mapper details, RFQ/history, session persistence and Custom functionality remain deliberately deferred. Their absence does not block the agreed placeholder-based first public flow. Do not reopen the 16 engine answers as a new questionnaire.

### 9.2 Resolved follow-up decisions

These are planning decisions, not claims of implementation. Preserve the original O-identifiers for traceability.

| Batch item | Plan ID | Confirmed outcome |
| --- | --- | --- |
| 1 | O3 | Code order is managed entirely by each configurator. No global default/copy/inheritance mechanism. UI display order remains separate. |
| 2 — A | O4 | Store the first included option as the initial explicit default; allow the admin to change it. Reordering options never changes the stored default. Association/storage details are implementation design; removal uses the later confirmed explicit-repair policy in O12. |
| 3 — A | O6 | Allow unmapped source options; the rule adds no restriction for them. Each saved mapping set requires source and target options. Reject duplicate source membership within one mapping rule. No intentional empty target sets. E01 still governs empty intersections across active rules. |
| 4 — A | O5 | Drag rules into order, highest priority at the top; application manages priority values. AND by default with optional one-level AND/OR groups. Constraint intersections remain unchanged. Detailed operators/presentation belong in the engine contract; structural editing follows the later confirmed O12 policy. |
| 5 — A | O7 | Several leaf groups can share a configurator; edits affect all assigned groups and duplication gives independent behavior. Explicit leaf assignments, parents for navigation, no ancestor inheritance. Unassigned groups/products remain visible without a configurator and receive an admin diagnostic. |
| 6 — A + clarification | O8 | Import creates Groups, Products and all three JSON fields, allocating internal IDs while retaining legacy IDs and the product's legacy group reference. Resolve application foreign keys through the legacy-to-internal ID map. On reimport source wins for imported fields, app-only fields are preserved, and absent products remain/report. Exact proposed mapping is now recorded in the core annex. |
| 7 — B + clarification | O2 | Use an Eloquent `name` accessor returning `product_code`; defer the physical/generated database column. Use `product_code` for admin labels and SQL search/sort, `product_name` for the public product page. |
| 8 — A | O11 | SubGroup is a filter preset; an incompatible newest filter clears it with feedback and ordinary filter visibility restored, within the actual Group. Working card/count/sort/URL defaults are recorded in section 4.2; Clear filters preserves the preset, Reset all clears both. Initial parent metadata remains content to supply. |
| 9 — A | O12 | Require explicit replacement/default or mapping repairs before removal; show references and prevent deletion of shared definitions in use. No automatic cleanup of dependents. Valid-rule runtime conflicts retain agreed diagnostics and E01 behavior. Exact navigation/resource screens remain design work. |
| 11 — A | O1 | Use a fresh MySQL database with retained infrastructure/package migrations and clean new domain migrations; transfer required users/non-domain data, import the new catalog, and archive the old database/files with matching code. Exact transfer map, production inventory, rehearsal and cutover remain to specify and verify. |

**Item 10 is not an A/B selection:** the user asked when/why whole-code inspection and changing selections would be needed. That investigation/explanation, skill selection, research and final planning are now complete. The next session executes the handoff within its instruction; it does not repeat this historical workflow or automatically cut over a database.

## 10. Progressive delivery plan

The workstream skeleton below preserves the requested coverage and original P0–P5 discussion. **The final execution sequence is T01–T13 in the implementation plan.** Use that single checklist for progress; these historical lists are supporting context.

### 10.1 Workstream skeleton

#### W1 — Filters UI and plan

**Target:** a leaf Group page with optional SubGroups, responsive filters, informative product cards, and configurable pagination. Product filtering remains independent of configurator rule evaluation.

1. Specify group-level filter definitions: property keys, labels, display order, value labels/order and result settings, using section 4.2 as the confirmed behavior.
2. Specify selection/reconciliation state under resolved O11, including clearing a conflicting SubGroup preset, field visibility, switching/reset and incompatible newest selections; group/configurator assignment follows resolved O7.
3. Design the Livewire page with reusable Filament components where suitable. Include loading, no-match and reset states, accessible controls and narrow-screen behavior; improve the reference's overflow.
4. Specify queries for matching products, filter values/counts and pagination across the full group scope. Evaluate JSON query/index requirements against actual data.
5. Implement and browser-check the agreed interactions, pagination settings and product links. Compare with the reference without retaining its single-result NEXT limitation.

**Deliverable:** filter interaction/query specification, followed by a usable group page. **Dependencies:** W3 product/property contract and W4 group foundation. O11 policy is resolved; exact screens, queries and proof cases are proposed in the public annex. Implementation is pending.

#### W2 — Engine changes and plan

**Target:** one engine shared by public configuration and admin Preview & Test, implementing E00–E16.

1. Specify canonical Attribute/Value/Option identity, configurator-local inclusion/defaults/display overrides, independent code order, rules and mapping-set storage.
2. Specify engine inputs and condition types/operators: product facts, selections, Territory/Application and other agreed inputs. Assess legacy generated-code conditions separately before treating them as a requirement.
3. Define the evaluation contract: applicability, condition matching, priority, intersected restrictions/exclusions, dependency propagation, selection fallback/restoration, completeness and diagnostics.
4. Define the editor/runtime validation boundary and the limited configurable policy overrides. Give admins actionable diagnostics for problematic combinations.
5. Build the shared engine and then its matrix/rule editor, preview and public integration. Preserve the agreed advanced POC abilities through independent expected outcomes in section 12.

**Deliverable:** model and engine contracts, then tested engine and management integration. The core annex supplies the proposed contracts; O9's completed assessment is carried as a scope recommendation, not a new user decision. Implement resolved order/default/mapping/assignment/editing policies in section 9.2. Separate stages and Custom behavior remain deferred.

#### W3 — Data changes and plan

**Target:** consistent naming and identities; Product core columns plus `properties`, `parts`, and `extra_data` JSON; canonical configurator codes separate from fixed product facts.

1. Complete a source-to-target map for every export column, preserving legacy IDs, source identity, numbered part slots and ambiguous values.
2. Specify core fields, types and indexes with `product_name` for the public page and `product_code` for admin/search/sort. Add the chosen Eloquent `name` accessor; do not add a physical name column. Define property metadata needed by group filters without introducing a generic field/value store by accident.
3. Carry the audited normalization policy into the importer; retain the original source and reports so corrected values can be reimported.
4. Specify the exact field map, missing-column handling and error reporting under section 5.4's confirmed identity/source-wins policy. Create Groups before Products, resolve new internal relationships and populate all three Product JSON fields through the import.
5. Specify global case-sensitive two-character code validation/storage and coherent model/resource/relation renames. Inventory retained polymorphic references before renaming their model classes.

**Deliverable:** schema and import mapping/contracts, followed by importer and data checks. Product name/import policies follow resolved O2/O8, and local ordering/defaults follow resolved O3/O4. O9 is a legacy engine assessment, not an assumed product-schema requirement. Shared Part models and final specifications remain deferred.

#### W4 — Catalog foundation and first usable public flow

**Target:** the client can browse a real group tree, filter imported products, and open the correct product page while later configuration-dependent content is clearly identified as a placeholder.

| Area | Planned foundation | Completion evidence |
| --- | --- | --- |
| **Core models** | Group tree, optional SubGroup presets, Product core/JSON data and legacy identity; agreed boundary for group Configurator assignment | Correct ownership, constraints and relationships; schema matches the reviewed design |
| **Import** | Create source Groups + 501 Products and their three JSON fields; resolve new internal IDs from legacy references; starter parent from supplied import metadata; repeatable source-wins updates and diagnostics | 501 distinct products; correct Group relationships; preserved legacy identities; stable internal IDs on reimport and no unexplained loss |
| **Public pages** | Main groups → leaf Group/SubGroups/filters → paginated cards → Product facts and tabs; configurator placeholder initially, real shared-engine integration later | Complete browser journey with correct product/context; honest empty/placeholder states |
| **Admin management** | Group tree/assignment and filter/SubGroup/result settings; Product lookup by code and review of imported facts; updated labels/navigation and wide modals; configurator management under W2 | Admin changes affect the intended public behavior; obsolete POC resources handled according to the agreed scope |

**Deliverable:** the first demonstrable public flow plus the admin controls needed to maintain it. Source mapping and exact public/admin screens are proposed in the technical annexes under resolved O7/O11/O12. The implementation plan sequences small visible steps; the engine need not be fully integrated before honest public placeholders can be shown.

#### W5 — Database transition: specify and rehearse the selected fresh-database approach

**Selected strategy (11-A):** create the redesigned domain in a fresh MySQL database, preserving required application/account data through an explicit transfer and archiving the old database/files/code.

**Status:** read-only local structural assessment completed on 2026-09-23; a fresh database is feasible in principle, subject to the transfer/design checks below, and the user has selected it. This is an inference from the local schema and code, not a successful migration rehearsal. No fresh database has been created; deployment-environment data and cutover are not verified.

**Observed locally through Laravel Boost and repository inspection:**

- MySQL **8.0.46**, **32 tables**, **36 recorded migrations**, no database triggers or stored routines reported.
- **2 users**, neither with stored two-factor data in this local database. User authentication and package data are separate from catalog tables. Preserve the actual retained users/IDs/password hashes and application encryption compatibility in a transfer; local counts do not establish deployment-environment state.
- Bolt contains **1 form, 1 section and 3 fields**; form ownership references `users`. Preserve this non-catalog data unless explicitly excluded. Workflow tables and notifications are currently empty locally.
- Current catalog is POC-sized: **1 group, 1 product, 1 configurator, 13 attributes, 35 options and 5 rules**. Product configurations/parts/specifications are separate legacy domain tables, supporting a clean domain rebuild from the source import rather than translating all POC records.
- **7 file attachments and 7 media records** require deliberate handling. Attachments refer to literal `CatalogGroup`, `ProductProfile` and `ProductConfiguration` class names; media refers to `FileAttachment`. If retained, both row identity/references and files must be mapped. Keeping the old database and files as an archive avoids treating POC assets as new-domain records by accident.
- **5 queued jobs** exist locally. Their cutover treatment must be decided from a fresh inventory; do not replay serialized old-model work blindly against the new domain. Sessions/cache/password-reset records are transient and are not part of the proposed durable-data transfer by default.
- Existing migrations include domain-specific follow-up alterations, and current models/resources still query the old tables. A clean migration baseline must be delivered with matching application code; retain the old code revision/migration history with the old database. Existing SQLite tests are not a MySQL transition rehearsal.

**Selected approach:** a new MySQL database with retained infrastructure/package migrations plus clean new domain migrations, transferring retained users and non-catalog data, importing the new catalog, and archiving the POC database/files. Required remaining evidence includes the exact retained-data map, file inventory, deployment-environment inventory, an isolated migration/transfer/import rehearsal, authentication checks and tested cutover/rollback steps.

| Check | Evidence needed before executing the transition |
| --- | --- |
| Retained versus rebuilt data | Inventory existing tables and dependencies. Identify required users, actual authorization/settings data and retained attachments/files; distinguish disposable POC catalog data from information the user wants to preserve. |
| Clean migration boundary | Determine which infrastructure migrations remain and which domain migrations are replaced. Check migration order, foreign keys, seeds, renamed models and polymorphic references. Existing installations keep their own migration history; do not rewrite their ledger to mimic a fresh install. |
| Account and storage compatibility | Verify account IDs/password hashes and any encrypted fields, 2FA or credentials in use; establish application-key requirements. Inventory referenced files/storage paths so copying rows does not leave broken attachments. |
| Transfer and verification | Design the retained-data mapping, count/relationship checks, authentication checks and repeatable product import. Treat sessions/cache/jobs according to their actual role rather than copying them indiscriminately. |
| MySQL rehearsal | Plan an isolated disposable-database rehearsal of clean migrations, transfer, import and relevant tests after the approach is reviewed. Verify JSON queries, resolved relationships and case-sensitive code uniqueness on MySQL. Product's generated name column is deferred. |
| Cutover and recovery | Define matching code revision/database configuration, backup, switch and rollback steps, and what happens to writes during transition. With deploy-on-push disabled, a push is not the deployment trigger. |

**Deliverable:** an executable transition and rehearsal plan for the selected strategy, recording concrete blockers if discovered. Do not call the approach proven until the necessary evidence exists; do not reset the current DB or switch connections as part of this documentation task. **Policy resolved:** O1. Exact steps depend on W3 schema and the retained-data inventory.

### 10.2 Proposed delivery sequence

The implementation plan and annexes specify files, interfaces, lifecycle behavior, authorization and focused tests. Execute one reviewed milestone at a time, starting with visible catalog foundations; specification completion does not mark implementation complete. The fresh-database strategy is selected, while operational rehearsal remains future work.

### P0 — Discovery and initial visible polish

- [x] Review all supplied references, current engine/models, MySQL schema and relevant tests.
- [x] Inspect the hosted filter and admin sketches through real browser interaction.
- [x] Exercise the authenticated demo, context changes, dependencies, tabs and modals.
- [x] Normalize a POC CSV and create a change audit without altering the workbook.
- [x] Apply initial Groups/Rules terminology and wide modal changes locally.
- [x] Consolidate the conversation into this living plan.

**Exit evidence:** section 11. Public pages, imports and rebuilt domain code are not part of this completed milestone.

### P1 — Model, migration and engine contracts

- [ ] Discuss section 9.1's gaps and settle the scope of the next bounded milestone.
- [x] Assess local structural feasibility and select the fresh-database approach (11-A); successful rehearsal remains pending.
- [x] Resolve O3/O4/O6: local code order, independent default and partial mappings with complete sets.
- [x] Resolve O5/O7/O8: drag rule priority, shared group configurators and import identity/update policy.
- [x] Resolve O2/O11/O12: accessor/admin naming, SubGroup preset reconciliation and explicit repair before removal.
- [x] Explain and assess legacy capability O9 without treating it as a new requirement; record actual code, stored usage and test coverage in the research report.
- [x] Specify exact proposed import field mapping under O8: all 67 positions covered once in the core annex.
- [x] Produce source-linked Laravel Daily/Filament Examples research and present the PodText skill inventory.
- [x] Receive the user's skill selection and import UX Design Thinking and Laravel Simplifier; explain Pest-5 provenance separately.
- [x] Use selected/relevant skills to evaluate the code and plan; record the proportional UX review and Laravel/Filament/Livewire findings in the three annexes.
- [x] Specify proposed Group, SubGroup, Product, Configurator, canonical Attribute/Value/Option definitions and configurator-specific associations.
- [x] Specify proposed rule conditions, mapping sets, priority, outcome types and engine result/diagnostic interfaces.
- [x] Specify fresh-database migration-file boundaries, retained-data categories, polymorphic-reference treatment and a guarded dedicated MySQL test configuration; actual transfer inventory and rehearsal remain open.
- [x] Produce a reviewable implementation plan with exact task boundaries and verification cases; user review selects execution scope.

**Planning evidence:** the implementation plan and three annexes provide the coherent proposed schema, engine contract, migration approach and independently specified expected outcomes. The first checklist item remains open for the user's review of execution scope. None of these completed specification items marks code, import, tests or database transition complete. Do not scaffold speculative parts/assets/custom-item systems.

### P2 — Catalog foundation and visible public flow

- [x] Specify proposed public/admin screens and interaction details under the confirmed O11/O12 policies in the public/admin annexes.
- [ ] Implement the agreed core catalog models/migrations and useful factories.
- [ ] Implement the tested, repeatable import of the normalized D060 data; preserve legacy IDs and source provenance.
- [ ] Use the importer and explicit source/parent metadata to create the starter hierarchy, D060 child, 501 Product records and their three JSON fields, preserving legacy identities and resolving internal relationships.
- [ ] Build public group navigation, leaf-group filters/SubGroups, immediate product cards and configurable pagination.
- [ ] Build product pages showing the correct product identity/facts and clear configurator/content placeholders.
- [ ] Add group/product admin controls and replace misleading/irrelevant navigation according to the agreed plan.

**Exit evidence:** browse parent → D060 → filtered/paginated products → the selected product's page. Counts and constraints apply to the full matching set, not just the current page. No duplicate import records. Placeholder pages do not pretend the POC's hardcoded configuration is correct for every product.

### P3 — Configurator models and rebuilt engine

- [ ] Implement canonical values/options/codes and configurator-specific setup using P1's approved schema.
- [ ] Implement condition groups, context handling, mapping restrictions, advanced exclusions, priority and dependency validation.
- [ ] Implement defaults, restoration, hidden-attribute participation, completeness, generated codes and diagnostics under E00–E16.
- [ ] Replace divergent evaluation paths with one engine contract usable by admin preview and public UI.
- [ ] Preserve agreed POC capabilities through meaningful regression tests and MySQL code-uniqueness checks.

**Exit evidence:** the engine verification scenarios in section 12 pass with independently derived expectations; UI ordering cannot accidentally change a code; no optional unanswered applicable attributes.

### P4 — Configurator management and product integration

- [ ] Build the sketch-inspired management flow for assignments, attributes/options/defaults, local overrides and ordered rules.
- [ ] Build the mapping-set matrix with validation and accurate Preview & Test diagnostics.
- [ ] Include the **Custom** placeholder tab only.
- [ ] Connect each product page to its group's configurator and the shared engine.
- [ ] Display Product Code and Configuration Code separately; wire single-select All/default context controls.
- [ ] Verify public and preview behavior agree across context/selection changes and reloading current definitions.

**Exit evidence:** a real product can be configured using its group's definition; saved admin edits affect evaluation; no dependency on the demo slug or first stored configuration. UI works at practical desktop and narrow widths.

### P5 — Review and controlled deployment

- [ ] Run affected tests, MySQL integration checks, browser checks and the relevant full regression suite after implementation.
- [ ] Confirm import counts, code uniqueness, renamed relations/attachments, authentication and restoration/cutover behavior.
- [ ] Prepare the Forge deployment sequence, database configuration, backups/rollback and retained-data transfer if applicable.
- [ ] Review the exact deployable change and agree the push and deployment steps separately. Deploy-on-push is disabled; execute the selected deployment/cutover explicitly when authorized.

**Exit evidence:** deployed behavior verified against the agreed milestone, with the database and code changed together. No deployment has happened as part of this plan.

### Later — Client-dependent capabilities

- [ ] Define the assets mapper, then replace configuration-dependent data/assets placeholders.
- [ ] Settle parts and specifications before introducing reusable models or relational migrations for them.
- [ ] Revisit RFQ/order snapshots, retained configurator versions and session behavior with the client.
- [ ] Revisit Custom functionality only when its actual behaviors are specified.

## 11. Completed work and evidence

### 11.1 Local application changes

| File | Change |
| --- | --- |
| [AppServiceProvider](../../app/Providers/AppServiceProvider.php) | Global Filament action modal width `Width::SevenExtraLarge`, using important configuration so action subclass defaults do not shrink it |
| [ConfigEngineDemo](../../app/Filament/Pages/ConfigEngineDemo.php) | Removed three narrower context/image/hint modal overrides |
| [CatalogGroupResource](../../app/Filament/Resources/CatalogGroups/CatalogGroupResource.php) | Categories/Category labels → Groups/Group |
| [OptionRuleResource](../../app/Filament/Resources/OptionRules/OptionRuleResource.php) | Rules & Dependencies navigation label → Rules |
| [ProductProfilesTable](../../app/Filament/Resources/ProductProfiles/Tables/ProductProfilesTable.php) | Product table/filter Category label → Group |
| [TableConfigurationStandardsTest](../../tests/Feature/Filament/TableConfigurationStandardsTest.php) | Updated label expectations |

Earlier this session: Pint passed; 14 affected tests passed with 383 assertions; the full `php artisan test --compact` run passed **71 tests / 555 assertions**. Runtime inspection covered Action/Create/Edit/Delete/Attach/Associate modal defaults, and Chrome inspection confirmed wide context/help/image modals. These are evidence for the existing local polish, not tests of the planned rebuild. Documentation-only updates do not imply another test run.

### 11.2 Browser observations

- Filter reference: 501 initial matches; 40 bar → 24; adding Standard Flow → 10; clicking incompatible Composite cleared 40 bar while retaining Standard Flow → 84.
- Reached single product `D60-LP6-T2`; NEXT only displayed a placeholder. Reset and keyboard toggling worked. This single-result navigation has been superseded by R5.
- Admin sketch: add/remove mapping sets works; a source in two sets blocks saving. Assignment/unassignment and duplication work; copies are drafts without group assignments.
- Removing Viton from an allowed mapping and saving did not prevent selecting it in preview, even after Reset. The new preview must use real engine results.
- Reordering changed the sketch's default; local label overrides appeared after resetting the preview. Add AND Condition and Select Values were placeholders. Duplicate code input received a save toast without validation.
- Horizontal overflow needs improvement in the admin table and especially narrow filter layouts. A narrow filter viewport was checked; this was not an exhaustive responsive audit of every page.
- Existing demo: territory/application changes applied; DIN 16 hid incompatible body materials, replaced Cast Steel with SS316, and updated the code. Original selections/context were restored after testing.
- Recent browser console checks returned no warnings/errors for the inspected flows. Boost's browser log file was absent; that absence is not evidence of comprehensive error-free operation.

Temporary sketch edits were reset by reload. Source reference files were not edited. No application data import, domain migration, commit, push, or deployment was performed for this refactor session's changes.

### 11.3 Existing code that needs redesign

- [CatalogGroup](../../app/Models/CatalogGroup.php): already has tree relationships and configurator reference.
- [ProductProfile](../../app/Models/ProductProfile.php): lacks the planned fixed-property JSON structure.
- [ConfigProfile](../../app/Models/ConfigProfile.php): product ownership conflicts with the new group-assigned definition boundary.
- [ConfigAttribute](../../app/Models/ConfigAttribute.php) / [ConfigOption](../../app/Models/ConfigOption.php): canonical identity, local setup and defaults need separation.
- [OptionRule](../../app/Models/OptionRule.php): currently requires an option trigger; product/context-only activation must work in the new engine.
- [ConfiguratorEngine](../../app/Services/ConfiguratorEngine.php): stage/completeness assumptions, trigger restrictions and separate PHP manifest evaluator need replacement against the agreed contract. Research found the active runtime uses PHP/Livewire and no application caller of the manifest helper; do not describe it as an existing application JavaScript evaluator.
- [ConfigEngineDemo](../../app/Filament/Pages/ConfigEngineDemo.php): hardcoded configurator slug and first stored configuration cannot become public-product lookup logic.
- [ProductConfiguration](../../app/Models/ProductConfiguration.php), parts/specification relations: POC persistence is not the target runtime model.
- [FileAttachment](../../app/Models/FileAttachment.php): existing polymorphic data includes literal old model class names. Renaming classes requires migration or a deliberate compatibility map if this data is retained.
- [Public routes](../../routes/web.php) / [home view](../../resources/views/home.blade.php): current home is not the requested public catalog.
- [Test configuration](../../phpunit.xml): still SQLite; add MySQL-specific verification for the new schema and queries.

Earlier data inspection found duplicate option codes across attributes (`D5`, `DX`, `EP`, `S6`, `S7`). Do not treat old POC codes as compliant with E14 or silently import them into the new unique registry.

## 12. Verification targets for implementation

These are planned acceptance cases, **not tests already written or passing**. Add tests with independent expected results rather than mirroring implementation details. Read the project's testing skill before writing them; preserve existing coverage unless its replacement is explicitly agreed.

| Area | Discriminating case | Required result |
| --- | --- | --- |
| Import | Import the same source twice, then a corrected or intentionally cleared source value | 501 products remain with stable internal IDs; imported fields update by legacy identity; application-only fields remain; omitted products remain and are reported |
| Import relationships | Pre-existing Group internal IDs differ from source group IDs; import into a clean catalog and repeat | Create/reuse Groups by legacy ID, preserve product/group legacy references, resolve Product internal group foreign keys correctly and populate all three JSON fields; no duplicate Groups |
| Normalization | Entities, literal angle-bracket material text, leading zeros, ambiguous direction markers | Shared canonical filter/import values; no identifier case/zero loss; uncertain conversions retained/audited |
| Stored default | Reorder options before and after an explicit admin default change | The stored default remains unchanged in both cases; initial default was the first included option |
| Product naming | Read Product name, render admin labels/search/sort and open its public page | Accessor returns product_code; SQL uses product_code; public page uses product_name; no physical name column is assumed |
| Admin removal | Remove a default/mapped option or delete a referenced shared definition | References are shown and explicit repairs are required; no silent replacement or dependent-rule deletion |
| Mapping authoring | Unmapped source; set missing sources or targets; duplicate source membership within a rule | Partial coverage accepted; incomplete sets/duplicate membership rejected; unmapped source adds no restriction from this rule |
| MySQL code uniqueness | Insert `Aa`, `aa`, `00`, then duplicate `Aa`; try invalid lengths/non-ASCII | Three distinct valid codes; duplicate rejected by DB and validation; invalid input rejected |
| Pagination | 23 matches at page sizes 10, 2 and 1 | All matches accessible through pages; never require exactly one total match |
| SubGroups | One-value preset, 10/16 preset, force-hide, switch/reset | Correct product scope and filter visibility; no duplicated products or stale page index |
| Filter interaction | Incompatible newest choice after several prior filters, including a conflicting SubGroup | Newest choice wins within the actual Group; conflicting preset clears with feedback and field visibility is restored; configurator behavior unaffected |
| Reset and navigation | Clear filters, Reset all and browser Back after changing page/criteria | Clear filters preserves SubGroup; Reset all clears it; URL restores discovery filters/preset/page and selection precedence in one coherent history step; changed criteria reset pagination |
| Empty intersection | One rule allows only option A, another only B | Empty allowed set, target cleared, incomplete result, no valid final code, contributing-rule diagnostic |
| Missing source | Unset source tested with equality and inequality; OR alternative also present | Missing source never becomes a match through negative comparison; other valid conditions/rules evaluate under the agreed groups |
| Fallback | Selected option invalid; default allowed / default blocked | Valid default selected, otherwise first legal option; already-valid user choice retained |
| Chains/cycles | A changes B; B changes C; separate A↔B dependency | Complete chain settled; cycle rejected with useful path/diagnostic |
| Hidden attribute | Hide and restore an attribute with outgoing rules | No hidden contribution or required-choice failure; previous valid choice restored or fallback applied |
| Mapping presentation | Mapping rejects option; explicit rule hides another | Mapping rejection disabled, explicit hidden outcome hidden; neither selectable by bypassing the UI |
| Priority | Reverse evaluation/input order; conflicting scalar outcomes with differing/tied priorities | Same allowed intersection; priority controls scalar result; equal-priority conflict diagnosed |
| Rule ordering | Drag one rule above another in admin | Higher row gains priority automatically; scalar conflicts follow that order while allowed restrictions still intersect |
| Context | All/All → specific Territory → specific Application → All | Single values only; matching rules and fallback recomputed; no expansion of All into every restriction |
| Code order | Change UI order without changing code order | Same option-code sequence; product code remains separately displayed |
| Local code order | Change code order in one of two configurators using the same canonical options | Only that configurator's sequence changes; no global ordering mechanism alters the other |
| Admin/public consistency | Save a mapping/default/override and evaluate identical inputs in both UIs | Same selections, restrictions, presentation and code; no hardcoded preview behavior |
| Current definitions | Edit rules while preserving valid prior choices | Next evaluation uses current rules and E01/E02 appropriately; no unapproved version pinning |
| Public product lookup | Open two products in the same group and a product in another group | Each uses its own fixed data and correct group-assigned definition; no demo-slug/first-record leakage |
| Shared/unassigned configurator | Assign one definition to two leaf groups, edit it, then inspect a separate unassigned group | Both assigned groups use the updated definition with their own product data; unassigned products remain visible without a configurator and admin flags the missing assignment |

Verification during implementation: narrow Pest coverage for each changed behavior, MySQL integration checks where relevant, Pint after PHP edits, then the relevant full regression suite and actual browser interaction. Recheck versions/docs before depending on new APIs. A passing SQLite suite alone is not sufficient for MySQL-specific decisions.

## 13. Research references retained for follow-up

The following were consulted earlier in this session; they support feasibility, not new requirements:

- [Laravel JSON queries](https://github.com/laravel/docs/blob/13.x/queries.md#json-where-clauses)
- [MySQL generated-column indexing](https://dev.mysql.com/doc/refman/8.0/en/create-table-secondary-indexes.html)
- [MySQL generated columns](https://dev.mysql.com/doc/refman/8.0/en/create-table-generated-columns.html)
- [Laravel migration column modifiers](https://laravel.com/docs/13.x/migrations#column-modifiers)
- [Laravel refresh after writes](https://laravel.com/docs/13.x/eloquent#refreshing-attributes-after-writes)
- [Filament record titles](https://filamentphp.com/docs/5.x/resources/overview#record-titles)
- [FilamentExamples dynamic filters](https://filamentexamples.com/project/filament-v4-dynamic-real-estate-filters)
- [FilamentExamples checkbox matrix](https://filamentexamples.com/project/filament-v4-many-to-many-checkbox-list-in-fieldset)
- [FilamentExamples public table](https://filamentexamples.com/project/filament-v4-public-table-outside-any-panel)

The examples target Filament 4. Adapt the interaction ideas to installed Filament 5 / Livewire 4 APIs; do not copy their version-specific syntax blindly. Public filter reconciliation and a comfortable matrix editor may use custom Livewire/Filament components; no additional frontend framework or dependency was approved.

## 14. Maintaining this plan

Within this task, update this document whenever a decision, correction, milestone or verification result changes:

1. Read the relevant confirmed decision before proposing a new one. Apply the latest user correction first.
2. Update the canonical section and retain the correction in section 8/change log; do not leave contradictory active statements.
3. Keep proposals and open details labeled until the user resolves consequential choices. Do not promote an assistant recommendation to a confirmed decision through repetition.
4. Mark implementation complete only after files exist and relevant checks pass. Record commands/results and browser scope, separating local completion from deployment.
5. Add exact interfaces/file changes and test cases to the active milestone's execution plan as its design is settled. Link any later detailed plan back here.
6. Move future requirements from Deferred only when the user brings them into scope. Keep this file as the overview instead of duplicating competing decision lists.
7. Update the date and append a short change-log row for meaningful changes. This document records task decisions; it does not change global assistant memory or repository-wide rules.

At the user's R19 request, this record and the research moved under `docs/catalog/`. Git ignore rules now permit the catalog handoff and `docs/README.md`, while other local docs remain ignored. No commit or push was performed by the documentation task.

### Change log

| Date | Change | Status |
| --- | --- | --- |
| 2026-09-23 | Initial consolidation of the full conversation, including original references, product/filter/storage clarifications, all 16 engine answers, final single-select context correction, local work/evidence, open choices and staged delivery | Living plan created; rebuild not started |
| 2026-09-23 | Added explicit filters, engine, data, first public catalog flow and database-transition workstreams; expanded remaining discussion topics and fresh-database feasibility checks; recorded deploy-on-push disabled | Planning discussion continues; no implementation or database transition started |
| 2026-09-23 | Recorded batch 1: configurator-local code order, independent stored defaults, partial mappings with complete sets; moved O3/O4/O6 to resolved and grouped remaining filter details under O11 | Three of eleven decision groups settled; continue three at a time; no code changes |
| 2026-09-23 | Recorded batch 2: drag rule priority, shared configurators and source-wins reimports; documented import-created Groups/Products/JSON and legacy-to-internal relationship mapping; checked sample header for group fields and missing parent column | Six of eleven groups settled; five remain; importer not implemented |
| 2026-09-23 | Made the user's exact source mapping explicit: `id` → Product legacy ID, `group_id` → Group legacy ID, `group_name` → Group name; internal IDs remain application-generated | Import clarification recorded; items 7–9 remain unanswered |
| 2026-09-23 | Recorded batch 3: accessor with physical name column deferred, product_code admin labels/product_name public label, SubGroup preset reconciliation and explicit repair on removal; completed read-only local database/engine review for final questions | Nine of eleven groups settled; final two pending; fresh DB supported in principle, not rehearsed or created |
| 2026-09-23 | Recorded 11-A fresh-database strategy and item-10 request for explanation; started parallel authenticated example/tool research and planned user selection of PodText skills before final evaluation | Ten follow-up choices settled; O9 is an evidence/clarification task, no new whole-code behavior approved; no rebuild or DB writes |
| 2026-09-23 | Added three source-linked research reports, cross-workstream synthesis and PodText skill comparison; verified legacy whole-code non-use and order-sensitive filter results | Research supports final planning; user skill selection requested; imports and final implementation plan remain pending |
| 2026-09-23 | Extended research at the user's request to Laravel Daily and Filament Daily YouTube Videos sections, covering 2025 and the past year | Parallel discovery scans in progress; use uploads to find deeper publisher sources; skill choice remains pending |
| 2026-09-23 | User required video descriptions as well as titles, selected UX Design Thinking and Laravel Simplifier, and requested Pest-version/provenance clarification | Two skills imported unchanged and verified; Pest 5 confirmed with PodText-specific parallel-run changes; Pest not imported; description research continues |
| 2026-09-23 | Completed channel description/source review, mapped all thirteen Laracon demonstrations, inspected four recent/relevant FilamentExamples projects and sixteen repository files, and followed AI Coding Daily's workflow repository | Source and version limits recorded; no complete spoken-content claim, app changes or demo installations |
| 2026-09-23 | Evaluated current code and accepted decisions with relevant skills; completed main implementation proposal plus core/engine, public and admin annexes; reconciled import mapping, staged saves, SubGroup vocabulary, settings and tests | Ready for user review; M1 prioritizes visible catalog foundations; implementation, MySQL rehearsal and cutover remain open |
| 2026-09-23 | User selected original package/Boost testing guidance; upstream PR verification found Pest retired its standalone skill in August in favor of Boost testing-best-practices | Official successor already installed; no PodText Pest import and no dependency change |
| 2026-09-23 | User requested a final handoff for a new session; moved all sixteen root documents to docs/catalog, added docs/README and implementation guidelines, and rewrote the plan as T01–T13 | Documentation-only; internal links and authority reconciled, targeted docs made trackable; no application/DB execution or commit |

**Next working step:** the new session reads [docs/README](../README.md), follows the [implementation plan](IMPLEMENTATION_PLAN.md) from T01 and uses the [guidelines](IMPLEMENTATION_GUIDELINES.md). Parent/initial Configurator content remains an explicit input at the relevant milestone. Settled engine/filter decisions stay settled; progress and actual test/browser evidence belong in the execution plan.
