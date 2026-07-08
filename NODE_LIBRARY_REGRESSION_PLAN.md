# Node Library Regression Plan

## Purpose

Node Library should be tested as a stable card component before future releases, especially for versions after Node 1.1.5 and for the 1.2.x line.

The goal is not to add another feature. The goal is to make existing platform behavior reproducible, visible, and hard to break during release work.

## Scope

This plan covers Node Library card rendering and interaction tests on `cybernode.local`.

It does not cover:

- Live store page scraping reliability
- Gemini prompt quality
- Production content migration
- SEO behavior
- Structured data changes

Gemini and external URL extraction should be tested separately from card rendering. Node Library regression tests should use fixed local fixtures so API changes, store page changes, or network issues do not create false failures.

## Test Matrix

Create fixed local sample posts for these patterns:

- Steam only
- Steam plus other stores
- Steam embed toggle off
- Steam embed toggle on
- Nintendo Switch only
- Nintendo Switch 2 only
- Nintendo Switch and Nintendo Switch 2
- PlayStation 4 only
- PlayStation 5 only
- PlayStation 4 and PlayStation 5
- Xbox One only
- Xbox Series X|S only
- Xbox One and Xbox Series X|S
- Microsoft Store for Windows
- Amazon App Store
- App Store
- Google Play
- Duplicate URLs
- Same store with multiple hardware targets
- Empty URL
- Invalid URL

## Fixture Policy

Add or generate local fixture posts with stable slugs, for example:

- `node-library-regression-steam-only`
- `node-library-regression-steam-mixed`
- `node-library-regression-console-mixed`
- `node-library-regression-mobile-apps`
- `node-library-regression-invalid-links`

Fixtures should be recreated deterministically in LocalWP. They should not depend on production posts.

## Playwright Checks

For each fixture page:

- Open the URL on `cybernode.local`
- Wait for network idle
- Switch every Node Library tab
- Collect visible platform buttons only
- Verify button labels
- Verify href values
- Verify platform notes such as `(PS4)`, `(PS5)`, `(Xbox One)`, and `(Xbox Series X|S)`
- Verify Steam appears as a pill when the Steam embed toggle is off
- Verify Steam pill remnants are removed when the Steam embed toggle is on
- Verify Nintendo warning appears only after click or tap
- Verify PlayStation and Xbox hardware warnings follow the current spec
- Verify warning timeout closes the warning and restores card dimensions
- Verify no visible empty pill remains
- Verify no horizontal overflow on mobile width
- Verify no console error is emitted

## Screenshot Checks

Capture screenshots for representative cases:

- Steam only with embed off
- Steam only with embed on
- Steam mixed with console stores
- Console mixed with Nintendo, PlayStation, and Xbox
- Mobile width console warning
- Mobile width Steam embed toggle

Full image diffing is not required at first. Screenshots should be used as release evidence and for manual visual inspection.

## Release Gate

For Node Library changes, use this order:

1. Run `bun x vite build`
2. Sync the theme to `cybernode.local`
3. Remove `plugins-embedded` from the LocalWP theme copy
4. Recreate Node Library fixture posts
5. Run Node Library Playwright regression checks
6. Capture representative screenshots
7. Check the top page for fatal or parse errors
8. Generate the ZIP only after the checks pass

If the Node Library regression check fails, do not generate a release ZIP.

## Version Strategy

Do not expand Node 1.1.5 after release only to add the regression suite.

The recommended path is:

- Add the regression plan and fixtures in a future maintenance version such as 1.1.6, or at the start of the 1.2.x line
- Keep the test suite independent from feature work
- Require the test suite before ZIP generation whenever Node Library code changes

## Implementation Notes

Keep the first implementation small:

- One fixture generation script
- One Playwright regression script
- A small set of representative screenshots
- A documented command in `HOW_TO_RELEASE.md`

Avoid starting with a large visual-diff system. The first useful version should catch broken labels, wrong URLs, duplicate pills, missing notes, Steam embed remnants, mobile overflow, and warning timeout regressions.

