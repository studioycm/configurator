# Rules Writing Cheat Sheet — Version 1

Version: **1** · Recorded: **2026-10-23**

This reference describes the **current standalone writing simulation** for Mapping and Advanced rules. It is not the syntax of a deployed Forge authoring feature or the Rules CSV importer. The prototype does not create, update, import or apply executable application rules.

Examples use the D-060 reference snapshot captured on **2026-10-20**. Their business policies are illustrative. Full command names are used; shortened aliases remain deferred. Proposed single quotes, text blocks and bracket delimiters are separated from working syntax below.

## 1. References and Values — both rule types

- `@attribute_key`: a selected Configurator Attribute, such as `@kinetic_valve_body_material`.
- `$Product_Field`: a field on the current Product, such as `$Connection_Type` or `$Valve_Type`. Use the exact key and casing.
- `#semantic_value_id`: a Value of the relevant Attribute, without quotes, such as `#europe`, `#din_16` or `#stst316`.
- `"literal text"`: Product text, labels, display text and hints.

Use the **pipe** to separate list items:

```text
#europe | #germany | #usa
"Flange" | "Threaded"
```

The current parser also accepts commas, but **pipes remain the agreed authoring convention** for examples and helpers.

A Value must belong to the Attribute used by the condition, pair or effect. A shared Value may appear in more than one Attribute's Options; the Attribute provides its context.

`@territory` means the selected Territory Attribute. It does not mean a separate runtime Territory context. SelectionCode and other runtime-context syntax are not defined in this simulation.

## 2. Conditions — both rule types

```text
/when CONDITION
/and CONDITION
/or CONDITION
```

- `/when`: start a flat condition sequence.
- `/and`: add another condition joined by AND.
- `/or`: add another condition joined by OR.

AND example:

```text
/when $Connection_Type = "Flange"
/and @territory in #europe | #germany
```

OR example:

```text
/when @territory = #europe
/or @territory = #usa
```

**Do not mix flat `/and` and `/or` in the same sequence.** Use explicit groups when both are required. Omitting the When conditions means the rule has no additional gate; a Mapping rule still needs its source selection to match a case.

### Comparison operators

- `=`: exactly one Value or quoted text value.
- `!=`: not equal to one Value or quoted text value.
- `in`: one of a list of Values or quoted text values.
- `not-in`: none of a list of Values or quoted text values.
- `contains`: Product text contains the quoted text; **Product fields only**.

```text
@territory = #usa
@kinetic_valve_body_material != #ductile_iron
@territory in #europe | #germany
@territory not-in #usa | #global

$Connection_Type = "Flange"
$Automatic_Type != "Composite"
$Valve_Type in "Non Slam" | "Standard Flow"
$Automatic_Config not-in "Extended"
$Model contains "D60"
```

These are condition fragments; inside a flat sequence they need `/when`, `/and` or `/or` as appropriate.

Multiple operands need `in` or `not-in`, rather than `=` or `!=`. Missing or unselected source data does not satisfy `!=` or `not-in`. `contains` is a case-sensitive text comparison, not a numeric comparison.

## 3. Condition groups — both rule types

```text
/when all {
  CONDITION
  all {
    CONDITION
    CONDITION
  }
  any {
    CONDITION
    CONDITION
  }
}
```

- `/when all {`: open the outer condition block.
- `all {` or `/all {`: open a child group in which **all** conditions must match.
- `any {` or `/any {`: open a child group in which **any** condition may match.
- `}`: close the current group.

Write conditions inside groups **without** `/when`, `/and` or `/or` prefixes. The outer block is ALL. One level of child ALL/ANY groups is supported. Do not nest a child group inside another child group or use `/when any {`.

```text
/when all {
  $Connection_Type = "Flange"
  any {
    $Valve_Type = "Non Slam"
    @territory in #europe | #germany
  }
}
```

This means: Connection Type is Flange **AND** (Valve Type is Non Slam **OR** Territory is one of Europe/Germany).

The pipe joins **Values inside one condition**. ANY joins **separate conditions**. Close every condition group before writing outcomes.

## 4. Mapping only

### Pair

```text
/pair @source -> @target
```

Choose the source and target Attributes. There is **one pair per rule**, and the Attributes must be different. This syntax takes Attributes, not Product fields. Product fields can gate a Mapping rule through When conditions.

### Case / mapping set

```text
/case #source_value | #another_source -> #target_value | #another_target
```

A matching source selection allows the listed target Values. It does not force the user to select every target. Both sides need at least one Value, scoped to their respective Attribute in the pair.

Do not assign the same source Value to multiple cases in one rule. Unmapped source Values receive no restriction from this Mapping rule.

### Other target Values

```text
/other disable
/other hide
```

These apply to the **preceding complete case**:

- `disable`: keep its other target Values visible but disabled. This is the **default when omitted**.
- `hide`: hide its other target Values.

### Complete Mapping example

