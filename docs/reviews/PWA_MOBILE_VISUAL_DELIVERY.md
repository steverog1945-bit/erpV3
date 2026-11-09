# ORION GRAFIKA — OpenCode implement & SHOW REAL MOBILE RESULT (PR #21)

**Current date:** 26 October 2026. **Active PR:** #21, branch `feat/pwa-mobile-completion-phase2`. **This is an execution command, not another audit.** This document specifies minimum visible output that is required *in addition to working source code*.

## Mandatory first response behavior

1. **Start code implementation immediately.** Read `AGENTS.md`, latest PR #21 head and `PWA_MOBILE_COVERAGE_MATRIX.md`; **do not reproduce old reports** and don't spend the run drafting another plan. Prior source updates already implemented Dashboard, Bahan, POS, SPK, Produksi, Collecting, Jersey, and PR #21's four operational index screens (Kasir Monitoring, Pembelian Bahan, Kedatangan Bahan, Kartu Stok).
2. **No "SELESAI"/"READY FOR OWNER VISUAL REVIEW" without visible source-derived evidence.** A passed Vite build is not visual acceptance. A static HTML mockup unrelated to Blade is **not** screenshot evidence.
3. Start with still-uncovered active screens and complete continuously: Kasir `waiting_list` / `open_order` / `kelola_kas`; Pembelian `create/edit/show`; Kedatangan `create/form`; Kartu Stok `detail`; then Master, Gudang/Logistik, Keuangan/Akuntansi, Laporan, Pengaturan, dashboard role and any other active screen. Preserve business handlers/RBAC/routes/forms.
4. Commit per coherent cluster to the **existing** PR #21. Avoid creating per-module PR or repeating old merges. Update the **complete 194-file inventory** with real progress; map to actual routes+roles (not file count alone).

## Visual source of truth

Owner reference: `docs/reviews/assets/MOBILE_VISUAL_390_SIDE_BY_SIDE.png` **LEFT only**. Do not reproduce old failed RIGHT as target. Use active `resources/views/**`, normal Blade components, `resources/css/mobile-ui.css` imported via actual Vite `resources/css/app.css`, actual user navigation, PWA theme tokens. Professional **solid** modern phone UI, not glass, not oversized stacked cards; concise dashboard/form/work-queue interactions. Desktop >=768px must not regress. Use 44×44px touch targets, controls with labels, keyboard safe areas and bottom navigation clearance.

## Screenshot requirement: REAL application, not fabricated previews

**Preferred: actual browser screenshots from Laravel staging, with synthetic fixture data.** Verify staging isolation **before** invoking Artisan/tests. Do not touch `C:\xampp\htdocs\grafikaorion`, `.env` production, DB `oriondb`, prod/SSL/backup services. Use a separate working copy and a wholly synthetic database (SQLite or isolated test DB with distinct name and no prod credentials). If PHP/vendor/DB cannot be safely set up, resolve install prerequisites **in dev sandbox only**, not by pointing at production. Do not claim real runtime screenshots if not available.

- Use actual browser automation (Playwright/Chromium or equivalent) to log in as **synthetic role fixtures only**. Run app under isolated local origin. Confirm screenshot source URL, route, role, viewport and commit SHA.
- Screenshot key screens with representative synthetic rows (varying length, positive/empty state), actual filter-open state, status badges, actions and overflow. Capture at mobile 320×~800, 390×844, 430×932; desktop 1366×768. Also mobile 360/767 and desktop 768/1024 smoke checks.
- Required **minimum gallery** from actual Blade rendered in browser: (1) Kasir Monitoring, (2) Kasir Waiting List, (3) Open Order, (4) Kelola Kas, (5) Pembelian index/create, (6) Kedatangan index/create, (7) Kartu Stok index/detail, (8) one Master Data with CRUD modal, (9) one Keuangan/Akuntansi, (10) one Laporan, (11) role dashboard, (12) Pengaturan/User/Role. Also review existing (13) Collecting, (14) Jersey create/order/keuangan, (15) SPK and Produksi to establish whole-system design consistency.
- Save screenshots in versioned path `docs/reviews/assets/pwa-mobile-phase2/` with real PNG names such as `kasir-monitoring-390-real.png`, `pembelian-create-390-real.png`, `master-modal-390-real.png`, `dashboard-1366-real.png`. For each include actual route/role/build SHA/viewport in `VISUAL_PROVENANCE.md`. Only images verified generated from current code with synthetic data get suffix `-real`.
- Create `docs/reviews/PWA_MOBILE_VISUAL_GALLERY.md` that embeds PNGs with Markdown (`![...](assets/pwa-mobile-phase2/....png)`), and includes before/after 390px side-by-side owner reference where applicable. Include clear **defects found, fixes and residual FAILs**. A screenshot link must actually open in GitHub and not be an imaginary path.
- In OpenCode **main chat**, show the screenshots inline (when tool supports inline rendering) or link directly to the gallery and individual PNGs in PR #21. Do not answer solely "Vite PASS"; owner explicitly requested **visible appearance**.

**If actual Laravel runtime screenshot cannot be captured:** say `RUNTIME VISUAL: NOT TESTED` and give exact blocking command/output (PHP, Composer/vendor, SQLite driver, browser). You may add truthful `-static-source-derived.png` screenshots built from the real Blade structure for illustration **only with prominent STATIC (NOT REAL ERP) labeling**; they do not fulfill real-app acceptance. Do not stop implementing unrelated UI on account of this blocker. Do not misrepresent static screenshots as real or suggest they're visually approved.

## Acceptance and no-loop protocol

- Check screenshots for clipping, overlapping text, sidebar/nav blocking last card, active filter persistence, forms keyboard, stacked cards height, modal z-index, Select2 search, long material/customer names, warning/confirmation, bulk checkboxes, safe area. Diff desktop against base for representative routes.
- Treat `npm run build`/Vite CI and `git diff --check` as **source CI**, and Laravel browser/functional and RBAC/IDOR as separate stages. Only PASS with actual evidence and current SHA.
- Commit/push source+gallery+provenance on #21; update `PWA_MOBILE_COVERAGE_MATRIX.md`, `PWA_MOBILE_COMPLETION_FINAL.md`. Keep #21 Draft, no merge/deploy.
- Continue remaining active screens **without more prompts**. If interrupted, save a short exact resume checkpoint with last SHA, next files/screens and test blockers, not "Final — partial" and not speculative future completion.
- **Do not read/modify production**, `.env`, `oriondb`, backups, Apache/SSL/scheduler or Windows services. Release gate stays NO-GO until owner approves after isolated staging validation.

**Deliverable:** real app visual gallery (or explicitly documented blocker) + actual code changes + evidence matrix + one owner-facing summary.
