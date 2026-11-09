# ORION GRAFIKA — PWA Mobile Completion Phase 2 (single uninterrupted implementation)

**Base:** `main` at `95627cdaf03200cb2f46d8d3a6f696e772c6b03a` (verify current HEAD before work).
**Target:** `feat/pwa-mobile-completion-phase2`, Draft PR for remaining screens, no unrelated branch.
**Status:** execution contract, **NOT a declaration that screens are complete, tested or deployed**.

## Critical correction

- PR #15, #18, #20 **already merged to main**. Their code is now baseline, not a merge task. PR #19 SPK remains open but its source is already present in main via PR #20. **Do not merge #19**, reimplement SPK or create another module PR. Keep PR #19 as history until owner formally disposes of it.
- Prior `AGENTS.md` and `docs/reviews/PWA_MOBILE_ONE_PASS_EXECUTION.md` were written before PR #20 merged; any instruction to checkout PR #20, merge it, or treat it as Draft is obsolete. This contract supersedes those portions only; safety/visual requirements still apply.
- Current baseline screen inventory `docs/reviews/PWA_MOBILE_COVERAGE_MATRIX.md` contains 194 Blade files, originally tagged 10 specific mobile, 112 generic-only, 63 components/partials, 9 print candidates; **the original labels predate Collecting and Jersey integration and MUST be refreshed from actual main source**. File inventory is not actual route coverage.

## One-run execution, no prompting loops

1. Confirm `git status`, `git fetch origin`, use `feat/pwa-mobile-completion-phase2` based on current `origin/main` in the existing owner workspace `C:\repos\grafikaorion-copilot-mobile\percetakan-app`. Protect all uncommitted work; if dirty/in use, create a safe worktree **inside development workspace**, never destructive reset, force-push or uncontrolled merge.
2. **One inventory refresh only:** read `routes/web.php` and route includes + menu/RBAC definitions + Blade screen inventory. Create/refresh coverage per active route + role + template. Note print-only, partial, unused, or role-limited views with evidence; do not infer permission from filenames. Check current source classes and CSS for Master/Pos/SPK/Produksi/Collecting/Jersey, mark `SOURCE ONLY / RUNTIME UNTESTED` rather than reimplementing.
3. Implement **every remaining active operational screen** using existing common components, theme tokens, mobile navbar/safe area, 44px key touch targets and @media <768 CSS **scoped to module**, with responsive page/form/control support, no gratuitous generic tables turned into unusable cards:
   - Kasir: riwayat, open/waiting order, monitoring, payment/closing screens, kas, penjualan barang, and remaining order create/edit/show states.
   - Logistik: purchases, receiving, stock movements, warehouses, kartu stok, suppliers, asset/material etc.
   - Master and admin: every active master, customer, employee, bank, machines, user/role/permission screens, settings.
   - Finance/accounting/reporting: piutang/hutang, deposit, kas kecil, journals/accounts, analysis, all reports, jersey financial flows not covered, dashboards by role, all other active routes.
   - Cross-module: Search/filter active state after reload, pagination, bulk actions, modal/Select2, warnings/confirmation, long text, empty/loading/validation state, print views retaining print fidelity.
4. After finishing each **group**, commit/push to this same branch and continue directly to next uncovered group; update each verified screen/route in the coverage matrix. **Do not stop to ask for a new prompt, open a PR per module, or label partial work as `SELESAI`.** If system limits interrupt, write exact persisted checkpoint with last SHA, completed files, next files and blocked checks so the next OpenCode session can resume deterministically.
5. QA and controls: run `git diff --check`, `npm ci` & `npm run build` safely, review GitHub frontend build CI. Compare mobile 320/360/390/430/767, desktop 768/1024/1366 **actual Laravel-rendered pages** when isolated runtime available; do not label static images as actual ERP screenshots. Check forms/JS and RBAC/IDOR negative tests on confirmed isolated synthetic DB only. No production services. Verify manifest, PWA service-worker cache, Android/iOS installability, keyboard safe area and hash invalidation in staging. Mark honest `PASS/FAIL/NOT TESTED/BLOCKED`.
6. Finish `docs/reviews/PWA_MOBILE_COVERAGE_MATRIX.md` (route/view/role/responsive/function/test evidence), `docs/reviews/PWA_MOBILE_COMPLETION_FINAL.md`, and `docs/reviews/PWA_MOBILE_RELEASE_RUNBOOK.md` with specific test URLs, SHA, packaging and operator-run rollback checklist. Stop only when all **active routes** are accounted and all safely implementable UI done, or a concrete unresolvable blocker with precise scope exists.

## Hard safety and release conditions

- **Do not** touch `C:\xampp\htdocs\grafikaorion`, production `.env`, `oriondb`, operational transactions, backup archives, SSL, Apache, scheduler, Windows services or secrets.
- **Do not** merge to `main`, deploy, change business calculations, route contract, stock/accounting, RBAC, API, SW/manifest or DB schema without separate owner-approved change request. If issue is in backend logic, report it instead of silently changing it.
- Functional tests/artisan only when **verified isolated** test environment and wholly synthetic database exist, with no chance of reading production configuration.
- For future production release, the authorized operator must verify backups **and restore**, Vite manifest+hashed files bundled correctly, smoke tests and rollback, then obtain owner's explicit GO for main merge and production deployment separately.
- GitHub mergeability and Vite CI success are **not** proof of ERP runtime readiness.

## Definition of completion

**Implementation done** means all active route screens have mobile source coverage and honest evidence. **Production ready** means additionally full staging acceptance/role/transaction/PWA/desktop tests PASS and explicit owner GO. No `READY FOR RELEASE` while critical tests remain `NOT TESTED`.
