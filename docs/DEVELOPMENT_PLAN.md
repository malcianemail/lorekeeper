# Development Plan — Malcion Homesteads (Base44)

> Based on [PROJECT_ANALYSIS.md](./PROJECT_ANALYSIS.md). This document breaks the project into development modules, ordered for incremental build-out. **No code is included** — this is a planning reference only.

---

## Overview

The app is a player-driven homestead decorator built on Base44 BaaS. Development should proceed **bottom-up**: platform wiring → auth → economy → catalog → characters → rooms → editor → social features → admin tooling.

Each module below is self-contained enough to implement and test before moving to the next. Modules marked **(parallel)** can be built alongside another module once its hard dependencies are met.

### Recommended Build Order (Summary)

| Phase | Order | Module | Rationale |
|-------|-------|--------|-----------|
| 0 | 1 | Platform Foundation | Nothing works without SDK, Vite, env, and query client |
| 0 | 2 | Design System (UI Primitives) | Shared components used by every screen **(parallel with 1)** |
| 1 | 3 | Authentication & User | All player data is user-scoped |
| 1 | 4 | App Shell, Routing & Layout | Navigation frame for all features |
| 2 | 5 | Entity Schemas (Base44) | Backend tables must exist before feature APIs |
| 2 | 6 | Store Catalog (Admin) | Items must exist before store, inventory, or room placement |
| 3 | 7 | Wallet & Transactions | Purchases require a coin ledger |
| 3 | 8 | Player Inventory | Bridge between store purchases and room editor |
| 3 | 9 | Player Store | Spend coins → gain inventory |
| 4 | 10 | Characters & Sprites | Room editor places characters; mods assign them |
| 4 | 11 | Room Slots & Management | Create/list/delete RoomSave before editing |
| 5 | 12 | Room Editor & Canvas | Core gameplay — depends on inventory + characters + RoomSave |
| 5 | 13 | Room Preview | Read-only renderer for downstream social modules **(parallel with 12)** |
| 6 | 14 | Featured Showcase | Moderator curation + public gallery |
| 6 | 15 | Favorites | Bookmarks on showcase content |
| 7 | 16 | Trading | Requires stable inventory + character ownership |
| 7 | 17 | Home Dashboard | Aggregates completed modules; build last or incrementally |
| 8 | 18 | Admin Mod Panel | Shell that wires admin tabs; tabs built with their features |

### Module Dependency Graph

```
[1 Foundation] ──► [3 Auth] ──► [4 Shell]
       │                              │
       ▼                              ▼
[2 UI Primitives] ────────────► (all feature modules)
       │
[5 Entity Schemas] ──► [6 Store Catalog Admin]
                              │
                    ┌─────────┴─────────┐
                    ▼                   ▼
              [7 Wallet]           [10 Characters]
                    │                   │
                    ▼                   │
              [8 Inventory] ◄───────────┤
                    │                   │
                    ▼                   │
              [9 Player Store]          │
                    │                   │
                    └─────────┬─────────┘
                              ▼
                    [11 Room Management]
                              │
                    ┌─────────┴─────────┐
                    ▼                   ▼
            [12 Room Editor]      [13 Room Preview]
                    │                   │
                    └─────────┬─────────┘
                              ▼
              [14 Featured] ──► [15 Favorites]
                              │
                              ▼
                        [16 Trading]
                              │
                              ▼
                    [17 Home] + [18 Mod Panel]
```

---

## Module 1 — Platform Foundation

**Build order: 1**

### Purpose
Bootstrap the React application, connect to Base44, configure build tooling, and establish shared infrastructure (API client, query client, path aliases, global styles).

### Files Involved

| Category | Paths |
|----------|-------|
| Entry | `index.html`, `src/main.jsx`, `src/App.jsx` |
| API client | `src/api/base44Client.js` |
| Config | `src/lib/app-params.js`, `src/lib/query-client.js`, `vite.config.js`, `jsconfig.json` |
| Base44 app | `base44/.app.jsonc`, `base44/config.jsonc` |
| Styles | `src/index.css`, `tailwind.config.js`, `postcss.config.js`, `components.json` |
| Root | `package.json`, `package-lock.json`, `.gitignore`, `eslint.config.js` |

### Database Tables
None directly — app ID and entity schemas are configured separately (Module 5).

### APIs
| API | Purpose |
|-----|---------|
| `createClient()` from `@base44/sdk` | Singleton `base44` client |
| `@base44/vite-plugin` | Dev proxy, HMR, analytics hooks |

### Components
None (infrastructure only).

### Dependencies
- **External:** Node.js, npm, Base44 app credentials (`VITE_BASE44_APP_ID`, `VITE_BASE44_APP_BASE_URL`)
- **Internal:** None (first module)

