---
name: planning-filament
description:
  Creates detailed Filament v5 implementation plans called Filament Blueprints.
  Use when asked to create a Filament Blueprint or plan a Filament application,
  panel, resource, page, form, table, action, widget, import, export, or test
  suite.
compatibility: Requires filament/blueprint to be installed through Composer.
---

# Planning Filament

Create a specification document that an implementing agent can follow without
making decisions. Do not implement the feature.

## Workflow

1. Read `vendor/filament/blueprint/resources/markdown/planning/overview.md`
   relative to the application root.
2. Read only the topic files linked from the overview that are relevant to the
   requested feature.
3. Inspect the application for existing structure and conventions that affect
   the plan.
4. Verify APIs and affected lifecycle ordering against documentation and
   resolved installed framework behavior; follow the overview's fallback if
   `search-docs` is unavailable.
5. Reconcile related requirements across affected state transitions using the
   overview's consistency check. Resolve consequential domain choices with the
   user; specify discriminating tests and their prerequisites using
   `testing.md`.
6. Read `vendor/filament/blueprint/resources/markdown/planning/checklist.md` and
   ensure every required implementation detail is copied into the plan.
