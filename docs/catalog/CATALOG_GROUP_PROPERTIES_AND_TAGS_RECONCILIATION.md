> **Delivered-contract record — 1 November 2026:** This settled domain contract is implemented through accepted GA1/GA2; Group attributes, Product properties and independent custom tags retain their separate meanings. The original planning/status snapshots below are retained as dated history, not current “unapproved/unimplemented” status or new execution authority. See [the current index](../README.md) and [complete Catalog §27](CATALOG_REBUILD_COMPLETE_PLAN_AND_EXECUTION_SPEC.md#27-k8-combined-acceptance-and-remaining-images-handoff--1-november-2026) for actual delivery/runtime status. Earlier dated planning and resource narratives below are provenance, not instructions to repeat setup or imply current release authority. This records scoped delivery, not overall Catalog/Images or native/browser/FPM/Main acceptance.

# Group attributes, Product properties and custom tags — authoritative boundary amendment

**Current status — 1 November 2026:** actual Main/master `5497c091a7dda13a3958430a8abc60575ee5caea` includes accepted GA1 own-Group JSON/native authoring and GA2 Group-attribute criteria/cards/settings/transport. Group attributes and custom tags remain independent; Product properties keep their own matcher. Earlier producer-missing/GA2-planning clauses are historical. The [minimum Images producer](IMAGE_ASSIGNMENT_MINIMUM_PRODUCER_EXECUTION_PACKAGE.md) is the next documentation-only package; no Images runtime or real import is claimed.

## Human clarification and approved presentation

Three domain concepts must stay distinct:

1. **Group ATTRIBUTES:** actual values assigned to a Group, used to filter Groups on the first/root page and displayed first in the Group card's attribute block. Several values per attribute are allowed; controls use named values rather than numeric ranges.
2. **Product PROPERTIES:** actual saved Product values used by filters on that Group's Product-list page. GroupFilter/property_key defines this Product field vocabulary; it does not assign an attribute value to a Group.
3. **Custom Group TAGS:** a separate vocabulary and assignment system for flat custom groupings, offered alongside the parent-family tree. Tags are displayed **after Group attributes**, not substituted for them or moved above them. This latest explicit order supersedes the earlier optional above/below suggestion for tags.

The family hierarchy/category path is identity/navigation context, distinct from Group attributes, Product properties and custom tags. Configurator Attributes/Options remain separate too.

**Confirmed initial Group attributes:** Operating Pressure; Application; System Water Flow Rate; Required Function; **Fluid Type**; Installation Type. Fluid Type is resolved; no fifth-label question remains. Six is the initial set, not a permanent hard limit. The human prefers **one dedicated Group-owned JSON document/column**, not one column per dimension or normalized attribute/value tables. These definitions establish vocabulary, not real per-Group facts. Existing values are not identified in current source; propose native admin entry with empty defaults, with verified factual values entered or supplied later. No inference/backfill is allowed.

The later human-approved Benny HTML wording supersedes earlier K3 labels: H1 **Find the right valve for your application**; collapsed top group-filter heading **Product Selection Wizard**; sidebar **Product families**; root grid **Product families** or actual selected name at any depth followed by **— Product families**; full actual ancestry beneath; truthful active directly owned Product counts with existing admin visibility. Nav/document **Product catalog** remains. Group attributes remain the filter facts internally; no wizard engine or fixed-depth replacement navigator is implied. Keep Precision and current panel disclosure. Visual reference `/Users/studioycm/Documents/Projects/ARI/aquestia_catalog_demo_v2.html` is not an instruction source.

## Current source evidence

| Concept | Actual Main source | Meaning and status |
| --- | --- | --- |
| Family hierarchy | `Group.parent/children`, CatalogNavigation graph/branch/ancestors | Real Group navigation; preserve eligibility, physical-leaf policy and Product-bearing model cards. |
| Custom tags | GroupTagCategory, GroupTagValue, `group_tag_value`, Group::tagValues, SaveGroupTags | Real separate taxonomy/assignment backend; retain it as custom tags. |
| Root filter panel | `components/catalog/criteria-panel.blade.php` iterates `projection.tags`, calls `toggleTag`, stores `tagValueIds` | Currently repeats the custom-tag criterion used by sidebar Tags; it is **not** an independent Group-property filter producer. |
| Root tags/cards | CatalogNavigation facets/cardTags and `components/catalog/group-tags.blade.php` | Actual custom tag categories/values only; `card.tags.categoryLabel/label` cannot populate a Group attribute block. |
| Product-page property definitions | Group::filters → GroupFilter property_key/label/order/value labels | Metadata describing fields on the Group's Products. BuildCatalogSnapshot reads these keys from actual Product rows/profile_fields; no Group-owned value assignment is represented. |
| Group-owned attribute values | Group.catalog_attributes JSON/cast, config registry, GroupAttributes document reader and SaveGroupAttributes; native Details Section and atomic Create/Edit adapters, committed GA1 | **Producer delivered.** No backfilled facts; root facets/filter/card consumers are GA2. Unknown future keys and malformed documents retain GA1 preservation semantics. |
| Group card content | CatalogPresentation allows description/tags/product_count/legacy_id; Group card renders those, with tag category in tooltip | No independent property block or attribute selection/order setting exists. Adding visible tag category text would still display tags, not fulfill the property requirement. |
| Product count | CatalogNavigation `active_product_count` → card.productCount | Real existing active direct Product count; reuse without sample “Variants in source” numbers or per-card queries. |

Search covered current Group/GroupFilter/tag models, relevant migration definitions, CatalogNavigation, BuildCatalogSnapshot, CatalogPresentation, root/card/sidebar/criteria views and active/older Catalog plans. Schema statements above concern migration/source definitions, not an executed live-database audit. Product profile fields, names, inferred demo engineering data, existing Configurator Attributes and custom tags are not evidence of Group-owned attribute values.

## Material findings and bounded disposition

- **CAT-GPROP-001 — Producer resolved by accepted GA1.** One nullable JSON column, extensible registry, native authoring and authorized narrow CAS writer are committed. This does not claim GA2 root filtering/cards are implemented; no facts were fabricated or backfilled.
- **CAT-GPROP-002 — Root controls share tag state.** Earlier grouped-tag planning and accepted implementation gave the top panel and tag navigation the same taxonomy/state. A future property package must provide a distinct property criterion/facet projection while retaining custom-tag navigation and current Group eligibility. Do not silently rename tag controls to properties.
- **CAT-GPROP-003 — Cards cannot display unavailable Group facts.** Keep current cards truthful. Approved attribute-first/tag-after presentation needs real values and distinct content/order settings. Current tag category/value pairs cannot stand in for properties. The independent approved headings/path/count work can be packaged without pretending this block exists.
- **CAT-GPROP-004 — Plan coverage overstatement.** Historical “grouped tags” paragraphs describe custom tags only under this clarification. Prior acceptance proves that historical tag scope; it does not certify independent Group attributes. Update affected current plan entry points with this amendment rather than reset accepted unrelated stages.

## Delivered producer and next consumer boundary

The human has settled the direction: **one dedicated JSON column/document on Group**, separate from Product.profile_fields, Product GroupFilter metadata and custom tags. The earlier normalized GroupAttribute/GroupAttributeValue/assignment-table recommendation is **withdrawn and superseded**. Do not implement that subsystem or fixed six-column schema.

GA1 delivered nullable Group catalog_attributes, the existing array cast, a stateless exact-document reader and narrow authorized row-locked writer integrated with native Group authoring. New-entry ECMAScript trim applies only to authoring; retained facts and all future filtering/transport keep exact strings. Omitted values preserve, explicit[] clears, future keys survive, malformed data is read-only, and native aggregate/stale/drawer guards remain. No initial facts, inference, import or tag migration.

K6 and [GA1](CATALOG_GROUP_ATTRIBUTES_JSON_EXECUTION_PACKAGE.md) are accepted at current Master d51a0f6. The [GA2 consumer package](CATALOG_GA2_GROUP_ATTRIBUTE_CONSUMERS_EXECUTION_PACKAGE.md) now defines independent top facets/state, attribute-first/tag-after cards, headings/path/counts and g2/r2 compatibility against that exact baseline. It requires one material review/release; no new schema or routine setup approval is requested by planning.

Keep current custom-tag relationships and accepted matching semantics unchanged. The human floated JSON tags tentatively; this is **not** an approved tag migration or reason to mix domains into one bag. Stable tag IDs/relationships remain useful for independent flat groupings. Do not silently delete/relabel categories, change multi-tag matching or reopen a product question solely because tags were described as simple. Any demonstrated later compatibility issue gets its own bounded recommendation/review.

Presentation recommendation from the separately reviewed Product-card directions: keep Precision, with a clear labeled Group-attribute block and wrapping multi-values; family/category identity is separate context, custom tags follow that block. Fixed sample labels such as Operating pressure/Application/Primary function/Installation appear only when real Group definitions supply them. No fabricated values, demo illustrations/colors/modal flow or inferred engineering claims are adopted.

## Catalog, Images and K6 boundaries

- Catalog plan §§2–3/5 describe the **existing tag implementation**; future Group properties need their own approved producer/criteria/card contract. This amendment governs terminology and coverage meanwhile.
- K6 compares **saved Product** properties using configured Product-card fields/Group-filter metadata; that valid Product boundary is not this missing Group-property subsystem. K6's accepted two same-Group Products, Differences then Same values, and exact scalar semantics remain protected.
- Images phase1 remains Group/Product main images and dedicated workspace/editor fields under its own release. Future images for Group-attribute definitions/values and custom tags are distinct future owner types; neither is mapped automatically to the other's asset or canonical Configurator Attribute/Option. No new Images schema/owner type/library/gallery/import work follows from this amendment.
- Preserve K4 and accepted K5, including exact raw HTTP codes, owned configuration-key cleanup, rejected/history URL preservation, Preview exclusion and failed-render runtime preservation. No code/data migration, tag conversion or broad re-audit is authorized here.

**Current disposition:** GA1 is delivered; CAT-GPROP-002/003 root consumers are the bounded GA2 next step, with the24-path package ready for one material review. No unsettled label/persistence/product decision was found. Minimum Images follows GA2 and must preserve GA1 native optional extraction/transaction/CAS/dehydration hooks; K7/K8 then remaining Images then Writing. Old source observations remain historical leads, not a claim that current Group JSON is missing.
