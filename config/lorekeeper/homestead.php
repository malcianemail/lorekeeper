<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Homestead Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for the homestead / room decoration module.
    |
    */

    'base_indoor_slots' => 1,
    'base_outdoor_slots' => 1,
    'base_sprite_slots' => 1,

    'slot_tags' => [
        'indoor' => 'room_slot',
        'outdoor' => 'house_slot',
    ],

    'sprite_slot_tag' => 'sprite_slot',

    /*
    |--------------------------------------------------------------------------
    | Unlimited Slot Permission
    |--------------------------------------------------------------------------
    |
    | Rank power key (from config/lorekeeper/powers.php) that grants unlimited
    | homestead room/house creation. Attach this power to ranks via
    | Admin → User Ranks. The admin rank always has all powers.
    |
    | Set to null to disable rank-based unlimited slots entirely.
    |
    */
    'unlimited_slots_power' => 'unlimited_homestead_slots',

    /*
    |--------------------------------------------------------------------------
    | Unlimited Slots UI Label
    |--------------------------------------------------------------------------
    |
    | Shown on the rooms/houses list when the user bypasses slot limits.
    |
    */
    'unlimited_slots_label' => 'Unlimited slots (rank permission)',

    /*
    |--------------------------------------------------------------------------
    | Space Labels (rooms / houses UI)
    |--------------------------------------------------------------------------
    */
    'spaces' => [
        'indoor' => [
            'segment' => 'rooms',
            'singular' => 'room',
            'title' => 'Rooms',
            'description' => 'Manage your indoor rooms.',
            'slot_limit_message' => 'You have reached your room slot limit. Activate a room slot item from your inventory to unlock more rooms.',
            'empty_message' => 'You have no rooms yet.',
            'empty_hint' => 'Create a room to start placing furniture and surfaces.',
            'name_label' => 'Room Name',
            'name_placeholder' => 'My Room',
            'create_action' => 'Create Room',
            'edit_action' => 'Edit Room',
            'delete_action' => 'Delete Room',
            'created_message' => 'Room created successfully.',
            'updated_message' => 'Room updated successfully.',
            'deleted_message' => 'Room deleted successfully.',
            'delete_confirm' => 'You are about to delete the room <strong>:name</strong>. This will also remove its layout and furniture placements. Are you sure?',
        ],
        'outdoor' => [
            'segment' => 'houses',
            'singular' => 'house',
            'title' => 'Houses',
            'description' => 'Manage your outdoor houses.',
            'slot_limit_message' => 'You have reached your house slot limit. Activate a house slot item from your inventory to unlock more houses.',
            'empty_message' => 'You have no houses yet.',
            'empty_hint' => 'Create a house to start placing exterior decor and ground cover.',
            'name_label' => 'House Name',
            'name_placeholder' => 'My House',
            'create_action' => 'Create House',
            'edit_action' => 'Edit House',
            'delete_action' => 'Delete House',
            'created_message' => 'House created successfully.',
            'updated_message' => 'House updated successfully.',
            'deleted_message' => 'House deleted successfully.',
            'delete_confirm' => 'You are about to delete the house <strong>:name</strong>. This will also remove its layout and furniture placements. Are you sure?',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Character Sprite Slots
    |--------------------------------------------------------------------------
    */
    'sprite_slots' => [
        'slot_limit_message' => 'This character has reached its sprite slot limit. Activate a sprite slot item from your inventory to unlock more sprites.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Editor Settings (per space type)
    |--------------------------------------------------------------------------
    |
    | Shared homestead editor UI differs by indoor room vs outdoor house.
    |
    */
        'editor' => [
        'indoor' => [
            'label' => 'Room Editor',
            'list_segment' => 'rooms',
            'placeable_group' => 'furniture',
            'help_text' => 'Click or drag furniture onto the canvas. Drag placed items to move. Use Surfaces for wallpaper and flooring. Save to keep your layout.',
            'save_message' => 'Room layout saved successfully.',
            'invalid_item_message' => 'One or more placed items cannot be used in this room.',
            'boundary_message' => 'One or more items are outside the room boundaries.',
            'empty_inventory_hint' => 'You need homestead decoration items in your inventory. Items must be configured for room use before they appear here.',
        ],
        'outdoor' => [
            'label' => 'House Editor',
            'list_segment' => 'houses',
            'placeable_group' => 'furniture',
            'help_text' => 'Click or drag furniture onto the canvas. Drag placed items to move. Use Surfaces for ground cover. Save to keep your layout.',
            'save_message' => 'House layout saved successfully.',
            'invalid_item_message' => 'One or more placed items cannot be used in this house.',
            'boundary_message' => 'One or more items are outside the house boundaries.',
            'empty_inventory_hint' => 'You need homestead decoration items in your inventory. Items must be configured for outdoor use before they appear here.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Editor Canvas Backgrounds
    |--------------------------------------------------------------------------
    |
    | Default preview backgrounds for the homestead editor canvas, per space
    | type. Indoor rooms keep the legacy CSS class for unchanged behavior.
    |
    | Outdoor houses support:
    |   - gradient: CSS gradient used when no admin image is uploaded
    |   - site_image_key: key from config/lorekeeper/image_files.php; when the
    |     file exists it overrides the gradient (Admin → Site Images)
    |
    */
    'canvas_backgrounds' => [
        'indoor' => [
            'css_class' => 'homestead-editor-canvas-room-bg',
        ],
        'outdoor' => [
            'gradient' => 'linear-gradient(180deg, #87ceeb 0%, #b3e5fc 42%, #7cb342 42%, #558b2f 100%)',
            'site_image_key' => 'homestead_house_editor_bg',
            'background_size' => 'cover',
            'background_position' => 'center center',
            'background_repeat' => 'no-repeat',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Editor Inventory
    |--------------------------------------------------------------------------
    |
    | Placement types shown in the editor sidebar, grouped by tab, per space type.
    | Items must also have is_homestead_item = 1 and match the room type.
    |
    */
    'editor_inventory_groups' => [
        'indoor' => [
            'furniture' => ['floor', 'decoration'],
            'surfaces' => ['wall', 'ceiling', 'flooring', 'floor'],
        ],
        'outdoor' => [
            'furniture' => ['floor', 'decoration', 'exterior'],
            'surfaces' => ['flooring', 'floor', 'wall', 'exterior', 'roof'],
        ],
    ],

    'excluded_editor_item_tags' => ['room_slot', 'house_slot', 'sprite_slot'],

    /*
    |--------------------------------------------------------------------------
    | Surface Layout Fields
    |--------------------------------------------------------------------------
    |
    | Maps item placement_type values to room_layouts columns when applied
    | from the Surfaces inventory tab (click-to-apply, not drag-and-drop).
    |
    */
    'surface_layout_fields' => [
        'indoor' => [
            'wall' => 'wallpaper_item_id',
            'ceiling' => 'wallpaper_item_id',
            'flooring' => 'flooring_item_id',
            'floor' => 'flooring_item_id',
        ],
        'outdoor' => [
            'flooring' => 'flooring_item_id',
            'floor' => 'flooring_item_id',
            'wall' => 'exterior_wall_item_id',
            'exterior' => 'exterior_wall_item_id',
            'roof' => 'roof_item_id',
        ],
    ],

    'surface_editor_notes' => [
        'indoor' => 'Wall and ceiling items share one wallpaper slot. Flooring and floor items share one flooring slot.',
        'outdoor' => 'Ground cover and floor items share one flooring slot. Exterior wall items share one wall slot.',
    ],

    'surface_canvas_layers' => [
        'roof_item_id' => 'wall',
        'wallpaper_item_id' => 'wall',
        'exterior_wall_item_id' => 'wall',
        'flooring_item_id' => 'floor',
    ],

    /*
    |--------------------------------------------------------------------------
    | Placement Types
    |--------------------------------------------------------------------------
    |
    | Valid placement_type values for homestead decor items (admin item form).
    |
    */
    'placement_types' => [
        'floor' => 'Floor (furniture)',
        'decoration' => 'Decoration',
        'exterior' => 'Exterior',
        'roof' => 'Roof',
        'wall' => 'Wallpaper',
        'ceiling' => 'Ceiling',
        'flooring' => 'Flooring',
    ],

    'homestead_room_types' => [
        '' => 'Any room type',
        'indoor' => 'Indoor rooms only',
        'outdoor' => 'Outdoor houses only',
        'both' => 'Indoor and outdoor',
    ],

    'canvas' => [
        'indoor' => ['width' => 700, 'height' => 500],
        'outdoor' => ['width' => 700, 'height' => 500],
    ],

    // Legacy defaults used when per-type canvas config is absent.
    'canvas_width' => 700,
    'canvas_height' => 500,

    /*
    |--------------------------------------------------------------------------
    | Character Sprite Placement Defaults
    |--------------------------------------------------------------------------
    */
    'default_sprite_width' => 80,
    'default_sprite_height' => 120,

    'editor_sprites' => [
        'empty_message' => 'No character sprites available.',
        'empty_hint' => 'Upload sprites on your character pages to place them in rooms and houses.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Featured Showcase
    |--------------------------------------------------------------------------
    */
    'featured' => [
        'title' => 'Showcase',
        'description' => 'Moderator-curated featured rooms, houses, and characters from the community.',
        'empty_message' => 'No featured content yet.',
        'empty_hint' => 'Check back later for highlighted creations.',
        'types' => [
            'all' => 'All',
            'room' => 'Rooms',
            'house' => 'Houses',
            'character' => 'Characters',
        ],
        'admin_title' => 'Featured Showcase',
        'admin_description' => 'Curate featured rooms, houses, and characters for the public showcase.',
        'created_message' => 'Featured entry created successfully.',
        'updated_message' => 'Featured entry updated successfully.',
        'deleted_message' => 'Featured entry removed successfully.',
        'sorted_message' => 'Featured display order saved.',
        'toggle_active_message' => 'Featured entry visibility updated.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Homestead Favorites
    |--------------------------------------------------------------------------
    */
    'favorites' => [
        'title' => 'Homestead Favorites',
        'description' => 'Rooms, houses, and characters you have saved.',
        'profile_title' => 'Homestead Favorites',
        'empty_message' => 'No favorites yet.',
        'empty_hint' => 'Favorite rooms, houses, and characters from the showcase or their pages.',
        'toggle_message' => 'Favorite updated successfully.',
        'types' => [
            'all' => 'All',
            'room' => 'Rooms',
            'house' => 'Houses',
            'character' => 'Characters',
        ],
    ],

];
