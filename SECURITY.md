# Security Policy

## Supported Versions

| Version | Supported                   |
| ------- | --------------------------- |
| 2.x     | :white_check_mark:          |
| 1.x     | Security fixes only         |

Only the latest `2.x` release gets bug fixes. The latest `1.x` release gets security fixes only.

## Reporting a Vulnerability

**Please do not report security vulnerabilities through public GitHub issues.**

Use [GitHub private vulnerability reporting](https://github.com/jtargosz/laravel-action-otp/security/advisories/new)
(`Security` tab → `Report a vulnerability`). This keeps the report private until a fix is released.

Include in the report:

- Package version (`composer show jtargosz/laravel-action-otp`), PHP version, Laravel version
- Short description of the vulnerability and its impact
- Steps to reproduce or a proof of concept (code, config, request sequence)
- Whether it affects `send`, `verify`, `peek`, `resend`, notifications, cache storage, or AI tools

What to expect:

- Acknowledgement within 48 hours (best effort, single maintainer).
- Follow-up with triage result: accepted, needs more info, or not a vulnerability.
- If accepted, a fix and a new patch release as soon as possible, plus a GitHub Security Advisory with credit to the reporter (unless you prefer to stay anonymous).
- Please give time to ship the fix before any public disclosure — coordinated disclosure.

## Scope

In scope: this package (`src/`, `config/`, `resources/`, `stubs/`, default notification, routes, magic link, `otp.confirm` and AI tools).

The security model is documented in `README.md` → Security: short-lived codes in cache,
session or challenge binding, `hash_equals` comparison, per-identifier throttling, send cooldown,
atomic single-run `verify()`, scanner-safe magic link.
Reports that bypass or break those guarantees are of particular interest.

Out of scope: vulnerabilities in Laravel itself, in your app code (e.g. missing `throttle`
middleware on OTP routes, weak action logic), or in third-party notification channels.
Still unsure? Report it — a false positive is better than a missed issue.

## Security Updates

Security fixes ship as patch releases and are noted in `CHANGELOG.md` and in the
GitHub release notes / advisories. Watch the repository releases to stay notified.
