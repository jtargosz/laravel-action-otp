# Security Policy

## Supported Versions

| Version | Supported           |
| ------- | ------------------- |
| 2.x     | Yes                 |
| 1.x     | Security fixes only |

## Reporting a Vulnerability

**Please do not report security vulnerabilities through public GitHub issues.**

Use [GitHub private vulnerability reporting](https://github.com/jtargosz/laravel-action-otp/security/advisories/new)
(`Security` tab → `Report a vulnerability`). The report stays private until a fix is released.

Include:

- Package version (`composer show jtargosz/laravel-action-otp`), PHP version, Laravel version
- Short description of the vulnerability and its impact
- Steps to reproduce or a proof of concept (code, config, request sequence)

What to expect:

- Acknowledgement within 48 hours (best effort, single maintainer).
- A triage result: accepted, needs more info, or not a vulnerability.
- If accepted, a patch release and a GitHub Security Advisory with credit to the reporter (unless you prefer to stay anonymous).
- Please wait for the fix before any public disclosure.

## Scope

In scope: this package (`src/`, `config/`, `resources/`, `stubs/`, default notification, routes, magic link, `otp.confirm` and AI tools). The security model is described in [README → Security](README.md#security).

Out of scope: vulnerabilities in Laravel itself, in your app code (e.g. missing `throttle`
middleware on OTP routes, weak action logic), or in third-party notification channels.
Still unsure? Report it. A false positive is better than a missed issue.
