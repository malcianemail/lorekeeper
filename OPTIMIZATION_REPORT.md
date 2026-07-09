# Homestead Optimization Report

**Date:** 2026-07-09  
**Scope:** Performance review of the Homestead module — database queries, eager loading, inventories, sprites, room loading, canvas layers, and images.  
**Constraint:** No functional behavior changes.

---

## Executive Summary

The Homestead module was audited for performance bottlenecks across PHP services, database access, and client-side canvas rendering. **15 optimizations** were applied. Functionality is unchanged; pages should load faster and the editor should handle larger inventories and placement counts more efficiently.

| Area | Before | After |
|------|--------|-------|
| Editor inventory query | `WHERE item_id IN (all placeable IDs)` | `WHERE EXISTS` subquery on placeable items |
| Sprite inventory | `whereHas('character')` subquery | `JOIN characters` |
| Showcase/favorites previews | N queries per room card | 1 batched preview build |
| Favorite button states | N count queries per card | 2 batched queries per page |
| Favorite card URLs | 1 `FeaturedItem` query per card | 1 batched query per page |
| Room/house hydration | 2 separate `RoomSave` queries | 1 merged query |
| Canvas render | Per-node DOM append | `DocumentFragment` batch append |
| Database indexes | Partial coverage | 4 new composite indexes |

---

## 1. Database Queries

### 1.1 Editor Inventory — `whereExists` Instead of Large `IN` Lists

**File:** `app/Services/Homestead/Concerns/ManagesHomesteadEditor.php`

**Problem:** `getEditorInventory()` loaded every placeable homestead item ID into PHP, then queried `user_items WHERE item_id IN (...)`. With a large item catalog, this produced a huge `IN` clause even when the user owned few items.

**Fix:** Replaced `whereIn('item_id', $placeableItemIds)` with a correlated `whereExists` subquery using `Item::placeableInHomestead($roomType)`. The database filters placeability; only owned stacks are aggregated.

**Impact:** Editor load time scales with **user inventory size**, not **catalog size**.

---

### 1.2 Sprite Inventory — Join Instead of `whereHas`

**File:** `app/Services/Homestead/Concerns/ManagesHomesteadEditorSprites.php`

**Problem:** `getEditorSpriteInventory()` and `normalizeSpritePlacements()` used `whereHas('character', ...)`, which generates a correlated subquery per request.

**Fix:** Replaced with `JOIN characters` on `character_id`, filtering `user_id` and `is_myo_slot = 0` directly.

**Impact:** Faster sprite sidebar load and faster editor save validation.

---

### 1.3 Batched Room Previews

**File:** `app/Services/Homestead/Concerns/ManagesHomesteadPreview.php`

**Problem:** Showcase, favorites, and profile pages called `getPreviewViewData()` per room/house card. Each call ran separate queries for layout, placements, items, and sprites.

**Fix:** Added `getBatchPreviewViewData()`:
- One eager load for all rooms (layout + ordered placements)
- One query for all referenced items
- One query for all referenced sprites
- Per-room catalog assembly from in-memory collections

`getPreviewViewData()` now delegates to the batch method for a single room (same output).

**Controllers updated:**
- `ShowcaseController::getIndex`
- `FavoriteController::getIndex`
- `UserController::getUserHomesteadFavorites`
- `UserController::getUser` (profile preview strip)

**Impact:** Showcase/favorites pages drop from **O(n) room query sets** to **O(1)** for preview data.

---

### 1.4 Batched Favorite Button States

**File:** `app/Services/Homestead/Concerns/ResolvesHomesteadRefTargets.php`

**Problem:** Showcase listing called `getFavoriteButtonState()` per featured entry (2 queries each: count + isFavorited).

**Fix:** Added `getFavoriteButtonStatesForTargets()` and `attachFavoriteButtonStates()` — one grouped count query per ref type, one user-favorites lookup per page.

**Impact:** Showcase page saves **~2N queries** (N = featured cards with subjects).

---

### 1.5 Batched Showcase URLs on Favorite Cards

**File:** `app/Services/Homestead/Concerns/ResolvesHomesteadRefTargets.php`

**Problem:** `_favorite_card.blade.php` queried `FeaturedItem` per card for detail URLs (N+1).

**Fix:** `attachShowcaseUrls()` runs during `hydrateRefSubjects()` — one query for all room/house favorites on the page. Cards use `$favorite->showcase_url`.

**Impact:** Favorites pages save **N queries** for URL resolution.

---

### 1.6 Merged Room/House Subject Loading