### Deliverables / Done When
- `npm run dev` starts without errors
- `base44` client resolves app ID and token from env/URL
- TanStack Query provider wraps the app
- Tailwind and path alias `@/` work

---

## Module 2 — Design System (UI Primitives)

**Build order: 2 (parallel with Module 1)**

### Purpose
Provide consistent, accessible UI building blocks (shadcn/ui + Radix) used across all pages and feature components.

### Files Involved

| Category | Paths |
|----------|-------|
| UI primitives | `src/components/ui/*` (~40 components) |
| Utilities | `src/lib/utils.js` (`cn()` helper) |
| Toasts | `src/components/ui/toaster.jsx`, `src/components/ui/sonner.jsx`, `src/components/ui/use-toast.jsx` |

**Heavily used primitives:** `button`, `card`, `dialog`, `input`, `label`, `select`, `tabs`, `badge`, `scroll-area`, `sheet`, `textarea`, `avatar`, `separator`, `skeleton`, `alert`, `alert-dialog`, `dropdown-menu`, `form`, `checkbox`, `switch`, `table`, `tooltip`, `popover`

### Database Tables
None.

### APIs
None.

### Components
All files under `src/components/ui/`.

### Dependencies
- **External:** Radix UI packages, `class-variance-authority`, `clsx`, `tailwind-merge`, `lucide-react`
- **Internal:** Module 1 (Tailwind config)

### Deliverables / Done When
- Core primitives render correctly in isolation
- Toast/sonner notifications work app-wide

---

## Module 3 — Authentication & User Management

**Build order: 3**

### Purpose
Handle user registration, login (email + Google), password reset, session state, route protection, and registration error states. Establish the `User` entity role for later admin gating.

### Files Involved

| Category | Paths |
|----------|-------|
| Context | `src/lib/AuthContext.jsx` |
| Auth pages | `src/pages/Login.jsx`, `src/pages/Register.jsx`, `src/pages/ForgotPassword.jsx`, `src/pages/ResetPassword.jsx` |
| Guards | `src/components/ProtectedRoute.jsx`, `src/components/UserNotRegisteredError.jsx` |
| Layout | `src/components/AuthLayout.jsx`, `src/components/GoogleIcon.jsx` |
| Schema | `base44/entities/User.jsonc` |
| Routing | Auth routes in `src/App.jsx` |

### Database Tables

| Table | Role in Module |
|-------|----------------|
| **User** | `role` field (`admin` \| `user`) for Mod Panel access; extends Base44 auth user |

*Base44 auth users also carry `email`, `full_name`, etc. via `base44.auth.me()` — not stored in the local schema file.*

### APIs

| API | Used For |
|-----|----------|
| `base44.auth.me()` | Session check, load current user |
| `base44.auth.loginViaEmailPassword()` | Email login |
| `base44.auth.register()` | Sign up |
| `base44.auth.verifyOtp()` | OTP confirmation |
| `base44.auth.resendOtp()` | Resend verification |
| `base44.auth.loginWithProvider("google")` | OAuth |
| `base44.auth.logout()` | Sign out |
| `base44.auth.redirectToLogin()` | Redirect unauthenticated users |
| `base44.auth.resetPasswordRequest()` | Forgot password |
| `base44.auth.resetPassword()` | Reset with token |
| `base44.auth.setToken()` | Store token after OTP |
| `GET /api/apps/public/prod/public-settings/by-id/{appId}` | App-level auth requirements |

| Entity API | Used For |
|------------|----------|
| `User.list()` | Admin player lists (later modules) |

### Components

| Component | Role |
|-----------|------|
| `AuthProvider` / `useAuth` | Global auth state |
| `ProtectedRoute` | Gate authenticated routes |
| `UserNotRegisteredError` | Unregistered user UX |
| `AuthLayout` | Login/register page wrapper |
| `GoogleIcon` | OAuth button icon |

### Dependencies
- **Internal:** Module 1 (base44 client, app params)
- **Internal:** Module 2 (form inputs, buttons, cards)

### Deliverables / Done When
- Users can register, verify OTP, and log in
- Protected routes redirect to `/login` when unauthenticated
- `user.role` available for admin checks
- Google OAuth flow works

---

## Module 4 — App Shell, Routing & Layout

**Build order: 4**

### Purpose
Define the application route map, authenticated layout (sidebar + mobile nav), 404 handling, and scroll behavior. Provides the navigation frame for all feature pages.

### Files Involved

| Category | Paths |
|----------|-------|
| Router | `src/App.jsx` |
| Layout | `src/components/layout/AppLayout.jsx`, `Sidebar.jsx`, `MobileNav.jsx` |
| Misc | `src/lib/PageNotFound.jsx`, `src/components/ScrollToTop.jsx` |