```text
/when $Connection_Type = "Flange"
/pair @territory -> @flange_standard
/case #europe | #germany -> #din_10 | #din_16
/other disable
/case #usa -> #asa_150 | #ansi_300
/other hide
```

## 5. Advanced only — restrictions

### Allow only the listed Values

```text
/allow @attribute #value | #another_value
```

Other Values become disabled. Allow narrows the legal set; it cannot restore Values restricted by another rule.

### Disable listed Values

```text
/disable @attribute #value | #another_value
```

Keep these Values visible but disabled.

### Exclude listed Values

```text
/exclude @attribute #value | #another_value
```

The current engine removes these legal choices and marks them disabled, like Disable. Exclude does not hide them.

### Hide listed Values

```text
/hide @attribute #value | #another_value
```

Hide these Values and remove them from legal choices.

### Hide an entire Attribute

```text
/hide @attribute
```

Omitting Values changes this command's scope to the entire Attribute.

These represent five engine effect kinds: `AllowOptions`, `DisableOptions`, `ExcludeOptions`, `HideOptions` and `HideAttribute`.

## 6. Advanced only — presentation

### Label

```text
/set-label @attribute "text"
/set-label @attribute #value | #another_value "text"
```

Set the Attribute's label, or the labels of the listed Options.

### Display text

```text
/set-display-value @attribute "text"
/set-display-value @attribute #value | #another_value "text"
```

Set display text at Attribute or listed-Options scope. It does not change an Option code or the underlying Value references.

### Hint

```text
/set-hint @attribute "text"
/set-hint @attribute #value | #another_value "text"
```

Set explanatory hint text at Attribute or listed-Options scope.

**Without `#Values`, the text effect targets the Attribute. With `#Values`, it targets those Options.** The quoted text follows the complete Value list.

These are the other three engine effect kinds: `SetLabel`, `SetDisplayValue` and `SetHint`. Highest-priority presentation wins. Conflicting text at the same winning priority falls back to base presentation with a diagnostic.

### Complete Advanced example

```text
/when all {
  $Connection_Type = "Flange"
  any {
    @territory = #usa
    $Valve_Type = "Non Slam"
  }
}
/allow @flange_standard #asa_150 | #ansi_300
/disable @kinetic_valve_seat_material #bronze
/hide @automatic_valve_seal_material
/set-label @flange_standard "Connection standard"
/set-hint @flange_standard #asa_150 "Confirm the mating flange class."
```

## 7. Text, escapes and comments — current syntax

Use **double-quoted JSON-style text**:

- Apostrophes need no escaping inside double quotes.
- Escape an inner double quote with `\"`.
- Escape a backslash with `\\`.
- `\n` represents a newline **in the text value**. It is not a physical newline in the source command; current commands are line-based.

```text
/set-hint @flange_standard "Check Beny's \"DIN\" flange."
/set-hint @flange_standard "First line.\nSecond line."
```

Lines beginning with `//` are comments. Blank lines are allowed:

```text
// Regional standards; explanation for the person authoring this draft.
```

## 8. Text extensions under discussion — not implemented

These are **alternatives being considered**, not additional syntax available in the current parser.

### Either quote style

```text
/set-hint @flange_standard 'Check the "DIN" flange.'
```

### Multiline text block

```text
/set-hint @flange_standard """
Check Beny's "DIN" flange.
Confirm the mating standard.
"""
```

The proposed text block would preserve actual line breaks. Its interior lines would be treated as text rather than commands.

### Bracket-delimited text

**Candidate syntax: double square brackets `[[` and `]]`.** Use the same opening and closing marks for one-line or multiline text. Ordinary single and double quotes inside are part of the text and need no escaping.

One-line example:

```text
/set-hint @flange_standard [[Check Beny's "DIN" flange.]]
```

Multiline example:

```text
/set-hint @flange_standard [[
Check Beny's "DIN" flange.
Confirm the mating standard.
]]
```

The multiline candidate preserves line breaks within the text. Interior lines are text, even if they contain command-like characters.

**This is a proposed example, not implemented syntax or a finalized grammar.** Before implementation, settle how literal `]]` inside text is represented, and whether framing newlines and indentation are included in the resulting text. The current quoted-text form remains available for text containing the closing bracket sequence.

An **Insert text** helper is also proposed: type or paste ordinary text and explicitly insert it with the required quoting/escaping. None of these proposals authorizes an engine or application change.

## 9. Syntax interpretation is not engine validation

The prototype checks supported writing syntax and its included reference snapshot. It previews complete statements while preserving unfinished text.

It does not prove that a complete rule batch is free of dependency cycles, cross-rule conflicts or business-policy errors. Draft text is stored in the current browser only; no Forge rule, import file or production data is written.

Rule names, descriptions, priority, enabled state, Create/Update identity and review/apply behavior belong to the surrounding authoring workspace. They are not extra source commands defined by this version.
