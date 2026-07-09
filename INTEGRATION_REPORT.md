# Homestead Integration Report

**Date:** 2026-07-09  
**Scope:** Full review of Homestead integration across Character, Inventory, Sprites, Rooms, Houses, Featured, Favorites, and Admin Panel.

---

## Executive Summary

The Homestead module is **functionally integrated** end-to-end: users unlock slots from inventory, create rooms/houses, decorate from inventory and character sprites, curate content via admin featured showcase, and bookmark content via favorites.

This review identified **6 integration issues**. **5 were fixed** in this pass. **1 known limitation** remains by design (no standalone public room/house pages — discovery flows through the showcase).

| Area | Status |
|------|--------|
| Inventory → room/house slots | ✅ Working |
| Inventory → sprite slots | ✅ Working |
| Inventory → editor furniture | ✅ Working |
| Character sprites → room editor | ✅ Working |
| Sprite delete → placements | ✅ Fixed |
| Room/house delete → favorites/featured | ✅ Fixed |
| Character delete → favorites/featured | ✅ Fixed |
| Favorite card links (inactive featured) | ✅ Fixed |
| Favorites hydration (hidden characters) | ✅ Fixed |
| Room/house public pages | ⚠️ Not implemented (by design) |
| `user_item_id` on placements | ⚠️ Schema only (documented) |

---

## Architecture Overview

Homestead spans multiple Lorekeeper subsystems:

```
┌─────────────┐     ┌──────────────┐     ┌─────────────────┐
│  Inventory  │────▶│ Slot Services│────▶│ room_saves      │
│  (items)    │     │ room/house/  │     │ characters      │
│             │     │ sprite_slot  │     │ max_sprite_slots│
└──────┬──────┘     └──────────────┘     └────────┬────────┘
       │                                            │
       │ placeable items                            │ layouts + placements
       ▼                                            ▼
┌─────────────┐     ┌──────────────┐     ┌─────────────────┐
│ Room Editor │────▶│ RoomManager  │────▶│ room_placements │
│ (JS + PHP)  │     │              │     │ room_layouts    │
└─────────────┘     └──────────────┘     └────────┬────────┘
                                                  │
       ┌──────────────────────────────────────────┘
       ▼
┌─────────────┐     ┌──────────────┐
│  Showcase   │◀────│ featured_items│
│  /showcase  │     └──────────────┘
└──────┬──────┘
       │
       ▼
┌─────────────┐
│  Favorites  │  homestead_favorites
│  /favorites │
└─────────────┘
```

**Central facade:** `App\Services\Homestead\RoomManager`  
**Config:** `config/lorekeeper/homestead.php`  
**Member routes:** `routes/lorekeeper/homestead.php` (included from `members.php`)  
**Public routes:** `routes/lorekeeper/browse.php` (`/showcase`, `/user/{name}/homestead-favorites`)  
**Admin routes:** `routes/lorekeeper/admin.php` (`/admin/homestead/*`)

---

## Module Integration Details

### 1. Character Module

| Component | Path |
|-----------|------|
| Sprite model | `app/Models/Character/CharacterSprite.php` |
| Character fields | `max_sprite_slots`, `active_sprite_id` |
| Public sprites page | `GET /character/{slug}/sprites` |
| Member sprite CRUD | `routes/lorekeeper/members.php` |
| Slot summary | `app/Services/Character/Concerns/ManagesCharacterSpriteSlots.php` |

**Connections:**
- Characters own sprites (`character_sprites.character_id`)
- `sprite_slot` inventory items increment `characters.max_sprite_slots` via `SpriteSlotService`
- Sprites appear in room editor from characters owned by the room owner
- Character pages show favorite button (non-owner) and link to sprites in sidebar
- Character soft-delete now cleans `homestead_favorites` and `featured_items` for that character

**Permissions:** Owner or `manage_characters` power for sprite management.

---

### 2. Inventory

| Component | Path |
|-----------|------|
| Item scopes | `Item::scopeHomestead()`, `scopePlaceableInHomestead()` |
| Slot tags | `config/lorekeeper/item_tags.php` — `room_slot`, `house_slot`, `sprite_slot` |
| Slot services | `RoomSlotService`, `HouseSlotService`, `SpriteSlotService` |
| Activation | `ActivatesSlotItem` trait → `user_items.activated_quantity` |
| Admin item fields | `admin/items/create_edit_item.blade.php` |