### Database Tables
None.

### APIs
None (uses `useAuth` for admin nav link visibility).

### Components

| Component | Role |
|-----------|------|
| `AppLayout` | Sidebar + `<Outlet />` + mobile nav |
| `Sidebar` | Desktop navigation, Mod Panel link for admins |
| `MobileNav` | Bottom tab bar on small screens |
| `PageNotFound` | 404 page |
| `ScrollToTop` | Scroll reset on navigation |

### Route Map (target)

| Path | Page Module |
|------|-------------|
| `/` | Home (Module 17) |
| `/characters` | Characters (Module 10) |
| `/rooms` | Rooms (Module 11) |
| `/rooms/:id/edit` | Room Editor (Module 12) — full-screen, outside AppLayout |
| `/showcase` | Showcase (Module 14) |
| `/store` | Store (Module 9) |
| `/wallet` | Wallet (Module 7) |
| `/inventory` | Inventory (Module 8) |
| `/trade` | Trade (Module 16) |
| `/favorites` | Favorites (Module 15) |
| `/mod` | Mod Panel (Module 18) |
| `/login`, `/register`, etc. | Auth (Module 3) |

### Dependencies
- **Internal:** Module 1, Module 3
- **Internal:** Module 2 (nav styling)

### Deliverables / Done When
- All routes resolve (placeholder pages acceptable initially)
- Sidebar and mobile nav highlight active route
- Room editor renders full-screen without sidebar
- Admin sees Mod Panel link when `role === "admin"`

---

## Module 5 — Entity Schemas (Base44)

**Build order: 5**

### Purpose
Define and sync all backend entity schemas to the Base44 platform. This module is a **prerequisite gate** — feature modules should not ship until their entities are published.

### Files Involved

| Entity | Schema File |
|--------|-------------|
| User | `base44/entities/User.jsonc` |
| RoomSave | `base44/entities/RoomSave.jsonc` |
| StoreItem | `base44/entities/StoreItem.jsonc` |
| UserInventory | `base44/entities/UserInventory.jsonc` |
| Character | `base44/entities/Character.jsonc` |
| Featured | `base44/entities/Featured.jsonc` |
| Favorite | `base44/entities/Favorite.jsonc` |
| WalletTransaction | `base44/entities/WalletTransaction.jsonc` |
| Trade | `base44/entities/Trade.jsonc` |

### Database Tables
All nine entities above. Publish via Base44 Builder after local schema edits.

### APIs
Entity CRUD becomes available on the platform after publish:
`list`, `filter`, `create`, `update`, `delete`, `subscribe`

### Components
None.

### Dependencies
- **Internal:** Module 1 (app ID configured)
- **External:** Base44 Builder access for publish/sync

### Deliverables / Done When
- All entity schemas published to Base44
- CRUD smoke tests pass for each entity via SDK
- Row-level user scoping behaves as expected

### Schema Rollout Order (within this module)

1. `User`
2. `StoreItem`
3. `WalletTransaction`
4. `UserInventory`
5. `Character`
6. `RoomSave`
7. `Featured`
8. `Favorite`
9. `Trade`

---

## Module 6 — Store Catalog (Admin)

**Build order: 6**

### Purpose
Allow moderators to create, edit, and delete store items (furniture, styles, slot upgrades, limited items). Seeds the catalog required by the player store, inventory, and room editor.

### Files Involved

| Category | Paths |
|----------|-------|
| Admin tab | `src/components/admin/StoreManagerTab.jsx` |
| Parent shell | `src/pages/admin/ModPanel.jsx` (tab wiring) |
| Schema | `base44/entities/StoreItem.jsonc` |

### Database Tables

| Table | Fields Used |
|-------|-------------|
| **StoreItem** | `name`, `description`, `image_url`, `price`, `category`, `placement_type`, `room_type`, `is_limited`, `limited_until`, `default_width`, `default_height` |

### APIs

| API | Operations |
|-----|------------|
| `base44.entities.StoreItem` | `list`, `create`, `update`, `delete` |
| `base44.integrations.Core.UploadFile` | Item image upload |

### Components

| Component | Role |
|-----------|------|
| `StoreManagerTab` | Full CRUD form + item grid |
| `ModPanel` | Tab container (partial — other tabs added later) |

### Dependencies
- **Internal:** Module 3 (admin role), Module 5 (`StoreItem` schema)
- **Internal:** Module 2 (forms, dialogs, tables)

### Deliverables / Done When
- Admin can create items in all categories (furniture, wallpaper, slots, etc.)
- Item images upload and display
- At least a starter catalog exists for testing downstream modules

---

