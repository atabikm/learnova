<?php
namespace theme_learnovatheme;

defined('MOODLE_INTERNAL') || die();

/**
 * "Browse by Category" front-page section.
 *
 * Data is pulled from Moodle's native course categories. The admin configures
 * which categories appear, in which order, and which icon each one uses via the
 * dynamic cats_items setting in Home Settings (stored as a JSON array of
 * {catid, icon}). Each resolved card carries the category name, live course
 * count, icon SVG and a link into the catalogue.
 */
class categories {

    /** Default maximum number of category cards (admin-overridable via cats_max). */
    public const SLOTS = 5;

    /** Lower bound for the admin-configurable maximum. */
    public const MIN_LIMIT = 2;

    /** Upper bound for the admin-configurable maximum. */
    public const MAX_LIMIT = 10;

    /**
     * The effective maximum number of category cards, from the cats_max admin
     * setting. Falls back to {@see self::SLOTS} when unset, and is clamped to
     * the MIN_LIMIT..MAX_LIMIT range.
     *
     * @return int
     */
    public static function max(): int {
        $value = get_config('theme_learnovatheme', 'cats_max');
        if ($value === false || $value === '') {
            return self::SLOTS;
        }
        $value = (int) $value;
        if ($value < self::MIN_LIMIT) {
            return self::MIN_LIMIT;
        }
        return min($value, self::MAX_LIMIT);
    }

    /** Most columns the card grid ever uses (design: max 5 cols, max 2 rows). */
    public const GRID_MAX_COLS = 5;

    /**
     * Number of grid columns for a given card count, per the card-layout design:
     * up to GRID_MAX_COLS the cards sit on a single row; beyond that they split
     * across two balanced rows, so columns = ceil(n / 2) capped at GRID_MAX_COLS.
     *
     * e.g. 2→2, 3→3, 4→4, 5→5, 6→3, 7→4, 8→4, 9→5, 10→5.
     *
     * @param int $count Number of cards.
     * @return int Column count (at least 1).
     */
    public static function column_count(int $count): int {
        if ($count <= 1) {
            return 1;
        }
        if ($count <= self::GRID_MAX_COLS) {
            return $count;
        }
        return min((int) ceil($count / 2), self::GRID_MAX_COLS);
    }

    /**
     * Options for the cats_max dropdown: each allowed value mapped to itself.
     *
     * @return array<int, int>
     */
    public static function max_options(): array {
        $options = [];
        for ($i = self::MIN_LIMIT; $i <= self::MAX_LIMIT; $i++) {
            $options[$i] = $i;
        }
        return $options;
    }

    /**
     * Default icon colours used when a card has no explicit colour, cycled by
     * position. Mirrors the v7_4 mockup's per-card palette (teal, amber, blue,
     * green, pink). Each is the full/dark icon colour; the tile is derived as a
     * light tint of it.
     */
    private const DEFAULT_COLORS = ['#006F6C', '#E89400', '#1E40AF', '#3B6D11', '#9D174D'];

