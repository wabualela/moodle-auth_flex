<?php
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
 * Callback implementations for Flex
 *
 * @package    auth_flex
 * @copyright  2025 Wail Abualela wailabualela@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


function profile_signup_fields_by_shortnames(MoodleQuickForm $mform, array $shortnames = []) : void {

    if ($fields = profile_get_signup_fields()) {
        foreach ($fields as $field) {
            if (! in_array($field->object->field->shortname, $shortnames)) {
                continue;
            }
            $field->object->field->defaultdata = $mform->_defaultValues['profile_field_' . $field->object->field->shortname] ?? null;

            $field->object->edit_field($mform);
        }
    }
}