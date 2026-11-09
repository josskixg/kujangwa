---
version: alpha
name: KujangWA Raycast Obsidian
description: Precision macOS-native dark operator surface for WhatsApp Cloud API routing, Spintax AST anti-ban compilation, and HMAC-SHA256 OTP telemetry.
colors:
  primary: "#FF6363"
  secondary: "#55b3ff"
  tertiary: "#5fc992"
  neutral: "#07080a"
  surface-100: "#101111"
  surface-200: "#1b1c1e"
  border-subtle: "#252829"
  text-primary: "#f9f9f9"
  text-secondary: "#9c9c9d"
  warning: "#ffbc33"
typography:
  h1:
    fontFamily: Inter
    fontSize: 1.5rem
    fontWeight: 600
    lineHeight: 1.15
    letterSpacing: "0.01em"
    fontFeature: "'calt' 1, 'kern' 1, 'liga' 1, 'ss03' 1"
  h2:
    fontFamily: Inter
    fontSize: 1.125rem
    fontWeight: 500
    lineHeight: 1.25
    letterSpacing: "0.01em"
    fontFeature: "'calt' 1, 'kern' 1, 'liga' 1, 'ss03' 1"
  body-md:
    fontFamily: Inter
    fontSize: 0.875rem
    fontWeight: 500
    lineHeight: 1.5
    letterSpacing: "0.012em"
    fontFeature: "'calt' 1, 'kern' 1, 'liga' 1, 'ss03' 1"
  code-mono:
    fontFamily: JetBrains Mono
    fontSize: 0.8125rem
    fontWeight: 500
    lineHeight: 1.55
    letterSpacing: "0.015em"
    fontFeature: "'tnum' 1, 'zero' 1"
rounded:
  xs: 4px
  sm: 6px
  md: 8px
  lg: 12px
  pill: 86px
spacing:
  xs: 4px
  sm: 8px
  md: 16px
  lg: 24px
  xl: 32px
components:
  button-primary:
    backgroundColor: "{colors.text-primary}"
    textColor: "{colors.neutral}"
    typography: "{typography.body-md}"
    rounded: "{rounded.pill}"
    padding: 10px
  button-primary-hover:
    backgroundColor: "#ffffff"
    textColor: "{colors.neutral}"
  button-danger:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.neutral}"
    rounded: "{rounded.sm}"
    padding: 8px
  card-surface:
    backgroundColor: "{colors.surface-100}"
    textColor: "{colors.text-primary}"
    rounded: "{rounded.lg}"
    padding: 16px
  badge-active:
    backgroundColor: "{colors.surface-200}"
    textColor: "{colors.tertiary}"
    typography: "{typography.code-mono}"
    rounded: "{rounded.sm}"
    padding: 6px
  link-interactive:
    backgroundColor: "{colors.surface-100}"
    textColor: "{colors.secondary}"
    rounded: "{rounded.xs}"
    padding: 4px
  alert-warning:
    backgroundColor: "{colors.surface-100}"
    textColor: "{colors.warning}"
    rounded: "{rounded.md}"
    padding: 12px
  panel-muted:
    backgroundColor: "{colors.border-subtle}"
    textColor: "{colors.text-secondary}"
    rounded: "{rounded.sm}"
    padding: 8px
---

## Overview

KujangWA adopts the **Raycast Obsidian** design system (`popular-web-designs/templates/raycast.md`) combined with a **Monitor & Command/Inspect** surface archetype (`claude-design`). Rather than a marketing landing page with centered hero banners or decorative feature cards, KujangWA presents a dense, keyboard-first operator console engineered for debugging Meta WhatsApp Cloud API webhooks, inspecting Spintax AST anti-ban permutations, verifying stateless HMAC-SHA256 OTP tokens, and operating a multi-session token-bucket failover router.

## Colors

- **Neutral (`#07080a`):** Near-black blue-tinted foundational canvas that avoids pure `#000000` void fatigue.
- **Surface 100 (`#101111`) & Surface 200 (`#1b1c1e`):** Stepped macOS-style window and inspector surfaces separated by `rgba(255, 255, 255, 0.06)` hairlines.
- **Primary (`#FF6363`):** Raycast Red used sparingly as punctuation for high-priority alerts, circuit-breaker trips, and brand slash geometry.
- **Secondary (`#55b3ff`):** Raycast Blue for interactive selection rings, webhook trace links, and carrier routing indicators.
- **Tertiary (`#5fc992`):** Raycast Green for verified `X-Hub-Signature-256` states, healthy session nodes, and low spam-similarity scores.
- **Warning (`#ffbc33`):** Raycast Yellow for token-bucket throttling and HALF_OPEN circuit states.

## Typography

- **Primary UI (`Inter`):** Weight 500 baseline with positive tracking (`+0.2px`) and OpenType features `'calt' 1, 'kern' 1, 'liga' 1, 'ss03' 1` for legible dark-mode density.
- **Monospace (`JetBrains Mono` / `Geist Mono`):** Enforces `font-variant-numeric: tabular-nums` (`'tnum' 1`) across all E.164 phone numbers, HMAC-SHA256 digests, OTP codes, Jaccard percentages, and token-bucket counters.

## Layout

- **Surface Commitment:** **Operate + Command/Inspect** dual-pane workspace with a 240px left navigation rail, a 4-cell tabular telemetry strip at the top, and an interactive split workbench (left: live protocol mutator & Spintax/OTP/Webhook inputs; right: deterministic cryptographic output, carrier routing matrix, and live delivery ledger).
- **Spacing Grid:** 8px base unit with 16px panel padding and 1px structural borders.

## Elevation & Depth

- **Double-Ring Containment:** Cards use `0 0 0 1px #1b1c1e` outer ring paired with `inset 0 1px 0 0 rgba(255, 255, 255, 0.06)` top highlight, replicating native desktop utility chrome without blurry neon drop-shadows.
- **Keycap Bevels:** Keyboard shortcut badges (`⌘K`, `⌘⏎`) use a `#121212` to `#0d0d0d` vertical gradient with inset bottom shadow.

## Shapes

- **Workhorse Radius (`6px`–`8px`):** Inputs, code blocks, and secondary actions.
- **Panel Radius (`12px`):** Inspector panes and telemetry cards.
- **Pill Radius (`86px`):** Primary high-contrast action triggers.

## Components

- `button-primary` uses high-contrast near-white (`#f9f9f9`) over `#07080a` with opacity hover transitions (`0.85`).
- `card-surface` houses the Spintax AST compiler, OTP/TOTP verifier, Token-Bucket simulator, and Webhook HMAC inspector.
- `badge-active` renders real-time session statuses and cryptographic verification verdicts.

## Do's and Don'ts

- **Do** keep `#07080a` as the root background and use positive letter-spacing (`0.2px`) on body copy.
- **Do** enforce `tabular-nums` on every counter, latency metric, E.164 MSISDN, and cryptographic hash.
- **Don't** use centered marketing heroes, 3-column feature-tile grids, left-border accent rails, or glassmorphic purple gradients (Slop Score: `0/10`).
