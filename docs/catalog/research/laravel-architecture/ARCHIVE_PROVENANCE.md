# Historical raw archive provenance

The user selected repository-safe handoff documents plus a private local raw archive. The repository's two original-answer files are exact recovered final-answer bytes, with original citations, wording, outdated claims and grammar retained. Corrections belong in the findings register/README, not in those originals.

## Private archive

Installed location: `/Users/studioycm/.codex/research/laravel-architecture/2026-10-21/`.

| File | Content |
| --- | --- |
| `AUDIT_1.jsonl`, `AUDIT_2.jsonl` | Canonical chronological allowlisted source projections: visible messages, tool calls/results, nested execution evidence and recoverable reviewer deliveries |
| `AUDIT_1_TRANSCRIPT.txt`, `AUDIT_2_TRANSCRIPT.txt` | Readable rendering with record labels |
| `AUDIT_1_ORIGINAL_ANSWER.md`, `AUDIT_2_ORIGINAL_ANSWER.md` | Exact original final answers, matching repository copies |
| `MANIFEST.json` | Source boundaries, counts, source-line mapping, exclusions, hashes and gaps |
| `SHA256SUMS.txt` | Export checksums, including originals and extraction script |
| `extract_archive.py` | Bounded extraction/verification procedure used; no application code |

Directory permissions are restricted to the current user, with raw files readable/writable only by that user. The archive was not uploaded, published or committed. Personal source-session paths are provenance, not a requirement for other machines; a repository clone contains the safe handoff and skills, not the private raw outputs.

## Source boundaries and recovery

Source session: `01a13a0e-95c0-7ed3-b9b6-95fe84d90a26`, at `/Users/studioycm/.codex/sessions/2026/10/14/rollout-2026-10-14T13-56-37-01a13a0e-95c0-7ed3-b9b6-95fe84d90a26.jsonl`.

- Audit 1: source lines 9–188; original final at line 169, `2026-10-14T11:11:38.738Z`; **10,897 bytes**.
- Audit 2: source lines 189–445; original final at line 435, `2026-10-20T08:25:31.307Z`; **20,723 bytes**.
- Stop before line 446, the current archive/skill-building request. Unrelated later session content was excluded.
- **171 exported records:** 81 from Audit 1 and 90 from Audit 2. All 38 recorded tool invocations have their 38 returned results; longer available nested outputs were retained alongside truncated outer wrappers.
- Twenty-two delivered agent-message portions were preserved. Five contain recoverable plaintext report/correction bodies; seventeen have unavailable encrypted bodies. No child-agent session histories were fetched or inferred.

“Full” means complete **available recorded plaintext within this scope**. Existing truncation remains: 36 marker occurrences across 18 Audit 1 records and 21 across 12 Audit 2 records. Markers are not estimates of missing bytes. Source websites, commands, child sessions and unrecorded output are not reconstructed. Transcripts add labels; canonical JSONL preserves extracted payload text and structured returned content.

Excluded: hidden reasoning, encrypted bodies, system/developer instructions, internal session metadata, renderer duplicates, unrelated app/environment wrappers and irrelevant private data. A credential-pattern review found no obvious secrets requiring redaction; this does not prove absence of every possible secret. Keep the raw archive private, including any recorded licensed/source snippets.

## Verification

Independent extraction QA checked original-answer equality, structured source projections, range coverage and all ten checksum entries. `MANIFEST.json` SHA-256 is `046970e60d60823a2213a0cfb019e538ef95438dcdcddfaca1057df9e67f9afa` for the historical archive. Hashes establish integrity of captured bytes, not correctness or currency of the audit conclusions. New skill research/validation is documented separately and does not rewrite this historical archive.
