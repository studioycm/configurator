# App reconciliation and robustness — bounded solution proposal

> **Historical record — publication note, 1 November 2026.** The original status, commands, approval gates and worktree assumptions below describe their dated October baseline; they are retained evidence, not current execution instructions. Groups One and Two were delivered on Master in `07701045e4dda35213e24b078eba19675d697fc6`. Use the [current delivery and history index](../README.md#current-delivery-and-historical-records), [stage protocol](STAGE_AUDIT_AND_DOWNSTREAM_REPLAN_PROTOCOL.md) and [current Catalog/Images execution plan](CATALOG_REBUILD_COMPLETE_PLAN_AND_EXECUTION_SPEC.md#27-k8-combined-acceptance-and-remaining-images-handoff--1-november-2026) for present scope and remaining verification. Host-local paths/hashes are historical evidence references, not bundled or fresh runtime proof.

**Status:** 29 October 2026 — the human now authorizes Group 1/2 execution after technical reviews, without another routine implementation-approval request. Plan bulk creation forms coordinates the independent readiness review and is the sole implementation writer in a separate isolated checkout. This audit chat remains the independent review/verification owner and does not implement concurrently. Stop for a genuine new decision or blocker. Existing-code verification retains its isolated-target/exclusive-MySQL requirements; Catalog/Images implementation, merge, push and security remain outside this authority.

**Baseline:** master `8e1f640219ec8dbd2ee4c739041907aba4c4224d`; the initial 14 pending paths are documentation. Current source inspection supports the recommendations below; no new application tests or runtime checks were executed to prepare them.

## Scope and ownership

| Mechanism | Owner / disposition |
| --- | --- |
| Rule parsing, allocated identity and shared Rule writer | Writing W1; already approved separately. Do not duplicate or edit its writer. |
| Ordinary/batch new-inclusion local defaults | Group 1, G1-A below. |
| Capture-array descriptions versus Preview DTO/Eloquent presentation | Group 1, G1-B retention recommendation below. |
| Table/profile authority and image completion/recovery | Catalog/Images I2–I4; no competing extraction here. |
| Full/narrow Group settings and Product-field authority | Existing authoritative writers; Catalog C1 extends them separately. |
| Invalid stored appearance settings | Group 2, G2-A / S08 below. |
| Snapshot command error presentation | Group 2, G2-B / S10 below; characterize before deciding on production edits. |

The existing [reconciliation](CROSS_WORKTREE_RECONCILIATION_2026_10_29.md) remains the complete finding register. Its historical feature requirements must be read with the Catalog owner's authoritative amendments; this proposal does not reopen retired R16 or repaired SA-01/RL-01/RL-02/AD-01.

## G1-A — one source for new-inclusion local defaults

**Confirmed source:** `ConfiguratorInclusionDrafts::option()` and the new-row branch of `SaveConfiguratorOption::handle()` repeat six local defaults. Ordinary and batch paths already share the authoritative definition writer. No current user-visible default mismatch is established.

**Recommended:** add `ConfiguratorInclusionDrafts::optionLocalDefaults(): array` containing only `label_override=null`, `subtitle_mode='none'`, `display_value_override=null`, `hint=null`, `hidden_by_default=false`, `disabled_by_default=false`. Compose it in the existing draft builder and ordinary new-inclusion path. UUID, canonical Option ID, ordering, owner admission and supplied overrides remain with their current callers. Preserve public operation signatures, array field ordering and partial-update semantics.

**Alternative:** keep the duplication and characterize operation parity. This avoids a production edit but leaves the same policy in two places. The small shared-default operation is recommended because it removes demonstrated policy duplication without adding an abstraction layer.

**Consumers/dependencies:** Add Attributes, Add Values and ordinary local Value creation; subtitle normalization, Default selection and the complete writer/loader remain authoritative. Do not replace the relation-manager submitted UI projection with this helper: it has a different lifecycle.

**Proof:** exercise actual ordinary and batch operations on asymmetric owners; compare persisted default values, local/canonical identity, ordering and unchanged selected Default. Preserve omitted/explicit Custom/Master subtitle intent, foreign-owner rejection and late-failure rollback. Characterization can pass before the refactor; no artificial failing baseline is required for a behavior-preserving change.

## G1-B — retain current presentation boundaries

**Confirmed source:** `ConfiguratorEditorChanges::describeChanges()` and `errorLocation()` already operate on supplied arrays without queries. Tolerant inspection and strict preparation share this formatting. Eloquent summaries and `ConfiguratorPreviewInspection` consume different representations.

**Recommended:** retain these boundaries in the first Group 1 slice. No demonstrated second consumer or runtime defect warrants another presenter class now. Existing readable-description, invalid raw Rule, actor/HMAC and exact-value regressions remain acceptance obligations when the capture owner changes.

**Alternative, explicitly excluded from this slice:** move only the array description/error-location block into a concrete query-free service. This could clarify class responsibilities, but adds wiring and test churn without an established behavioral gain. It must not absorb DB-loading labels, compilation, capture verification or Eloquent/Preview presenters. No universal presenter or generic editor framework is proposed.

## G2-A / S08 — invalid stored appearance recovery

**Confirmed conditional failure path:** `AdminAppearance::current()` validates the stored settings without handling `ValidationException`. Invalid stored ranges/unknown keys/cross-field combinations can therefore fail readers, including the repair page. No corrupt live row or observed outage is claimed. The repaired transaction-aware memo behavior is a separate contract to preserve.

**Settled human behavior decision:** recover selectively, preserve unrelated valid saved preferences and show a clear safe user notice. This supersedes the earlier whole-set fallback recommendation and strict-page-failure alternative. The later human execution authority now permits the reviewed Group 1/2 package to proceed after technical readiness review without another approval. No Group 1/2 product question remains from S08.

**Concrete engineering policy:** read the stored payload without catching database/cache exceptions. Merge normal missing legacy fields with defaults; ignore/report unknown fields without exposing their names/values. Validate known fields with the same field rules used by strict Save. Replace only individually invalid known fields with their defaults, retaining the validator's existing accepted types/normalization. Then preserve the validated header height and check the two dependent fields: default logo_height if it exceeds header_height−12; default page_title_size if ceil(size×1.2) exceeds header_height−12. Finish with complete strict validation. No clamping, guessed edit history or minimum-change search algorithm.

**Why this coupled rule is safe:** every allowed header is at least 40px. Default logo 28px fits its minimum 28px usable height; default title 20px needs 24px and also fits. The existing validator reports these fit errors on logo/title, so keeping the valid structural header and defaulting the failing dependent field follows current validation ownership. Both dependents can need recovery; unrelated preferences remain intact. This is a deterministic recovery policy, not a claim about which historical edit caused the conflict.

| Stored example, other fields valid | Temporary effective result |
| --- | --- |
| sidebar_width288, control_height34, logo_height99, header_height48 | Keep sidebar288/control34/header48; default only logo to28. |
| header_height40, logo_height32, page_title_size20, plus unknown extra key | Keep header40/title20; logo→28; ignore/report the unknown key. |
| header_height40, logo_height32, page_title_size30 | Keep header40; logo→28 and title→20 because each fails its own fit constraint. Other valid fields remain unchanged. |

If the entire stored JSON payload is non-array and contains no usable settings map, use validated defaults for that payload and report it as unusable; this is the narrow justified all-default case. A normal legacy map missing newly added keys keeps its existing default behavior without a false corruption notice. No whole-map fallback is needed for any known-field/coupled failure under current rules. An unexpected final validation failure is an engineering mismatch to resolve before shipping, not permission to silently discard the rest of the preferences.

**Notice and side effects:** expose query-free safe recovery metadata and render one accessible generic notice in existing admin pages, including the appearance repair page. Suggested text: “Some saved appearance settings could not be used. Temporary safe values are active; stored settings have not been changed.” Show a repair-page link only to manage-catalog users. Log once per scoped service instance using known field identifiers/unknown count only. No raw values, unknown key names, credentials or stack traces in the notice. Never write a repair, flush storage/cache or publish recovered settings to shared cache. Missing rows, DB/cache outages and unrelated exceptions still surface. Authorized shared Save remains strict; it is the explicit persistence operation.

**Cost and proof:** modestly larger than one whole-set catch: share field validation/normalization, add the two existing fit checks to read recovery and expose the notice. Test valid input unchanged; individual range/type/enum/boolean errors; unknown-only input; legacy omissions; unusable payload; both fit errors together; the three examples; actual notice/permission/render and raw row/version preservation. Preserve strict Save messages/order, no-op, real commit/cache and same-instance warm/cold/nested rollback. No new dependency, migration or generic repair framework is needed.

## G2-B / S10 — command characterization before any patch

**Confirmed source:** `CatalogSnapshotCommand` obtains the admitted revision before its query-log `try/finally`; snapshot services can throw HTTP exceptions. Whether framework console rendering already provides an adequate nonzero, readable failure remains unverified. The old generic import concern is superseded by current native-import failure classification.

**Characterization contract:** exercise the actual terminal exception-rendering path for invalid ID, unavailable Group, physical branch, temporary snapshot contention and valid leaf JSON. Use console `Kernel::handle(ArrayInput, BufferedOutput)` or an explicitly isolated subprocess, with current guards. `$this->artisan()` / PendingCommand invokes `Kernel::call`, so it can test command behavior but cannot establish terminal exception rendering. Inspect status, safe message, revision/cache effects and query-log cleanup. The coordinator's frozen implementation receipt now reports six actual Kernel::handle cases passing and no production command patch needed; this audit has verified the nine candidate fingerprints, not rerun those cases. RefreshDatabase operation effects are not independent physical-commit proof.

**Conditional narrow solution:** only if this reveals an inadequate contract, map expected 404/409/503 `HttpExceptionInterface` outcomes inside the command to a nonempty safe error and `FAILURE`. Preserve `INVALID` for malformed IDs and normal success JSON; unexpected exceptions propagate. Do not change domain exception types or importers. If current behavior already satisfies this contract, retain production code and report the characterization result.

**Important:** valid `--invalidate` may commit a revision before later snapshot failure. Do not describe that failure as a rollback, retry it automatically or conceal the committed invalidation. Unavailable/branch admission must reject before invalidation.

## Execution authority and order

1. Complete the coordinator's independent technical readiness review, then let its single implementation writer characterize S10 first. A production patch is permitted only for an actual mismatch covered by the reviewed command-only policy; otherwise retain current code or stop for a new genuine issue/decision. New characterization tests belong to the implementation lane, not this chat's unchanged-baseline verification lane.
2. Execute reviewed G1-A without another human approval. Writing keeps sole ownership of its approved Rule-writer changes; G1-A does not depend on a command patch.
3. S08 selective behavior is settled; execute reviewed G2-A recovery/notice policy and tests under the same authority, without repeating a product questionnaire. It is independent of the S10 patch decision.
4. Keep G1-B retained. Do not make optional extraction, Catalog redesign, security, CI or a full refactoring campaign prerequisites.

Each slice has its own acceptance/checkpoint in the [execution specification](APP_RECONCILIATION_AND_ROBUSTNESS_EXECUTION_SPEC.md). Merges, pushes, dependency changes and retained-runtime mutations require separate authority. The coordinator supplied independent readiness PASS for G1-A/G2-A, incorporated the hook/metadata clarifications, then froze the nine-path candidate after a clean independent code review and its own full SQLite1497/9387 pass. G2-B characterization reports six actual terminal cases passing with no production command patch. Its sole implementation checkout is `/Users/studioycm/.codex/worktrees/app-reconciliation/configurator`, based on master `8e1f640`, distinct from this audit's snapshot. This audit matched all nine receipt fingerprints; MySQL/browser acceptance remains pending. These owner-reported code/test receipts do not become this audit's independent runtime execution; see the [verification report](COMBINED_REGRESSION_VERIFICATION_REPORT_2026_10_29.md).

## Version-specific reference

Laravel 13 documentation was consulted through Boost: [validation](https://github.com/laravel/docs/blob/13.x/validation.md), [Artisan exit codes](https://github.com/laravel/docs/blob/13.x/artisan.md), and [console testing](https://github.com/laravel/docs/blob/13.x/console-tests.md). These interpret the proposed boundaries; they do not replace application-source or actual-engine proof.
