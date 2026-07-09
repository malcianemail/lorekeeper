<?php

namespace App\Services\Homestead;

use Config;
use App\Models\Homestead\RoomSave;

class HomesteadConfig
{
    /**
     * UI and routing labels for a homestead space type.
     *
     * @param  string  $roomType
     * @return array
     */
    public static function spaceLabels($roomType)
    {
        return Config::get('lorekeeper.homestead.spaces.' . $roomType, []);
    }

    /**
     * URL segment for a homestead space type (rooms or houses).
     *
     * @param  string  $roomType
     * @return string
     */
    public static function listSegment($roomType)
    {
        return static::spaceLabels($roomType)['segment']
            ?? static::editorSettings($roomType)['list_segment']
            ?? ($roomType === RoomSave::TYPE_OUTDOOR ? 'houses' : 'rooms');
    }

    /**
     * Editor sidebar inventory groups for a space type.
     *
     * @param  string  $roomType
     * @return array
     */
    public static function inventoryGroups($roomType)
    {
        $groups = Config::get('lorekeeper.homestead.editor_inventory_groups', []);

        if (isset($groups[$roomType]) && is_array($groups[$roomType])) {
            return $groups[$roomType];
        }

        if (isset($groups['furniture'])) {
            return $groups;
        }

        return [];
    }