## Module 7 — Wallet & Transactions

**Build order: 7**

### Purpose
Implement the coin economy ledger. Players view balance and transaction history. Admins can grant or deduct coins. Store purchases debit this ledger.

### Files Involved

| Category | Paths |
|----------|-------|
| Hook | `src/hooks/useWallet.js` |
| Player page | `src/pages/Wallet.jsx` |
| Admin tab | `src/components/admin/CoinsManagerTab.jsx` |
| Schema | `base44/entities/WalletTransaction.jsonc` |

### Database Tables

| Table | Role |
|-------|------|
| **WalletTransaction** | Ledger entries; balance = `SUM(amount)` |

*No separate Wallet table.*

### APIs

| API | Operations |
|-----|------------|
| `base44.entities.WalletTransaction` | `list`, `filter`, `create`, `subscribe` |
| `base44.auth.me()` | Resolve user email for mod-transaction filter |

| Transaction Types | When |
|-------------------|------|
| `purchase` | Store buy (negative amount) |
| `credit` | Manual add funds (if exposed) |
| `refund` | Refunds (schema support) |
| `mod_adjustment` | Admin give/deduct with `target_user_email` |

### Components

| Component | Role |
|-----------|------|
| `useWallet` | Balance computation, spend/addFunds mutations, real-time subscribe |
| `Wallet` page | Balance card + history list |
| `CoinsManagerTab` | Per-player coin adjustments |

### Dependencies
- **Internal:** Module 3, Module 5 (`WalletTransaction` schema)
- **Internal:** Module 2 (cards, badges)

### Deliverables / Done When
- Balance displays correctly from transaction sum
- Admin can give/deduct coins to any user
- `subscribe` refreshes balance on new transactions
- `spend` mutation validates sufficient funds

---

## Module 8 — Player Inventory

**Build order: 8**

### Purpose
Track owned store items per player, including quantity and slot activation state. Provides `useInventory` hook used by store, rooms, room editor, and trading.

### Files Involved

| Category | Paths |
|----------|-------|
| Hook | `src/hooks/useInventory.js` |
| Player page | `src/pages/Inventory.jsx` |
| Admin tab | `src/components/admin/InventoryManagerTab.jsx` |
| Schema | `base44/entities/UserInventory.jsonc` |

### Database Tables

| Table | Role |
|-------|------|
| **UserInventory** | `item_id` → StoreItem, `quantity`, `activated_quantity`, `purchased_at` |
| **StoreItem** | Joined for display metadata |

### APIs

| API | Operations |
|-----|------------|
| `base44.entities.UserInventory` | `list`, `create`, `update`, `delete`, `filter` |
| `base44.entities.StoreItem` | `list` (join for item details) |
| `base44.auth.me()` | Scope inventory to current user |

### Components

| Component | Role |
|-----------|------|
| `useInventory` | Inventory list, slot limits, `addItem`, `activateSlot`, `ownsItem`, `getQuantity` |
| `Inventory` page | Grid of owned items + characters tab; slot activation UI |
| `InventoryManagerTab` | Admin: add/remove items for any user |

### Key Logic (implement here)

```
maxIndoorRooms  = 1 + activated room_slot count
maxOutdoorRooms = 1 + activated house_slot count
unusedSlots     = quantity - activated_quantity (tradeable)
```

### Dependencies
- **Internal:** Module 5 (`UserInventory`), Module 6 (`StoreItem` catalog exists)
- **Internal:** Module 7 (optional for admin testing with coins)

### Deliverables / Done When
- `addItem` increments quantity or creates new row
- `activateSlot` consumes one unused slot item permanently
- Slot limit calculations are correct
- Inventory page shows items with unused/active slot badges

---

## Module 9 — Player Store

**Build order: 9**

### Purpose
Browse and purchase store items with coins. Purchases debit the wallet and credit inventory atomically (client-side sequence).

### Files Involved

| Category | Paths |
|----------|-------|
| Page | `src/pages/Store.jsx` |
| Component | `src/components/store/StoreItemCard.jsx` |
| Hooks | `src/hooks/useWallet.js`, `src/hooks/useInventory.js` |

### Database Tables

| Table | Role |
|-------|------|
| **StoreItem** | Catalog listing |
| **WalletTransaction** | Purchase debit |
| **UserInventory** | Item granted on buy |

### APIs

| API | Operations |
|-----|------------|
| `base44.entities.StoreItem` | `list("price")` |
| `useWallet.spend` | `WalletTransaction.create` (negative) |
| `useInventory.addItem` | `UserInventory.create/update` |

### Components

| Component | Role |
|-----------|------|
| `Store` | Category tabs, filtered grid, buy handler |
| `StoreItemCard` | Item card with price, owned badge, buy button |

