// AMD module: theme_learnovatheme/cat_settings
// Progressively enhances the category admin-setting (admin_setting_catitems)
// into a dynamic add / remove / reorder form. The backing textarea holds a JSON
// array of {catid, icon}; this module renders one editable row per entry, keeps
// the textarea in sync on every change, and enforces the max-item cap.
//
// The category is a native <select>. The icon is chosen via a shared modal grid
// that renders the real SVG icons (search + section grouping), so the admin
// sees the actual artwork rather than a text dropdown.
define([], function() {
    'use strict';

    function str(value) {
        return value === null || value === undefined ? '' : String(value);
    }

    function enhance(container) {
        var textarea = container.querySelector('.learnova-catset__data');
        var rows     = container.querySelector('.learnova-catset__rows');
        var addBtn   = container.querySelector('.learnova-catset__add');
        var note     = container.querySelector('.learnova-catset__note');
        if (!textarea || !rows || !addBtn) {
            return;
        }

        // The cap is "streamed" live from the cats_max setting field on the same
        // page, so changing it reflows the form without saving first. data-max
        // is the server-resolved value (used as the fallback / default).
        var maxDefault = parseInt(container.dataset.max, 10) || 5;
        var minLimit   = parseInt(container.dataset.minLimit, 10) || 2;
        var maxLimit   = parseInt(container.dataset.maxLimit, 10) || 10;
        var maxField   = document.getElementById('id_s_theme_learnovatheme_cats_max');

        // Reads the effective max: the live field value clamped to the
        // minLimit..maxLimit range, or the server default when unreadable.
        function getMax() {
            if (maxField) {
                var raw = parseInt(maxField.value, 10);
                if (!isNaN(raw)) {
                    return Math.min(Math.max(raw, minLimit), maxLimit);
                }
            }
            return maxDefault;
        }

        var catLabel   = container.dataset.catLabel    || 'Category';
        var iconLabel  = container.dataset.iconLabel   || 'Icon';
        var pickLabel  = container.dataset.pickerLabel || 'Choose icon';
        var searchPh   = container.dataset.searchPh    || 'Search icons…';
        var colorLabel = container.dataset.colorLabel  || 'Colour';
        var colorHint  = container.dataset.colorHint   || '';
        var resetLabel = container.dataset.resetLabel  || 'Default';
        var labels = {
            remove: container.dataset.removeLabel || 'Remove',
            up:     container.dataset.upLabel     || 'Move up',
            down:   container.dataset.downLabel   || 'Move down'
        };

        // Cycling default colours — must match categories::DEFAULT_COLORS (PHP),
        // so the live preview matches the rendered front page.
        var DEFAULT_COLORS = ['#006f6c', '#e89400', '#1e40af', '#3b6d11', '#9d174d'];

        // Light tile tint from a hex colour (mirrors categories::tint, weight .12).
        function tint(hex) {
            hex = hex.replace('#', '');
            if (hex.length === 3) {
                hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
            }
            var w = 0.12;
            function mix(c) { return Math.round(c * w + 255 * (1 - w)); }
            var r = mix(parseInt(hex.substr(0, 2), 16));
            var g = mix(parseInt(hex.substr(2, 2), 16));
            var b = mix(parseInt(hex.substr(4, 2), 16));
            function hx(n) { return ('0' + n.toString(16)).slice(-2); }
            return '#' + hx(r) + hx(g) + hx(b);
        }

        // The default colour for a row at position i.
        function defaultColor(i) {
            return DEFAULT_COLORS[i % DEFAULT_COLORS.length];
        }

        var categories, icons;
        try { categories = JSON.parse(container.dataset.categories || '[]'); } catch (e) { categories = []; }
        try { icons      = JSON.parse(container.dataset.icons      || '[]'); } catch (e) { icons = []; }

        // Fast lookup: slug -> icon meta {key, label, section, sectionlabel, svg}.
        var iconMap = {};
        icons.forEach(function(ic) { iconMap[ic.key] = ic; });
        var defaultIcon = icons.length ? icons[0].key : '';

        var data;
        try { data = JSON.parse(textarea.value || '[]'); } catch (e) { data = []; }
        if (!Array.isArray(data)) { data = []; }

        // ── Shared icon-picker modal (one per control) ────────────────────
        var picker = buildPicker();
        var activeButton = null; // The row icon-button currently picking.

        function rowEls() {
            return rows.querySelectorAll('.learnova-catset__row');
        }

        function serialize() {
            var items = [];
            rowEls().forEach(function(row) {
                var catid = parseInt(row.querySelector('.learnova-catset__cat').value, 10);
                var icon  = row.querySelector('.learnova-catset__iconbtn').dataset.value;
                if (catid > 0) {
                    var entry = { catid: catid, icon: icon };
                    // Store a colour only when the row is in custom mode (the
                    // admin touched the swatch). Otherwise leave it out so the
                    // front page uses the cycling default palette.
                    var color = row.querySelector('.learnova-catset__color');
                    if (row.dataset.colorCustom === '1' && color) {
                        entry.color = color.value;
                    }
                    items.push(entry);
                }
            });
            textarea.value = JSON.stringify(items);
            refresh();
        }

        function refresh() {
            var max = getMax();
            var count = rowEls().length;
            addBtn.disabled = count >= max;
            if (note) { note.hidden = count < max; }
            rowEls().forEach(function(row, i) {
                row.querySelector('.learnova-catset__num').textContent = '#' + (i + 1);
                paintColorRow(row, i);
            });
            refreshCatOptions();
        }

        // Drops any rows beyond the current cap (e.g. cats_max lowered from 5 to
        // 2), then re-syncs. Called when the cats_max field changes.
        function enforceMax() {
            var max = getMax();
            var list = rowEls();
            for (var i = list.length - 1; i >= max; i--) {
                list[i].remove();
            }
            serialize();
        }

        // Paints the row's effective colour (custom swatch or position default)
        // onto the icon-button tile preview, syncs the visible swatch value, and
        // shows the "Default" reset link only while a custom colour is active.
        function paintColorRow(row, i) {
            var color = row.querySelector('.learnova-catset__color');
            var btn   = row.querySelector('.learnova-catset__iconbtn');
            var reset = row.querySelector('.learnova-catset__colorreset');
            if (!color || !btn) { return; }

            var custom = row.dataset.colorCustom === '1';
            var effective = custom ? color.value : defaultColor(i);

            // In default mode, keep the always-visible swatch showing the colour
            // that would actually be used, so it's never an arbitrary value.
            if (!custom) { color.value = effective; }
            if (reset) { reset.hidden = !custom; }

            var glyph = btn.querySelector('.learnova-catset__iconbtn-glyph');
            if (glyph) {
                glyph.style.background = tint(effective);
                glyph.style.color = effective;
            }
        }

        function buildCatSelect(selectedVal) {
            var sel = document.createElement('select');
            sel.className = 'form-control form-select learnova-catset__cat';
            categories.forEach(function(opt) {
                var o = document.createElement('option');
                o.value = str(opt.id);
                o.textContent = str(opt.name);
                if (str(opt.id) === str(selectedVal)) { o.selected = true; }
                sel.appendChild(o);
            });
            return sel;
        }

        // The id of the first category not yet chosen in any row, or '' if all
        // are taken. Used to seed a freshly added row.
        function firstFreeCat() {
            var taken = {};
            rowEls().forEach(function(row) {
                var v = row.querySelector('.learnova-catset__cat').value;
                if (v && v !== '0') { taken[v] = true; }
            });
            for (var i = 0; i < categories.length; i++) {
                if (!taken[String(categories[i].id)]) { return String(categories[i].id); }
            }
            return '';
        }

        // Hides categories already chosen in other rows, so each category can be
        // picked only once. A row always keeps its own current selection. Called
        // on every refresh (after add / remove / reorder / change).
        function refreshCatOptions() {
            var taken = {};
            rowEls().forEach(function(row) {
                var v = row.querySelector('.learnova-catset__cat').value;
                if (v && v !== '0') { taken[v] = true; }
            });
            rowEls().forEach(function(row) {
                var sel = row.querySelector('.learnova-catset__cat');
                var own = sel.value;
                Array.prototype.forEach.call(sel.options, function(o) {
                    // Show the option only if it's free or it's this row's own pick.
                    o.hidden = (o.value !== own && taken[o.value] === true);
                });
            });
        }

        // Renders the chosen icon (svg + label) inside a row's picker button.
        // The glyph keeps any inline colour styles set by paintColorRow.
        function paintIconButton(btn) {
            var meta = iconMap[btn.dataset.value];
            var glyph = btn.querySelector('.learnova-catset__iconbtn-glyph');
            var prevBg = glyph ? glyph.style.background : '';
            var prevColor = glyph ? glyph.style.color : '';
            btn.innerHTML = '';
            glyph = document.createElement('span');
            glyph.className = 'learnova-catset__iconbtn-glyph';
            glyph.innerHTML = meta ? meta.svg : '';
            glyph.style.background = prevBg;
            glyph.style.color = prevColor;
            var text = document.createElement('span');
            text.className = 'learnova-catset__iconbtn-text';
            text.textContent = meta ? meta.label : pickLabel;
            btn.appendChild(glyph);
            btn.appendChild(text);
        }

        function addRow(item) {
            item = item || {};
            // For a brand-new row (no stored catid), preselect the first category
            // not already taken, so two empty rows don't default to the same one.
            var selectedCat  = item.catid ? String(item.catid) : firstFreeCat();
            var selectedIcon = (item.icon && iconMap[item.icon]) ? item.icon : defaultIcon;

            var row = document.createElement('div');
            row.className = 'learnova-catset__row border rounded p-2 mb-2 bg-white';

            // Header: counter + action buttons.
            var head = document.createElement('div');
            head.className = 'd-flex align-items-center justify-content-between mb-2';
            head.innerHTML =
                '<span class="learnova-catset__num fw-semibold text-muted small"></span>' +
                '<span>' +
                    '<button type="button" class="btn btn-link btn-sm py-0 learnova-catset__up"' +
                        ' title="' + str(labels.up) + '" aria-label="' + str(labels.up) + '">&uarr;</button>' +
                    '<button type="button" class="btn btn-link btn-sm py-0 learnova-catset__down"' +
                        ' title="' + str(labels.down) + '" aria-label="' + str(labels.down) + '">&darr;</button>' +
                    '<button type="button" class="btn btn-link btn-sm py-0 text-danger learnova-catset__remove">' +
                        str(labels.remove) + '</button>' +
                '</span>';
            row.appendChild(head);

            // Category select.
            var catWrap = document.createElement('div');
            catWrap.className = 'mb-2';
            var catLbl = document.createElement('label');
            catLbl.className = 'form-label small mb-1';
            catLbl.textContent = catLabel;
            catWrap.appendChild(catLbl);
            catWrap.appendChild(buildCatSelect(selectedCat));
            row.appendChild(catWrap);

            // Icon picker button.
            var iconWrap = document.createElement('div');
            var iconLbl = document.createElement('label');
            iconLbl.className = 'form-label small mb-1 d-block';
            iconLbl.textContent = iconLabel;
            iconWrap.appendChild(iconLbl);

            var iconBtn = document.createElement('button');
            iconBtn.type = 'button';
            iconBtn.className = 'btn btn-outline-secondary learnova-catset__iconbtn';
            iconBtn.dataset.value = selectedIcon;
            paintIconButton(iconBtn);
            iconWrap.appendChild(iconBtn);
            row.appendChild(iconWrap);

            // Colour field: an always-visible swatch + a "Default" reset link.
            // Touching the swatch switches the row to custom mode; "Default"
            // clears it back to the cycling palette. Custom state is tracked on
            // the row via data-color-custom so serialize() knows whether to save.
            var hasCustom = typeof item.color === 'string' && item.color !== '';
            row.dataset.colorCustom = hasCustom ? '1' : '0';

            var colorWrap = document.createElement('div');
            colorWrap.className = 'learnova-catset__colorwrap mt-2';

            var colorLbl = document.createElement('label');
            colorLbl.className = 'form-label small mb-1 d-block';
            colorLbl.textContent = colorLabel;
            colorWrap.appendChild(colorLbl);

            var line = document.createElement('div');
            line.className = 'd-flex align-items-center gap-2';

            var colorInput = document.createElement('input');
            colorInput.type = 'color';
            colorInput.className = 'form-control form-control-color learnova-catset__color';
            // Seed with the saved colour, or this row's position default so the
            // swatch always shows the colour that is actually in effect.
            colorInput.value = hasCustom ? item.color : defaultColor(rows.children.length);

            var resetBtn = document.createElement('button');
            resetBtn.type = 'button';
            resetBtn.className = 'btn btn-link btn-sm p-0 learnova-catset__colorreset';
            resetBtn.textContent = resetLabel;
            resetBtn.hidden = !hasCustom;

            line.appendChild(colorInput);
            line.appendChild(resetBtn);
            colorWrap.appendChild(line);

            if (colorHint) {
                var hint = document.createElement('small');
                hint.className = 'text-muted d-block mt-1';
                hint.textContent = colorHint;
                colorWrap.appendChild(hint);
            }

            row.appendChild(colorWrap);

            rows.appendChild(row);
        }

        // ── Picker modal construction ─────────────────────────────────────
        function buildPicker() {
            var overlay = document.createElement('div');
            overlay.className = 'learnova-iconpicker';
            overlay.hidden = true;
            overlay.innerHTML =
                '<div class="learnova-iconpicker__dialog" role="dialog" aria-modal="true">' +
                    '<div class="learnova-iconpicker__head">' +
                        '<strong class="learnova-iconpicker__title">' + str(pickLabel) + '</strong>' +
                        '<button type="button" class="btn-close learnova-iconpicker__close" aria-label="Close"></button>' +
                    '</div>' +
                    '<div class="learnova-iconpicker__search">' +
                        '<input type="text" class="form-control learnova-iconpicker__searchinput" placeholder="' +
                            str(searchPh) + '">' +
                    '</div>' +
                    '<div class="learnova-iconpicker__grid"></div>' +
                '</div>';

            var grid = overlay.querySelector('.learnova-iconpicker__grid');
            var search = overlay.querySelector('.learnova-iconpicker__searchinput');

            // Build the full grid once, grouped by section.
            var lastSection = null;
            icons.forEach(function(ic) {
                if (ic.section !== lastSection) {
                    lastSection = ic.section;
                    var h = document.createElement('div');
                    h.className = 'learnova-iconpicker__section';
                    h.textContent = ic.sectionlabel;
                    h.dataset.section = ic.section;
                    grid.appendChild(h);
                    var wrap = document.createElement('div');
                    wrap.className = 'learnova-iconpicker__cards';
                    wrap.dataset.section = ic.section;
                    grid.appendChild(wrap);
                }
                var cards = grid.querySelector('.learnova-iconpicker__cards[data-section="' + ic.section + '"]');
                var card = document.createElement('button');
                card.type = 'button';
                card.className = 'learnova-iconpicker__card';
                card.dataset.key = ic.key;
                card.dataset.search = (ic.label + ' ' + ic.key).toLowerCase();
                card.title = ic.label;
                card.innerHTML =
                    '<span class="learnova-iconpicker__glyph">' + ic.svg + '</span>' +
                    '<span class="learnova-iconpicker__label">' + str(ic.label) + '</span>';
                cards.appendChild(card);
            });

            function close() {
                overlay.hidden = true;
                activeButton = null;
                search.value = '';
                filter('');
            }

            function filter(term) {
                term = term.toLowerCase().trim();
                grid.querySelectorAll('.learnova-iconpicker__card').forEach(function(card) {
                    card.hidden = term !== '' && card.dataset.search.indexOf(term) === -1;
                });
                // Hide a section header when all its cards are filtered out.
                grid.querySelectorAll('.learnova-iconpicker__cards').forEach(function(wrap) {
                    var anyVisible = wrap.querySelector('.learnova-iconpicker__card:not([hidden])');
                    wrap.hidden = !anyVisible;
                    var header = grid.querySelector('.learnova-iconpicker__section[data-section="' +
                        wrap.dataset.section + '"]');
                    if (header) { header.hidden = !anyVisible; }
                });
            }

            search.addEventListener('input', function() { filter(search.value); });

            grid.addEventListener('click', function(e) {
                var card = e.target.closest('.learnova-iconpicker__card');
                if (!card || !activeButton) { return; }
                activeButton.dataset.value = card.dataset.key;
                paintIconButton(activeButton);
                serialize();
                close();
            });

            overlay.addEventListener('click', function(e) {
                if (e.target === overlay || e.target.closest('.learnova-iconpicker__close')) {
                    close();
                }
            });
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && !overlay.hidden) { close(); }
            });

            document.body.appendChild(overlay);
            return {
                open: function(btn) {
                    activeButton = btn;
                    overlay.hidden = false;
                    // Highlight the current selection.
                    grid.querySelectorAll('.learnova-iconpicker__card').forEach(function(c) {
                        c.classList.toggle('is-selected', c.dataset.key === btn.dataset.value);
                    });
                    search.focus();
                }
            };
        }

        // ── Initial render ────────────────────────────────────────────────
        rows.textContent = '';
        data.slice(0, getMax()).forEach(addRow);
        refresh();

        // Stream changes from the cats_max field: trim excess rows and update
        // the Add button / note live, without needing to save the page first.
        if (maxField) {
            maxField.addEventListener('input', enforceMax);
            maxField.addEventListener('change', enforceMax);
        }

        addBtn.addEventListener('click', function() {
            if (rowEls().length >= getMax()) { return; }
            addRow();
            serialize();
        });

        rows.addEventListener('click', function(e) {
            var row;
            if (e.target.closest('.learnova-catset__remove')) {
                row = e.target.closest('.learnova-catset__row');
                if (row) { row.remove(); serialize(); }
            } else if (e.target.closest('.learnova-catset__up')) {
                row = e.target.closest('.learnova-catset__row');
                if (row && row.previousElementSibling) {
                    rows.insertBefore(row, row.previousElementSibling);
                    serialize();
                }
            } else if (e.target.closest('.learnova-catset__down')) {
                row = e.target.closest('.learnova-catset__row');
                if (row && row.nextElementSibling) {
                    rows.insertBefore(row.nextElementSibling, row);
                    serialize();
                }
            } else if (e.target.closest('.learnova-catset__iconbtn')) {
                picker.open(e.target.closest('.learnova-catset__iconbtn'));
            } else if (e.target.closest('.learnova-catset__colorreset')) {
                // Clear back to the cycling default palette.
                row = e.target.closest('.learnova-catset__row');
                if (row) {
                    row.dataset.colorCustom = '0';
                    serialize();
                }
            }
        });

        rows.addEventListener('change', function(e) {
            if (e.target.closest('.learnova-catset__cat')) {
                serialize();
            } else if (e.target.closest('.learnova-catset__color')) {
                // Touching the swatch commits a custom colour for that row.
                var crow = e.target.closest('.learnova-catset__row');
                if (crow) { crow.dataset.colorCustom = '1'; }
                serialize();
            }
        });

        // Live preview while dragging the colour input (input fires continuously).
        rows.addEventListener('input', function(e) {
            if (e.target.closest('.learnova-catset__color')) {
                var row = e.target.closest('.learnova-catset__row');
                row.dataset.colorCustom = '1';
                var i = Array.prototype.indexOf.call(rowEls(), row);
                paintColorRow(row, i);
            }
        });

        var form = container.closest('form');
        if (form) { form.addEventListener('submit', serialize); }

        textarea.hidden = true;
    }

    return {
        init: function() {
            document.querySelectorAll('.learnova-catset').forEach(enhance);
        }
    };
});