    /**
     * Returns the resolved cards for the section, in stored order.
     *
     * Entries pointing at a missing or invisible category are skipped.
     * Duplicate cat IDs are ignored. Falls back to migrating old slot-based
     * settings on first call after upgrade.
     *
     * @return array<int, array{name: string, count: string, countnum: int,
     *     url: string, iconsvg: string, hasicon: bool}>
     */
    public static function cards(): array {
        $raw = get_config('theme_learnovatheme', 'cats_items');

        // One-time migration: if the new JSON setting is empty but old slot
        // configs exist, import them transparently on first read.
        if (($raw === false || $raw === '') && self::has_legacy_slots()) {
            $raw = self::migrate_slots();
        }

        if ($raw === false || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $cards = [];
        $seen  = [];
        $index = 0;
        $max   = self::max();

        foreach ($decoded as $entry) {
            if (count($cards) >= $max) {
                break;
            }
            if (!is_array($entry)) {
                continue;
            }
            $catid = (int) ($entry['catid'] ?? 0);
            if ($catid <= 0 || isset($seen[$catid])) {
                continue;
            }

            $coursecat = \core_course_category::get($catid, IGNORE_MISSING, true);
            if (!$coursecat || !$coursecat->is_uservisible()) {
                continue;
            }
            $seen[$catid] = true;

            // Resolve the stored slug (remaps legacy icon names to the new set).
            $iconkey = icons::resolve(isset($entry['icon']) ? (string) $entry['icon'] : '');
            $count   = $coursecat->get_courses_count();
            $url     = new \moodle_url('/course/index.php', ['categoryid' => $coursecat->id]);

            // Colour: admin's pick, or the cycling default for this position.
            // Icon takes the full colour; the tile is a light tint of it.
            $color = self::normalise_color((string) ($entry['color'] ?? ''));
            if ($color === '') {
                $color = self::DEFAULT_COLORS[$index % count(self::DEFAULT_COLORS)];
            }

            $cards[] = [
                'name'      => format_string($coursecat->get_formatted_name()),
                'countnum'  => $count,
                'count'     => get_string('cats_course_count', 'theme_learnovatheme', $count),
                'url'       => $url->out(false),
                'iconsvg'   => icons::svg($iconkey),
                'hasicon'   => icons::exists($iconkey),
                'iconcolor' => $color,
                'tilecolor' => self::tint($color, 0.12),
            ];
            $index++;
        }

        return $cards;
    }

    // ── Colour helpers ─────────────────────────────────────────────────────

    /**
     * Validates a hex colour. Returns a normalised "#rrggbb" string, or '' when
     * the input isn't a usable hex colour.
     *
     * @param string $hex
     * @return string
     */
    public static function normalise_color(string $hex): string {
        $hex = ltrim(trim($hex), '#');
        if (preg_match('/^[0-9a-fA-F]{3}$/', $hex)) {
            // Expand shorthand #abc to #aabbcc.
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return '';
        }
        return '#' . strtolower($hex);
    }

    /**
     * Mixes a hex colour with white to produce a light tile background.
     *
     * @param string $hex    A normalised "#rrggbb" colour.
     * @param float  $weight Fraction of the colour kept (0..1); the rest is white.
     * @return string "#rrggbb" tint.
     */
    private static function tint(string $hex, float $weight): string {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $mix = function (int $c) use ($weight) {
            return (int) round($c * $weight + 255 * (1 - $weight));
        };
        return sprintf('#%02x%02x%02x', $mix($r), $mix($g), $mix($b));
    }

    // ── Legacy migration helpers ──────────────────────────────────────────

    /**
     * Returns true if any of the old per-slot cat configs have a non-zero value.
     */
    private static function has_legacy_slots(): bool {
        for ($i = 1; $i <= self::SLOTS; $i++) {
            if ((int) get_config('theme_learnovatheme', "cats_slot_{$i}_cat") > 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Reads the old per-slot settings, writes them as a cats_items JSON, and
     * returns the JSON so cards() can use it immediately.
     */
    private static function migrate_slots(): string {
        $items = [];
        $seen  = [];
        for ($i = 1; $i <= self::SLOTS; $i++) {
            $catid = (int) get_config('theme_learnovatheme', "cats_slot_{$i}_cat");
            if ($catid <= 0 || isset($seen[$catid])) {
                continue;
            }
            $icon = icons::resolve((string) get_config('theme_learnovatheme', "cats_slot_{$i}_icon"));
            if ($icon === '') {
                $icon = icons::default_key();
            }
            $seen[$catid] = true;
            $items[] = ['catid' => $catid, 'icon' => $icon];
        }

        $json = json_encode(array_values($items), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        set_config('cats_items', $json, 'theme_learnovatheme');
        return $json;
    }
}