### Dependencies
- **Internal:** Module 6 (catalog populated), Module 7 (wallet), Module 8 (inventory)
- **Internal:** Module 4 (route `/store`)

### Deliverables / Done When
- Category filtering works (all, furniture, slots, limited, etc.)
- Insufficient funds shows error toast
- Successful purchase updates balance and inventory
- Owned quantity badge displays on cards

---

## Module 10 — Characters & Sprites

**Build order: 10**

### Purpose
Moderators create and assign characters to players. Players view their characters, edit bio, upload sprites, and set active pose. Characters appear in the room editor and trading.

### Files Involved

| Category | Paths |
|----------|-------|
| Player page | `src/pages/Characters.jsx` |
| Components | `src/components/characters/CharacterCard.jsx`, `CharacterForm.jsx`, `SpriteManager.jsx` |
| Admin tab | `src/components/admin/AllCharactersTab.jsx` |
| Schema | `base44/entities/Character.jsonc` |

### Database Tables

| Table | Role |
|-------|------|
| **Character** | `name`, `description`, `thumbnail_url`, `owner_email`, `sprites[]`, `max_sprite_slots`, `active_sprite_index` |
| **User** | Listed for character assignment (admin) |

### APIs

| API | Operations |
|-----|------------|
| `base44.entities.Character` | `list`, `filter`, `create`, `update`, `delete` |
| `base44.entities.User` | `list` (admin assignment picker) |
| `base44.integrations.Core.UploadFile` | Thumbnail and sprite image upload |
| `base44.auth.me()` | Filter characters by `owner_email` |

### Components

| Component | Role |
|-----------|------|
| `Characters` | Grid + detail dialog with edit mode |
| `CharacterCard` | Grid thumbnail card |
| `CharacterForm` | Create/edit form (admin) |
| `SpriteManager` | Upload, delete, set active sprite; enforces `max_sprite_slots` |
| `AllCharactersTab` | Admin CRUD, reassign owner, feature shortcut |

### Dependencies
- **Internal:** Module 3 (auth), Module 5 (`Character` schema)
- **Internal:** Module 6 (optional: `sprite_slot` store items for future slot upgrades)
- **Internal:** Module 14 (optional: `AddToFeaturedDialog` from admin tab)

### Deliverables / Done When
- Admin can create character and assign to player email
- Player sees only own characters
- Sprite upload respects slot limit
- Active sprite index persists on character

---

## Module 11 — Room Slots & Management

**Build order: 11**

### Purpose
Create, list, and delete room/house save slots. Enforce indoor/outdoor slot limits from activated inventory slots. Entry point to the room editor.

### Files Involved

| Category | Paths |
|----------|-------|
| Page | `src/pages/Rooms.jsx` |
| Admin tab | `src/components/admin/AllRoomsTab.jsx` |
| Schema | `base44/entities/RoomSave.jsonc` |
| Hook | `src/hooks/useInventory.js` (slot limits) |

### Database Tables

| Table | Role |
|-------|------|
| **RoomSave** | `name`, `room_type`, `placed_items` (initially `[]`) |
| **UserInventory** | Slot activation counts |

### APIs

| API | Operations |
|-----|------------|
| `base44.entities.RoomSave` | `list`, `create`, `delete` |
| `useInventory.activateSlot` | Unlock extra room/house slots |

### Components

| Component | Role |
|-----------|------|
| `Rooms` | Indoor/outdoor grids, create dialog, slot activation, delete, edit link |
| `AllRoomsTab` | Admin browse/delete/feature rooms |
| `AddToFeaturedDialog` | Triggered from admin rooms tab (Module 14) |

### Dependencies
- **Internal:** Module 8 (slot limit logic), Module 5 (`RoomSave` schema)
- **Internal:** Module 4 (routes `/rooms`, `/rooms/:id/edit`)

### Deliverables / Done When
- Player can create indoor/outdoor rooms within slot limits
- Empty slot cards and "get more slots" link to store work
- Unused slot activation consumes inventory item
- Delete removes RoomSave record
- Edit navigates to room editor

---

## Module 12 — Room Editor & Canvas

**Build order: 12**

### Purpose
Core gameplay: decorate rooms/houses by placing furniture, applying styles, and positioning character sprites on a 700×500 canvas. Save/load state to `RoomSave`.

### Files Involved

| Category | Paths |
|----------|-------|
| Page | `src/pages/RoomEditor.jsx` |
| Components | `src/components/room-editor/RoomCanvas.jsx`, `EditorToolbar.jsx`, `InventoryPanel.jsx`, `CharacterPicker.jsx` |
| Hooks | `src/hooks/useInventory.js` |
| Schemas | `base44/entities/RoomSave.jsonc`, `StoreItem.jsonc`, `Character.jsonc` |

