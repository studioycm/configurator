# PodText skills: candidates for this project

> Historical research evidence. Execute the [final plan](../IMPLEMENTATION_PLAN.md) and [guidelines](../IMPLEMENTATION_GUIDELINES.md); their contracts supersede earlier alternatives here. Dates, versions, access and test observations are snapshots, not fresh verification.

Inspected locally and against upstream on 2026-09-23. The user selected **UX Design Thinking** and **Laravel Simplifier**; both are now imported. The user subsequently chose official package/Boost testing guidance instead of PodText's customized Pest skill. **No PodText Pest skill is imported.** The current official successor is already installed; see the upstream verification below.

The PodText `.agents/skills` directory exposes 14 skills, including symlinks to its `.ai/skills`. Seven have equivalents already installed in this project; two more are already available as user-level skills with byte-identical complete file trees. Five would be new. Do not replace this project's framework skills with the PodText copies merely because their names match.

## Recommended additions

| Choice | Skill and inspected source | Value for this task | Adaptation before use |
| --- | --- | --- | --- |
| 1 | [ux-design-thinking](/Users/studioycm/Herd/PodText/.ai/skills/ux-design-thinking/SKILL.md) | Evaluate the public discovery journey, configurator management, vocabulary and shared-versus-local editing before fixing screen structure. | Begin with our confirmed living plan and browser evidence; do not restart the questionnaire or import PodText media terminology. Its full process is a design audit, so label a narrower review honestly. Preserve the user's explicitly requested public placeholders and Custom tab. |
| 2 | [pest-testing](/Users/studioycm/Herd/PodText/.ai/skills/pest-testing/SKILL.md) | Adds Pest-specific syntax and component/browser-testing patterns to the existing testing-best-practices skill, which covers test design and value. | Remove PodText-specific references to its machine-global lane lock and InstalledSkillGuardTest. Verify APIs and installed test packages here. Importing the skill does not install the browser plugin, Playwright, Tia dependencies or any other package. |

**Initial recommendation:** additions 1 and 2. **User selection supersedes it:** import 1 and 3, and use official package/Boost testing guidance instead of importing 2. The installed `testing-best-practices` is the current upstream successor to the retired standalone Pest skill.

## Optional additions and deferred candidates

| Choice | Skill and inspected source | Assessment |
| --- | --- | --- |
| 3 | [laravel-simplifier](/Users/studioycm/Herd/PodText/.ai/skills/laravel-simplifier/SKILL.md) | Useful only if an additional explicit simplification audit is wanted. It is deliberately secondary to UX framing and has a strict read-only audit followed by a separate approval stage. Optional; not recommended as an automatic gate for this rebuild. Existing Laravel guidance already discourages speculative abstraction. |
| 4 | [spatie-laravel-php](/Users/studioycm/Herd/PodText/.ai/skills/spatie-laravel-php/SKILL.md) | Mostly duplicates existing Laravel/Pint conventions. Some directions differ from this project's current conventions, including route naming, trait formatting and migration down methods. Defer rather than introduce competing style instructions. If selected later, reconcile those differences explicitly. |
| 5 | [socialite-development](/Users/studioycm/Herd/PodText/.agents/skills/socialite-development/SKILL.md) | OAuth/social-login work is outside the catalog/configurator request. Defer. No new social-login capability is proposed. |

## Already available: no import needed

| Skill | Present source | Comparison |
| --- | --- | --- |
| filament-forms-ux-audit | `/Users/studioycm/.codex/skills/filament-forms-ux-audit` | All 26 files match PodText's complete skill tree by SHA-256. Available in this task already. |
| filament-performance-audit | `/Users/studioycm/.codex/skills/filament-performance-audit` | All 9 files match PodText's complete skill tree by SHA-256. Available in this task already. |
| configuring-horizon | Project `.agents/skills` | Entry file matches. No catalog-driven Horizon change required. |
| filament-security-audit | Project `.agents/skills` | Existing equivalent; keep destination version and scope checks to affected boundaries. |
| infer-conventions | Project `.agents/skills` | Existing equivalent; not authorization to write new standing rules. |
| laravel-best-practices | Project `.agents/skills` | Existing version with dedicated rule files; keep destination version. |
| livewire-development | Project `.agents/skills` | Existing Livewire 4 guidance; keep destination version. |
| tailwindcss-development | Project `.agents/skills` | Existing equivalent; keep destination version. |
| technical-debt-manager-php-laravel | Project `.agents/skills` | Existing equivalent; no extra audit/report system needed just to import a duplicate. |

The project also already has `filament-development`, `planning-filament`, `reviewing-filament-plans`, `testing-best-practices`, `fortify-development`, `fluxui-development` and `volt-development`. Use only those relevant to the final chosen implementation.

