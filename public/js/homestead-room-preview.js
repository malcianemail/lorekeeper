(function(window, $) {
    'use strict';

    if (!$) return;

    function HomesteadRoomPreview(options) {
        this.canvasWidth = options.canvasWidth || 700;
        this.canvasHeight = options.canvasHeight || 500;
        this.catalog = options.catalog || {};
        this.spriteCatalog = options.spriteCatalog || {};
        this.surfaceCanvasLayers = options.surfaceCanvasLayers || {};
        this.maxWidth = options.maxWidth || null;

        this.$root = $(options.rootSelector);
        this.$stage = this.$root.find('[data-preview-stage]').first();
        this.$wallSurface = this.$root.find('[data-preview-wall]').first();
        this.$floorSurface = this.$root.find('[data-preview-floor]').first();
        this.$items = this.$root.find('[data-preview-items]').first();

        this.placements = this.normalizePlacements(options.initialPlacements || []);
        this.layout = options.initialLayout || {};

        this.scale = 1;
        this.init();
    }

    HomesteadRoomPreview.prototype.normalizePlacements = function(payload) {
        var self = this;
        return payload.map(function(placement, index) {
            var itemId = placement.item_id ? parseInt(placement.item_id, 10) : null;
            var spriteId = placement.character_sprite_id
                ? parseInt(placement.character_sprite_id, 10)
                : null;

            var record = {
                id: index + 1,
                x: parseFloat(placement.x),
                y: parseFloat(placement.y),
                width: parseFloat(placement.width),
                height: parseFloat(placement.height),
                zIndex: parseInt(placement.z_index, 10),
            };

            if (spriteId) {
                record.spriteId = spriteId;
            } else if (itemId) {
                record.itemId = itemId;
            }

            return record;
        });
    };

    HomesteadRoomPreview.prototype.getPlacementEntry = function(placement) {
        if (placement.spriteId) {
            return this.spriteCatalog[placement.spriteId] || null;
        }
        if (placement.itemId) {
            return this.catalog[placement.itemId] || null;
        }
        return null;
    };

    HomesteadRoomPreview.prototype.init = function() {
        if (!this.$root.length || !this.$stage.length) return;

        this.updateScale();
        this.applySurfaceLayers();
        this.render();
        $(window).on('resize.homesteadPreview', this.updateScale.bind(this));
    };

    HomesteadRoomPreview.prototype.updateScale = function() {
        var availableWidth = this.maxWidth || this.$root.innerWidth() || this.canvasWidth;
        this.scale = Math.min(1, availableWidth / this.canvasWidth);

        this.$stage.css({
            width: this.canvasWidth + 'px',
            height: this.canvasHeight + 'px',
            transform: 'scale(' + this.scale + ')',
            transformOrigin: 'top left',
        });

        this.$root.css('height', Math.round(this.canvasHeight * this.scale) + 'px');
    };

    HomesteadRoomPreview.prototype.applySurfaceLayers = function() {
        var self = this;

        this.$wallSurface.css({ backgroundImage: '', backgroundColor: '' });
        this.$floorSurface.css({ backgroundImage: '', backgroundColor: '' });

        Object.keys(this.layout).forEach(function(field) {
            var itemId = self.layout[field];
            var item = self.catalog[itemId];
            if (!item || !item.hasImage || !item.imageUrl) return;

            var layer = self.surfaceCanvasLayers[field] || 'floor';
            var $target = layer === 'wall' ? self.$wallSurface : self.$floorSurface;

            $target.css({
                backgroundImage: 'url(' + item.imageUrl + ')',
                backgroundSize: 'cover',
                backgroundRepeat: 'repeat',
                backgroundPosition: 'center center',
            });
        });
    };

    HomesteadRoomPreview.prototype.createPlacementNode = function(placement) {
        var entry = this.getPlacementEntry(placement);
        if (!entry) return null;

        var $node = $('<div class="homestead-editor-placed-item"></div>');
        $node.css({
            left: placement.x + 'px',
            top: placement.y + 'px',
            width: placement.width + 'px',
            height: placement.height + 'px',
            zIndex: placement.zIndex,
        });

        if (entry.hasImage && entry.imageUrl) {
            $('<img>', {
                src: entry.imageUrl,
                alt: entry.name,
                draggable: false,
                loading: 'lazy',
            }).appendTo($node);
        } else {
            $('<div class="homestead-editor-placed-item-fallback"><i class="fas fa-cube"></i></div>').appendTo($node);
        }

        return $node;
    };

    HomesteadRoomPreview.prototype.render = function() {
        var self = this;
        var sorted = this.placements.slice().sort(function(a, b) {
            return a.zIndex - b.zIndex;
        });
        var fragment = document.createDocumentFragment();

        this.$items.empty();

        sorted.forEach(function(placement) {
            var $node = self.createPlacementNode(placement);
            if ($node) {
                fragment.appendChild($node[0]);
            }
        });

        if (this.$items.length) {
            this.$items[0].appendChild(fragment);
        }
    };

    HomesteadRoomPreview.mountAll = function(selector) {
        $(selector).each(function() {
            var $el = $(this);
            if ($el.data('preview-mounted')) return;

            var options = $el.data('preview-options');
            if (!options) return;

            options.rootSelector = '#' + $el.attr('id');
            new HomesteadRoomPreview(options);
            $el.data('preview-mounted', true);
        });
    };

    window.HomesteadRoomPreview = HomesteadRoomPreview;
})(window, window.jQuery);