**Files:**
- `app/Services/Homestead/Concerns/ResolvesHomesteadRefTargets.php`
- `app/Services/Homestead/FeaturedService.php`

**Problem:** Rooms and houses are both `room_saves` rows but were loaded in two separate `whereIn` queries.

**Fix:** Merge room + house IDs into one `RoomSave::whereIn()` query, assign by ref type.

**Impact:** One fewer query per favorites/showcase hydration.

---

### 1.7 Narrow Column Selection

**Files:**
- `ManagesHomesteadEditor.php` — item queries select only columns needed for catalog/persistence
- `ManagesHomesteadEditor.php` — placement eager load selects only editor columns
- `ManagesHomesteadPreview.php` — item queries select catalog columns only

**Impact:** Reduced memory and row transfer for editor and preview payloads.

---

## 2. Eager Loading

| Location | Eager Load |
|----------|------------|
| Editor view | `layout`, `placements` (ordered, selected columns) |
| Batch preview | `layout`, `placements` (ordered) for all rooms at once |
| Sprite inventory | `character:id,slug,name,is_myo_slot` (partial) |
| Favorite hydration | `user` on spaces; `user`, `image` on characters |
| Featured hydration | Same as favorites |

**Sprite partial eager load** avoids loading full character rows when only `fullName` and `slug` are needed in the editor sidebar.

---

## 3. Large Inventories

### Strategies Applied

1. **`whereExists` inventory filter** — see §1.1; primary fix for users with small inventories vs. large catalogs.
2. **Request-scoped placeable ID cache** — `getPlaceableItemIds()` still caches per room type for save-path validation (`array_flip` lookups); unchanged.
3. **`user_items` composite index** — `(user_id, item_id)` speeds inventory aggregation (see §7).
4. **Items composite index** — `(is_homestead_item, placement_type, homestead_room_type)` speeds placeable subquery (see §7).

### Existing Behavior Preserved

- Inventory is still grouped by furniture/surfaces/sprites in PHP after DB fetch.
- Quantity validation on save is unchanged.
- No pagination was added to the editor sidebar (would be a functional change).

### Future Consideration (Not Implemented)

Virtual scrolling or paginated inventory tabs for users with 500+ placeable stacks would require UI changes.

---

## 4. Sprite Loading

| Optimization | Detail |
|--------------|--------|
| JOIN vs whereHas | §1.2 |
| Partial character eager load | Only `id, slug, name, is_myo_slot` |
| Batch sprite catalog in previews | Single `CharacterSprite::whereIn()` per page |
| Save validation | Join-based ownership check for placed sprite IDs |

Sprites without images are still filtered post-query (`imageUrl` check) — same behavior as before.

---

## 5. Room Loading

| Optimization | Detail |
|--------------|--------|
| Placement column select | Only editor-needed columns loaded |
| Ordered placement index | `(room_save_id, z_index)` composite index |
| Slot count index | `(user_id, room_type)` on `room_saves` |
| Batch preview | §1.3 |
| Space list | Already uses `select('id', 'name', 'room_type', 'created_at')` — unchanged |

---

## 6. Layer Performance (Client-Side)

**Files:** `public/js/homestead-room-editor.js`, `public/js/homestead-room-preview.js`

### 6.1 DocumentFragment Batch Rendering

**Problem:** `render()` appended each placement node individually, causing repeated DOM reflows.

**Fix:** Build all placement nodes into a `DocumentFragment`, append once to the canvas container.

**Impact:** Faster initial load and full re-renders for rooms with many placements.

### 6.2 Existing Optimizations (Already Present)

These were reviewed and left in place:

| Technique | Where |
|-----------|-------|
| `requestAnimationFrame` drag updates | `scheduleDragPositionUpdate()` |
| `requestAnimationFrame` resize handling | `observeCanvasResize()` |
| Placement index map | `placementIndex` / `getPlacementById()` |
| DOM node cache | `$placementNodes` |
| Inventory element cache | `$inventoryByItemId`, `$inventoryBySpriteId` |
| Layer reorder without full re-render | `applyZOrderFromSorted()` updates z-index + append order only |
| Incremental position updates | `updatePlacementPosition()` during drag |

### 6.3 Inventory Count Updates

**Fix:** `updateInventoryCounts()` now uses cached `$inventoryByItemId` / `$inventoryBySpriteId` maps instead of re-scanning the entire inventory DOM when no explicit ID list is passed.

---

## 7. Image Optimization

### 7.1 Blade Templates (Already Present)

- Editor inventory: `loading="lazy"` + `decoding="async"` on item/sprite thumbnails
- Preview component: `loading="lazy"` on placement images