### Database Tables

| Table | Role |
|-------|------|
| **RoomSave** | Full save payload: `placed_items`, style IDs, `character_id`, `sprite_position` |
| **StoreItem** | Item images, sizes, categories, placement types |
| **UserInventory** | Ownership + placement availability |
| **Character** | Sprite URL via `sprites[active_sprite_index]` |

### APIs

| API | Operations |
|-----|------------|
| `base44.entities.RoomSave` | `filter({ id })`, `update` |
| `base44.entities.StoreItem` | `list` |
| `base44.entities.Character` | `list` (character picker) |
| `useInventory` | `inventory` for owned items |

### Components

| Component | Role |
|-----------|------|
| `RoomEditor` | State orchestration, save handler, layout |
| `RoomCanvas` | Scaled 700×500 canvas, drag/drop, z-order, sprite drag |
| `EditorToolbar` | Back, save, unsaved indicator |
| `InventoryPanel` | Items tab (draggable) + Styles tab (clickable) |
| `CharacterPicker` | Select character with sprites |

### Key Logic

| Concern | Behavior |
|---------|----------|
| Draggable placement | Append to `placed_items` with x, y, width, height, z_index |
| Clickable styles | Set `wallpaper_item_id`, `flooring_item_id`, `roof_item_id`, or `exterior_wall_item_id` |
| Availability | `quantity - placed count` per `item_id` |
| Indoor canvas | Starter room PNG + wallpaper/flooring overlays |
| Outdoor canvas | Sky/ground gradients + roof/wall overlays |
| Save | `RoomSave.update` with all mutable fields |

### Dependencies
- **Internal:** Module 8 (inventory), Module 10 (characters/sprites), Module 11 (RoomSave exists)
- **Internal:** Module 6 (store items with images)
- **Internal:** Module 2 (`sheet` for mobile inventory panel)

### Deliverables / Done When
- Room loads saved state correctly
- Furniture drags, deletes, and z-orders
- Styles apply as background overlays
- Character sprite drags independently
- Save persists and invalidates queries
- Mobile bottom sheet works for inventory

---

## Module 13 — Room Preview

**Build order: 13 (parallel with Module 12)**

### Purpose
Read-only renderer that mirrors the room editor canvas at any scale. Used in showcase, favorites, and detail modals without edit controls.

### Files Involved

| Category | Paths |
|----------|-------|
| Component | `src/components/room-editor/RoomPreview.jsx` |

### Database Tables
Reads the same data as Module 12 (no writes).

### APIs
None directly — receives `room`, `storeItems`, `character` as props.

### Components

| Component | Role |
|-----------|------|
| `RoomPreview` | Scaled static canvas; indoor/outdoor layouts match `RoomCanvas` |

### Dependencies
- **Internal:** Module 12 (must keep coordinate system and layout in sync)
- **Shared constants:** `EDITOR_WIDTH = 700`, `EDITOR_HEIGHT = 500`

### Deliverables / Done When
- Preview matches editor rendering for same room data
- ResizeObserver scales canvas to container
- No pointer interaction

### Maintenance Note
Any visual change to `RoomCanvas` indoor/outdoor layouts **must** be mirrored in `RoomPreview`.

---

## Module 14 — Featured Showcase

**Build order: 14**

### Purpose
Moderators curate featured rooms and characters. Public showcase page displays active entries with previews, owner info, and favorite buttons.

### Files Involved

| Category | Paths |
|----------|-------|
| Player page | `src/pages/Showcase.jsx` |
| Admin tab | `src/components/admin/FeaturedManagerTab.jsx` |
| Dialog | `src/components/admin/AddToFeaturedDialog.jsx` |
| Preview | `src/components/room-editor/RoomPreview.jsx` |
| Schema | `base44/entities/Featured.jsonc` |

### Database Tables

| Table | Role |
|-------|------|
| **Featured** | `type`, `ref_id`, `owner_email`, `owner_username`, `note`, `active`, `featured_order` |
| **RoomSave** | Resolved via `ref_id` when `type === "room"` |
| **Character** | Resolved via `ref_id` when `type === "character"` |
| **StoreItem** | For room preview item images |

### APIs

| API | Operations |
|-----|------------|
| `base44.entities.Featured` | `list`, `filter`, `create`, `update`, `delete` |
| `base44.entities.RoomSave` | `list` (join, limit 200) |
| `base44.entities.Character` | `list` (join, limit 200) |
| `base44.entities.StoreItem` | `list` (preview) |

### Components

