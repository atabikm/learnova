<?php
namespace theme_learnovatheme;

defined('MOODLE_INTERNAL') || die();

/**
 * Curated SVG icon library for course categories, bundled with the theme in
 * pix/icons/category/*.svg (the Learnova icon system, ~56 line icons).
 *
 * Single source of truth for the category icon picker in Home Settings and for
 * inlining the chosen SVG into front-end templates so it inherits colour via
 * `currentColor`.
 *
 * Only slugs listed in {@see self::catalogue()} are ever read from disk, so a
 * stored setting value can never be used for path traversal.
 */
class icons {

    /** Sub-directory (relative to the theme root) holding the category SVGs. */
    private const DIR = '/theme/learnovatheme/pix/icons/category/';

    /**
     * Human-readable labels for the picker's section groups, in display order.
     *
     * @return array<string, string>
     */
    public static function sections(): array {
        return [
            'core'     => 'Core Learning',
            'business' => 'Business & Management',
            'tech'     => 'Digital & Technology',
            'people'   => 'People & HR',
            'ops'      => 'Operations & Industry',
        ];
    }

    /**
     * The full icon catalogue: slug => [label, section]. Slugs are the SVG file
     * basenames in pix/icons/category/. Labels come from the designer's icon
     * system (already localised copy), so no lang strings are needed here.
     *
     * @return array<string, array{label: string, section: string}>
     */
    public static function catalogue(): array {
        return [
            // Core Learning
            'compliance-k3' => ['label' => 'Compliance & K3', 'section' => 'core'],
            'soft-skills' => ['label' => 'Soft Skills & Produktivitas', 'section' => 'core'],
            'onboarding' => ['label' => 'Onboarding', 'section' => 'core'],
            'certification' => ['label' => 'Sertifikasi', 'section' => 'core'],
            'microlearning' => ['label' => 'Microlearning', 'section' => 'core'],
            'gamification' => ['label' => 'Gamifikasi', 'section' => 'core'],
            'elearning-content' => ['label' => 'E-Learning Content', 'section' => 'core'],
            'video-learning' => ['label' => 'Video Learning', 'section' => 'core'],
            'assessment' => ['label' => 'Assessment & Quiz', 'section' => 'core'],
            'blended-learning' => ['label' => 'Blended Learning', 'section' => 'core'],
            'mentoring' => ['label' => 'Mentoring & Coaching', 'section' => 'core'],
            'simulation' => ['label' => 'Simulasi & Skenario', 'section' => 'core'],
            // Business & Management
            'leadership' => ['label' => 'Leadership & Management', 'section' => 'business'],
            'sales-marketing' => ['label' => 'Sales & Marketing', 'section' => 'business'],
            'finance' => ['label' => 'Finance & Accounting', 'section' => 'business'],
            'project-management' => ['label' => 'Project Management', 'section' => 'business'],
            'strategy' => ['label' => 'Strategic Thinking', 'section' => 'business'],
            'customer-service' => ['label' => 'Customer Service', 'section' => 'business'],
            'negotiation' => ['label' => 'Negosiasi', 'section' => 'business'],
            'presentation' => ['label' => 'Presentasi', 'section' => 'business'],
            'communication' => ['label' => 'Komunikasi', 'section' => 'business'],
            'risk-management' => ['label' => 'Risk Management', 'section' => 'business'],
            'decision-making' => ['label' => 'Decision Making', 'section' => 'business'],
            'problem-solving' => ['label' => 'Problem Solving', 'section' => 'business'],
            // Digital & Technology
            'digital-teknologi' => ['label' => 'Digital & Teknologi', 'section' => 'tech'],
            'data-analytics' => ['label' => 'Data Analytics & BI', 'section' => 'tech'],
            'cybersecurity' => ['label' => 'Cybersecurity', 'section' => 'tech'],
            'ai-ml' => ['label' => 'AI & Machine Learning', 'section' => 'tech'],
            'data-privacy' => ['label' => 'Data Privacy & PDPA', 'section' => 'tech'],
            'digital-marketing' => ['label' => 'Digital Marketing', 'section' => 'tech'],
            'lms-platform' => ['label' => 'LMS & Platform', 'section' => 'tech'],
            'automation' => ['label' => 'Automation & Tools', 'section' => 'tech'],
            'ms-office' => ['label' => 'Microsoft Office', 'section' => 'tech'],
            'ux-design' => ['label' => 'UI/UX Design', 'section' => 'tech'],
            // People & HR
            'hr-management' => ['label' => 'HR Management', 'section' => 'people'],
            'talent-development' => ['label' => 'Talent Development', 'section' => 'people'],
            'succession-planning' => ['label' => 'Succession Planning', 'section' => 'people'],
            'performance-management' => ['label' => 'Performance Management', 'section' => 'people'],
            'teamwork' => ['label' => 'Teamwork & Kolaborasi', 'section' => 'people'],
            'conflict-resolution' => ['label' => 'Conflict Resolution', 'section' => 'people'],
            'diversity-inclusion' => ['label' => 'Diversity & Inclusion', 'section' => 'people'],
            'employee-wellbeing' => ['label' => 'Employee Wellbeing', 'section' => 'people'],
            'recruitment' => ['label' => 'Rekrutmen', 'section' => 'people'],
            'culture' => ['label' => 'Budaya Organisasi', 'section' => 'people'],
            // Operations & Industry
            'safety-k3' => ['label' => 'Safety & K3', 'section' => 'ops'],
            'quality-management' => ['label' => 'Quality Management', 'section' => 'ops'],
            'supply-chain' => ['label' => 'Supply Chain', 'section' => 'ops'],
            'lean-process' => ['label' => 'Lean & Process Improvement', 'section' => 'ops'],
            'sop' => ['label' => 'SOP & Prosedur', 'section' => 'ops'],
            'manufacturing' => ['label' => 'Manufacturing', 'section' => 'ops'],
            'hospitality' => ['label' => 'Hospitality & Service', 'section' => 'ops'],
            'healthcare' => ['label' => 'Healthcare', 'section' => 'ops'],
            'retail' => ['label' => 'Retail & FMCG', 'section' => 'ops'],
            'banking-finance' => ['label' => 'Banking & Finance', 'section' => 'ops'],
            'energy-mining' => ['label' => 'Energy & Mining', 'section' => 'ops'],
            'environment' => ['label' => 'ESG & Lingkungan', 'section' => 'ops'],
        ];
    }