## Import boundary

- Import only the selected additions. Choices 1 and 3 are installed; the user explicitly excluded PodText's choice 2 in favor of upstream package/Boost guidance.
- Use independent project-local copies for selected additions, with their required references. Do not create a cross-project symlink that makes this application depend on the PodText checkout.
- Do not copy PodText's AGENTS.md, rule files, models, credentials, browser state, full raw research archive or application-specific operating procedures.
- Preserve provenance and record any adaptations in the import result. User instructions and this project's conventions take precedence over skill defaults.
- The reusable Laravel Daily research index and acquisition utilities are separate artifacts, not missing skills. Research notes have already been consulted as discovery aids; copying that archive is not part of this skills choice.

## User decision

The user asked: “does the pest testing skill is for pest 5? import the other 2 skills” and asked whether PodText's Pest skill is an upstream skill with custom changes.

### Import result

- Copied all **3 UX files** and **2 Laravel Simplifier files** unchanged from PodText's `.ai/skills` into this project's `.ai/skills`. Every destination file matches its source SHA-256. Source checkout: `5a7557797b8833dbcc1e008e67dcd1a3f7a3bfd3`.
- [UX Design Thinking](../../../.ai/skills/ux-design-thinking/SKILL.md) and [Laravel Simplifier](../../../.ai/skills/laravel-simplifier/SKILL.md) are exposed through `.agents/skills` links pointing to this project's own copies. There are no runtime links to PodText. Both names were added to `boost.json`; no broad Boost synchronization was run.
- These skills are available for discovery on the next turn and may be read directly now. Importing Laravel Simplifier does not make it an automatic gate on ordinary implementation; its scope explicitly remains secondary and opt-in.
- Application of UX guidance starts with this conversation's accepted decisions and existing evidence. It does not restart discovery, copy the optional media-domain example, remove the requested Custom/public placeholders, or override the user's authorization to maintain plan/research documents. No skill text needed editing to preserve that instruction hierarchy.
- No package or browser-testing dependency was installed. The Pest skill was not imported.

### Pest 5 provenance and exact custom changes

**Yes, this is a Pest 5 skill.** Its heading is `Pest Testing 5`, its description names Pest 5, and this application's installed `pestphp/pest` is **5.2.1** (`composer show pestphp/pest`, checked 2026-09-23).

The upstream source is PodText's installed `vendor/pestphp/pest/resources/boost/skills/pest-testing/SKILL.blade.php`, distributed to agent skill directories by Laravel Boost. It is a rendered, project-owned copy of that source, not an unrelated testing methodology. Comparing the complete texts after resolving the ordinary Blade command/snippet placeholders shows exactly these semantic changes:

1. Remove `--parallel` from the two `--tia` commands.
2. Add a reminder beside the first command and a paragraph explaining PodText's machine-global test-lane lock and its `InstalledSkillGuardTest`.

PodText commits `a17e19a1aa1e01c0707d76bb346abb1fff00a7de` (2026-08-13) and `83e141282589a55f06a1d3a7f1bfb83610726894` (2026-08-14) confirm the upstream Pest-5 refresh, local parallel-run correction, and move to `.ai/skills` so synchronization retains it. Normal Blade-to-Markdown rendering accounts for the other textual differences.

### Current upstream decision: use Boost's installed successor

The user subsequently specified the original package/Boost guidance, not PodText's copy. Current-source verification changed the installation recommendation:

- Installed Pest **5.2.1**, commit `94f4f1ff4835dae2519f8b13f349d1042318e076`, contains no `resources/boost/skills/pest-testing` directory. Its complete upstream Git tree confirms the same absence; this is not a local discovery failure.
- Pest [PR #1889](https://github.com/pestphp/pest/pull/1889), merged **2026-08-25**, deliberately removed that skill because Boost **2.6.0** supplies `testing-best-practices`, including Pest 5 guidance. The corresponding Boost [PR #769](https://github.com/laravel/boost/pull/769) consolidates overlapping testing skills and renders guidance for the installed testing packages.
- This checkout already uses Boost **2.9.1** and its [official Testing Best Practices skill](../../../.agents/skills/testing-best-practices/SKILL.md), rendered with Pest guidance. `php artisan boost:list-skills --no-interaction` lists it. Use that skill plus installed-version API documentation; do not restore a retired skill merely to retain its old filename.
- No Pest skill was copied, no package was updated, and no broad Boost synchronization was necessary. PodText's historical customized copy remains provenance evidence only. Optional Pest browser/Livewire plugins are not implied by the installed guidance.

The earlier finding that PodText contains a Pest-5-derived skill remains correct; it predates the upstream consolidation. This distinction prevents treating that older copy as today's original package distribution.
