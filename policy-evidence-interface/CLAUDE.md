# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is the **Policy Evidence Interface** — an open-source Drupal module (compatible with Drupal 10 and 11) built as an ANU COMP3500 TechLauncher project in partnership with Shape Policy and Australia Policy Online (APO).

**Goal:** Enable AI systems to access APO's 45,000+ policy resources through a structured interface (Model Context Protocol / MCP), with semantic retrieval (RAG), tiered access controls, and licensing compliance (Creative Commons).

## Current State

The repository is in early scaffolding phase. The only code present is the Drupal module definition file (`policy_evidence_interface.info.yml`). No build, test, or lint tooling is configured yet — the CI workflow (`.github/workflows/blank.yml`) is a placeholder.

## Intended Architecture

```
AI Systems
    ↓
MCP Interface (Drupal module endpoint)
    ↓
Access Control Layer (tiered, CC licensing-aligned)
    ↓
Semantic Retrieval Layer (RAG / vector search)
    ↓
APO Policy Repository Database (Drupal CMS)
    ↓
Policy Resources + Rich Metadata
```

**Key components to build:**
- **Drupal module** (PHP) — hooks, endpoints, and plugin wiring within Drupal 10/11
- **MCP server/interface** — structured protocol layer AI clients call
- **Semantic search** — meaning-based retrieval over the document corpus
- **Access tier enforcement** — aligned with Creative Commons and APO access rules
- **Metadata exposure** — topics, jurisdictions, publication types, authorship, access conditions

## Technology Stack

- **CMS:** Drupal 10 / 11
- **Primary language:** PHP (Drupal conventions); Python or JavaScript may be added for MCP/AI layers
- **Protocol:** Model Context Protocol (MCP) for AI system integration
- **Retrieval:** Retrieval Augmented Generation (RAG) for semantic search

## Key Files

| File | Purpose |
|------|---------|
| `policy_evidence_interface.info.yml` | Drupal module metadata (name, package, core version) |
| `Project.md` | Full project brief: stakeholders, aims, scope, desired outcomes |
| `.github/workflows/blank.yml` | CI placeholder — needs replacing with real pipeline |

## Development Setup

No Composer, npm, or build scripts exist yet. When adding them:
- PHP dependencies go in `composer.json` (Drupal/Composer ecosystem)
- Use `phpunit` for Drupal module testing (Drupal's testing framework)
- Drupal coding standards use `phpcs` with the `Drupal` and `DrupalPractice` sniffs

## Stakeholders

- **Shape Policy** — lead org, technical/strategic guidance, open-source AI focus
- **APO (Australia Policy Online)** — provides repository access, metadata framework, domain expertise
- **ANU COMP3500 students** — implementation team