**Connections:**
- **Room/house slots:** `activated_quantity` on `user_items` with matching tag increases max creatable spaces
- **Sprite slots:** increments `max_sprite_slots` on a selected owned character
- **Editor furniture:** `ManagesHomesteadEditor::getEditorInventory()` loads placeable homestead items from user inventory
- **Placement validation:** save counts placements per `item_id` against owned quantity

**Item fields:** `is_homestead_item`, `placement_type`, `homestead_room_type`, `default_width`, `default_height`

---

### 3. Sprite System

| Component | Path |
|-----------|------|
| Editor integration | `ManagesHomesteadEditorSprites` trait |
| Editor view | `resources/views/homestead/_editor_sprites.blade.php` |
| Placement column | `room_placements.character_sprite_id` (nullable FK) |
| Admin sprites | `/admin/homestead/sprites` |
| Admin slot limits | `/admin/homestead/sprite-slots` |

**Connections:**
- Sprites saved to `room_placements` with `item_id = null`, `character_sprite_id` set
- Editor loads sprites from `CharacterSprite` where `character.user_id = room.user_id`
- Sprite delete clears both `room_layouts.character_sprite_id` and `room_placements.character_sprite_id` before deletion

**Fix applied:** `CharacterSpriteService::deleteSprite()` now nulls `room_placements.character_sprite_id` to prevent FK constraint failures.

---

### 4. Rooms & Houses

| Component | Path |
|-----------|------|
| Model | `app/Models/Homestead/RoomSave.php` (soft deletes) |
| Types | `indoor` = room, `outdoor` = house |
| Layout | `room_layouts` (surfaces: wallpaper, flooring, etc.) |
| Placements | `room_placements` (furniture + sprites) |
| CRUD | `SpaceController` |
| Editor | `HomesteadEditorController` + `homestead-room-editor.js` |

**Routes (auth required):**
- `GET /homestead/rooms`, `/homestead/houses` — list
- `GET/POST /homestead/{rooms|houses}/{id}/editor` — decorate
- Create/edit/delete per space type

**Connections:**
- Slot limits from config base + activated inventory items
- `unlimited_homestead_slots` rank power bypasses limits
- Delete removes placements, layout, favorites, featured entries, then soft-deletes room

**Fix applied:** `ManagesHomesteadSpaces::deleteRoom()` calls `cleanupHomesteadReferences()` for the correct ref type (`room` or `house`).

---

### 5. Featured (Showcase)

| Component | Path |
|-----------|------|
| Model | `app/Models/Homestead/FeaturedItem.php` |
| Service | `app/Services/Homestead/FeaturedService.php` |
| Public | `GET /showcase`, `GET /showcase/{id}` |
| Admin | `GET /admin/homestead/featured/{rooms|houses|characters}` |

**Connections:**
- Polymorphic via `type` + `ref_id` → `RoomSave` or `Character`
- Room/house previews via `RoomManager::getPreviewViewData()`
- Favorite buttons on showcase cards and detail pages
- Admin create form excludes already-featured targets

**Fix applied:**
- `FeaturedService::getPublicFeaturedEntry()` allows direct access to inactive featured entries (so favorited links still resolve)
- `FeaturedItem::findUrlForRef()` helper for favorite card URLs

**Admin permission:** `edit_data`

---

### 6. Favorites

| Component | Path |
|-----------|------|
| Model | `app/Models/Homestead/HomesteadFavorite.php` |
| Service | `app/Services/Homestead/FavoriteService.php` |
| Resolver | `ResolvesHomesteadRefTargets` trait |
| Routes | `GET /favorites`, `POST /favorites/toggle` |
| Profile | `GET /user/{name}/homestead-favorites` |
| JS | `public/js/homestead-favorite-button.js` |

**Connections:**
- Same ref types as featured: `room`, `house`, `character`
- Toggle blocked for own content
- Counts aggregated per target
- Room/house cards show canvas preview when subject resolves

**Favorite button locations:**
- Showcase listing + detail
- Character header (non-owner)
- Favorites pages (own list + profile)

**Fixes applied:**
- Favorite card links use `FeaturedItem::findUrlForRef()` (includes inactive featured)
- Character hydration uses `myo(0)->visible()` for consistency with toggle validation
- Profile favorites page pagination deduplicated (bottom only, matching `/favorites`)

