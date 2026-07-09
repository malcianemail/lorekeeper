# Changelog

All notable changes to the Homestead module are documented in this file.

## [Unreleased] — 2026-07-09

### Added

- **Homestead admin panel integration** — New Homestead and Homestead Characters sidebar sections with featured showcase tabs (rooms, houses, characters), character sprite management, and sprite slot limits. Reuses existing admin layout, `edit_data` and `manage_characters` permissions, and `CharacterSpriteService`.

- **Homestead favorites system** — Users can favorite rooms, houses, and characters with a heart toggle (AJAX), duplicate prevention, and public favorite counts. Includes `/favorites` page, profile homestead favorites section, and buttons on showcase and character pages. Separate from character bookmarks and gallery favorites.

- **Featured showcase system** — Moderators can feature rooms, houses, and characters via Admin → Featured Showcase. Public gallery at `/showcase` with filter tabs, cards, read-only room/house previews, and detail pages. Supports notes, display order, and enable/disable visibility.

- **Character sprites in homestead editor** — Sprites from owned characters appear in a Sprites inventory tab. Drag or click to place them on room/house canvases with the same move, layer, save, and load flow as furniture. Placements persist in `room_placements.character_sprite_id`.

- **Character sprite slot system** — Each character starts with 1 sprite slot (`base_sprite_slots`). Additional slots are unlocked by activating `sprite_slot` inventory items for a chosen character, which increments `max_sprite_slots`. The sprites page shows used/max/remaining slots and blocks uploads at the limit. Shared slot activation logic also powers `room_slot` and `house_slot` inventory activation.

- **Character sprite management** — Characters can have multiple homestead sprites (image, optional name, display order) with one active/default sprite. Owners and staff can upload, edit, delete, reorder, and set the active sprite at `/character/{slug}/sprites`. Upload validation matches character images (JPG, GIF, PNG; 20MB max).

- **Outdoor surface editing** — House editor Surfaces tab now supports exterior wall and roof items via `surface_layout_fields.outdoor` (`wall`, `exterior`, `roof` placement types).

- **Editor surface guidance** — Configurable `surface_editor_notes` for indoor/outdoor editors explain shared wallpaper/flooring slots.

- **Performance indexes** — Migration `2026_07_09_120000_add_homestead_performance_indexes.php` adds indexes for favorites, featured items, placements, and editor inventory queries.

### Fixed

- **Showcase HTTP 500 (BUG-001)** — Removed invalid `extension` column from Item `select()` lists in preview/editor batch queries (items use `{id}-image.png` naming).
- **Sprite delete FK** — Clearing `room_placements.character_sprite_id` before sprite deletion; character delete cleans homestead favorites and featured references.
- **Room/house delete cleanup** — `CleansHomesteadReferences` trait removes related favorites and featured rows on space deletion.
- **Editor save blocked by legacy placements (W-01)** — Save now skips non-placeable items instead of rejecting the entire payload; flash warns how many placements were dropped.
- **Silent placement loss (W-02)** — Editor shows a warning banner when saved placements cannot be loaded; save flash reports dropped invalid placements.
- **Inactive featured on direct URL (W-03)** — `getPublicFeaturedEntry()` requires active entries; deactivated showcase items return 404.
- **Cannot re-feature deactivated entry (W-04)** — `createFeatured()` reactivates an existing inactive row; admin candidate lists exclude only active featured refs and cap at 500.
- **Own-content favorite button (W-07)** — `canFavorite` is false for owned content; button hidden, count-only display shown.
- **Admin sprite slot bypass (W-08)** — Staff with `manage_characters` can upload sprites without raising slot limits first.
- **Editor unsaved-state on save** — Removed premature `markClean()` on form submit so dirty state persists until reload.
- **Editor layout/placements payload validation** — Reject malformed JSON shapes at the controller before service calls.
- **Admin tag edit 500** — Fixed after adding homestead slot tags.
- **Profile favorites pagination** — Corrected paginator on user profile homestead favorites section.
- **Inactive featured cards** — Showcase index and batch previews handle deactivated featured entries consistently.

### Changed

- **Rank-based unlimited homestead slots** — Replaced broad `isStaff` bypass with `unlimited_homestead_slots` rank power (`config/lorekeeper/homestead.php`).
- **Configurable house editor background** — Outdoor canvas background from config / site images instead of hardcoded CSS gradient.
- **Editor layer controls** — Per-item forward/backward buttons with swap-based z-order normalization; fixed mousedown/click handling on layer buttons.
- **Editor inventory queries** — `whereExists` for placeable items; batched sprite JOIN inventory; cached placeable ID lookups per request.
- **Favorite/showcase hydration** — Batched favorite counts, button states, and showcase URLs on list pages.
- **Outdoor preview surfaces** — Batch preview loads `roof_item_id` alongside wallpaper, exterior wall, and flooring.

### QA

- Full module QA completed; see [QA_REPORT.md](QA_REPORT.md).
- All open warnings W-01 through W-08 addressed in this release.
- Integration review: [INTEGRATION_REPORT.md](INTEGRATION_REPORT.md)
- Performance review: [OPTIMIZATION_REPORT.md](OPTIMIZATION_REPORT.md)

### Deployment

Run from the project root after pulling this release:

```bash
composer install --optimize-autoloader --no-dev
npm ci && npm run production          # requires Node.js; compiles public/js and public/css
php artisan migrate --force
php artisan config:cache
php artisan view:cache
```

**Notes:**

- Do **not** run `php artisan route:cache` on this codebase — several controllers resolve route parameters in `__construct()`, which breaks cached routing (e.g. `/showcase` returns 404).
- Homestead JS lives in `public/js/homestead-room-editor.js` and `public/js/homestead-favorite-button.js`; no console logging in homestead sources.
- Clear caches after config changes: `php artisan config:clear && php artisan view:clear`.