| Component | Role |
|-----------|------|
| `Showcase` | Public gallery with room/character tabs, detail modals |
| `FeaturedManagerTab` | Toggle active, delete, reorder featured entries |
| `AddToFeaturedDialog` | Create featured entry from admin rooms/characters tabs |
| `RoomPreview` | Room thumbnails |
| `FavoriteButton` | Heart on cards (Module 15) |

### Dependencies
- **Internal:** Module 11/12/13 (rooms exist, preview works)
- **Internal:** Module 10 (characters exist)
- **Internal:** Module 3 (admin for curation)
- **Internal:** Module 15 (`FavoriteButton` — can stub until Module 15 ships)

### Deliverables / Done When
- Admin can feature a room or character with note and display order
- Showcase lists active entries sorted by `featured_order`
- Detail modals show full preview and owner info
- Deactivated entries hidden from public view

---

## Module 15 — Favorites

**Build order: 15**

### Purpose
Let players bookmark rooms and characters from the showcase (and elsewhere). Display saved favorites in a dedicated page.

### Files Involved

| Category | Paths |
|----------|-------|
| Hook | `src/hooks/useFavorites.js` |
| Page | `src/pages/Favorites.jsx` |
| Component | `src/components/FavoriteButton.jsx` |
| Preview | `src/components/room-editor/RoomPreview.jsx` |
| Schema | `base44/entities/Favorite.jsonc` |

### Database Tables

| Table | Role |
|-------|------|
| **Favorite** | `ref_type` (`room` \| `character`), `ref_id` |
| **RoomSave** | Joined for favorite rooms |
| **Character** | Joined for favorite characters |
| **StoreItem** | Room preview images |

### APIs

| API | Operations |
|-----|------------|
| `base44.entities.Favorite` | `list`, `create`, `delete` |

### Components

| Component | Role |
|-----------|------|
| `useFavorites` | `isFavorited`, `toggleFavorite` mutation |
| `FavoriteButton` | Heart toggle UI |
| `Favorites` | Tabbed grid of favorited rooms/characters + modals |

### Dependencies
- **Internal:** Module 13 (`RoomPreview`)
- **Internal:** Module 14 (primary UX surface for favoriting)
- **Internal:** Module 5 (`Favorite` schema)

### Deliverables / Done When
- Toggle adds/removes favorite per user
- Favorites page resolves refs to full room/character data
- Heart state reflects correctly on showcase cards

---

## Module 16 — Trading

**Build order: 16**

### Purpose
Players create trade offers (items + characters), send to another player by email, and accept/decline/cancel pending trades. Admin can view trade logs.

### Files Involved

| Category | Paths |
|----------|-------|
| Page | `src/pages/Trade.jsx` |
| Components | `src/components/trade/CreateTradeDialog.jsx`, `TradeOfferCard.jsx` |
| Admin tab | `src/components/admin/TradeLogsTab.jsx` |
| Hooks | `src/hooks/useInventory.js` |
| Schema | `base44/entities/Trade.jsonc` |

### Database Tables

| Table | Role |
|-------|------|
| **Trade** | Full offer payload and status |
| **UserInventory** | Offered items reference `inventory_id` |
| **Character** | Offered/requested character IDs |
| **StoreItem** | Item display metadata |
| **User** | `from_user_email`, `to_user_email` |

### APIs

| API | Operations |
|-----|------------|
| `base44.entities.Trade` | `list`, `create`, `update` |
| `base44.entities.Character` | `list`, `filter` (owned vs other) |
| `base44.entities.StoreItem` | `list` |
| `base44.auth.me()` | Current user for filtering trades |

### Components

| Component | Role |
|-----------|------|
| `Trade` | Incoming/outgoing/history tabs |
| `CreateTradeDialog` | Build offer: items + characters offered; characters requested |
| `TradeOfferCard` | Display trade details + action buttons |
| `TradeLogsTab` | Admin read-only log |

### Known Gap (plan for future work)
Accepting a trade currently **only updates `status`** — it does not transfer inventory quantities or reassign `Character.owner_email`. A complete implementation needs server-side transfer logic (Base44 function or transactional client workflow).

### Dependencies
- **Internal:** Module 8 (inventory), Module 10 (characters)
- **Internal:** Module 3 (auth email identity)
- **Internal:** Module 5 (`Trade` schema)

### Deliverables / Done When
- Player can send trade offer to email address
- Incoming/outgoing/history views filter correctly
- Accept/decline/cancel update status
- Admin can view all trades
- *(Stretch)* Asset transfer on accept

---

## Module 17 — Home Dashboard

**Build order: 17**

### Purpose
Landing page after login. Aggregates wallet balance, room count, character count, recent rooms, featured preview, and quick navigation links.

### Files Involved

| Category | Paths |
|----------|-------|
| Page | `src/pages/Home.jsx` |
| Hooks | `src/hooks/useWallet.js` |

