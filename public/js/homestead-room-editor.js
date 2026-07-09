(function($) {
    'use strict';

    function HomesteadRoomEditor(options) {
        this.canvasWidth = options.canvasWidth;
        this.canvasHeight = options.canvasHeight;
        this.catalog = options.catalog || {};
        this.spriteCatalog = options.spriteCatalog || {};
        this.layout = {};
        this.surfaceCanvasLayers = options.surfaceCanvasLayers || {};
        this.placements = [];
        this.placedCounts = {};
        this.placedSpriteCounts = {};
        this.selectedId = null;
        this.nextId = 1;
        this.scale = 1;
        this.dragState = null;
        this.inventoryDrag = null;
        this.isDirty = false;
        this.placementIndex = {};
        this.$placementNodes = {};
        this.$inventoryByItemId = {};
        this.$inventoryBySpriteId = {};
        this.dragRafId = null;
        this.pendingDragPlacement = null;
        this.resizeRafId = null;

        this.$wrap = $(options.canvasWrapSelector);
        this.$container = $(options.canvasContainerSelector);
        this.$stage = $(options.canvasStageSelector);
        this.$items = $(options.canvasItemsSelector);
        this.$placeholder = $(options.canvasPlaceholderSelector || []);
        this.$loading = $(options.canvasLoadingSelector || []);
        this.$wallSurface = $(options.wallSurfaceSelector || []);
        this.$floorSurface = $(options.floorSurfaceSelector || []);
        this.$controls = $(options.controlsSelector);
        this.$inventoryRoot = $(options.inventorySelector);

        this.bindEvents();
        this.ensureStageDimensions();
        this.updateScale();
        this.observeCanvasResize();
        if (options.initialLayout) {
            this.loadLayout(options.initialLayout);
        }
        if (options.initialPlacements && options.initialPlacements.length) {
            this.loadPlacements(options.initialPlacements);
        } else {
            this.render();
            this.updateInventoryCounts();
            this.applySurfaceLayers();
            this.updateSurfaceSelection();
        }

        this.hideLoading();
        this.cacheInventoryElements();
        this.markClean();
    }

    HomesteadRoomEditor.prototype.syncPlacementIndex = function() {
        this.placementIndex = {};
        for (var i = 0; i < this.placements.length; i++) {
            var placement = this.placements[i];
            this.placementIndex[placement.id] = placement;
        }
    };

    HomesteadRoomEditor.prototype.getPlacementById = function(placementId) {
        return this.placementIndex[placementId] || null;
    };

    HomesteadRoomEditor.prototype.cacheInventoryElements = function() {
        var self = this;
        this.$inventoryByItemId = {};
        this.$inventoryBySpriteId = {};
        this.$inventoryRoot.find('.homestead-editor-inventory-item').each(function() {
            var $entry = $(this);
            var itemId = parseInt($entry.data('item-id'), 10);
            var spriteId = parseInt($entry.data('sprite-id'), 10);

            if (itemId && !self.$inventoryByItemId[itemId]) {
                self.$inventoryByItemId[itemId] = $entry;
            }

            if (spriteId && !self.$inventoryBySpriteId[spriteId]) {
                self.$inventoryBySpriteId[spriteId] = $entry;
            }
        });
    };

    HomesteadRoomEditor.prototype.getPlacementEntry = function(placement) {
        if (placement.spriteId) {
            return this.spriteCatalog[placement.spriteId] || null;
        }

        return this.catalog[placement.itemId] || null;
    };

    HomesteadRoomEditor.prototype.updatePlacementPosition = function(placement) {
        var $node = this.$placementNodes[placement.id];
        if ($node && $node.length) {
            $node.css({
                left: placement.x + 'px',
                top: placement.y + 'px',
            });
        }
    };

    HomesteadRoomEditor.prototype.updateSelection = function() {
        var self = this;
        var selectedId = this.selectedId;

        Object.keys(this.$placementNodes).forEach(function(placementId) {
            self.$placementNodes[placementId].toggleClass(
                'is-selected',
                parseInt(placementId, 10) === selectedId
            );
        });

        if (selectedId) {
            this.$controls.removeClass('d-none');
        } else {
            this.$controls.addClass('d-none');
        }

        this.updateLayerControlsState();
    };

    HomesteadRoomEditor.prototype.scheduleDragPositionUpdate = function(placement) {
        var self = this;
        this.pendingDragPlacement = placement;

        if (this.dragRafId) {
            return;
        }

        this.dragRafId = window.requestAnimationFrame(function() {
            self.dragRafId = null;
            if (self.pendingDragPlacement) {
                self.updatePlacementPosition(self.pendingDragPlacement);
                self.pendingDragPlacement = null;
            }
        });
    };

    HomesteadRoomEditor.prototype.markDirty = function() {
        if (this.isDirty) return;
        this.isDirty = true;
        $(document).trigger('homesteadEditor:dirty');
    };

    HomesteadRoomEditor.prototype.markClean = function() {
        this.isDirty = false;
        $(document).trigger('homesteadEditor:clean');
    };

    HomesteadRoomEditor.prototype.hideLoading = function() {
        if (this.$loading.length) {
            this.$loading.addClass('d-none');
        }
    };

    HomesteadRoomEditor.prototype.updateEmptyPlaceholder = function() {
        if (!this.$placeholder.length) return;
        this.$placeholder.toggleClass('d-none', this.placements.length > 0);
    };

    HomesteadRoomEditor.prototype.getLayoutPayload = function() {
        return $.extend({}, this.layout);
    };

    HomesteadRoomEditor.prototype.loadLayout = function(initialLayout) {
        var self = this;
        this.layout = {};

        Object.keys(initialLayout || {}).forEach(function(field) {
            self.layout[field] = parseInt(initialLayout[field], 10);
        });
    };

    HomesteadRoomEditor.prototype.applySurface = function(layoutField, itemId) {
        if (!layoutField) return;

        if (this.layout[layoutField] === itemId) {
            delete this.layout[layoutField];
        } else {
            this.layout[layoutField] = itemId;
        }

        this.applySurfaceLayers();
        this.updateSurfaceSelection();
        this.markDirty();
    };

    HomesteadRoomEditor.prototype.applySurfaceLayers = function() {
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

    HomesteadRoomEditor.prototype.updateSurfaceSelection = function() {
        var self = this;

        this.$inventoryRoot.find('.homestead-editor-inventory-item.is-surface-selectable').each(function() {
            var $item = $(this);
            var layoutField = $item.data('layout-field');
            var itemId = parseInt($item.data('item-id'), 10);
            var isActive = layoutField && self.layout[layoutField] === itemId;

            $item.toggleClass('is-selected', !!isActive);
        });
    };

    HomesteadRoomEditor.prototype.getPlacements = function() {
        return this.placements.slice();
    };

    HomesteadRoomEditor.prototype.getPlacementsPayload = function() {
        return this.placements.map(function(placement) {
            var payload = {
                x: placement.x,
                y: placement.y,
                width: placement.width,
                height: placement.height,
                z_index: placement.zIndex,
            };

            if (placement.spriteId) {
                payload.character_sprite_id = placement.spriteId;
            } else {
                payload.item_id = placement.itemId;
            }

            return payload;
        });
    };

    HomesteadRoomEditor.prototype.loadPlacements = function(initialPlacements) {
        var self = this;
        this.placements = [];
        this.placedCounts = {};
        this.selectedId = null;
        this.nextId = 1;

        initialPlacements.forEach(function(placement) {
            var spriteId = placement.character_sprite_id
                ? parseInt(placement.character_sprite_id, 10)
                : null;
            var itemId = placement.item_id ? parseInt(placement.item_id, 10) : null;
            var entry = spriteId ? self.spriteCatalog[spriteId] : self.catalog[itemId];

            if (!entry) return;

            var width = parseFloat(placement.width) || entry.width;
            var height = parseFloat(placement.height) || entry.height;
            var pos = self.clampPosition(
                parseFloat(placement.x),
                parseFloat(placement.y),
                width,
                height
            );

            var record = {
                id: self.nextId++,
                x: pos.x,
                y: pos.y,
                width: width,
                height: height,
                zIndex: parseInt(placement.z_index, 10) || 10,
            };

            if (spriteId) {
                record.spriteId = spriteId;
                self.placedSpriteCounts[spriteId] = (self.placedSpriteCounts[spriteId] || 0) + 1;
            } else {
                record.itemId = itemId;
                self.placedCounts[itemId] = (self.placedCounts[itemId] || 0) + 1;
            }

            self.placements.push(record);
        });

        this.normalizeZIndices();
        this.syncPlacementIndex();
        this.render();
        this.updateInventoryCounts();
        this.applySurfaceLayers();
        this.updateSurfaceSelection();
    };

    HomesteadRoomEditor.prototype.getAvailable = function(itemId) {
        var item = this.catalog[itemId];
        if (!item) return 0;
        return item.quantity - (this.placedCounts[itemId] || 0);
    };

    HomesteadRoomEditor.prototype.getAvailableSprite = function(spriteId) {
        var sprite = this.spriteCatalog[spriteId];
        if (!sprite) return 0;
        return sprite.quantity - (this.placedSpriteCounts[spriteId] || 0);
    };

    HomesteadRoomEditor.prototype.clampPosition = function(x, y, width, height) {
        return {
            x: Math.max(0, Math.min(x, this.canvasWidth - width)),
            y: Math.max(0, Math.min(y, this.canvasHeight - height)),
        };
    };

    HomesteadRoomEditor.prototype.ensureStageDimensions = function() {
        this.$stage.css({
            width: this.canvasWidth + 'px',
            height: this.canvasHeight + 'px',
        });
    };

    HomesteadRoomEditor.prototype.observeCanvasResize = function() {
        var self = this;

        setTimeout(function() {
            self.updateScale();
        }, 0);

        setTimeout(function() {
            self.updateScale();
        }, 150);

        if (typeof ResizeObserver !== 'undefined' && this.$container.length) {
            if (this.resizeObserver) {
                this.resizeObserver.disconnect();
            }

            this.resizeObserver = new ResizeObserver(function() {
                if (self.resizeRafId) {
                    return;
                }

                self.resizeRafId = window.requestAnimationFrame(function() {
                    self.resizeRafId = null;
                    self.updateScale();
                });
            });
            this.resizeObserver.observe(this.$container[0]);
        }
    };

    HomesteadRoomEditor.prototype.updateScale = function() {
        if (!this.$container.length) return;

        this.ensureStageDimensions();

        var width = this.$container.innerWidth();
        var height = this.$container.innerHeight();
        if (!width || !height) {
            this.scale = 1;
            this.$stage.css({ transform: 'scale(1)' });
            return;
        }

        this.scale = Math.min(width / this.canvasWidth, height / this.canvasHeight);
        this.$stage.css({
            transform: 'scale(' + this.scale + ')',
        });
    };

    HomesteadRoomEditor.prototype.isPointInsideCanvas = function(clientX, clientY) {
        if (!this.$stage.length) return false;
        var rect = this.$stage[0].getBoundingClientRect();
        return clientX >= rect.left && clientX <= rect.right
            && clientY >= rect.top && clientY <= rect.bottom;
    };

    HomesteadRoomEditor.prototype.placeItemFromClientPoint = function(itemId, clientX, clientY) {
        if (this.getAvailable(itemId) <= 0) return false;

        var item = this.catalog[itemId];
        if (!item) return false;

        var coords = this.toCanvasCoords(clientX, clientY);
        this.placeItem(
            itemId,
            coords.x - (item.width / 2),
            coords.y - (item.height / 2)
        );

        return true;
    };

    HomesteadRoomEditor.prototype.placeSpriteFromClientPoint = function(spriteId, clientX, clientY) {
        if (this.getAvailableSprite(spriteId) <= 0) return false;

        var sprite = this.spriteCatalog[spriteId];
        if (!sprite) return false;

        var coords = this.toCanvasCoords(clientX, clientY);
        this.placeSprite(
            spriteId,
            coords.x - (sprite.width / 2),
            coords.y - (sprite.height / 2)
        );

        return true;
    };

    HomesteadRoomEditor.prototype.toCanvasCoords = function(clientX, clientY) {
        var rect = this.$stage[0].getBoundingClientRect();
        return {
            x: (clientX - rect.left) / this.scale,
            y: (clientY - rect.top) / this.scale,
        };
    };

    HomesteadRoomEditor.prototype.placeSprite = function(spriteId, x, y) {
        if (this.getAvailableSprite(spriteId) <= 0) return;

        var sprite = this.spriteCatalog[spriteId];
        var pos = this.clampPosition(
            typeof x === 'number' ? x : (this.canvasWidth / 2) - (sprite.width / 2),
            typeof y === 'number' ? y : (this.canvasHeight / 2) - (sprite.height / 2),
            sprite.width,
            sprite.height
        );

        var maxZ = 10;
        this.placements.forEach(function(placement) {
            if (placement.zIndex > maxZ) maxZ = placement.zIndex;
        });

        var placement = {
            id: this.nextId++,
            spriteId: spriteId,
            x: pos.x,
            y: pos.y,
            width: sprite.width,
            height: sprite.height,
            zIndex: maxZ + 1,
        };

        this.placements.push(placement);
        this.placementIndex[placement.id] = placement;
        this.placedSpriteCounts[spriteId] = (this.placedSpriteCounts[spriteId] || 0) + 1;
        this.renderPlacement(placement);
        this.updateInventoryCounts([], [spriteId]);
        this.updateEmptyPlaceholder();
        this.updateSurfaceSelection();
        this.markDirty();
    };

    HomesteadRoomEditor.prototype.placeItem = function(itemId, x, y) {
        if (this.getAvailable(itemId) <= 0) return;

        var item = this.catalog[itemId];
        var pos = this.clampPosition(
            typeof x === 'number' ? x : (this.canvasWidth / 2) - (item.width / 2),
            typeof y === 'number' ? y : (this.canvasHeight / 2) - (item.height / 2),
            item.width,
            item.height
        );

        var maxZ = 10;
        this.placements.forEach(function(placement) {
            if (placement.zIndex > maxZ) maxZ = placement.zIndex;
        });

        var placement = {
            id: this.nextId++,
            itemId: itemId,
            x: pos.x,
            y: pos.y,
            width: item.width,
            height: item.height,
            zIndex: maxZ + 1,
        };

        this.placements.push(placement);
        this.placementIndex[placement.id] = placement;
        this.placedCounts[itemId] = (this.placedCounts[itemId] || 0) + 1;
        this.renderPlacement(placement);
        this.updateInventoryCounts([itemId]);
        this.updateEmptyPlaceholder();
        this.updateSurfaceSelection();
        this.markDirty();
    };

    HomesteadRoomEditor.prototype.removePlacement = function(placementId) {
        var placement = this.getPlacementById(placementId);
        if (!placement) return;

        var itemId = placement.itemId;
        var spriteId = placement.spriteId;
        this.placements = this.placements.filter(function(entry) {
            return entry.id !== placementId;
        });
        delete this.placementIndex[placementId];

        if (itemId) {
            this.placedCounts[itemId] = Math.max(0, (this.placedCounts[itemId] || 1) - 1);
        }

        if (spriteId) {
            this.placedSpriteCounts[spriteId] = Math.max(0, (this.placedSpriteCounts[spriteId] || 1) - 1);
        }

        if (this.$placementNodes[placementId]) {
            this.$placementNodes[placementId].remove();
            delete this.$placementNodes[placementId];
        }

        if (this.selectedId === placementId) {
            this.selectedId = null;
            this.updateSelection();
        }

        this.updateInventoryCounts(itemId ? [itemId] : [], spriteId ? [spriteId] : []);
        this.updateEmptyPlaceholder();
        this.updateSurfaceSelection();
        this.markDirty();
    };

    HomesteadRoomEditor.prototype.selectPlacement = function(placementId) {
        this.selectedId = placementId;
        this.updateSelection();
    };

    HomesteadRoomEditor.prototype.clearSelection = function() {
        this.selectedId = null;
        this.updateSelection();
    };

    HomesteadRoomEditor.prototype.getSortedPlacements = function() {
        return this.placements.slice().sort(function(a, b) {
            if (a.zIndex !== b.zIndex) {
                return a.zIndex - b.zIndex;
            }
            return a.id - b.id;
        });
    };

    HomesteadRoomEditor.prototype.findPlacementSortIndex = function(sorted, placementId) {
        for (var i = 0; i < sorted.length; i++) {
            if (sorted[i].id === placementId) {
                return i;
            }
        }
        return -1;
    };

    HomesteadRoomEditor.prototype.normalizeZIndices = function() {
        var sorted = this.getSortedPlacements();
        this.applyZOrderFromSorted(sorted);
    };

    HomesteadRoomEditor.prototype.applyZOrderFromSorted = function(sorted) {
        for (var i = 0; i < sorted.length; i++) {
            sorted[i].zIndex = 10 + i;
            this.updatePlacementZIndex(sorted[i]);
        }

        for (var j = 0; j < sorted.length; j++) {
            var $node = this.$placementNodes[sorted[j].id];
            if ($node && $node.length) {
                this.$items.append($node);
            }
        }
    };

    HomesteadRoomEditor.prototype.updatePlacementZIndex = function(placement) {
        var $node = this.$placementNodes[placement.id];
        if ($node && $node.length) {
            $node.css('zIndex', placement.zIndex);
        }
    };

    HomesteadRoomEditor.prototype.moveLayerForward = function(placementId) {
        var sorted = this.getSortedPlacements();
        var index = this.findPlacementSortIndex(sorted, placementId);
        if (index < 0 || index >= sorted.length - 1) {
            return false;
        }

        var moved = sorted[index + 1];
        sorted[index + 1] = sorted[index];
        sorted[index] = moved;

        this.applyZOrderFromSorted(sorted);
        this.updateLayerControlsState();
        this.markDirty();
        return true;
    };

    HomesteadRoomEditor.prototype.moveLayerBackward = function(placementId) {
        var sorted = this.getSortedPlacements();
        var index = this.findPlacementSortIndex(sorted, placementId);
        if (index <= 0) {
            return false;
        }

        var moved = sorted[index - 1];
        sorted[index - 1] = sorted[index];
        sorted[index] = moved;

        this.applyZOrderFromSorted(sorted);
        this.updateLayerControlsState();
        this.markDirty();
        return true;
    };

    HomesteadRoomEditor.prototype.getLayerAction = function($button) {
        return $button.attr('data-layer-action') || $button.data('layerAction');
    };

    HomesteadRoomEditor.prototype.updateLayerControlsState = function() {
        var self = this;
        var sorted = this.getSortedPlacements();
        var index = this.selectedId
            ? this.findPlacementSortIndex(sorted, this.selectedId)
            : -1;
        var canForward = index >= 0 && index < sorted.length - 1;
        var canBackward = index > 0;

        if (this.selectedId) {
            var $selected = this.$placementNodes[this.selectedId];
            if ($selected && $selected.length) {
                $selected.find('[data-layer-action="forward"]').prop('disabled', !canForward);
                $selected.find('[data-layer-action="backward"]').prop('disabled', !canBackward);
            }
        }

        this.$controls.find('[data-editor-action="forward"]').prop('disabled', !canForward);
        this.$controls.find('[data-editor-action="backward"]').prop('disabled', !canBackward);
    };

    HomesteadRoomEditor.prototype.handleLayerAction = function(placementId, action) {
        if (action === 'forward') {
            this.moveLayerForward(placementId);
        } else if (action === 'backward') {
            this.moveLayerBackward(placementId);
        }
    };

    HomesteadRoomEditor.prototype.createPlacementNode = function(placement) {
        var entry = this.getPlacementEntry(placement);
        if (!entry) return null;

        var $node = $('<div class="homestead-editor-placed-item"></div>');
        $node.attr({
            'data-placement-id': placement.id,
        });

        if (placement.spriteId) {
            $node.attr('data-sprite-id', placement.spriteId);
        } else {
            $node.attr('data-item-id', placement.itemId);
        }
        $node.css({
            left: placement.x + 'px',
            top: placement.y + 'px',
            width: placement.width + 'px',
            height: placement.height + 'px',
            zIndex: placement.zIndex,
        });

        if (this.selectedId === placement.id) {
            $node.addClass('is-selected');
        }

        if (entry.hasImage && entry.imageUrl) {
            $('<img>', {
                src: entry.imageUrl,
                alt: entry.name,
                draggable: false,
                loading: 'lazy',
                decoding: 'async',
            }).appendTo($node);
        } else {
            $('<div class="homestead-editor-placed-item-fallback"><i class="fas fa-cube"></i></div>').appendTo($node);
        }

        var $layerControls = $('<div class="homestead-editor-placement-layer-controls" role="toolbar" aria-label="Layer controls"></div>');
        $layerControls.append(
            $('<button type="button" class="homestead-editor-layer-btn" data-layer-action="forward" title="Move Forward" aria-label="Move Forward"><i class="fas fa-arrow-up"></i></button>'),
            $('<button type="button" class="homestead-editor-layer-btn" data-layer-action="backward" title="Move Backward" aria-label="Move Backward"><i class="fas fa-arrow-down"></i></button>')
        );
        $node.append($layerControls);

        return $node;
    };

    HomesteadRoomEditor.prototype.renderPlacement = function(placement) {
        var $node = this.createPlacementNode(placement);
        if (!$node) return;

        this.$placementNodes[placement.id] = $node;
        this.$items.append($node);
    };

    HomesteadRoomEditor.prototype.render = function() {
        var self = this;
        var sorted = this.placements.slice().sort(function(a, b) {
            return a.zIndex - b.zIndex;
        });
        var fragment = document.createDocumentFragment();

        this.$items.empty();
        this.$placementNodes = {};

        sorted.forEach(function(placement) {
            var $node = self.createPlacementNode(placement);
            if (!$node) {
                return;
            }

            self.$placementNodes[placement.id] = $node;
            fragment.appendChild($node[0]);
        });

        if (this.$items.length) {
            this.$items[0].appendChild(fragment);
        }

        this.updateSelection();
        this.updateEmptyPlaceholder();
        this.updateLayerControlsState();
    };

    HomesteadRoomEditor.prototype.updateInventoryCounts = function(itemIds, spriteIds) {
        var self = this;
        var ids = itemIds;
        var spriteIdList = spriteIds;

        if (!ids || !ids.length) {
            ids = Object.keys(this.$inventoryByItemId).map(function(itemId) {
                return parseInt(itemId, 10);
            }).filter(Boolean);
        }

        if (!spriteIdList || !spriteIdList.length) {
            spriteIdList = Object.keys(this.$inventoryBySpriteId).map(function(spriteId) {
                return parseInt(spriteId, 10);
            }).filter(Boolean);
        }

        ids.forEach(function(itemId) {
            if (!itemId) return;
            var $item = self.$inventoryByItemId[itemId];
            if (!$item || !$item.length || !$item.hasClass('is-placeable')) return;

            var available = self.getAvailable(itemId);
            var total = self.catalog[itemId] ? self.catalog[itemId].quantity : 0;
            var placed = self.placedCounts[itemId] || 0;

            $item.toggleClass('is-unavailable', available <= 0);

            if (placed > 0) {
                $item.find('.homestead-editor-inventory-item-qty').last().text(available + ' / ' + total);
            } else {
                $item.find('.homestead-editor-inventory-item-qty').last().text('x' + total);
            }
        });

        spriteIdList.forEach(function(spriteId) {
            if (!spriteId) return;
            var $sprite = self.$inventoryBySpriteId[spriteId];
            if (!$sprite || !$sprite.length || !$sprite.hasClass('is-sprite-placeable')) return;

            var available = self.getAvailableSprite(spriteId);
            var total = self.spriteCatalog[spriteId] ? self.spriteCatalog[spriteId].quantity : 0;
            var placed = self.placedSpriteCounts[spriteId] || 0;

            $sprite.toggleClass('is-unavailable', available <= 0);

            if (placed > 0) {
                $sprite.find('.homestead-editor-inventory-item-qty').last().text(available + ' / ' + total);
            } else {
                $sprite.find('.homestead-editor-inventory-item-qty').last().text('x' + total);
            }
        });
    };

    HomesteadRoomEditor.prototype.bindEvents = function() {
        var self = this;

        $(window).on('resize.homesteadEditor', function() {
            self.updateScale();
        });

        this.$inventoryRoot.on('click', '.homestead-editor-inventory-item.is-sprite-placeable', function(e) {
            e.preventDefault();
            var $sprite = $(this);
            if ($sprite.hasClass('is-unavailable')) return;
            if (self.inventoryDrag && self.inventoryDrag.moved) return;
            self.placeSprite(parseInt($sprite.data('sprite-id'), 10));
        });

        this.$inventoryRoot.on('mousedown touchstart', '.homestead-editor-inventory-item.is-sprite-placeable', function(e) {
            if (e.type === 'mousedown' && e.which !== 1) return;
            var $sprite = $(this);
            if ($sprite.hasClass('is-unavailable')) return;

            var point = self.getEventPoint(e);
            self.inventoryDrag = {
                spriteId: parseInt($sprite.data('sprite-id'), 10),
                startX: point.x,
                startY: point.y,
                moved: false,
            };
        });

        this.$inventoryRoot.on('click', '.homestead-editor-inventory-item.is-placeable', function(e) {
            e.preventDefault();
            var $item = $(this);
            if ($item.hasClass('is-unavailable')) return;
            if (self.inventoryDrag && self.inventoryDrag.moved) return;
            self.placeItem(parseInt($item.data('item-id'), 10));
        });

        this.$inventoryRoot.on('mousedown touchstart', '.homestead-editor-inventory-item.is-placeable', function(e) {
            if (e.type === 'mousedown' && e.which !== 1) return;
            var $item = $(this);
            if ($item.hasClass('is-unavailable')) return;

            var point = self.getEventPoint(e);
            self.inventoryDrag = {
                itemId: parseInt($item.data('item-id'), 10),
                startX: point.x,
                startY: point.y,
                moved: false,
            };
        });

        $(document).on('mousemove.homesteadEditorInventory touchmove.homesteadEditorInventory', function(e) {
            if (!self.inventoryDrag) return;

            var point = self.getEventPoint(e);
            if (Math.abs(point.x - self.inventoryDrag.startX) > 4
                || Math.abs(point.y - self.inventoryDrag.startY) > 4) {
                self.inventoryDrag.moved = true;
            }
        });

        $(document).on('mouseup.homesteadEditorInventory touchend.homesteadEditorInventory touchcancel.homesteadEditorInventory', function(e) {
            if (!self.inventoryDrag) return;

            if (self.inventoryDrag.moved) {
                var point = self.getEventPoint(e);
                if (self.isPointInsideCanvas(point.x, point.y)) {
                    if (self.inventoryDrag.spriteId) {
                        self.placeSpriteFromClientPoint(self.inventoryDrag.spriteId, point.x, point.y);
                    } else if (self.inventoryDrag.itemId) {
                        self.placeItemFromClientPoint(self.inventoryDrag.itemId, point.x, point.y);
                    }
                }
            }

            self.inventoryDrag = null;
        });

        this.$inventoryRoot.on('click', '.homestead-editor-inventory-item.is-surface-selectable', function(e) {
            e.preventDefault();
            var $item = $(this);
            if ($item.hasClass('is-unavailable')) return;
            self.applySurface($item.data('layout-field'), parseInt($item.data('item-id'), 10));
        });

        this.$items.on('mousedown touchstart', '.homestead-editor-placed-item', function(e) {
            if ($(e.target).closest('.homestead-editor-layer-btn').length) {
                return;
            }
            if (e.type === 'mousedown' && e.which !== 1) return;
            e.preventDefault();
            e.stopPropagation();

            var placementId = parseInt($(this).data('placement-id'), 10);
            var placement = self.getPlacementById(placementId);
            if (!placement) return;

            var point = self.getEventPoint(e);
            var coords = self.toCanvasCoords(point.x, point.y);

            self.selectPlacement(placementId);
            self.dragState = {
                placementId: placementId,
                offsetX: coords.x - placement.x,
                offsetY: coords.y - placement.y,
                startX: placement.x,
                startY: placement.y,
            };
        });

        $(document).on('mousemove.homesteadEditor touchmove.homesteadEditor', function(e) {
            if (!self.dragState) return;
            e.preventDefault();

            var placement = self.getPlacementById(self.dragState.placementId);
            if (!placement) return;

            var point = self.getEventPoint(e);
            var coords = self.toCanvasCoords(point.x, point.y);
            var pos = self.clampPosition(
                coords.x - self.dragState.offsetX,
                coords.y - self.dragState.offsetY,
                placement.width,
                placement.height
            );

            placement.x = pos.x;
            placement.y = pos.y;
            self.scheduleDragPositionUpdate(placement);
        });

        $(document).on('mouseup.homesteadEditor touchend.homesteadEditor touchcancel.homesteadEditor', function() {
            if (self.dragState) {
                var placement = self.getPlacementById(self.dragState.placementId);

                if (placement
                    && (placement.x !== self.dragState.startX || placement.y !== self.dragState.startY)) {
                    self.markDirty();
                }
            }

            self.pendingDragPlacement = null;
            if (self.dragRafId) {
                window.cancelAnimationFrame(self.dragRafId);
                self.dragRafId = null;
            }

            self.dragState = null;
        });

        this.$stage.on('mousedown touchstart', function(e) {
            if ($(e.target).closest('.homestead-editor-placed-item').length) return;
            self.clearSelection();
        });

        this.$items.on('mousedown touchstart', '.homestead-editor-layer-btn', function(e) {
            e.stopPropagation();
        });

        this.$items.on('click', '.homestead-editor-layer-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();

            if ($(this).prop('disabled')) {
                return;
            }

            var placementId = parseInt($(this).closest('.homestead-editor-placed-item').data('placement-id'), 10);
            self.handleLayerAction(placementId, self.getLayerAction($(this)));
        });

        this.$controls.on('click', '[data-editor-action]', function(e) {
            e.preventDefault();
            if (!self.selectedId || $(this).prop('disabled')) return;

            var action = $(this).attr('data-editor-action') || $(this).data('editorAction');
            if (action === 'remove') {
                self.removePlacement(self.selectedId);
            } else if (action === 'forward') {
                self.moveLayerForward(self.selectedId);
            } else if (action === 'backward') {
                self.moveLayerBackward(self.selectedId);
            }
        });

        $(document).on('keydown.homesteadEditor', function(e) {
            if (!self.selectedId) return;
            if (e.key === 'Delete' || e.key === 'Backspace') {
                if ($(e.target).is('input, textarea')) return;
                e.preventDefault();
                self.removePlacement(self.selectedId);
            }
        });
    };

    HomesteadRoomEditor.prototype.getEventPoint = function(e) {
        if (e.originalEvent && e.originalEvent.touches && e.originalEvent.touches.length) {
            return {
                x: e.originalEvent.touches[0].clientX,
                y: e.originalEvent.touches[0].clientY,
            };
        }
        if (e.originalEvent && e.originalEvent.changedTouches && e.originalEvent.changedTouches.length) {
            return {
                x: e.originalEvent.changedTouches[0].clientX,
                y: e.originalEvent.changedTouches[0].clientY,
            };
        }
        return {
            x: e.clientX,
            y: e.clientY,
        };
    };

    window.HomesteadRoomEditor = HomesteadRoomEditor;
    window.HomesteadEditor = HomesteadRoomEditor;
})(jQuery);