    /**
     * Flattened placement types allowed in the editor for a space type.
     *
     * @param  string  $roomType
     * @return array
     */
    public static function placementTypes($roomType)
    {
        return collect(static::inventoryGroups($roomType))
            ->flatten()
            ->unique()
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Editor UI settings for a space type.
     *
     * @param  string  $roomType
     * @return array
     */
    public static function editorSettings($roomType)
    {
        return Config::get('lorekeeper.homestead.editor.' . $roomType, []);
    }

    /**
     * Canvas dimensions for the editor.
     *
     * @param  string  $roomType
     * @return array{width: int, height: int}
     */
    public static function canvasSize($roomType)
    {
        $canvas = Config::get('lorekeeper.homestead.canvas.' . $roomType);

        if (is_array($canvas)) {
            return [
                'width' => (int) ($canvas['width'] ?? Config::get('lorekeeper.homestead.canvas_width', 700)),
                'height' => (int) ($canvas['height'] ?? Config::get('lorekeeper.homestead.canvas_height', 500)),
            ];
        }

        return [
            'width' => (int) Config::get('lorekeeper.homestead.canvas_width', 700),
            'height' => (int) Config::get('lorekeeper.homestead.canvas_height', 500),
        ];
    }

    /**
     * Surface layout field map for a space type.
     *
     * @param  string  $roomType
     * @return array
     */
    public static function surfaceLayoutFields($roomType)
    {
        return Config::get('lorekeeper.homestead.surface_layout_fields.' . $roomType, []);
    }

    /**
     * Editor notes for shared surface layout slots.
     *
     * @param  string  $roomType
     * @return string|null
     */
    public static function surfaceEditorNotes($roomType)
    {
        return Config::get('lorekeeper.homestead.surface_editor_notes.' . $roomType);
    }

    /**
     * Inventory tag used to unlock extra slots.
     *
     * @param  string  $roomType
     * @return string
     */
    public static function slotTag($roomType)
    {
        $tags = Config::get('lorekeeper.homestead.slot_tags', []);

        return $roomType === RoomSave::TYPE_INDOOR
            ? ($tags['indoor'] ?? 'room_slot')
            : ($tags['outdoor'] ?? 'house_slot');
    }

    /**
     * Base slot count before activated slot items.
     *
     * @param  string  $roomType
     * @return int
     */
    public static function baseSlots($roomType)
    {
        return $roomType === RoomSave::TYPE_INDOOR
            ? (int) Config::get('lorekeeper.homestead.base_indoor_slots', 1)
            : (int) Config::get('lorekeeper.homestead.base_outdoor_slots', 1);
    }

    /**
     * Base sprite slot count before activated sprite slot items.
     *
     * @return int
     */
    public static function baseSpriteSlots()
    {
        return (int) Config::get('lorekeeper.homestead.base_sprite_slots', 1);
    }

    /**
     * Inventory tag used to unlock extra character sprite slots.
     *
     * @return string
     */
    public static function spriteSlotTag()
    {
        return Config::get('lorekeeper.homestead.sprite_slot_tag', 'sprite_slot');
    }

    /**
     * UI message shown when a character reaches its sprite slot limit.
     *
     * @return string
     */
    public static function spriteSlotLimitMessage()
    {
        return Config::get(
            'lorekeeper.homestead.sprite_slots.slot_limit_message',
            'This character has reached its sprite slot limit. Activate a sprite slot item from your inventory to unlock more sprites.'
        );
    }

    /**
     * Default placement size for character sprites in the editor.
     *
     * @return array{width: int, height: int}
     */
    public static function defaultSpriteSize()
    {
        return [
            'width' => (int) Config::get('lorekeeper.homestead.default_sprite_width', 80),
            'height' => (int) Config::get('lorekeeper.homestead.default_sprite_height', 120),
        ];
    }

    /**
     * UI copy for the editor sprites tab.
     *
     * @param  string  $key
     * @return string
     */
    public static function editorSpritesLabel($key)
    {
        return Config::get('lorekeeper.homestead.editor_sprites.' . $key, '');
    }

    /**
     * Featured showcase UI copy.
     *
     * @param  string  $key
     * @return string
     */
    public static function featuredLabel($key)
    {
        return Config::get('lorekeeper.homestead.featured.' . $key, '');
    }

    /**
     * Featured type labels for filters and badges.
     *
     * @param  string|null  $type
     * @return string
     */
    public static function featuredTypeLabel($type)
    {
        $labels = Config::get('lorekeeper.homestead.featured.types', []);

        if (!$type) {
            return $labels['all'] ?? 'All';
        }

        return $labels[$type] ?? ucfirst($type);
    }

    /**
     * Featured type filter options for public showcase tabs.
     *
     * @return array
     */
    public static function featuredTypeFilters()
    {
        return Config::get('lorekeeper.homestead.featured.types', []);
    }

    /**
     * Homestead favorites UI copy.
     *
     * @param  string  $key
     * @return string
     */
    public static function favoriteLabel($key)
    {
        return Config::get('lorekeeper.homestead.favorites.' . $key, '');
    }

    /**
     * Favorite type filter options.
     *
     * @return array
     */
    public static function favoriteTypeFilters()
    {
        return Config::get('lorekeeper.homestead.favorites.types', []);
    }

    /**
     * Favorite type label.
     *
     * @param  string  $type
     * @return string
     */
    public static function favoriteTypeLabel($type)
    {
        return static::featuredTypeLabel($type);
    }

    /**
     * Rank power key that grants unlimited homestead slots, or null when disabled.
     *
     * @return string|null
     */
    public static function unlimitedSlotsPower()
    {
        $power = Config::get('lorekeeper.homestead.unlimited_slots_power');

        return is_string($power) && $power !== '' ? $power : null;
    }

    /**
     * UI label shown when a user has unlimited homestead slots.
     *
     * @return string
     */
    public static function unlimitedSlotsLabel()
    {
        return Config::get('lorekeeper.homestead.unlimited_slots_label', 'Unlimited slots');
    }

    /**
     * Resolved canvas background attributes for the homestead editor.
     *
     * @param  string  $roomType
     * @return array{class: string, style: string}
     */
    public static function canvasBackgroundAttributes($roomType)
    {
        $config = Config::get('lorekeeper.homestead.canvas_backgrounds.' . $roomType, []);
        $baseClass = 'homestead-editor-canvas-base-bg';

        if (!empty($config['css_class'])) {
            return [
                'class' => trim($baseClass . ' ' . $config['css_class']),
                'style' => '',
            ];
        }

        $style = static::resolveCanvasBackgroundStyle($config);

        return [
            'class' => $baseClass,
            'style' => $style,
        ];
    }

    /**
     * Build inline CSS for a configurable canvas background.
     *
     * @param  array  $config
     * @return string
     */
    protected static function resolveCanvasBackgroundStyle(array $config)
    {
        $imageUrl = static::resolveSiteImageUrl($config['site_image_key'] ?? null);
        if ($imageUrl) {
            return sprintf(
                'background-image: url(%s); background-size: %s; background-position: %s; background-repeat: %s;',
                $imageUrl,
                $config['background_size'] ?? 'cover',
                $config['background_position'] ?? 'center center',
                $config['background_repeat'] ?? 'no-repeat'
            );
        }

        if (!empty($config['gradient'])) {
            return 'background: ' . $config['gradient'] . ';';
        }

        if (!empty($config['color'])) {
            return 'background-color: ' . $config['color'] . ';';
        }

        return '';
    }

    /**
     * Resolve a public asset URL for an admin-managed site image key.
     *
     * @param  string|null  $key
     * @return string|null
     */
    protected static function resolveSiteImageUrl($key)
    {
        if (!$key) {
            return null;
        }

        $filename = Config::get('lorekeeper.image_files.' . $key . '.filename');
        if (!$filename || !file_exists(public_path('images/' . $filename))) {
            return null;
        }

        return asset('images/' . $filename);
    }
}