    /**
     * Maps slugs from the original (small) icon set to the nearest icon in the
     * new category library, so already-saved settings keep working.
     *
     * @return array<string, string>
     */
    private static function legacy_map(): array {
        return [
            'innovation' => 'strategy',
            'digital'    => 'digital-teknologi',
            'data'       => 'data-analytics',
            'people'     => 'hr-management',
            'compliance' => 'compliance-k3',
            'growth'     => 'performance-management',
            'book'       => 'elearning-content',
            'message'    => 'communication',
        ];
    }

    /**
     * Resolves a stored slug to a valid catalogue slug: returns it unchanged if
     * known, remaps a legacy slug if recognised, else ''.
     *
     * @param string $key
     * @return string
     */
    public static function resolve(string $key): string {
        if ($key === '') {
            return '';
        }
        if (array_key_exists($key, self::catalogue())) {
            return $key;
        }
        $map = self::legacy_map();
        return $map[$key] ?? '';
    }

    /**
     * Returns true when $key is a known, pickable icon.
     */
    public static function exists(string $key): bool {
        return array_key_exists($key, self::catalogue());
    }

    /**
     * The catalogue's first slug — used as a safe default.
     */
    public static function default_key(): string {
        return (string) (array_key_first(self::catalogue()) ?? '');
    }

    /**
     * Picker data for the modal grid: one entry per icon with its slug, label,
     * section and inline SVG markup, grouped under section headings.
     *
     * @return array<int, array{key: string, label: string, section: string,
     *     sectionlabel: string, svg: string}>
     */
    public static function picker_items(): array {
        $sections = self::sections();
        $items = [];
        foreach (self::catalogue() as $slug => $meta) {
            $items[] = [
                'key'          => $slug,
                'label'        => $meta['label'],
                'section'      => $meta['section'],
                'sectionlabel' => $sections[$meta['section']] ?? $meta['section'],
                'svg'          => self::svg($slug),
            ];
        }
        return $items;
    }

    /**
     * Inline SVG markup for a catalogue icon, ready to drop into a template
     * with {{{ }}}. Returns '' for an unknown key or a missing file.
     *
     * The SVG ships with stroke="currentColor", so the rendering element's
     * `color` controls the icon colour.
     *
     * @param string $key Catalogue slug (e.g. 'leadership').
     * @return string Inline <svg>…</svg> markup, or '' when unavailable.
     */
    public static function svg(string $key): string {
        global $CFG;
        if ($key === '' || !self::exists($key)) {
            return '';
        }
        $file = $CFG->dirroot . self::DIR . $key . '.svg';
        if (!is_readable($file)) {
            return '';
        }
        return trim((string) file_get_contents($file));
    }
}
