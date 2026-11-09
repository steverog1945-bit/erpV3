LANDING PAGE BUILDER — FOUNDATION VERIFIED

Checkpoint 1.1 complete. All 12 foundation tests pass (12/12, 0 failures, 0 errors, 0 risky).
- Authorization: exact match role name (not str_starts_with prefix)
- Test infrastructure: role name format aligned with RBAC contract
- Risky tests: 3 remediated with meaningful assertions
- prefers-reduced-motion: test fixed + stylesheet added to editor
- Regression: baseline preserved (893 tests / 4399 assertions)
- Database: no schema changes, oriondb untouched, no new dependencies

Files modified:
1. app/Http/Controllers/LandingPageBuilderController.php — exact match authorization
2. tests/Feature/LandingPageBuilderTest.php — fixed setUp, 3 remediated tests, 1 fixed test
3. resources/views/pengaturan/landing-page-builder.blade.php — prefers-reduced-motion stylesheet

Stop condition met: Foundation stable. Ready for Checkpoint 2.