# Go! AOP agent plugin

A plugin for coding agents (Claude Code and every agent that reads [Agent Skills](https://agentskills.io)) that
teaches aspect-oriented programming with `goaop/framework` 4.x: when an aspect is the right tool, how to write
pointcuts and advices, how to set up the aspect kernel, how to deploy the cache and how to find out why an advice
does not run.

## Install

Claude Code, from the marketplace of this repository:

```bash
claude plugin marketplace add goaop/framework
```

```bash
claude plugin install goaop@goaop
```

The skill is then loaded automatically for AOP tasks, or on demand with `/goaop:go-aop`.

The plugin is part of the composer package, so a project can also use the copy that matches its installed
framework version:

```bash
claude plugin marketplace add ./vendor/goaop/framework
```

Other agents: copy or symlink `skills/go-aop` into the skills directory of the agent, e.g.
`.claude/skills/go-aop` or `.agents/skills/go-aop`.

## Contents

| File | Covers |
|------|--------|
| `skills/go-aop/SKILL.md` | Mental model, workflow, rules that are easy to get wrong |
| `skills/go-aop/references/setup.md` | Installation, kernel options, entry points, application container, tests, deployment, console |
| `skills/go-aop/references/pointcuts.md` | Pointcut grammar with examples |
| `skills/go-aop/references/advices.md` | Advice types, joinpoint API, execution order, properties, functions, `new`, introductions |
| `skills/go-aop/references/recipes.md` | Complete aspects: logging, caching, transactions, retry, authorization, and more |
| `skills/go-aop/references/troubleshooting.md` | Symptom → cause → fix, error messages, limitations |

The skill describes the user-facing API. It follows `docs/` of the framework: change both together.