**Limitation:** Room/house favorites can only be created from showcase entries (no public room pages). This is intentional — showcase is the discovery surface.

---

### 7. Admin Panel

| Sidebar Section | Permission | URLs |
|-----------------|------------|------|
| Homestead | `edit_data` | Featured Showcase, Featured Rooms/Houses/Characters |
| Homestead Characters | `manage_characters` | Character Sprites, Sprite Slots |

| Path | Controller | Purpose |
|------|------------|---------|
| `/admin/homestead/featured/*` | `FeaturedController` | Curate showcase |
| `/admin/homestead/sprites` | `SpriteController` | List characters with sprites |
| `/admin/homestead/sprites/character/{slug}` | `SpriteController` | Per-character sprite CRUD |
| `/admin/homestead/sprite-slots` | `SpriteSlotController` | Override `max_sprite_slots` |

Item homestead fields configured under **Admin → Data → Items**.

---

## Foreign Key Map

| Table | Column | References | Notes |
|-------|--------|------------|-------|
| `room_saves` | `user_id` | `users.id` | Soft deletes |
| `room_layouts` | `room_save_id` | `room_saves.id` | One per space |
| `room_layouts` | `wallpaper_item_id`, etc. | `items.id` | Surfaces |
| `room_placements` | `room_save_id` | `room_saves.id` | |
| `room_placements` | `item_id` | `items.id` | Nullable (sprites) |
| `room_placements` | `character_sprite_id` | `character_sprites.id` | Nullable (furniture) |
| `room_placements` | `user_item_id` | `user_items.id` | **Never populated** |
| `characters` | `active_sprite_id` | `character_sprites.id` | |
| `featured_items` | `owner_user_id` | `users.id` | ON DELETE SET NULL |
| `homestead_favorites` | `user_id` | `users.id` | ON DELETE CASCADE |
| `homestead_favorites` | `ref_id` | — | Polymorphic, no FK |

---

## Issues Found & Resolution

### Fixed in This Review

#### 1. Sprite delete FK violation
**Problem:** Deleting a sprite left `room_placements.character_sprite_id` pointing at the deleted row, causing FK errors.  
**Fix:** `CharacterSpriteService::deleteSprite()` nulls placement references before delete.  
**File:** `app/Services/Character/CharacterSpriteService.php`

#### 2. Room/house delete orphaned social data
**Problem:** Deleting a space did not remove `homestead_favorites` or `featured_items`.  
**Fix:** `ManagesHomesteadSpaces::deleteRoom()` calls `cleanupHomesteadReferences()`.  
**Files:** `app/Services/Homestead/Concerns/ManagesHomesteadSpaces.php`, `CleansHomesteadReferences.php`

#### 3. Character delete orphaned social data
**Problem:** Soft-deleting a character left favorites/featured entries.  
**Fix:** `CharacterManager::deleteCharacter()` calls `cleanupHomesteadReferences()`.  
**File:** `app/Services/CharacterManager.php`

#### 4. Favorite card broken links after unfeaturing
**Problem:** Cards only linked to active featured entries; deactivated entries fell back to generic `/showcase`.  
**Fix:** `FeaturedItem::findUrlForRef()` + showcase detail allows inactive entries by direct ID.  
**Files:** `FeaturedItem.php`, `FeaturedService.php`, `_favorite_card.blade.php`

#### 5. Hidden characters in favorites hydration
**Problem:** `hydrateRefSubjects()` loaded all characters; toggle used `visible()` scope.  
**Fix:** Hydration now uses `myo(0)->visible()`.  
**File:** `ResolvesHomesteadRefTargets.php`

#### 6. Duplicate pagination on profile favorites
**Problem:** `user/homestead_favorites.blade.php` rendered pagination twice.  
**Fix:** Removed top pagination; bottom only (matches `/favorites`).  
**File:** `resources/views/user/homestead_favorites.blade.php`

---

### Known Limitations (Not Fixed)

#### A. No public room/house detail pages
Rooms and houses are owner-only (`/homestead/rooms`, editor). Public discovery is via `/showcase`. Users cannot favorite a room/house unless it appears in the showcase. Config hint references "their pages" but those pages do not exist for spaces.

**Recommendation (future):** Add `GET /showcase/space/{id}` or public read-only room view, or accept showcase-only discovery.

