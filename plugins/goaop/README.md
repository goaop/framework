# Go! AOP agent plugin

A plugin for coding agents that read [Agent Skills](https://agentskills.io). It holds two skills for
`goaop/framework`:

| Skill | Use it to |
|-------|-----------|
| `aspect-oriented-development` | Write aspects, pointcuts and advices, integrate the framework (through the Symfony, Laravel and Yii3 bridges or a kernel of your own), deploy the cache, reason about performance and find out why an advice does not run |
| `aspect-oriented-refactoring` | Convert existing object-oriented code: find scattered and tangled cross-cutting code, decide what belongs in an aspect, and extract it without changing behaviour |

## Install

In an agent with plugin marketplaces, add the marketplace of this repository and install the plugin:

```
/plugin marketplace add goaop/framework
```

```
/plugin install goaop@goaop
```

The skills are then loaded automatically for matching tasks, or on demand with
`/goaop:aspect-oriented-development` and `/goaop:aspect-oriented-refactoring`.

The plugin is part of the composer package, so a project can also use the copy that matches its installed
framework version:

```
/plugin marketplace add ./vendor/goaop/framework
```

Agents without marketplaces: copy or symlink the directories below `skills/` into the skills directory of the
agent, e.g. `.agents/skills/`. Copy both: the refactoring skill refers to the development skill.

## Contents

`skills/aspect-oriented-development/`

| File | Covers |
|------|--------|
| `SKILL.md` | Mental model, choosing the integration, workflow, rules that are easy to get wrong |
| `references/frameworks.md` | Symfony bundle, Laravel bridge, Yii3 bridge, and what to do for a framework without a bridge |
| `references/setup.md` | A kernel of your own: options, entry points, dependencies of aspects, tests, deployment, console |
| `references/pointcuts.md` | Pointcut grammar with examples |
| `references/advices.md` | Advice types, joinpoint API, execution order, properties, functions, `new`, introductions |
| `references/recipes.md` | Complete aspects: logging, caching, transactions, retry, authorization, metrics, circuit breaker, rate limiting |
| `references/performance.md` | Measured costs, production checklist, OPcache, preloading, containers, fast aspects |
| `references/troubleshooting.md` | Symptom → cause → fix, error messages, limitations |

`skills/aspect-oriented-refactoring/`

| File | Covers |
|------|--------|
| `SKILL.md` | The smells, the workflow from scan to proposal to extraction, how to find repeated code with `grep`, marker attributes, rules for preserving behaviour, when not to extract |
| `references/extract-aspect.md` | "Extract an aspect": mechanics, a complete before/after example, checklist |
| `references/extract-advice.md` | "Extract an advice": fragment position to advice type, what an advice can reach, fragments in the middle of a method |
| `references/catalog.md` | Concerns and their code shapes, related refactorings, lessons from AspectJ and Spring |

The skills describe the user-facing API. They follow `docs/` of the framework: change both together.
