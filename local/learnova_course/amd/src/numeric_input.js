// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Restricts Learnova course meta number fields to numeric input only.
 *
 * Marked inputs use type="text" with inputmode for a mobile numeric keypad;
 * this filters keystrokes/paste on the "input" event (reliable across browsers,
 * unlike type="number" which lets Firefox display letters).
 *
 * @module     local_learnova_course/numeric_input
 * @copyright  2026 Look Media Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Strip disallowed characters from a value.
 *
 * @param {string} value Raw input value.
 * @param {boolean} decimals Whether a single decimal point is allowed.
 * @return {string} Sanitised value.
 */
const sanitise = (value, decimals) => {
    if (!decimals) {
        return value.replace(/[^0-9]/g, '');
    }
    let cleaned = value.replace(/[^0-9.]/g, '');
    const dot = cleaned.indexOf('.');
    if (dot !== -1) {
        // Keep the first dot, drop any others.
        cleaned = cleaned.slice(0, dot + 1) + cleaned.slice(dot + 1).replace(/\./g, '');
    }
    return cleaned;
};

export const init = () => {
    const inputs = document.querySelectorAll('input[data-learnova-numeric]');
    inputs.forEach((input) => {
        const decimals = input.dataset.learnovaDecimals === '1';
        input.addEventListener('input', () => {
            const cleaned = sanitise(input.value, decimals);
            if (cleaned !== input.value) {
                const pos = input.selectionStart;
                const removed = input.value.length - cleaned.length;
                input.value = cleaned;
                // Keep the caret in a sensible place after stripping characters.
                if (pos !== null) {
                    const next = Math.max(0, pos - removed);
                    input.setSelectionRange(next, next);
                }
            }
        });
    });
};
