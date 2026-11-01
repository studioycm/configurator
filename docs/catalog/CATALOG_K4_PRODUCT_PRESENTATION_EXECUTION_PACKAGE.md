> **Delivered-contract record — 1 November 2026:** K4 Product presentation CODE+TEST is accepted and locally committed at `eb82a02b541126ad4b46e5aa205f1399f28f8230`. The original planning/status snapshots below are retained as dated history, not current “unapproved/unimplemented” status or new execution authority. See [the current index](../README.md) and [complete Catalog §27](CATALOG_REBUILD_COMPLETE_PLAN_AND_EXECUTION_SPEC.md#27-k8-combined-acceptance-and-remaining-images-handoff--1-november-2026) for actual delivery/runtime status. Earlier dated planning and resource narratives below are provenance, not instructions to repeat setup or imply current release authority. This records scoped delivery, not overall Catalog/Images or native/browser/FPM/Main acceptance.

# K4 remaining Product presentation — execution package

> Use `superpowers:executing-plans` inline after the coordinator releases this bounded package following one material-delta review. Main has accepted K4-J1; this package completes the remaining K4 Product presentation bindings, without renaming or reopening K4/J1. Existing human implementation/test/local-commit authority remains; no routine new approval question.

**Goal:** Make existing Product image placement/scale and Product page arrangement/scale settings control their real consumers, using truthful missing-image placeholders until the approved Images producer is available.

**Architecture:** Reuse `CatalogPresentation` and its accepted ownership/validation rules. Forward existing coherent card metadata into the Product card partial; read shared-only page settings in the existing independently available ProductConfigurator render. A small shared Blade placeholder and scoped CSS provide the finite arrangements. No new service, provider, settings, cache, schema or image producer.

**Baseline:** `/Users/studioycm/Herd/configurator`, Main/master `5d0f9ad7bccccd32fa41f7c4e1f6b5b5ef844d0a`, parent `3235a4c…`. Accepted K4-J1 source/build snapshot `4bb19e60…`; commit receipt `/private/tmp/catalog-k4-j1-numeric-map-20261031/ROOT_COMMIT_RECEIPT.json` (`167400df…`). Exact proposed file hashes/ABSENT markers and 25 read-only dependency hashes are in `/private/tmp/catalog-k4-product-presentation-plan-20261031/BASELINE_AND_SCOPE.json`.

**Spec:** Complete Catalog plan §§3,6,8,9 and19. Installed PHP8.4/Livewire4.4.7/Filament5.9.0/current Tailwind/Vite/Pest conventions apply. No application/test/build/runtime execution occurred in this preparation.

## 1. Actual gaps and preserved contracts

1. `CatalogCards::get` already reads real saved shared settings, resolves category references and projects effective own-Group settings inside its accepted revision/displayVersion boundary. It passes `presentation` to `card-chunk`; that partial currently omits it when calling `product-card`. Fix this template handoff, not the producer or engine.
2. `ProductConfigurator::render` already rechecks current Product availability and uses `ProductEditorData::groupFields($product)` for public Properties. Retain it. `editorFields` is an admin classification and is not a replacement. The existing child render is the right consumer for page settings, including independent native child requests; the parent ProductShow/journey remains unchanged.
3. Existing settings: card `product_image_placement` above/below/left/right, default left; card `product_image_scale` small/medium/large, default medium. Shared defaults plus **own leaf Group override only**, no ancestor inheritance. Page `product_page_layout` compact_header/image_column and `product_page_image_scale` small/medium/large are **shared only**. Do not expand the six accepted override keys to include page settings.
4. Images are not available yet. Render a generic missing-image placeholder, never a product/family/D60 photograph, `product_pic` URL, source-folder lookup or pretend managed image. Do not introduce asset-shaped public APIs before the approved producer package. K7 later replaces placeholders using actual ready owner-image metadata.

## 2. Exact proposed source/test scope

| Operation | Path | Bounded responsibility |
| --- | --- | --- |
| Modify | `app/Livewire/Catalog/ProductConfigurator.php` | Add existing CatalogPresentation shared read in render; expose page settings and observed displayVersion without changing evaluation, Product facts or availability |
| Modify | `resources/views/livewire/catalog/product-configurator.blade.php` | Bind shared layout/scale around one existing identity/header, tablist and flat configuration/facts/Parts content; one placeholder |
| Modify | `resources/views/components/catalog/card-chunk.blade.php` | Forward already-produced `presentation` to the Product card |
| Modify | `resources/views/components/catalog/product-card.blade.php` | Optional presentation prop; existing image placement/scale bindings and placeholder/body wrapper; preserve actual link and all identity/fact/ARIA attributes |
| Add | `resources/views/components/catalog/image-placeholder.blade.php` | Shared decorative generic missing-image mark, using existing `catalog.browse.no_image`; no URL, file or model lookup |
| Modify | `resources/css/app.css` | Scoped Product card/page layout/scale and light/dark placeholder rules only |
| Add | `tests/Feature/Catalog/CatalogProductPresentationTest.php` | Actual producer→partial and native page/child binding, ownership and unchanged facts/journey/result assertions |

Seven paths: 2 additions and 5 modifications. No ProductShow, GroupShow, CatalogCards, CatalogPresentation, engine, provider, owner writer, JS, guard or model edits. No new translations/settings. If a genuinely necessary unlisted seam is found, report that small delta before editing.

Planning documents only: this package and the complete Catalog plan's bounded status/§19 and settled K6/Images ordering amendments. Preserve all other pending files and historical evidence.

## 3. Concrete consumer contract

### Product cards

- `card-chunk` passes the current response's `presentation` array directly to `product-card`. Never call `current()`/`effective()`/a model relationship per card.
- Add optional `presentation` prop to retain existing standalone Blade render callers. When absent, obtain the existing service's **pure `defaults()`**, not a saved-settings query. Actual catalog chunks always pass their validated, coherent metadata. Do not duplicate the default enums in a new settings owner.
- Bind `data-image-placement` and `data-image-scale` from `product_image_placement`/`product_image_scale`. Keep one anchor and its unchanged named href, reactive `productHref` binding, actual Product ID and `data-catalog-focus`. Keep `aria-labelledby`/`aria-describedby`, IDs, code/name/Group context and fact fields/labels/order intact.
- Add one placeholder and one body wrapper inside the existing anchor. The wrapper retains header/facts behavior. Use flex directions row/row-reverse/column/column-reverse for left/right/above/below without duplicating interactive links or introducing a new Alpine scope.
- Preserve Group-owned grid/padding/card-properties/labels/differences/property-column settings. Image settings do not change eligible Products, filter totals, chunks, URLs or displayed fact selection.

### Product page/native child

- `ProductConfigurator::render` reads `CatalogPresentation::current()` once and passes only the shared page arrangement/scale plus its observed version to the view. It already loads the Product with Group filters and asserts availability; retain those exact calls and `ProductEditorData::groupFields`.
- Do not call `effective()` without trusted category references to obtain these two **shared-only** fields, or add unnecessary taxonomy/ancestor queries. The shared row's settings/version are read together by the existing service. No new Group-dependent freshness envelope or polling is needed for two global page fields.
- Bind the root's page layout/scale and observed displayVersion as data attributes; no URL/state binding. A fresh native child render receives the current saved page settings without changing its evaluation input, choice chronology or generated code.
- Restructure the existing child view into one placeholder, one existing identity/code/result header and one existing main tab/content region. Compact_header places the image beside the upper identity header with the main region spanning beneath; image_column keeps an image column beside header/content. Keep all configuration choices in the existing **single flat list**.
- Preserve the existing root Alpine tab state, tab IDs/ARIA/keyboard behavior, `selectOption`/`refreshDefinition` targets, details/information sections and Parts1…Part28. Do not render duplicate children, two tab trees hidden by CSS, expandable configuration groups or a second result header.
- Parent ProductShow and its accepted route-bound return context are not modified. Independent child availability checks remain in force.

### Placeholder and scale boundaries

The new component accepts normal Blade attributes for its consumer class; it has no source/image/asset identifier and performs no service or disk work. Use a generic decorative image mark with visible existing “No image” text, hidden from duplicate card-link announcements. No `img src` or fake photo. Reserve a bounded tray; avoid fixed page overflow. Light/dark surfaces remain consistent with accepted Catalog colors and a white-photo-safe tray.

Reuse exact specified maxima: card side trays **64/96/128px**; above/below max block **96/144/192px**; page max inline **160/240/320px** for small/medium/large. Bind finite data-attribute selectors/CSS variables; constrain them to their container. No runtime image resizing/cropping or file-size work. Normal narrow-width containment is implementation hygiene, not a new mobile/visual QA gate.

## 4. Tasks and focused functional assertions

### Task A — card settings reach actual chunks

- [ ] Generate `Catalog/CatalogProductPresentationTest` with existing guarded Artisan `make:test --pest --no-interaction` only after package release.
- [ ] Write a failing case using actual `CatalogCards::get` output, not a direct component-only imitation. Assert saved shared placement/scale reach card attributes, then an own leaf override changes them. An ancestor override must not leak into a leaf. Test the four placements and three scale bindings as a small data set; do not build a screenshot matrix.
- [ ] Forward the current response metadata and add the optional prop/placeholder/body wrapper. Existing standalone card rendering without the new prop must still work with pure defaults and exact zero-value facts.
- [ ] Keep one existing Product link, its reactive href and focus attributes; assert the actual rendered chunk retains them and real code/Group/facts. No new per-card read is allowed. Since only the view handoff changes and the producer stays byte-identical, reuse accepted K2/K4-J1 query/transport proof instead of a new performance project.

### Task B — native page arrangements preserve real data

- [ ] Add failing actual Product page and independently rendered `ProductConfigurator` cases for compact_header/image_column and page scales. Read saved shared settings; attempt raw own/ancestor page settings and show that they do not override the shared page contract. A current saved version/settings change is visible on a fresh render while the Group revision is unchanged.
- [ ] Read the existing shared settings in child render; bind the finite layout/scale/version and restructure the one existing view. No evaluation/definition writer or classification change.
- [ ] One focused preservation fixture uses Group filter property key `"0"`, value `"001"`, another literal `"0"`, explicit property order, differing admin `editor_properties`, Others and Parts1/28 with empty slots. Assert public Properties still follow actual Group filter order; no associative-map/list shape rule is added. Reuse existing `PublicCatalogTest` for the wider escaped-facts/Parts matrix.
- [ ] With the existing real definition fixture, compare native ProductConfigurator result/choices/generated code before and after a saved presentation change. Assert one real tablist and unchanged flat field list; do not duplicate the full engine truth table. Retain existing unavailable child behavior through `ProductConfiguratorAvailabilityTest`.
- [ ] Reuse the accepted journey test including its numeric-key correction: confirm that changing only wrappers/presentation has not removed actual Product link/focus attributes or changed the parent route-bound return behavior. Do not repeat all JS lifecycle cases; no JS changes are proposed.

### Task C — scoped layout rules and evidence

- [ ] Add the finite Product card/page layout and scale selectors using the values above and existing card spacing/appearance variables; keep all unrelated Group-card, filter, shell and admin selectors unchanged. Light/dark support remains part of the source implementation.
- [ ] Targeted Pint/lint for the changed PHP component/test, `git diff --check`, then one necessary Vite build after final CSS/view edits. FilaCheck is not applicable without `app/Filament` edits.
- [ ] Run new presentation tests plus affected unchanged `PublicCatalogTest`, `ProductConfiguratorAvailabilityTest` and `CatalogJourneyTest`. They prove the actual native/view/fact/return boundaries touched; reuse the unchanged settings service, independent-commit, engine and JS evidence. No new concurrency/MySQL suite for a read-only presentation consumer.
- [ ] Record exact source/build hashes, commands/exits/assertions, limits and unrelated preservation. One independent execution review, then correction-only follow-up and coordinator-owned local allowlisted commit. No broad staging/push.

## 5. Reuse and execution safety

Use the same approved inert SQLite `:memory:` runner and guard, with unused `k4-presentation-*` log labels. Do not rerun setup or change the current Pest independent-commit partition, provider registration, QA profile or environment.

```sh
python3 /private/tmp/catalog-k3-execution-evidence-20261031/run.py k4-presentation-green pest tests/Feature/Catalog/CatalogProductPresentationTest.php tests/Feature/Catalog/PublicCatalogTest.php tests/Feature/Catalog/ProductConfiguratorAvailabilityTest.php tests/Feature/Catalog/CatalogJourneyTest.php --log-junit /private/tmp/catalog-k4-product-presentation-execution-20261031/green.xml
npm run build
```

The execution evidence directory is created only when released; this is not a new runtime/test environment. Narrow red/green cases use unused labels and `--filter` before the single affected final run. No test, generator, app/bootstrap, DB or build command ran during planning. No native-browser workaround or mandatory visual/mobile/cosmetic/200% zoom check. Report implemented support separately from any deferred visual review.

## 6. Next frontier, already settled

No new human choice blocks these bindings. Await one material-plan review/coordinator release before application edits. K4-J1 remains accepted and committed; this package supplies the remaining Product presentation consumer bindings only.

The human approved the future Images order exception: after intervening Catalog slices, build the **minimum required Images foundation plus individual Group/Product image fields/actual owner assignment and required I3a lifecycle proof**, then finish Catalog K7/K8, then complete the remaining Images workspace/manager, then Writing. This authorizes that order, not an unspecified early workspace/batch/matching/import/Apply scope or DB operation. Cut and review that minimum producer's exact scope at its frontier; no full Images replan or producer implementation now. Shared library remains Phase 2 and D60 import excluded.

K6 is settled for two eligible Products in the same Group comparing saved properties: **Differences first, Same values after**, preserving the existing Group/property order within each section and exact `0`/`001`/missing semantics. No configured variants/cross-Group comparison or K6 implementation here. K5 saved-code work remains separate.