### Database Tables (read-only aggregates)

| Table | Usage |
|-------|-------|
| **Character** | Count + preview |
| **RoomSave** | Recent rooms list |
| **Featured** | Featured preview cards |
| **WalletTransaction** | Balance via hook |

### APIs

| API | Operations |
|-----|------------|
| `base44.entities.Character` | `list` |
| `base44.entities.RoomSave` | `list("-updated_date")` |
| `base44.entities.Featured` | `filter({ active: true })` |
| `useWallet` | `balance` |

### Components
Uses Module 2 cards, badges, buttons only — no new domain components.

### Dependencies
- **Internal:** Module 7 (wallet), Module 11 (rooms), Module 10 (characters), Module 14 (featured)
- **Internal:** Module 4 (route `/`)

### Deliverables / Done When
- Hero section and quick-link cards render
- Recent rooms section links to editor
- Featured preview links to showcase
- Limited items banner links to store

---

## Module 18 — Admin Mod Panel

**Build order: 18 (shell early; tabs incrementally with each feature)**

### Purpose
Unified admin interface consolidating all moderator tools: featured management, room/character oversight, coin adjustments, store catalog, inventory grants, and trade logs.

### Files Involved

| Category | Paths |
|----------|-------|
| Page | `src/pages/admin/ModPanel.jsx` |
| Tabs | `src/components/admin/FeaturedManagerTab.jsx`, `AllRoomsTab.jsx`, `AllCharactersTab.jsx`, `CoinsManagerTab.jsx`, `StoreManagerTab.jsx`, `InventoryManagerTab.jsx`, `TradeLogsTab.jsx` |
| Dialog | `src/components/admin/AddToFeaturedDialog.jsx` |

### Database Tables
All entities — admin has cross-cutting read/write access.

### APIs
Aggregates all entity and integration APIs from Modules 6–16.

### Components

| Tab | Module Origin |
|-----|---------------|
| Featured Showcase | Module 14 |
| All Rooms | Module 11 |
| All Characters | Module 10 |
| Coins | Module 7 |
| Store | Module 6 |
| Inventory | Module 8 |
| Trade Logs | Module 16 |

| Component | Role |
|-----------|------|
| `ModPanel` | Admin gate (`user.role === "admin"`), tab shell |

### Dependencies
- **Internal:** Module 3 (admin role check)
- **Internal:** All feature admin tabs (build tab when its feature module ships)

### Deliverables / Done When
- Non-admins see access denied
- All seven tabs functional
- Tab navigation persists default to Featured

### Recommended Approach
Create `ModPanel` shell in Module 4 with admin gate, then add each tab as its feature module completes rather than waiting until Module 18.

---

## Cross-Module Concerns

### Testing Checklist Per Module

| Module | Minimum Test |
|--------|--------------|
| Auth | Login/logout cycle, protected route redirect |
| Wallet | Balance math, insufficient funds rejection |
| Store | End-to-end purchase |
| Inventory | Slot activation irreversibility |
| Characters | Sprite upload limit |
| Rooms | Slot limit enforcement |
| Room Editor | Save/reload round-trip |
| Featured | Active/inactive visibility |
| Favorites | Toggle idempotency |
| Trading | Status transitions |

### Shared Hooks Reference

| Hook | Modules Using It |
|------|------------------|
| `useInventory` | Store, Rooms, RoomEditor, Inventory, Trade |
| `useWallet` | Store, Wallet, Home |
| `useFavorites` | FavoriteButton, Favorites |
| `useAuth` | ProtectedRoute, Sidebar, ModPanel |

### Integration API (shared)

| API | Modules |
|-----|---------|
| `base44.integrations.Core.UploadFile` | Characters, Sprites, Admin Store, Admin Characters |

---

## Suggested Sprint Mapping

For a team building from scratch, a practical sprint breakdown:

| Sprint | Modules | Goal |
|--------|---------|------|
| Sprint 1 | 1, 2, 3, 4, 5 | Runnable app with auth and empty shell |
| Sprint 2 | 6, 7, 8, 9 | Economy loop: seed catalog → earn coins → buy items |
| Sprint 3 | 10, 11 | Characters assigned; rooms creatable |
| Sprint 4 | 12, 13 | Room decoration gameplay works |
| Sprint 5 | 14, 15, 17 | Social discovery: showcase, favorites, home |
| Sprint 6 | 16, 18 | Trading + admin consolidation + trade execution fix |

---

## Related Documents

- [PROJECT_ANALYSIS.md](./PROJECT_ANALYSIS.md) — Full architecture reference
- [README.md](./README.md) — Local setup and environment variables

---

*Planning document only. No application code was created or modified.*
