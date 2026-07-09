# Migration Plan — Base44 Malcion Homesteads → Lorekeeper Laravel

> **Reference project:** `/var/www/html/mini-game/base44` (React SPA on Base44 BaaS)  
> **Target project:** `/var/www/html/mini-game/lorekeeper` (Laravel 8 Blade ARPG platform)  
> **Companion document:** [LARAVEL_ANALYSIS.md](./LARAVEL_ANALYSIS.md)

This document defines what already exists in Lorekeeper, what should be reused, what must be newly developed, and what can be adapted from the Base44 reference implementation.

---

## Table of Contents

1. [Migration Goals](#1-migration-goals)
2. [Architectural Decision: Frontend Strategy](#2-architectural-decision-frontend-strategy)
3. [Feature Comparison Matrix](#3-feature-comparison-matrix)
4. [Module-by-Module Migration Plan](#4-module-by-module-migration-plan)
5. [Data Model Mapping](#5-data-model-mapping)
6. [Reusable Lorekeeper Components](#6-reusable-lorekeeper-components)
7. [Adaptable Base44 Patterns](#7-adaptable-base44-patterns)
8. [New Development Required](#8-new-development-required)
9. [Phased Implementation Roadmap](#9-phased-implementation-roadmap)
10. [Risk Register](#10-risk-register)
11. [Out of Scope](#11-out-of-scope)

---

## 1. Migration Goals

Port the **Malcion Homesteads** gameplay loop from the Base44 reference into the existing Lorekeeper Laravel project:

```
Earn currency → Buy decoration items → Manage inventory →
Create rooms/houses → Decorate with canvas editor →
Showcase featured content → Favorite others' creations → Trade items/characters
```

### Principles

1. **Preserve Lorekeeper core** — masterlist, submissions, galleries, raffles, and the existing admin power system remain intact.
2. **Reuse before rebuild** — leverage existing Managers, models, and widgets wherever semantics align.
3. **Adapt, don't copy** — Base44 is a BaaS SPA; Laravel is server-rendered with service-layer business logic. Port behavior and UX, not file structure.
4. **Incremental delivery** — each phase should be deployable and testable independently.

---

## 2. Architectural Decision: Frontend Strategy

Base44 and Lorekeeper use fundamentally different frontend architectures. This is the most consequential migration decision.

| Approach | Description | Pros | Cons |
|----------|-------------|------|------|
| **A. Blade + jQuery (extend existing)** | New homestead pages follow Lorekeeper conventions: Blade views, jQuery modals, AJAX partials | Consistent with codebase; no new build pipeline; reuses widgets | Room editor canvas is complex for jQuery; harder to port Base44 React components directly |
| **B. Hybrid (Blade shell + React islands)** | Laravel serves pages; room editor and canvas are React components mounted in Blade via Vite | Best of both worlds; can port Base44 room editor with adaptation | Two frontend stacks to maintain; needs Vite alongside Laravel Mix |
| **C. Full SPA + Laravel API** | Build React SPA (like Base44); add REST API to Laravel | Closest to Base44 architecture; modern UX | Largest effort; must build entire API layer; duplicates auth/routing |

### Recommendation

**Approach B (Hybrid)** for the homestead features, with **Approach A** for simpler CRUD pages (rooms list, store, wallet, favorites).

| Feature | Recommended frontend |
|---------|-------------------|
| Rooms list, store, wallet, inventory, favorites, showcase | Blade + jQuery (Lorekeeper pattern) |
| Room editor canvas | React island (ported from Base44 `room-editor/` components) |
| Admin homestead tabs | Blade (extend existing admin panel) |
| Trade UI | Blade (extend existing trade module) |

This avoids building a full API while allowing the complex canvas editor to reuse Base44's React implementation.

---

## 3. Feature Comparison Matrix

| Feature | Base44 | Lorekeeper | Migration action |
|---------|--------|------------|------------------|
| **Auth (email/password)** | ✅ Email + OTP + Google OAuth | ✅ Email + verify + password reset | **Keep Laravel** — add Google OAuth only if needed |
| **Auth (social linking)** | ❌ Not required | ✅ Required alias gate (DeviantArt, etc.) | **Keep Laravel** — Base44 has no equivalent |
| **User roles** | `admin` / `user` enum | Rank + power system | **Keep Laravel** — map Base44 `admin` to staff rank |
| **User profiles** | ❌ No profile page | ✅ Full public profiles | **Keep Laravel** — no migration needed |
| **Characters (masterlist)** | Simple: name, sprites, owner | Full: traits, images, design updates, MYO | **Keep Laravel model** — add sprite layer for rooms |
| **Character sprites** | ✅ Multi-sprite upload per character | ❌ No sprite concept | **New development** — sprite fields/storage on characters |
| **Inventory** | `UserInventory` with slot activation | `UserItem` stacks with item tags | **Adapt** — extend item tags for homestead categories |
| **Store / shop** | Single coin, tabbed categories | Multi-currency shops with stock limits | **Reuse ShopManager** — add homestead item categories |
| **Wallet** | Transaction ledger (sum = balance) | Multi-currency bank with logs | **Reuse CurrencyManager** — designate one currency as "coins" |
| **Rooms** | `RoomSave` entity (indoor/outdoor) | ❌ Does not exist | **New development** — new model, migrations, services |
| **Room editor** | React canvas with drag-and-drop | ❌ Does not exist | **Port from Base44** — React island or full rewrite |
| **Showcase** | Mod-curated featured rooms/chars | ❌ Does not exist | **New development** — new model + admin tab |
| **Favorites** | Rooms + characters | Character bookmarks only | **Extend BookmarkManager** — add room favorite type |
| **Trading** | UI only (no asset settlement) | ✅ Full settlement via TradeManager | **Keep Laravel** — extend trade UI for homestead context |
| **Admin: store CRUD** | `StoreManagerTab` | `Admin\Data\ShopController` | **Reuse** — extend for homestead categories |
| **Admin: coin management** | `CoinsManagerTab` | `Admin\Users\GrantController` | **Reuse** — already grants currency |
| **Admin: inventory management** | `InventoryManagerTab` | `Admin\Users\GrantController` + item grants | **Reuse** |
| **Admin: character management** | `AllCharactersTab` | `Admin\Characters\CharacterController` | **Reuse** — add sprite management |
| **Admin: featured showcase** | `FeaturedManagerTab` | ❌ Does not exist | **New development** |
| **Admin: room management** | `AllRoomsTab` | ❌ Does not exist | **New development** |
| **Admin: trade logs** | `TradeLogsTab` (read-only) | Trade model + logs exist | **Reuse** — add admin view |
| **Home dashboard** | Aggregated stats/previews | `HomeController` dashboard | **Extend** — add homestead widgets |
| **Submissions** | ❌ Not in Base44 | ✅ Full queue | **Keep** — no migration |
| **Galleries** | ❌ Not in Base44 | ✅ Full system | **Keep** — no migration |
| **Raffles** | ❌ Not in Base44 | ✅ Full system | **Keep** — no migration |

---

## 4. Module-by-Module Migration Plan

### 4.1 Authentication — KEEP AS-IS

| Item | Action |
|------|--------|
| Laravel session auth | **Keep** |
| Email verification | **Keep** |
| Social alias linking | **Keep** (Base44 has no equivalent; required for Lorekeeper) |
| Invitation codes | **Keep** |
| Google OAuth | **Optional add** — only if Base44 Google login is a hard requirement |
| OTP registration | **Skip** — Laravel email verification covers this |

**Base44 reference files (behavior only):**
- `base44/src/pages/Login.jsx` — UI patterns for login form layout
- `base44/src/pages/Register.jsx` — registration flow reference
- `base44/src/lib/AuthContext.jsx` — auth state management (not portable directly)

---

### 4.2 Users — KEEP AS-IS

| Item | Action |
|------|--------|
| User model + profiles | **Keep** |
| Rank/power system | **Keep** — map Base44 `admin` role to existing staff ranks |
| Public profile pages | **Keep** |
| Account settings | **Keep** |

No new development required. Base44's minimal user model is a subset of what Lorekeeper already provides.

---

### 4.3 Characters — EXTEND

Lorekeeper characters are masterlist entries with traits, images, and ownership history. Base44 characters are lightweight entities with sprites for room placement. These serve different purposes but can coexist.

| Item | Lorekeeper today | Base44 reference | Action |
|------|------------------|------------------|--------|
| Character creation | Admin-only (masterlist) | Admin-only | **Keep** — same policy |
| Character profile/bio | ✅ `CharacterProfile` | name + description | **Keep** — already richer |
| Character images | ✅ `CharacterImage` with traits | ❌ | **Keep** |
| Character sprites | ❌ | ✅ Multi-sprite upload | **New** — add sprite storage |
| Sprite selection | ❌ | `active_sprite_index` | **New** — field on character or new table |
| Sprite slot limits | ❌ | `max_sprite_slots` + inventory activation | **Adapt** — use item tag `sprite_slot` |
| Owner reassignment | ✅ `CharacterManager` transfers | Admin reassign by email | **Keep** — already implemented |
| Featured characters | ❌ | Showcase system | **New** — via showcase module |

#### New database elements needed

```
character_sprites
  - id
  - character_id (FK → characters)
  - image_path
  - sort_order
  - is_active (or use active_sprite_index on characters)
  - created_at, updated_at

characters table additions:
  - active_sprite_id (nullable FK → character_sprites)
  - max_sprite_slots (default 1)
```

#### Reuse from Lorekeeper

- `CharacterManager` — ownership, settings
- `Admin\Characters\CharacterController` — admin CRUD
- `FileManager` — image upload pipeline
- `CharacterImage` model patterns — for sprite storage conventions

#### Adapt from Base44

- `base44/src/components/characters/SpriteManager.jsx` — sprite upload/delete/set-active UX
- `base44/src/components/characters/CharacterCard.jsx` — card layout for homestead views
- `base44/base44/entities/Character.jsonc` — sprite field schema

---

### 4.4 Inventory — EXTEND

| Item | Lorekeeper today | Base44 reference | Action |
|------|------------------|------------------|--------|
| Item stacks | ✅ `UserItem` with quantity | ✅ `UserInventory` with quantity | **Keep** `UserItem` model |
| Item activation | ✅ Item tags (`slot`) | ✅ `activated_quantity` for slots | **Extend** item tag system |
| Slot unlocking | ✅ `SlotService` | room_slot, house_slot, sprite_slot | **Add new tag types** |
| Item categories | ✅ `ItemCategory` | furniture, wallpaper, flooring, etc. | **Add homestead categories** |
| Placement metadata | ❌ | width, height, placement_type | **Add item fields** |
| Per-user inventory view | ✅ Blade inventory page | React inventory page | **Extend view** with homestead tabs |
| Admin grant/remove | ✅ `GrantController` | `InventoryManagerTab` | **Keep** — already exists |

#### Item model extensions needed

```
items table additions:
  - placement_type (enum: floor, wall, ceiling, exterior, character, slot)
  - room_type (enum: indoor, outdoor, both, null)
  - default_width (integer, nullable)
  - default_height (integer, nullable)
  - is_homestead_item (boolean, default false)
```

#### Item tag extensions (`config/lorekeeper/item_tags.php`)

| New tag | Behavior | Base44 equivalent |
|---------|----------|-------------------|
| `room_slot` | Activating permanently increases indoor room limit | `room_slot` store category |
| `house_slot` | Activating permanently increases outdoor room limit | `house_slot` store category |
| `sprite_slot` | Activating increases character sprite limit | `sprite_slot` store category |
| `homestead_decor` | Placeable in room editor | furniture, wallpaper, flooring, etc. |

#### Reuse from Lorekeeper

- `InventoryManager` — grant, debit, credit, transfer
- `Item\SlotService` — activation logic (extend for new slot types)
- `InventoryController` — inventory page and AJAX modals
- Widgets: `_inventory_select` — item pickers in room editor forms

#### Adapt from Base44

- `base44/src/hooks/useInventory.js` — slot limit calculation logic
- `base44/src/pages/Inventory.jsx` — tab layout (items vs characters)
- `base44/base44/entities/UserInventory.jsonc` — `activated_quantity` pattern

---

### 4.5 Shop / Store — EXTEND

| Item | Lorekeeper today | Base44 reference | Action |
|------|------------------|------------------|--------|
| Shop CRUD | ✅ `ShopService` + admin controller | `StoreManagerTab` | **Keep** — extend categories |
| Stock management | ✅ `ShopStock` model | `StoreItem` entity | **Keep** — add homestead fields to items |
| Purchase flow | ✅ `ShopManager::buyStock()` | Store page buy button | **Keep** — wire to homestead shop |
| Purchase limits | ✅ Stock limits in `ShopStock` | `is_limited` flag | **Keep** — already supported |
| Multi-currency | ✅ Per-stock currency_id | Single coin currency | **Configure** one currency as "coins" |
| Purchase history | ✅ `ShopLog` + history page | Wallet transactions | **Keep** |
| Tabbed categories | ❌ | furniture, wallpaper, slots, etc. | **Add** category filtering to shop view |

#### Reuse from Lorekeeper

- `ShopManager` — complete purchase pipeline
- `ShopController` — public shop pages
- `Admin\Data\ShopController` — admin CRUD
- `ShopStock` model — pricing, limits, currency

#### Adapt from Base44

- `base44/src/pages/Store.jsx` — tabbed category UI, owned-state display
- `base44/src/components/store/StoreItemCard.jsx` — item card design
- `base44/src/components/admin/StoreManagerTab.jsx` — admin form fields for homestead items
- `base44/base44/entities/StoreItem.jsonc` — category enum, placement fields

---

### 4.6 Wallet / Currency — REUSE

| Item | Lorekeeper today | Base44 reference | Action |
|------|------------------|------------------|--------|
| Balance storage | ✅ `UserCurrency` per currency type | Computed from transaction sum | **Keep** `UserCurrency` — simpler and already transactional |
| Transaction log | ✅ `CurrencyLog` | `WalletTransaction` entity | **Keep** `CurrencyLog` |
| Spend on purchase | ✅ `CurrencyManager::debit()` | `useWallet.spend()` | **Keep** |
| Admin credit/debit | ✅ `GrantController` | `CoinsManagerTab` | **Keep** |
| Real-time balance | ❌ Page refresh | `subscribe()` on transactions | **Optional** — Laravel Echo or polling |
| Player top-up | ❌ | `addFunds()` exists but no UI | **Skip** unless Stripe integration is planned |
| Transaction history page | ✅ `currency_logs` view | `Wallet.jsx` | **Extend** — homestead-branded wallet page |

#### Configuration step

Designate one existing `Currency` record (or create a new one) as the homestead "coin" currency. All homestead purchases debit this currency.

#### Reuse from Lorekeeper

- `CurrencyManager` — debit, credit, transfer with logging
- `BankController` — bank page and transfers
- `Admin\Users\GrantController` — admin currency grants
- `CurrencyLog` model — full audit trail

#### Adapt from Base44

- `base44/src/pages/Wallet.jsx` — balance display and transaction list UI
- `base44/src/hooks/useWallet.js` — balance calculation pattern (reference only; Laravel stores balance directly)

---

### 4.7 Rooms & Room Editor — NEW DEVELOPMENT

This is the largest new module. Lorekeeper has no equivalent.

| Item | Action |
|------|--------|
| `RoomSave` model + migration | **New** |
| Room CRUD (create, list, delete) | **New** — controller + views |
| Slot limit enforcement | **Adapt** from Base44 `useInventory` slot math |
| Room editor (canvas) | **Port** from Base44 React components |
| Room preview (read-only) | **Port** from Base44 `RoomPreview.jsx` |
| Save/load placed items | **New** — JSON storage on `RoomSave` |
| Character sprite placement | **New** — links to character sprites module |
| Wallpaper/flooring/exterior | **New** — FK to item IDs |

#### New database schema

```
room_saves
  - id
  - user_id (FK → users)
  - name (string)
  - room_type (enum: indoor, outdoor)
  - character_id (nullable FK → characters)
  - active_sprite_id (nullable FK → character_sprites)
  - sprite_position_x (decimal)
  - sprite_position_y (decimal)
  - wallpaper_item_id (nullable FK → items)
  - flooring_item_id (nullable FK → items)
  - roof_item_id (nullable FK → items)
  - exterior_wall_item_id (nullable FK → items)
  - placed_items (JSON — array of {item_id, x, y, width, height, z_index})
  - created_at, updated_at
  - deleted_at (soft deletes)
```

#### New Laravel components

| Component | Path (proposed) |
|-----------|-----------------|
| Model | `app/Models/Homestead/RoomSave.php` |
| Manager | `app/Services/Homestead/RoomManager.php` |
| Controller | `app/Http/Controllers/Homestead/RoomController.php` |
| Editor controller | `app/Http/Controllers/Homestead/RoomEditorController.php` |
| Routes | `routes/lorekeeper/homestead.php` (new file) |
| Views | `resources/views/homestead/` |
| Admin controller | `app/Http/Controllers/Admin/Homestead/RoomController.php` |

#### Port from Base44 (React components)

| Base44 file | Port target |
|-------------|-------------|
| `src/pages/Rooms.jsx` | Blade rooms list + create/delete |
| `src/pages/RoomEditor.jsx` | React island — main editor page |
| `src/components/room-editor/RoomCanvas.jsx` | React — drag/drop canvas |
| `src/components/room-editor/RoomPreview.jsx` | React — read-only renderer |
| `src/components/room-editor/InventoryPanel.jsx` | React — placeable items sidebar |
| `src/components/room-editor/EditorToolbar.jsx` | React — save/cancel toolbar |
| `src/components/room-editor/CharacterPicker.jsx` | React — character selection |

#### API endpoints needed (for room editor)

Even with hybrid frontend, the room editor React island needs JSON endpoints:

```
GET    /api/homestead/rooms              → list user's rooms
POST   /api/homestead/rooms              → create room
GET    /api/homestead/rooms/{id}         → get room save data
PUT    /api/homestead/rooms/{id}         → save room state
DELETE /api/homestead/rooms/{id}         → delete room
GET    /api/homestead/rooms/{id}/inventory → placeable items for this room
```

---

### 4.8 Showcase — NEW DEVELOPMENT

| Item | Lorekeeper today | Base44 reference | Action |
|------|------------------|------------------|--------|
| Featured content | ❌ | `Featured` entity | **New** model + admin tab |
| Public gallery | ❌ | `Showcase.jsx` | **New** view |
| Feature by admin | ❌ | `FeaturedManagerTab` | **New** admin tab |
| Home preview strip | ❌ | `Home.jsx` featured section | **Extend** home dashboard |

#### New database schema

```
featured_items
  - id
  - type (enum: room, character)
  - ref_id (polymorphic — room_save_id or character_id)
  - owner_user_id (FK → users)
  - note (text, nullable — mod caption)
  - featured_order (integer)
  - is_active (boolean, default true)
  - created_at, updated_at
```

#### Adapt from Base44

- `base44/src/pages/Showcase.jsx` — gallery grid with detail modals
- `base44/src/components/admin/FeaturedManagerTab.jsx` — admin list/toggle/delete
- `base44/src/components/admin/AddToFeaturedDialog.jsx` — feature dialog with note/order
- `base44/src/components/FavoriteButton.jsx` — heart button on cards

---

### 4.9 Favorites — EXTEND

| Item | Lorekeeper today | Base44 reference | Action |
|------|------------------|------------------|--------|
| Character bookmarks | ✅ `CharacterBookmark` + `BookmarkManager` | Character favorites | **Keep** — same concept |
| Room favorites | ❌ | `Favorite` entity with `ref_type` | **New** — extend or new model |
| Toggle favorite | ❌ | `useFavorites.toggle()` | **New** endpoint |
| Favorites page | ❌ | `Favorites.jsx` | **New** view |

#### Option A: Extend bookmarks

Add `type` field to `character_bookmarks` table (rename to `favorites`) with `ref_type` (character, room) and `ref_id`.

#### Option B: New table

```
favorites
  - id
  - user_id (FK → users)
  - ref_type (enum: character, room)
  - ref_id (integer)
  - created_at
  - unique(user_id, ref_type, ref_id)
```

**Recommendation:** Option B — cleaner separation; character bookmarks continue working unchanged.

#### Adapt from Base44

- `base44/src/hooks/useFavorites.js` — toggle logic
- `base44/src/pages/Favorites.jsx` — favorites grid with previews

---

### 4.10 Trading — KEEP & EXTEND UI

| Item | Lorekeeper today | Base44 reference | Action |
|------|------------------|------------------|--------|
| Trade creation | ✅ `TradeManager::createTrade()` | `CreateTradeDialog` | **Keep** — extend UI |
| Trade acceptance | ✅ Full asset settlement | Status update only (broken) | **Keep Laravel** — already correct |
| Trade history | ✅ Trade model with status | `Trade.jsx` history tab | **Keep** — restyle if desired |
| Item offers | ✅ Supported | `offered_items[]` | **Keep** |
| Character offers | ✅ Supported | `offered_character_ids[]` | **Keep** |
| Admin trade logs | ❌ No dedicated view | `TradeLogsTab` | **Add** admin read-only view |

Lorekeeper's `TradeManager` is **more complete** than Base44's implementation. No business logic migration needed — only UI improvements if desired.

#### Reuse from Lorekeeper

- `TradeManager` — full trade lifecycle
- `TradeController` — member trade routes
- `Trade` model — sender, recipient, items, characters, status

#### Adapt from Base44 (UI only)

- `base44/src/pages/Trade.jsx` — incoming/outgoing/history tab layout
- `base44/src/components/trade/CreateTradeDialog.jsx` — trade creation form UX
- `base44/src/components/trade/TradeOfferCard.jsx` — offer card display

---

### 4.11 Admin Panel — EXTEND

The existing Lorekeeper admin panel is significantly more capable than Base44's mod panel. Add homestead-specific tabs rather than replacing anything.

| Base44 admin tab | Lorekeeper equivalent | Action |
|------------------|----------------------|--------|
| Featured Showcase | ❌ | **New tab** — `Admin\Homestead\FeaturedController` |
| All Rooms | ❌ | **New tab** — `Admin\Homestead\RoomController` |
| All Characters | `Admin\Characters\CharacterController` | **Extend** — add sprite management, feature button |
| Coins | `Admin\Users\GrantController` | **Keep** — link from homestead admin section |
| Store | `Admin\Data\ShopController` | **Keep** — filter for homestead items |
| Inventory | `Admin\Users\GrantController` | **Keep** — link from homestead admin section |
| Trade Logs | Trade model exists | **New tab** — read-only trade audit view |

#### Admin sidebar extension

Add a "Homestead" section to `config/lorekeeper/admin_sidebar.php`:

```php
'homestead' => [
    'rooms' => ['power' => 'edit_data'],
    'featured' => ['power' => 'edit_data'],
    'trade_logs' => ['power' => 'edit_user_info'],
],
```

Or create a new power `manage_homestead` if homestead admin should be separately permissioned.

---

### 4.12 Home Dashboard — EXTEND

| Item | Action |
|------|--------|
| Existing dashboard | **Keep** — `HomeController@getIndex` |
| Featured preview strip | **Add** — query active `featured_items` |
| Quick links to rooms/store | **Add** — navigation cards |
| Wallet balance summary | **Add** — show coin currency balance |

#### Adapt from Base44

- `base44/src/pages/Home.jsx` — dashboard layout with featured previews and quick-nav cards

---

## 5. Data Model Mapping

### Base44 entity → Lorekeeper model

| Base44 entity | Lorekeeper model | Status |
|---------------|------------------|--------|
| `User` | `User` | ✅ Exists (richer) |
| `Character` | `Character` + `CharacterSprite` (new) | ⚠️ Extend |
| `RoomSave` | `Homestead\RoomSave` (new) | ❌ New |
| `StoreItem` | `Item` + `ShopStock` | ✅ Exists (extend fields) |
| `UserInventory` | `UserItem` | ✅ Exists (extend tags) |
| `WalletTransaction` | `CurrencyLog` + `UserCurrency` | ✅ Exists |
| `Trade` | `Trade` | ✅ Exists (more complete) |
| `Featured` | `Homestead\FeaturedItem` (new) | ❌ New |
| `Favorite` | `Homestead\Favorite` (new) | ❌ New |

### New tables summary

| Table | Purpose |
|-------|---------|
| `character_sprites` | Character sprite images for room placement |
| `room_saves` | Room/house save data with placed items JSON |
| `featured_items` | Mod-curated showcase entries |
| `favorites` | User favorites for rooms and characters |

### Extended tables summary

| Table | New columns |
|-------|-------------|
| `characters` | `active_sprite_id`, `max_sprite_slots` |
| `items` | `placement_type`, `room_type`, `default_width`, `default_height`, `is_homestead_item` |

---

## 6. Reusable Lorekeeper Components

These existing components should be used directly in the homestead module:

### Services / Managers

| Component | Reuse for |
|-----------|-----------|
| `InventoryManager` | Item grants from store, placement validation, trade items |
| `ShopManager` | Homestead store purchases |
| `CurrencyManager` | Coin debits/credits, wallet balance |
| `CharacterManager` | Character ownership, admin reassignment |
| `TradeManager` | Player-to-player trading |
| `FileManager` | Sprite uploads, room screenshots |
| `BookmarkManager` | Pattern reference for favorites |
| `UserService` | User lookup for admin panels |

### Controllers (extend, don't replace)

| Controller | Extend for |
|------------|------------|
| `ShopController` | Homestead store front |
| `InventoryController` | Homestead inventory tabs |
| `BankController` | Wallet/coin view |
| `Admin\Data\ShopController` | Homestead item admin |
| `Admin\Users\GrantController` | Coin/inventory admin |
| `Admin\Characters\CharacterController` | Sprite + feature management |

### Blade widgets

| Widget | Reuse for |
|--------|-----------|
| `_inventory_select` | Room editor item picker (server-rendered fallback) |
| `_character_select` | Room character assignment |
| `_image_upload_js` | Sprite upload in admin |
| `_bank_select` | Currency selection in admin grants |

### Infrastructure

| Component | Reuse for |
|-----------|-----------|
| Power middleware | Homestead admin permissions |
| `Settings` facade | Homestead configuration (slot defaults, etc.) |
| `Notifications` facade | Purchase confirmations, trade alerts |
| Admin layout + sidebar | New homestead admin tabs |
| Member middleware chain | Homestead route protection |

---

## 7. Adaptable Base44 Patterns

These Base44 patterns should be studied and adapted (not copied verbatim):

### Business logic patterns

| Base44 pattern | Laravel adaptation |
|----------------|-------------------|
| Slot limit = 1 base + sum of activated slot items | Implement in `RoomManager::getSlotLimits()` using `SlotService` |
| Balance = sum of transactions | **Skip** — use `UserCurrency` balance directly |
| `activated_quantity` on inventory | Map to item tag activation count on `UserItem` |
| Room types: `indoor` / `outdoor` | Direct mapping to `room_saves.room_type` enum |
| Placed items as JSON array | Store in `room_saves.placed_items` JSON column |
| Featured with `featured_order` | Direct mapping to `featured_items` table |

### UI/UX patterns

| Base44 component | Adaptation target |
|------------------|-------------------|
| Tabbed store categories | Blade shop view with category tabs |
| Room card with preview thumbnail | Blade card partial with `RoomPreview` React mount |
| Slot activation UI | Extend `inventory/_slot.blade.php` for new tag types |
| Trade incoming/outgoing/history tabs | Extend existing trade views |
| Admin tab panel layout | Extend `admin/layout.blade.php` with homestead section |
| Favorite heart toggle | AJAX endpoint + JS toggle (jQuery) |
| Dashboard quick-nav cards | Extend `pages/_dashboard.blade.php` |

### React components to port (room editor only)

| Component | Priority |
|-----------|----------|
| `RoomCanvas.jsx` | **High** — core gameplay |
| `RoomPreview.jsx` | **High** — showcase/favorites |
| `InventoryPanel.jsx` | **High** — placement sidebar |
| `EditorToolbar.jsx` | **Medium** |
| `CharacterPicker.jsx` | **Medium** |
| `StoreItemCard.jsx` | **Low** — can replicate in Blade |
| `CharacterCard.jsx` | **Low** — can replicate in Blade |

---

## 8. New Development Required

### Must build from scratch

| Module | Estimated complexity | Dependencies |
|--------|---------------------|--------------|
| Room saves (model, migration, CRUD) | Medium | User module |
| Room manager service | Medium | Inventory, characters |
| Room editor (React port) | **High** | Room saves, inventory, character sprites |
| Room preview renderer | Medium | Room saves |
| Character sprites (model, upload, management) | Medium | Character module, FileManager |
| Featured/showcase system | Medium | Room saves, characters |
| Favorites (rooms) | Low | Room saves |
| Homestead API routes (for editor) | Medium | Room saves, inventory |
| Admin: rooms tab | Low | Room saves |
| Admin: featured tab | Low | Featured system |
| Admin: trade logs tab | Low | Trade model |
| Item field extensions (placement metadata) | Low | Item module |
| Item tag extensions (homestead tags) | Low | Item tag system |
| Homestead routes file | Low | All homestead controllers |
| Home dashboard widgets | Low | Featured, wallet |

### Configuration / data setup

| Task | Description |
|------|-------------|
| Create "coins" currency | Seed a currency record for homestead economy |
| Create homestead item categories | furniture, wallpaper, flooring, decoration, exterior, slots |
| Seed starter shop | Create shop with homestead stock entries |
| Configure item tags | Add `room_slot`, `house_slot`, `sprite_slot`, `homestead_decor` |
| Admin sidebar update | Add homestead section |
| Navigation update | Add homestead links to member nav |

---

## 9. Phased Implementation Roadmap

### Phase 0 — Foundation (no user-facing changes)

| # | Task | Reuses |
|---|------|--------|
| 0.1 | Fix broken login route (`getNewReply` → `showLoginForm`) | — |
| 0.2 | Create homestead migrations (sprites, room_saves, featured, favorites, item extensions) | Existing migration patterns |
| 0.3 | Create `Homestead` model namespace | `Model` base class |
| 0.4 | Add homestead item tags to config | `item_tags.php` |
| 0.5 | Seed coin currency and homestead item categories | `CurrencyService`, `ItemService` |
| 0.6 | Add `routes/lorekeeper/homestead.php` scaffold | Route file patterns |
| 0.7 | Set up Vite alongside Laravel Mix (if hybrid approach) | — |

### Phase 1 — Economy bridge

| # | Task | Reuses |
|---|------|--------|
| 1.1 | Extend items with homestead placement fields | `Item` model, admin item controller |
| 1.2 | Add homestead item categories and seed data | `ItemCategory` |
| 1.3 | Create homestead shop with stock | `ShopService`, `ShopManager` |
| 1.4 | Build homestead store page (Blade) | `ShopController`, Base44 `Store.jsx` UX |
| 1.5 | Build wallet page (Blade) | `BankController`, `CurrencyManager` |
| 1.6 | Extend inventory page with homestead tabs | `InventoryController` |
| 1.7 | Implement slot item activation (room/house/sprite) | `SlotService` |

### Phase 2 — Characters & sprites

| # | Task | Reuses |
|---|------|--------|
| 2.1 | Character sprites model and upload | `FileManager`, `CharacterManager` |
| 2.2 | Sprite management UI (member) | Base44 `SpriteManager.jsx` |
| 2.3 | Admin sprite management on character edit | `Admin\Characters\CharacterController` |
| 2.4 | Sprite slot limits via inventory activation | `SlotService`, `InventoryManager` |

### Phase 3 — Rooms

| # | Task | Reuses |
|---|------|--------|
| 3.1 | RoomSave model and RoomManager service | `Service` base class |
| 3.2 | Room CRUD (create, list, delete) — Blade | Base44 `Rooms.jsx` |
| 3.3 | Slot limit enforcement on room creation | `RoomManager`, `SlotService` |
| 3.4 | Homestead API routes for editor | New API controllers |
| 3.5 | Port room editor React components | Base44 `room-editor/*` |
| 3.6 | Room save/load API integration | `RoomManager` |
| 3.7 | Room preview component | Base44 `RoomPreview.jsx` |

### Phase 4 — Social features

| # | Task | Reuses |
|---|------|--------|
| 4.1 | Featured items model and admin tab | Base44 `FeaturedManagerTab` |
| 4.2 | Showcase public page | Base44 `Showcase.jsx` |
| 4.3 | Favorites model and toggle endpoint | Base44 `useFavorites.js` |
| 4.4 | Favorites page | Base44 `Favorites.jsx` |
| 4.5 | Home dashboard homestead widgets | Base44 `Home.jsx` |

### Phase 5 — Polish & admin

| # | Task | Reuses |
|---|------|--------|
| 5.1 | Admin rooms management tab | Base44 `AllRoomsTab` |
| 5.2 | Admin trade logs tab | Base44 `TradeLogsTab` |
| 5.3 | Extend trade UI (optional restyle) | Base44 `Trade.jsx` |
| 5.4 | Navigation updates (member nav, admin sidebar) | Existing nav partials |
| 5.5 | Notifications for homestead events | `Notifications` facade |

### Dependency graph

```
Phase 0 (Foundation)
    │
    ▼
Phase 1 (Economy) ──────────────────────────┐
    │                                       │
    ▼                                       │
Phase 2 (Sprites)                           │
    │                                       │
    ▼                                       │
Phase 3 (Rooms + Editor) ◄──────────────────┘
    │
    ▼
Phase 4 (Showcase + Favorites)
    │
    ▼
Phase 5 (Admin polish + Trade UI)
```

---

## 10. Risk Register

| Risk | Severity | Mitigation |
|------|----------|------------|
| Room editor complexity | **High** | Port Base44 React components rather than rebuilding in jQuery; allocate most time to Phase 3 |
| Dual frontend stacks (Mix + Vite) | Medium | Scope Vite to room editor only; keep everything else in Blade |
| Item tag system may not fit all homestead behaviors | Medium | Prototype slot activation early in Phase 1; add dedicated columns if tags are insufficient |
| Character model mismatch (masterlist vs homestead) | Medium | Add sprites as extension, not replacement; homestead views filter to user's own characters |
| No existing API layer | Medium | Build minimal JSON API scoped to room editor; avoid general-purpose API scope creep |
| Laravel 8 EOL | Low (near-term) | Does not block migration; plan upgrade separately |
| Base44 trade logic is incomplete | None | Lorekeeper `TradeManager` is strictly better; do not port Base44 trade logic |
| JSON `placed_items` column size | Low | Monitor payload size; normalize to child table if rooms become very large |

---

## 11. Out of Scope

The following Base44 features are explicitly **not** being migrated:

| Feature | Reason |
|---------|--------|
| Base44 BaaS SDK (`@base44/sdk`) | Replaced by Laravel backend |
| Google OAuth login | Lorekeeper uses social alias linking instead (add only if required) |
| OTP registration flow | Laravel email verification covers this |
| Stripe / player wallet top-up | No payment integration planned; admin grants coins |
| Base44 real-time subscriptions | Optional enhancement; not required for MVP |
| Base44 `User` role enum | Lorekeeper rank/power system is strictly better |
| Replacing Lorekeeper auth | Session auth with alias gate is intentional |
| Replacing masterlist character system | Homestead sprites extend, not replace, masterlist |
| Submissions, galleries, raffles | Lorekeeper-only features; preserved as-is |
| Full SPA rewrite | Unnecessary scope; hybrid approach recommended |

---

## Quick Reference: Decision Summary

| Category | Decision |
|----------|----------|
| **Authentication** | Keep Laravel |
| **User profiles** | Keep Laravel |
| **Characters** | Extend with sprites |
| **Inventory** | Extend item tags and categories |
| **Shop** | Reuse ShopManager |
| **Wallet** | Reuse CurrencyManager (one "coin" currency) |
| **Rooms** | New development |
| **Room editor** | Port Base44 React (hybrid) |
| **Showcase** | New development |
| **Favorites** | New table (keep bookmarks) |
| **Trading** | Keep TradeManager (already complete) |
| **Admin** | Extend existing panel |
| **Frontend** | Blade + jQuery for CRUD; React island for editor |
| **API** | Minimal JSON API for room editor only |
