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

namespace auth_flex\form;

use core_user;
use moodleform;

require_once "$CFG->libdir/formslib.php";

/**
 * Class step1_form
 *
 * @package    auth_flex
 * @copyright  2025 Wail Abualela wailabualela@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class step2_form extends moodleform {
    public function definition() {
        global $CFG;

        $mform = $this->_form;

        $mform->addElement('header', 'step2', get_string('personal_information', 'auth_flex'));

        profile_signup_fields_by_shortnames($mform, ['dob', 'gender', 'nationality']);

        $country             = get_string_manager()->get_list_of_countries();
        $default_country[''] = get_string('selectacountry');
        $country             = array_merge($default_country, $country);

        $mform->addElement('select', 'country', get_string('country'), $country);
        $mform->addRule('country', get_string('required'), 'required', null, 'client');

        if (! empty($CFG->country)) {
            $mform->setDefault('country', $CFG->country);
        } else {
            $mform->setDefault('country', '');
        }

        $mform->addElement('text', 'city', get_string('city'), 'maxlength="120" size="20"');
        $mform->setType('city', core_user::get_property_type('city'));
        if (! empty($CFG->defaultcity)) {
            $mform->setDefault('city', $CFG->defaultcity);
        }

    }
}