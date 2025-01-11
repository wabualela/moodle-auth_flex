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
class step3_form extends moodleform {
    public function definition() {
        global $CFG;

        $mform = $this->_form;

        $mform->addElement('header', 'step3', get_string('academic_informaiton', 'auth_flex'));

        profile_signup_fields_by_shortnames($mform, ['education_level', 'current_job', 'marital_status']);

        $mform->addElement('text', 'phone1', get_string('phone1'), 'maxlength="100" size="25"');
        $mform->setType('phone1', core_user::get_property_type('phone1'));
        $mform->addRule('phone1', get_string('missingemail'), 'required', null, 'client');
        $mform->setForceLtr('phone1');

        $manager = new \core_privacy\local\sitepolicy\manager();
        if ($manager->is_defined()) {
            $mform->addElement('checkbox', 'sitepolicyagree', '', '<a href="' . $manager->get_redirect_url() . '">' . get_string('sitepolicyagreement', 'auth_flowstep') . '</a>');
            $mform->addRule('sitepolicyagree', get_string('required'), 'required', null, 'client');
        }
        $manager->signup_form($mform);

    }
}