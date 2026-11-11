# Go! AOP agent plugin

A plugin for coding agents that read [Agent Skills](https://agentskills.io). It teaches aspect-oriented programming
with `goaop/framework`: when an aspect is the right tool, how to write pointcuts and advices, how to integrate the
framework (through the Symfony, Laravel and Yii3 bridges or a kernel of your own), how to deploy the cache, what
aspects cost and how to find out why an advice does not run.

## Install

In an agent with plugin marketplaces, add the marketplace of this repository and install the plugin:

```
/plugin marketplace add goaop/framework
```

```
/plugin install goaop@goaop
```

The skill is then loaded automatically for AOP tasks, or on demand with `/goaop:aspect-oriented-development`.

The plugin is part of the composer package, so a project can also use the copy that matches its installed
framework version:

```
/plugin marketplace add ./vendor/goaop/framework
```

Agents without marketplaces: copy or symlink `skills/aspect-oriented-development` into the skills directory of the
agent, e.g. `.agents/skills/aspect-oriented-development`.

## Contents

All paths are below `skills/aspect-oriented-development/`.

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

The skill describes the user-facing API. It follows `docs/` of the framework: change both together.