#### B. `user_item_id` on placements unused
Column exists in schema and model fillable but `persistPlacements()` never writes it. Quantity tracking uses `item_id` counts against inventory totals.

**Recommendation (future):** Either implement per-stack tracking or remove the column in a migration.

#### C. Legacy `room_layouts` sprite fields
`character_id`, `character_sprite_id`, `sprite_position_x/y` on `room_layouts` are unused; sprites live in `room_placements`.

**Recommendation (future):** Deprecate and remove in a later migration if confirmed unused.

#### D. Admin permission split
Featured curation (`edit_data`) and sprite management (`manage_characters`) require different powers. Intentional but worth documenting for staff assignments.

#### E. Orphaned favorites when subject becomes unavailable
If a room is soft-deleted outside the normal delete flow, or a character is hidden (not deleted), favorite rows may remain but cards show empty subjects. Delete/hide cleanup is partial.

---

## Navigation & UI Integration

| Location | Homestead Links |
|----------|-----------------|
| Main navbar | Rooms, Houses, Showcase, Favorites (auth) |
| Homestead sidebar | Same four links |
| Character sidebar | Sprites |
| User profile sidebar | Homestead Favorites |
| Admin sidebar | Featured + Sprites sections |

**JS loaded where needed:**
- `homestead-room-editor.js` — editor
- `homestead-room-preview.js` — showcase, favorites
- `homestead-favorite-button.js` — showcase, character layout, favorites pages

---

## Data Flow: Complete User Journey

1. **Unlock capacity** — Activate `room_slot` / `house_slot` / `sprite_slot` items from inventory
2. **Create space** — `/homestead/rooms/create` or `/homestead/houses/create`
3. **Upload sprites** — `/character/{slug}/sprites` (within slot limit)
4. **Decorate** — Editor: drag furniture from inventory tab, sprites from sprites tab
5. **Save** — POST editor → `RoomManager` persists layout surfaces + placements
6. **Discover** — Moderator features content → appears on `/showcase`
7. **Favorite** — Users heart showcase entries or characters
8. **Review** — `/favorites` or profile homestead favorites page

---

## Test Checklist

| Test | URL / Action | Expected |
|------|--------------|----------|
| Create room | `/homestead/rooms/create` | Respects slot limit |
| Activate room slot item | Inventory | Increases max rooms |
| Place sprite in editor | Room editor → Sprites tab | Saves to `room_placements` |
| Delete placed sprite | Character sprites page | Succeeds; placement reference nulled |
| Feature a room | Admin featured | Appears on showcase |
| Favorite from showcase | Heart button | Toggle works; count updates |
| Delete room | Room delete | Favorites + featured removed |
| Delete character | Admin delete | Favorites + featured removed |
| Inactive featured link | Favorite card | Links to `/showcase/{id}` still works |
| Admin sprite slots | `/admin/homestead/sprite-slots` | Can raise/lower max slots |

---

## Files Modified in This Review

| File | Change |
|------|--------|
| `app/Services/Homestead/Concerns/CleansHomesteadReferences.php` | **New** — shared cleanup trait |
| `app/Services/Homestead/Concerns/ManagesHomesteadSpaces.php` | Cleanup on room delete |
| `app/Services/Character/CharacterSpriteService.php` | Null placement refs on sprite delete |
| `app/Services/CharacterManager.php` | Cleanup on character delete |
| `app/Services/Homestead/FeaturedService.php` | Allow inactive featured detail by ID |
| `app/Models/Homestead/FeaturedItem.php` | `findUrlForRef()` helper |
| `app/Services/Homestead/Concerns/ResolvesHomesteadRefTargets.php` | Visible character hydration |
| `resources/views/homestead/_favorite_card.blade.php` | Better URL resolution |
| `resources/views/user/homestead_favorites.blade.php` | Pagination fix |

---

## Conclusion

Homestead is **well integrated** across Lorekeeper's character, inventory, and admin systems. The core decoration loop, slot economy, sprite placement, showcase curation, and favorites social layer all connect through shared services and consistent ref types (`room`, `house`, `character`).

Critical lifecycle gaps (sprite delete FK, delete cascades for social data) have been addressed. Remaining gaps are primarily **product decisions** (showcase-only public discovery for spaces) and **schema debt** (`user_item_id`, legacy layout sprite columns).
