# Worked Example: Deriving Lenses from Media Operations

This reference is intentionally domain-specific. Load it only after another
task's objects, jobs, questions, and candidate model have already been derived,
or when the user explicitly asks to inspect this Media Operations case.

It illustrates a reasoning sequence. Do not use it to seed another task's
object model, vocabulary, state axes, lens count, lens names, principles,
information architecture, screen solutions, or phase order. Start every real
task from fresh contracts, evidence, operator answers, and user questions.

## 1. Contracts constrained the design

The relevant engine behavior established:

- acquiring a media item made it permanent immediately;
- choosing it for an owner remained pending until the owner form was saved;
- one authority owned the attachment and another owned file location.

That ruled out a design that presented intake as temporary work inside an
owner-selection modal. Acquisition belonged to the permanent collection even
when the later owner choice was canceled.

## 2. The question audit exposed the mismatch

The operator arrived at a media card asking questions such as:

- Is this used?
- Is it healthy?
- What can I safely do next?

The card mainly answered:

- Which disk contains it?
- What is its internal identifier?
- What is its filename?

That mismatch, not visual taste, produced the primary findings. Real filenames,
operator verification steps, navigation, locale, and observed behavior refined
the user model.

## 3. Objects and postures were separated

The interface implied many conceptual things, but the domain exposed one
permanent library item plus its attachments. Jobs differed mainly by the
operator's posture toward that object:

- finding or surveying it;
- choosing it for something;
- diagnosing or repairing it;
- introducing new material.

The permanence contract showed that introducing material was part of the
collection rather than a temporary owner operation. Diagnosis, inspection, and
file surgery shared an object and working session, so they could be compressed.

For that product and evidence, the result was:

- **Browse**
- **Choose**
- **Care**

The sentence "One library, three lenses" tested whether the compression was
coherent. It was not a slogan chosen in advance and is not a reusable
taxonomy.

## 4. The lens map became a linter

The model made later findings mechanical:

- file-surgery actions dominating Browse violated Browse's verb;
- a selection flow with two competing commit buttons violated Choose's single
  commit thread;
- one owner modal serving both selection and repair mixed lenses;
- labels that used the same replacement verb for attachment choice and
  destructive file replacement concealed different impact.

Each failure could be stated as a lens, question, verb, transition, or contract
violation rather than an aesthetic opinion.

## 5. Principles stayed traceable

Principles were retained only when they cited both observed friction and a
contract. Generic advice such as "keep it simple" was discarded. Product
fidelity mattered because Hebrew RTL, dark theme, real podcast titles, and
narrow layouts exposed defects that LTR grayscale sketches would not.

The adversarial pass compared alternatives with an adopt/steer table and
right-sized the recommendation to the actual operator and record count.

## What transfers

- contracts before screen opinions;
- evidence-led operator questions;
- the question-audit instrument;
- separating objects from postures;
- compressing and naming only after the jobs are known;
- using the resulting model as a linter;
- traceable principles;
- product-fidelity validation;
- adversarial right-sizing.

## What does not transfer

- the media domain;
- "library" as the object;
- three as the required number of lenses;
- Browse, Choose, or Care as default verbs;
- any specific screen, action, navigation, or phase;
- the conclusion that acquisition belongs with browsing in another product.