### 7.2 Editor Placed Items

**File:** `public/js/homestead-room-editor.js`

**Fix:** Added `decoding="async"` to dynamically created placement `<img>` elements (preview already had it).

### 7.3 Catalog Payload

Item and sprite catalog entries still send full `imageUrl` paths — required for canvas rendering. No CDN or thumbnail variant was introduced (would change visual fidelity).

### 7.4 Recommendations (Not Implemented)

- Serve homestead item/sprite thumbnails at display size via image processing (requires asset pipeline changes)
- Add `width`/`height` attributes on `<img>` tags to reduce layout shift (needs consistent dimensions)

---

## 8. Database Indexes Added

**Migration:** `2026_07_09_120000_add_homestead_performance_indexes.php`

| Table | Index | Purpose |
|-------|-------|---------|
| `room_placements` | `(room_save_id, z_index)` | Ordered placement loads per room |
| `room_saves` | `(user_id, room_type)` | Slot count queries |
| `items` | `(is_homestead_item, placement_type, homestead_room_type)` | Placeable item subquery |
| `user_items` | `(user_id, item_id)` | Inventory aggregation for editor |

---

## 9. Files Modified

| File | Changes |
|------|---------|
| `app/Services/Homestead/Concerns/ManagesHomesteadEditor.php` | `whereExists` inventory, column selects |
| `app/Services/Homestead/Concerns/ManagesHomesteadEditorSprites.php` | JOIN sprites, partial eager load |
| `app/Services/Homestead/Concerns/ManagesHomesteadPreview.php` | Batch preview builder |
| `app/Services/Homestead/Concerns/ResolvesHomesteadRefTargets.php` | Merged space load, batched URLs, batched favorite states |
| `app/Services/Homestead/FeaturedService.php` | Merged space hydration |
| `app/Http/Controllers/Homestead/ShowcaseController.php` | Batch previews + favorite states |
| `app/Http/Controllers/Homestead/FavoriteController.php` | Batch previews |
| `app/Http/Controllers/Users/UserController.php` | Batch previews |
| `resources/views/homestead/_favorite_card.blade.php` | Use preloaded `showcase_url` |
| `public/js/homestead-room-editor.js` | DocumentFragment render, async decoding, cached count updates |
| `public/js/homestead-room-preview.js` | DocumentFragment render |
| `database/migrations/2026_07_09_120000_add_homestead_performance_indexes.php` | **New** |

---

## 10. Query Count Estimates

### Showcase Page (12 featured entries, 8 rooms)

| | Before (approx.) | After (approx.) |
|--|------------------|-----------------|
| Featured list | 1 | 1 |
| Subject hydration | 3 | 2 |
| Preview data | 8 × 4 = 32 | 4 |
| Favorite states | 16 | 3 |
| **Total** | **~52** | **~10** |

### Editor Page (1 room, 200 owned items, 50 sprites)

| | Before (approx.) | After (approx.) |
|--|------------------|-----------------|
| Placeable ID pluck | 1 | 1 (cached, save only) |
| Inventory aggregate | 1 (large IN) | 1 (EXISTS) |
| Items load | 1 | 1 (narrow columns) |
| Sprites | 1 + subquery | 1 JOIN |
| Room + placements | 2 | 2 |
| **Total** | **~6** | **~6** (faster queries, less data) |

### Favorites Page (12 entries, 6 rooms)

| | Before (approx.) | After (approx.) |
|--|------------------|-----------------|
| Favorites + hydration | 4 | 3 |
| Preview data | 24 | 4 |
| Featured URL per card | 6 | 0 (batched in hydration) |
| **Total** | **~34** | **~7** |

---

## 11. Verification Checklist

| Test | Expected |
|------|----------|
| Open room editor | Inventory, sprites, placements load correctly |
| Place furniture + sprites, save | Same validation and persistence |
| Showcase grid | Previews render; favorite buttons work |
| Favorites page | Cards link correctly; previews render |
| Layer forward/backward | Z-order unchanged |
| Drag placement | Smooth movement (RAF) |
| Large room (50+ placements) | Faster initial render |

---

## 12. Conclusion

Homestead performance was improved at the **query batching**, **index**, and **DOM rendering** layers without altering user-visible behavior. The largest wins are on **showcase and favorites pages** where room previews and favorite metadata are now loaded in batched queries instead of per-card loops. The **editor** benefits from more efficient inventory/sprite SQL and reduced payload size, which matters most for sites with large item catalogs.

Remaining opportunities (virtual inventory scrolling, thumbnail resizing, public room pages) would require functional or visual changes and were intentionally excluded from this pass.
