# Laravel Project Analysis — Lorekeeper

> **Project path:** `/var/www/html/mini-game/lorekeeper`  
> **Framework:** Lorekeeper v2 (Laravel 8 ARPG platform)  
> **Purpose of this document:** Baseline architecture analysis before migrating Base44 "Malcion Homesteads" features into this codebase.

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Technology Stack](#2-technology-stack)
3. [Architecture Overview](#3-architecture-overview)
4. [Authentication](#4-authentication)
5. [User Module](#5-user-module)
6. [Character Module](#6-character-module)
7. [Inventory System](#7-inventory-system)
8. [Shop System](#8-shop-system)
9. [Admin Panel](#9-admin-panel)
10. [Routes](#10-routes)
11. [Controllers](#11-controllers)
12. [Models](#12-models)
13. [Views & Frontend Stack](#13-views--frontend-stack)
14. [Reusable Components](#14-reusable-components)
15. [Database & Migrations](#15-database--migrations)
16. [API Layer](#16-api-layer)
17. [Additional Modules (Beyond Base44 Scope)](#17-additional-modules-beyond-base44-scope)
18. [Known Issues & Technical Debt](#18-known-issues--technical-debt)

---

## 1. Executive Summary

Lorekeeper is a mature, server-rendered Laravel application for managing deviantART-based ARPGs and closed-species masterlists. It provides:

- User accounts with social alias linking and rank-based staff permissions
- A character masterlist with traits, images, design updates, and ownership tracking
- Multi-currency economy with user/character banks
- Item inventories with tag-driven behaviors (slots, boxes, etc.)
- Shops, secure trades, submissions, galleries, raffles, and a full admin panel

The application is **not** a SPA. Nearly all game logic runs through Blade views, form POSTs, and jQuery-driven modals. Business logic is centralized in **Manager** and **Service** classes under `app/Services/`.

**Base44 gap:** Lorekeeper has no room/homestead editor, no placement-based decoration system, no showcase/favorites for decorated rooms, and no single-coin wallet ledger. Those are the primary new development areas when porting the Base44 reference.

---

## 2. Technology Stack

| Layer | Technology | Version / Notes |
|-------|------------|-----------------|
| **Runtime** | PHP | `~8.1` |
| **Framework** | Laravel | `^8.0` |
| **Auth scaffolding** | `laravel/ui` | `^3.1` — login, register, password reset, email verification |
| **OAuth** | `laravel/socialite` + SocialiteProviders | DeviantArt, Twitter, Instagram, Tumblr, Imgur, Twitch |
| **Forms** | `laravelcollective/html` | `^6.0` |
| **Images** | `intervention/image` | `^2.4` |
| **Markdown** | `erusev/parsedown` | Profile/page content |
| **HTML sanitization** | `ezyang/htmlpurifier` | User-submitted HTML |
| **Spam protection** | `spatie/laravel-honeypot` | `^4.1` |
| **Flash messages** | `laracasts/flash` | `^3.0` |
| **Build tool** | Laravel Mix + Webpack | `^4.0.7` |
| **CSS** | Bootstrap 4 + Sass | `resources/sass/app.scss` |
| **JS runtime** | jQuery 3, Bootstrap 4, axios | Primary interactive layer |
| **JS framework** | Vue 2 | Scaffolded but minimally used |

### Key configuration directories

| Path | Purpose |
|------|---------|
| `config/lorekeeper/powers.php` | Staff permission definitions |
| `config/lorekeeper/settings.php` | Default site settings |
| `config/lorekeeper/admin_sidebar.php` | Admin navigation structure |
| `config/lorekeeper/item_tags.php` | Item behavior tags (slot, box, etc.) |
| `config/lorekeeper/sites.php` | OAuth provider configuration |
| `config/lorekeeper/notifications.php` | In-app notification types |
| `config/lorekeeper/extensions.php` | Site extension hooks |

### Autoloaded helpers

- `app/Helpers/Helpers.php` — `set_active()`, `breadcrumbs()`, formatting
- `app/Helpers/AssetHelpers.php` — asset URL helpers
- `app/Helpers/Settings.php` — DB-backed site settings facade
- `app/Helpers/Notifications.php` — in-app notification facade

---

## 3. Architecture Overview

```
┌─────────────────────────────────────────────────────────────────────┐
│                         Browser (Blade + jQuery)                    │
│   layouts/app.blade.php → module views → widgets (partials)         │
└───────────────────────────────┬─────────────────────────────────────┘
                                │ HTTP (session cookies)
┌───────────────────────────────▼─────────────────────────────────────┐
│                    Laravel 8 (routes/web.php)                       │
│                                                                     │
│  Middleware chain: web → auth → verified → alias → staff → power  │
│                                                                     │
│  Controllers (thin) ──► Services / Managers (business logic)        │
│                              │                                      │
│                              ▼                                      │
│                         Eloquent Models                             │
└───────────────────────────────┬─────────────────────────────────────┘
                                │
┌───────────────────────────────▼─────────────────────────────────────┐
│                         MySQL Database                              │
│   116 migrations, ~50+ tables                                       │
└─────────────────────────────────────────────────────────────────────┘
```

### Request flow by access level

| Level | Middleware | Route file | Who can access |
|-------|------------|------------|----------------|
| Public | `web` | `routes/lorekeeper/browse.php` | Everyone |
| Authenticated | `auth`, `verified` | `routes/web.php` (link, birthday) | Logged-in users |
| Member | `auth`, `verified`, `alias` | `routes/lorekeeper/members.php` | Users with linked alias |
| Staff | above + `staff` | `routes/lorekeeper/admin.php` | Users with rank powers |
| Power-gated | above + `power:{key}` | per-route in admin.php | Specific staff capabilities |

### Design patterns in use

| Pattern | Location | Usage |
|---------|----------|-------|
| **Manager** | `app/Services/*Manager.php` | Transactional business logic (buy, trade, transfer, grant) |
| **Service** | `app/Services/*Service.php` | CRUD and admin data operations |
| **Base Service** | `app/Services/Service.php` | Error bag, DB transaction helpers |
| **Trait** | `app/Traits/` | `Commentable`, `Commenter` |
| **Widget partials** | `resources/views/widgets/` | Reusable form selectors and modals |
| **Power middleware** | `CheckPower` | Fine-grained staff authorization |

---

## 4. Authentication

### Guards and providers (`config/auth.php`)

| Guard | Driver | Provider |
|-------|--------|----------|
| `web` (default) | `session` | `App\Models\User\User` |
| `api` | `token` (unhashed) | Same user provider |

### Auth controllers (`app/Http/Controllers/Auth/`)

| Controller | Responsibility |
|------------|----------------|
| `LoginController` | Session login via `AuthenticatesUsers` trait |
| `RegisterController` | Registration with invitation code support |
| `ForgotPasswordController` | Password reset email |
| `ResetPasswordController` | Password reset form |
| `VerificationController` | Email verification |

### Registration requirements

- Unique `name` (alpha_dash), `email`, `password`
- Terms agreement checkbox
- Date of birth (13+ age gate)
- Optional invitation `code` when registration is closed (`Settings::get('is_registration_open')`)
- User created via `App\Services\UserService::createUser()`
- Email verification required (`User implements MustVerifyEmail`)

### Post-login gates

```
Register/Login → Email Verified → Social Alias Linked → Member Routes Unlocked
```

| Middleware | Class | Behavior |
|------------|-------|----------|
| `auth` | `Authenticate` | Redirect to login |
| `verified` | `EnsureEmailIsVerified` | Block until email confirmed |
| `alias` | `CheckAlias` | Requires linked social alias, birthday set, not banned |
| `staff` | `CheckStaff` | User has rank powers or is admin |
| `admin` | `CheckAdmin` | Admin rank only |
| `power:{key}` | `CheckPower` | Checks `User::hasPower($power)` |

### Social account linking (required gate, not primary login)

| Route | Controller | Purpose |
|-------|------------|---------|
| `GET /link` | `HomeController@getLink` | Alias linking page |
| `GET /auth/redirect/{driver}` | `HomeController@getAuthRedirect` | OAuth redirect |
| `GET /auth/callback/{driver}` | `HomeController@getAuthCallback` | OAuth callback |

**Service:** `App\Services\LinkService`  
**Providers:** DeviantArt, Twitter, Instagram, Tumblr, Imgur, Twitch, Toyhouse (custom), Discord (config only)  
**Custom provider:** `app/Providers/Socialite/ToyhouseProvider.php`

### Auth views

| View | Path |
|------|------|
| Login | `resources/views/auth/login.blade.php` |
| Register | `resources/views/auth/register.blade.php` |
| Verify email | `resources/views/auth/verify.blade.php` |
| Link alias | `resources/views/auth/link.blade.php` |
| Birthday | `resources/views/auth/birthday.blade.php` |
| Blocked / banned | `resources/views/auth/blocked.blade.php` |

---

## 5. User Module

### Models (`app/Models/User/`)

| Model | Table | Purpose |
|-------|-------|---------|
| `User` | `users` | Core account: ranks, aliases, currencies, items |
| `UserSettings` | `user_settings` | FTO flag, character/MYO counts |
| `UserProfile` | `user_profiles` | Editable profile text |
| `UserAlias` | `user_aliases` | Linked social accounts |
| `UserItem` | `user_items` | Inventory stacks |
| `UserCurrency` | `user_currencies` | Currency balances |
| `UserCharacterLog` | `user_character_log` | Ownership history |
| `UserUpdateLog` | `user_update_log` | Admin edit audit trail |

### Controllers (`app/Http/Controllers/Users/`)

| Controller | Key routes | Purpose |
|------------|------------|---------|
| `UserController` | `/user/{name}/*` | Public profiles |
| `AccountController` | `/account/*` | Settings, password, email, avatar, aliases |
| `BankController` | `/bank/*` | Currency bank (view, transfer) |
| `InventoryController` | `/inventory/*` | Inventory management |
| `CharacterController` | `/characters/*` | User's character list, transfers, MYO slots |
| `BookmarkController` | `/account/bookmarks` | Character bookmarks |
| `TradeController` | `/trades/*` | Secure trades between users |
| `SubmissionController` | `/submissions/*` | Prompt submissions and claims |
| `ReportController` | `/reports/*` | User reports |

### Public user routes (`routes/lorekeeper/browse.php`)

```
GET  /users                          → user directory
GET  /blacklist                      → banned users list
GET  /user/{name}                    → profile
GET  /user/{name}/characters         → character list
GET  /user/{name}/inventory          → public inventory
GET  /user/{name}/bank               → public bank
GET  /user/{name}/gallery            → gallery
GET  /user/{name}/favorites          → gallery favorites
GET  /user/{name}/currency-logs      → currency audit
GET  /user/{name}/item-logs          → item audit
GET  /user/{name}/ownership-logs     → ownership audit
GET  /user/{name}/submission-logs    → submission audit
```

### Member user routes (`routes/lorekeeper/members.php`)

```
GET/POST  /account/*                 → account settings
GET/POST  /notifications/*           → notification center
GET/POST  /inventory/*               → inventory edit, selectors
GET/POST  /bank/*                    → bank transfers
GET/POST  /characters/*              → character management
GET/POST  /trades/*                  → trade create/accept/cancel
GET/POST  /submissions/*             → submission queue
GET/POST  /reports/*                 → report submission
```

### Views

| Directory | Purpose |
|-----------|---------|
| `resources/views/user/` | Public profile pages |
| `resources/views/account/` | Account settings, aliases, bookmarks, notifications |
| `resources/views/home/` | Logged-in dashboard (inventory, bank, characters, trades) |

### Services

| Service | Purpose |
|---------|---------|
| `UserService` | User creation, profile updates |
| `LinkService` | Social alias linking |
| `CurrencyManager` | Debit/credit currency with logging |
| `BookmarkManager` | Character bookmark CRUD |
| `TradeManager` | Full trade lifecycle with asset transfer |

---

## 6. Character Module

### Models (`app/Models/Character/`)

| Model | Table | Purpose |
|-------|-------|---------|
| `Character` | `characters` | Masterlist entry (soft deletes, MYO slot support) |
| `CharacterImage` | `character_images` | Character artwork with traits |
| `CharacterFeature` | `character_features` | Trait assignments per image |
| `CharacterProfile` | `character_profiles` | Owner-editable bio |
| `CharacterCategory` | `character_categories` | Masterlist categories |
| `CharacterItem` | `character_items` | Character-held items |
| `CharacterCurrency` | `character_currencies` | Character-held currency |
| `CharacterLog` | `character_log` | Change audit trail |
| `CharacterTransfer` | `character_transfers` | Transfer requests |
| `CharacterDesignUpdate` | `design_updates` | Design approval workflow |
| `CharacterBookmark` | `character_bookmarks` | User bookmarks |
| `CharacterImageCreator` | `character_image_creators` | Artist credits |
| `Sublist` | `masterlist_sub` | Alternate masterlist views |

### Controllers

| Controller | Namespace | Purpose |
|------------|-----------|---------|
| `CharacterController` | `Characters` | Public pages, profile edit, inventory, bank, transfers |
| `MyoController` | `Characters` | MYO slot pages |
| `DesignController` | `Characters` | Design update approval workflow |
| `CharacterController` | `Admin\Characters` | Admin create/edit/delete, queues |
| `CharacterImageController` | `Admin\Characters` | Image upload, traits, credits |
| `GrantController` | `Admin\Characters` | Grant currency/items to characters |

### Public routes

```
GET  /masterlist                     → character masterlist
GET  /myos                           → MYO slot masterlist
GET  /sublist/{key}                  → sublist view
GET  /character/{slug}               → character page
GET  /character/{slug}/profile       → bio
GET  /character/{slug}/bank            → character bank
GET  /character/{slug}/inventory     → character inventory
GET  /character/{slug}/images        → image gallery
GET  /character/{slug}/gallery       → linked gallery
GET  /character/{slug}/logs          → change log
GET  /myo/{id}                       → MYO slot page
```

### Member routes

```
GET/POST  /character/{slug}/profile/edit
GET/POST  /character/{slug}/inventory/edit
GET/POST  /character/{slug}/bank/transfer
GET/POST  /character/{slug}/transfer
GET/POST  /character/{slug}/approval
GET/POST  /myo/{id}/*
GET/POST  /designs/*
```

### Admin routes (power: `manage_characters`)

```
GET/POST  /admin/masterlist/*
GET/POST  /admin/character/{slug}/*
GET/POST  /admin/designs/*
```

### Views (`resources/views/character/`)

- `character.blade.php`, `profile.blade.php`, `inventory.blade.php`, `bank.blade.php`
- `images.blade.php`, `gallery.blade.php`
- `myo/` — MYO-specific layouts
- `design/` — multi-step design approval wizard
- `admin/` — staff modals for image/stats editing

### Core service

`App\Services\CharacterManager` — character creation, ownership transfers, design updates, settings management.

---

## 7. Inventory System

### Data model

| Table | Model | Purpose |
|-------|-------|---------|
| `user_items` | `UserItem` | User inventory stacks |
| `character_items` | `CharacterItem` | Character inventory stacks |
| `items` | `Item` | Item definitions |
| `item_categories` | `ItemCategory` | Item grouping |
| `item_tags` | `ItemTag` | Behavior tags (slot, box, etc.) |
| `items_log` | `ItemLog` | Transfer/use audit trail |
| `character_items_log` | — | Character item audit |

### Controller

`App\Http\Controllers\Users\InventoryController`

| Method | Route | Purpose |
|--------|-------|---------|
| `getIndex` | `GET /inventory` | User inventory page |
| `postEdit` | `POST /inventory/edit` | Transfer/use items |
| `getStack` | `GET /items/{id}` | Stack detail modal (AJAX) |
| `getCharacterStack` | `GET /items/character/{id}` | Character stack modal |
| `getSelector` | `GET /inventory/selector` | Item picker for forms |
| `getAccountSearch` | `GET /inventory/account-search` | User search for transfers |

### Services

| Service | Purpose |
|---------|---------|
| `InventoryManager` | `grantItems()`, `creditItem()`, `debitItem()`, transfers between users/characters |
| `Item\SlotService` | Slot-type item activation |
| `Item\BoxService` | Box/loot item opening |

### Views

| View | Purpose |
|------|---------|
| `resources/views/home/inventory.blade.php` | Member inventory |
| `resources/views/home/_inventory_stack.blade.php` | Stack modal |
| `resources/views/user/inventory.blade.php` | Public profile inventory |
| `resources/views/character/inventory.blade.php` | Character inventory |
| `resources/views/inventory/_slot.blade.php` | Slot item UI |
| `resources/views/inventory/_box.blade.php` | Box item UI |

### Item tag system

Item behaviors are driven by tags configured in `config/lorekeeper/item_tags.php`. Tags like `slot` and `box` route to dedicated service classes and Blade partials. This is the closest existing mechanism to Base44's "activate slot item to unlock capacity" pattern.

---

## 8. Shop System

### Models (`app/Models/Shop/`)

| Model | Table | Purpose |
|-------|-------|---------|
| `Shop` | `shops` | Shop definitions |
| `ShopStock` | `shop_stock` | Stock entries (item + price + limits) |
| `ShopLog` | `shop_log` | Purchase history |

### Public controller

`App\Http\Controllers\ShopController`

| Method | Route | Middleware |
|--------|-------|------------|
| `getIndex` | `GET /shops` | Public |
| `getShop` | `GET /shops/{id}` | Public |
| `getShopStock` | `GET /shops/{id}/{stockId}` | Public (AJAX modal) |
| `postBuy` | `POST /shops/buy` | Member |
| `getPurchaseHistory` | `GET /shops/history` | Member |

### Admin controller

`App\Http\Controllers\Admin\Data\ShopController` — CRUD shops, manage stock, sort order (power: `edit_data`)

### Services

| Service | Purpose |
|---------|---------|
| `ShopManager` | `buyStock()` — validates stock, purchase limits, debits currency, credits items |
| `ShopService` | Admin CRUD for shops and stock |

### Purchase flow

```
User selects stock → ShopManager::buyStock()
  → validates availability and purchase limits
  → CurrencyManager::debit() (user or character currency)
  → InventoryManager::creditItem()
  → ShopLog entry created
```

### Views

| View | Purpose |
|------|---------|
| `resources/views/shops/index.blade.php` | Shop listing |
| `resources/views/shops/shop.blade.php` | Shop detail with stock |
| `resources/views/shops/_stock_modal.blade.php` | Purchase modal |
| `resources/views/shops/purchase_history.blade.php` | Purchase log |
| `resources/views/admin/shops/` | Admin shop management |

---

## 9. Admin Panel

### Access model

- **URL prefix:** `/admin`
- **Gate:** `middleware: ['staff']` on route group
- **Fine-grained:** per-route `power:{key}` middleware
- **Dashboard:** `Admin\HomeController@getIndex` → `resources/views/admin/index.blade.php`
- **Layout:** `resources/views/admin/layout.blade.php`
- **Sidebar:** driven by `config/lorekeeper/admin_sidebar.php`

### Staff powers (`config/lorekeeper/powers.php`)

| Power key | Capability |
|-----------|------------|
| `edit_site_settings` | Site settings, file/image uploads, invitations |
| `edit_data` | World data CRUD (items, species, shops, loot, etc.) |
| `edit_pages` | Site pages, news, sales |
| `edit_user_info` | User management, invitation keys |
| `edit_ranks` | Rank management (requires `admin` middleware) |
| `edit_inventories` | Grant/remove items and currency |
| `manage_characters` | Masterlist, images, design approvals |
| `manage_raffles` | Raffle management |
| `manage_submissions` | Submission queue, claims, gallery |
| `manage_reports` | Report queue |

### Admin controllers (60 total under `app/Http/Controllers/Admin/`)

| Area | Controllers |
|------|-------------|
| **Core** | `HomeController`, `SettingsController`, `FileController`, `InvitationController` |
| **Content** | `PageController`, `NewsController`, `SalesController` |
| **Users** | `Users\UserController`, `Users\RankController`, `Users\GrantController` |
| **World data** | `Data\ItemController`, `CurrencyController`, `RarityController`, `SpeciesController`, `FeatureController`, `ShopController`, `LootTableController`, `PromptController`, `GalleryController`, `CharacterCategoryController`, `SublistController` |
| **Characters** | `Characters\CharacterController`, `CharacterImageController`, `GrantController` |
| **Workflow** | `SubmissionController`, `DesignController`, `GalleryController`, `RaffleController`, `ReportController` |

### Admin views

`resources/views/admin/` — 113+ Blade files organized by module (users, items, shops, masterlist, galleries, raffles, etc.)

---

## 10. Routes

### Route files

| File | Loaded by | Middleware | Purpose |
|------|-----------|------------|---------|
| `routes/web.php` | `RouteServiceProvider` | `web` | Main entry, auth, includes lorekeeper routes |
| `routes/api.php` | `RouteServiceProvider` | `api` prefix | Minimal API (one route) |
| `routes/lorekeeper/browse.php` | `require` from `web.php` | None | Public encyclopedia, profiles, masterlist |
| `routes/lorekeeper/members.php` | Inside `auth+verified+alias` | Member | User dashboard actions |
| `routes/lorekeeper/admin.php` | Inside `auth+verified+alias+staff` | Staff + `power:*` | Admin panel |
| `routes/channels.php` | Broadcasting | — | `App.User.{id}` private channel |
| `routes/console.php` | Artisan | — | Scheduled/custom commands |

### `routes/web.php` structure

```
GET  /                              → HomeController@getIndex
GET  /login                         → LoginController@getNewReply  ⚠️ broken ref
Auth::routes(['verify' => true])
require browse.php                  (public, no auth)

Route::group [auth, verified]:
  /link, /auth/*, /birthday, /blocked, /banned
  Route::group [alias]:
    require members.php
    Route::group [prefix admin, namespace Admin, staff]:
      require admin.php
```

---

## 11. Controllers

### Directory structure (60 controllers)

```
app/Http/Controllers/
├── Controller.php
├── HomeController.php
├── BrowseController.php
├── WorldController.php
├── PageController.php
├── NewsController.php
├── SalesController.php
├── ShopController.php
├── GalleryController.php
├── PromptsController.php
├── RaffleController.php
├── PermalinkController.php
├── Auth/
│   ├── LoginController.php
│   ├── RegisterController.php
│   ├── ForgotPasswordController.php
│   ├── ResetPasswordController.php
│   └── VerificationController.php
├── Users/
│   ├── UserController.php
│   ├── AccountController.php
│   ├── BankController.php
│   ├── InventoryController.php
│   ├── CharacterController.php
│   ├── BookmarkController.php
│   ├── TradeController.php
│   ├── SubmissionController.php
│   └── ReportController.php
├── Characters/
│   ├── CharacterController.php
│   ├── MyoController.php
│   └── DesignController.php
├── Comments/
│   ├── CommentController.php
│   └── CommentControllerInterface.php
└── Admin/
    ├── HomeController.php
    ├── SettingsController.php
    ├── FileController
    ├── InvitationController.php
    ├── (workflow controllers)
    ├── Users/ (3 controllers)
    ├── Characters/ (3 controllers)
    └── Data/ (11 controllers)
```

Controllers are intentionally thin. Complex logic lives in Services/Managers.

---

## 12. Models

### Directory structure (62 models)

```
app/Models/
├── Model.php                    (base — timestamps disabled by default)
├── Comment.php
├── Invitation.php
├── News.php
├── Notification.php
├── Rarity.php
├── SitePage.php
├── Trade.php
├── Character/     (13 models)
├── Currency/      (2 models)
├── Feature/       (2 models)
├── Gallery/       (5 models)
├── Item/          (4 models)
├── Loot/          (2 models)
├── Prompt/        (3 models)
├── Raffle/        (3 models)
├── Rank/          (2 models)
├── Report/        (1 model)
├── Sales/         (2 models)
├── Shop/          (3 models)
├── Species/       (2 models)
├── Submission/    (2 models)
└── User/          (8 models)
```

All models extend `App\Models\Model`, which disables automatic timestamps. Individual models opt in to `$timestamps = true` where needed.

---

## 13. Views & Frontend Stack

### View inventory (~342 Blade templates)

| Directory | Count / Purpose |
|-----------|-----------------|
| `layouts/` | App shell, nav, footer |
| `auth/` | Login, register, verify, link |
| `account/` | Settings, aliases, bookmarks |
| `user/` | Public profiles |
| `home/` | Member dashboard |
| `character/` | Character pages + design workflow |
| `browse/` | Masterlist, user directory |
| `world/` | Encyclopedia (species, items, traits) |
| `shops/` | Shop UI |
| `galleries/` | Gallery browsing |
| `admin/` | Full admin panel (113+ files) |
| `widgets/` | Reusable partials |
| `comments/` | Comment system |
| `inventory/` | Item tag UIs |
| `js/` | Inline JS Blade partials |

### Frontend architecture

| Layer | Technology |
|-------|------------|
| **Rendering** | Server-side Blade templates |
| **Build** | Laravel Mix 4 + Webpack (`webpack.mix.js`) |
| **CSS** | Sass → Bootstrap 4 |
| **JS compiled** | `resources/js/app.js` → minimal Vue 2 scaffold |
| **Runtime JS** | jQuery 3, Bootstrap 4, axios |
| **Vendor JS (layout)** | TinyMCE, jQuery UI, Selectize, Lightbox, Croppie, bootstrap4-toggle, colorpicker, timepicker |
| **Static assets** | `public/js/site.js`, vendor libs in `public/js/` |
| **Forms** | Laravel Collective HTML helpers |
| **Icons** | Font Awesome |

**Key takeaway:** This is a traditional server-rendered application. Interactive features use jQuery AJAX calls to load modal partials (inventory stacks, shop stock, character selectors). Vue is present but not used for feature development.

---

## 14. Reusable Components

### Blade widgets (`resources/views/widgets/`)

| Widget | Purpose |
|--------|---------|
| `_inventory_select` + `_inventory_select_js` | Item picker for forms |
| `_bank_select` + `_bank_select_row` + `_bank_select_js` | Currency picker |
| `_character_select` + `_character_select_entry` | Character picker |
| `_my_character_select` + `_my_character_select_js` | Own-character picker |
| `_loot_select` + `_loot_select_row` | Loot table editor (admin) |
| `_image_upload_js` | Image upload helper |
| `_character_code_js` | Character code input |
| `_character_create_options_js` | Admin character creation options |
| `_gallery_thumb` | Gallery thumbnail |

### Services (`app/Services/` — 34 files)

**Managers (transactional business logic):**

| Manager | Responsibility |
|---------|----------------|
| `InventoryManager` | Item grants, debits, transfers |
| `ShopManager` | Purchase processing |
| `CurrencyManager` | Currency debit/credit with logging |
| `CharacterManager` | Character lifecycle |
| `TradeManager` | Secure trade with asset settlement |
| `SubmissionManager` | Submission approval and rewards |
| `GalleryManager` | Gallery submissions |
| `RaffleManager` | Raffle tickets and rolling |
| `BookmarkManager` | Character bookmarks |
| `ReportManager` | User reports |
| `FileManager` | File uploads |

**Services (CRUD/admin):** `UserService`, `ItemService`, `ShopService`, `CurrencyService`, `SpeciesService`, `FeatureService`, `RarityService`, `PromptService`, `LootService`, `GalleryService`, `NewsService`, `PageService`, `SalesService`, `RankService`, `SublistService`, `CharacterCategoryService`, `InvitationService`, `LinkService`, `ExtensionService`, `RaffleService`

### Traits (`app/Traits/`)

| Trait | Used by | Purpose |
|-------|---------|---------|
| `Commentable` | Models with comments | Comment relationship |
| `Commenter` | `User` | User can post comments |

### Providers

| Provider | Purpose |
|----------|---------|
| `SettingsProvider` | Binds `Settings` facade |
| `NotificationsProvider` | Binds `Notifications` facade |
| `CommentProvider` | Comment views and gates |

### Console commands (`app/Console/Commands/`)

`SetupAdminUser`, `AddSiteSettings`, `AddTextPages`, `UpdateLorekeeperV2`, `MigrateAliases`, `CheckNews`, `CheckSales`, and others for site maintenance.

---

## 15. Database & Migrations

**116 migration files** in `database/migrations/`.

### Core table groups

| Domain | Tables |
|--------|--------|
| **Users & auth** | `users`, `ranks`, `rank_powers`, `user_settings`, `user_profiles`, `user_aliases`, `user_update_log`, `password_resets`, `invitations`, `notifications` |
| **Economy** | `currencies`, `user_currencies`, `character_currencies`, `currencies_log`, `user_items`, `character_items`, `items_log`, `character_items_log` |
| **Items** | `items`, `item_categories`, `item_tags`, `loot_tables`, `loots` |
| **Characters** | `characters`, `character_images`, `character_features`, `character_profiles`, `character_categories`, `character_log`, `character_transfers`, `character_image_creators`, `design_updates`, `character_bookmarks`, `masterlist_sub`, `subtypes` |
| **Shops** | `shops`, `shop_stock`, `shop_log` |
| **World data** | `rarities`, `specieses`, `features`, `feature_categories` |
| **Gameplay** | `prompts`, `prompt_categories`, `prompt_rewards`, `submissions`, `submission_characters`, `claims`, `claim_characters` |
| **Social** | `galleries`, `gallery_submissions`, `gallery_favorites`, `trades`, `reports`, `comments` |
| **Content** | `site_settings`, `site_pages`, `site_extensions`, `news`, `sales`, `sales_characters` |
| **Raffles** | `raffle_groups`, `raffles`, `raffle_tickets` |

### Foundational migrations

| Migration | Creates |
|-----------|---------|
| `2014_10_12_000000_create_users_table` | users, ranks, notifications |
| `2019_02_27_075638_create_game_tables` | rarities, items, inventory, species, features, characters |
| `2019_04_09_095513_create_currency_tables` | currencies |
| `2019_09_28_040911_create_shops` | shops |

---

## 16. API Layer

**File:** `routes/api.php`  
**Prefix:** `/api`  
**Middleware:** `api` (throttle 60/min)

Only one route exists:

```php
Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
```

There is **no REST API** for characters, inventory, shops, or other game features. All game logic is web/session-based.

**Implication for Base44 migration:** If a React SPA frontend is desired (like Base44), a new API layer must be built. Alternatively, new homestead features can follow the existing Blade + jQuery pattern.

---

## 17. Additional Modules (Beyond Base44 Scope)

Lorekeeper includes substantial functionality not present in the Base44 reference:

| Module | Key files | Purpose |
|--------|-----------|---------|
| **Submissions** | `SubmissionManager`, `routes/lorekeeper/members.php` | Art submission queue with automated rewards |
| **Galleries** | `GalleryManager`, `resources/views/galleries/` | Community gallery with approval queue |
| **Raffles** | `RaffleManager`, `resources/views/raffles/` | Ticket-based raffle system |
| **Prompts** | `PromptService`, `resources/views/prompts/` | Activity prompts with rewards |
| **Sales** | `SalesService`, `resources/views/sales/` | Character sales announcements |
| **News** | `NewsService`, `resources/views/news/` | Site news posts |
| **World encyclopedia** | `WorldController`, `resources/views/world/` | Searchable species, traits, items reference |
| **Comments** | `CommentController`, `Commentable` trait | Threaded comments on models |
| **Reports** | `ReportManager` | User reporting system |
| **Design updates** | `DesignController`, `CharacterManager` | Multi-step character redesign approval |
| **MYO slots** | `MyoController` | Make-your-own character slots |
| **Loot tables** | `LootService` | Randomized item drops |
| **Sublists** | `SublistService` | Alternate masterlist views |

These modules should be preserved during migration. They represent Lorekeeper's core ARPG value and are unrelated to Base44's homestead decorator features.

---

## 18. Known Issues & Technical Debt

| Issue | Location | Impact |
|-------|----------|--------|
| Broken login route | `routes/web.php` line 15: `LoginController@getNewReply` — method does not exist | GET `/login` may 500; POST login via `Auth::routes()` likely still works |
| No REST API | `routes/api.php` | Blocks SPA frontend without new API development |
| Vue scaffold unused | `resources/js/app.js` | Dead code; jQuery is the real frontend |
| Laravel 8 EOL | `composer.json` | Framework upgrade path needed long-term |
| Token API guard unhashed | `config/auth.php` | Security concern if API is expanded |
| Base model disables timestamps | `app/Models/Model.php` | Each model must opt in individually |

---

## Summary Matrix: Lorekeeper vs Base44 Feature Areas

| Feature area | Lorekeeper status | Notes |
|--------------|-------------------|-------|
| Authentication | ✅ Full (different model) | Session + email verify + social alias |
| User profiles | ✅ Full | Public profiles, settings, aliases |
| Characters | ✅ Full (different model) | Masterlist with traits/images, not room sprites |
| Inventory | ✅ Full | Stack-based with item tags |
| Shop | ✅ Full | Multi-currency, purchase limits |
| Wallet / currency | ✅ Via bank system | Multi-currency, not single-coin ledger |
| Trades | ✅ Full with settlement | Base44 trade is UI-only |
| Rooms / homestead | ❌ Missing | Entirely new development |
| Room editor | ❌ Missing | Canvas placement, drag-and-drop |
| Showcase | ❌ Missing | Mod-curated featured content |
| Favorites (rooms/chars) | ⚠️ Partial | Character bookmarks exist; no room favorites |
| Admin panel | ✅ Extensive | Power-based, broader than Base44 mod panel |
| Submissions / galleries | ✅ Extra | Not in Base44 |
| Raffles / prompts | ✅ Extra | Not in Base44 |
