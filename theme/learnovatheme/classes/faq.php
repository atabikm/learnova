<?php
namespace theme_learnovatheme;

defined('MOODLE_INTERNAL') || die();

/**
 * "Common questions" (FAQ) front-page section.
 *
 * The Q&A pairs are edited entirely in Home Settings via a single dynamic list
 * control ({@see admin_setting_faqitems}) stored as a JSON array of {q, a}.
 * Items are added / removed / reordered in that form; a blank question is
 * dropped on save. Up to MAX items are shown, in stored order.
 */
class faq {

    /** Maximum number of FAQ items. */
    public const MAX = 10;

    /**
     * The default item list as a JSON string. Single source of truth shared by
     * the admin setting's default ({@see admin_setting_faqitems}) and the
     * install/upgrade seed ({@see theme_learnovatheme_apply_faq_defaults()}),
     * so the form's "Default" value and the seeded value can never drift.
     *
     * @return string
     */
    public static function default_items_json(): string {
        return json_encode([[
            'q' => get_string('faq_default_q1', 'theme_learnovatheme'),
            'a' => get_string('faq_default_a1', 'theme_learnovatheme'),
        ]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Returns the resolved FAQ items, in display order.
     *
     * @return array<int, array{id: string, first: bool, question: string, answer: string}>
     */
    public static function items(): array {
        $raw = get_config('theme_learnovatheme', 'faq_items');
        if ($raw === false || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $items = [];
        foreach ($decoded as $entry) {
            if (count($items) >= self::MAX) {
                break;
            }
            if (!is_array($entry)) {
                continue;
            }
            $question = isset($entry['q']) ? trim((string) $entry['q']) : '';
            if ($question === '') {
                continue;
            }
            $answer = isset($entry['a']) ? (string) $entry['a'] : '';

            $items[] = [
                'id'       => 'learnova-faq-' . (count($items) + 1),
                'first'    => count($items) === 0,
                'question' => format_string($question),
                'answer'   => format_text($answer, FORMAT_PLAIN, [
                    'context' => \context_system::instance(),
                    'filter'  => false,
                ]),
            ];
        }

        return $items;
    }
}
