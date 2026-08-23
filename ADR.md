# PHP SDK Architecture Decision Record

## Summary Table

| Decision Area | Choice | Rationale |
|---|---|---|
| Runtime Version | PHP 8.2+ | PHP 8.2 still receives security fixes in the initial delivery window and offers enums, readonly properties, and modern typing |
| Dependency Strategy | Zero Composer runtime packages; require `ext-curl`, `ext-json`, and `ext-openssl` | Keep the payment runtime small while relying on maintained PHP extensions for TLS, JSON, and RSA |
| HTTP Client | Native cURL behind `HttpTransport` | TLS verification, timeout control, test injection, and framework independence |
| JSON Framework | Native JSON with `JSON_THROW_ON_ERROR` | Deterministic compact UTF-8 request bytes and no runtime library dependency |
| Build Tool | Composer | Standard package metadata, PSR-4 autoloading, archive creation, and clean-install verification |
| Test Framework | PHPUnit 11 | Data-provider support for shared vectors and JUnit evidence output |
| E2E Test Framework | PHPUnit + Playwright helper + local PHP webhook server | Real signed API, browser, and callback paths can be proven with inspectable artifacts |
| Type System | Generated-committed PHP DTOs from `openapi.json` | API fields remain contract-derived while users get named PHP classes and IDE metadata |
| Code Style | PHP-CS-Fixer with PSR-12 + PHPStan | Deterministic formatting and static analysis |
| Framework Adaptation | Framework-neutral core; Laravel, Symfony, and Slim examples | Common PHP stacks can use the same raw-body webhook contract |
| Package and Release Workflow | Composer package `waffo/waffo-php`, namespace `Waffo`, public repository `waffo-com/waffo-php` | A manual workflow validates and syncs the package to a repository where `composer.json` is at the root; Packagist registration remains separate |

## Detailed Decisions

### 1. Runtime Version

**Decision:** Support PHP 8.2, 8.3, 8.4, and 8.5. CI uses all four versions.

**Rationale:** PHP 8.2 supplies the language features required by the SDK and remains security-supported through 2026-12-31. The CI range proves current maintained runtimes without creating separate code paths.

**Alternatives Considered:** PHP 8.1 would widen adoption but is outside upstream security support. PHP 8.3+ would extend the support window but unnecessarily exclude PHP 8.2 merchants during the initial beta.

### 2. Dependency Strategy

**Decision:** Use no Composer runtime library packages. Require `ext-curl`, `ext-json`, and `ext-openssl`.

**Rationale:** The required primitives are already available as standard extensions. The smaller dependency graph reduces supply-chain and version-conflict risk in merchant applications.

### 3. HTTP Client / Network Layer

**Decision:** Use `CurlHttpTransport` behind the public `HttpTransport` interface.

**Rationale:** The interface makes tests deterministic and permits merchant-specific adapters. The default transport verifies certificates, requires TLS 1.2 or newer, and exposes connect/read timeouts.

### 4. JSON Framework

**Decision:** Serialize with native `json_encode` using `JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`.

**Rationale:** Waffo signs exact JSON bytes. Compact stable serialization keeps the signed body identical to the transmitted body. Response parsing preserves unknown fields.

### 5. Build Tool

**Decision:** Use Composer with PSR-4 autoloading and `composer archive` for the distributable artifact.

**Rationale:** Composer is the standard PHP package manager and gives a direct clean-project install smoke path.

### 6. Test Framework

**Decision:** Use PHPUnit 11, PHPStan, and PHP-CS-Fixer as development dependencies.

**Rationale:** PHPUnit data providers consume every registered shared vector. PHPStan and PHP-CS-Fixer cover static and style checks before packaging.

### 7. E2E Test Framework

**Decision:** Use PHPUnit E2E suites. Browser operations run through a small Node Playwright helper already available in the monorepo environment; callbacks enter a PHP built-in HTTP server exposed through `cloudflared`.

**Rationale:** The PHP SDK remains the signed API driver and webhook parser. Playwright supplies real user interaction without adding a PHP runtime dependency.

### 8. Type System

**Decision:** Commit DTOs generated deterministically from `openapi.json` under `src/Model/Generated`.

**Rationale:** `openapi.json` remains the field authority. Generated classes expose typed properties, `fromArray`, and `toArray`; resource methods also accept arrays for incremental adoption.

### 9. Code Style

**Decision:** Follow PSR-12 through PHP-CS-Fixer and analyze `src` plus tests with PHPStan.

**Rationale:** The rules are widely understood in PHP projects and can be reproduced locally and in CI.

### 10. Framework Adaptation

**Decision:** Keep the core independent of Laravel, Symfony, and Slim. Provide raw-body webhook examples for each framework.

**Rationale:** Signature verification must receive unmodified bytes. Framework examples explain that boundary while keeping the package reusable.

### 11. Package and Release Workflow

**Decision:** Prepare `waffo/waffo-php` version `0.1.0`, runtime header `waffo-php/0.1.0`, workflow `.github/workflows/publish-php.yml`, and public repository tags `v*` in `waffo-com/waffo-php`.

**Version Source:** Git/Packagist tags determine the Composer package version; `src/Version.php::VERSION` is the single committed runtime version source and must match the release tag.

**Build Artifact:** Composer archive plus a clean temporary Composer project install/import smoke. The release script syncs `packages/waffo-php/` to the root of `waffo-com/waffo-php` because Packagist requires `composer.json` at the repository root.

**Publish Auth:** The workflow accepts a scoped GitHub token only for syncing the public repository. Packagist registration or refresh is a separate authorized operation; neither credential is stored in this repository.

**Artifact Verification:** Conformance, static analysis, unit/vector tests, README demo tests, Composer validation/archive, and clean install/import must pass before the publish step.

**Release SOP:** `/Users/zhaozhongyuan/.agents/skills/sdk-release/references/php-release.md` records version files, gates, tag workflow, Packagist verification, and rollback.

## Contract Decisions

- API models and the 16 supported operations come from `openapi.json` and `sdk-spec/sdk-manifest.json`.
- Requests send `X-API-VERSION: 1.0.0` and `X-SDK-VERSION: waffo-php/0.1.0`.
- RSA uses SHA-256 with PKCS#1 v1.5, PKCS#8 private keys, and X.509 SubjectPublicKeyInfo public keys.
- Time values generated by the SDK are UTC with exactly three millisecond digits.
- Webhook events are the five manifest events. Callback response bytes are exactly `{"message":"success"}` and `{"message":"failed"}`.
- `webhook-verify.json` has one stale response-body example; this change aligns it with `WEBHOOK_HANDLER.md` and `webhook-response.json` before PHP consumes the vector.

## Decision Log

| Date | Decision | Change | Author |
|---|---|---|---|
| 2026-08-22 | Initial PHP SDK ADR | Runtime, package, generated types, tests, E2E, and release preparation selected | Codex |
