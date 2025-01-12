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

require_once "$CFG->libdir/formslib.php";
require_once "$CFG->dirroot/user/profile/lib.php";
require_once "$CFG->dirroot/user/editlib.php";
require_once "$CFG->dirroot/auth/flex/lib.php";
require_once "$CFG->dirroot/auth/flex/classes/form/step2_form.php";
require_once "$CFG->dirroot/auth/flex/classes/form/step3_form.php";

use auth_flex\form\step1_form;

/**
 * TODO describe file signup_form
 *
 * @package    auth_flex
 * @copyright  2025 Wail Abualela wailabualela@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class login_signup_form extends moodleform implements renderable, templatable {

    protected $step;
    public function definition() {
        global $SESSION;

        $mform      = $this->_form;
        $this->step = optional_param('step', 1, PARAM_INT);

        $mform->addElement('hidden', 'step', $this->step);
        $mform->setType('step', PARAM_INT);

        $this->set_display_vertical();

        switch ($this->step) {
            case 1:
                if (isset($SESSION->step1data)) {
                    $this->set_data($SESSION->step1data);
                }
                $this->step1_form($mform);
                break;
            case 2:
                if (isset($SESSION->step2data)) {
                    $this->set_data($SESSION->step2data);
                }
                $this->step2_form($mform);
                break;
            case 3:
                $this->step3_form($mform);
                break;
        }

    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (signup_captcha_enabled()) {
            $recaptchaelement = $this->_form->getElement('recaptcha_element');
            if (! empty($this->_form->_submitValues['g-recaptcha-response'])) {
                $response = $this->_form->_submitValues['g-recaptcha-response'];
                if (! $recaptchaelement->verify($response)) {
                    $errors['recaptcha_element'] = get_string('incorrectpleasetryagain', 'auth');
                }
            } else {
                $errors['recaptcha_element'] = get_string('missingrecaptchachallengefield');
            }
        }
        $authplugin = new auth_plugin_flex();
        $errors += $authplugin->signup_validate_data($data, $files);

        return $errors;
    }

    public function export_for_template(renderer_base $output) {
        ob_start();
        $this->display();
        $formhtml = ob_get_contents();
        ob_end_clean();
        $context = [
            'formhtml' => $formhtml
        ];
        return $context;
    }

    protected function step1_form($mform) {
        global $CFG, $SESSION;

        $mform->addElement('header', 'step1', get_string('regisration_information', 'auth_flex'));

        $namefields = useredit_get_required_name_fields();
        foreach ($namefields as $field) {
            $mform->addElement('text', $field, get_string($field), 'maxlength="100" size="30"');
            $mform->setType($field, core_user::get_property_type('firstname'));
            $stringid = 'missing' . $field;
            if (! get_string_manager()->string_exists($stringid, 'moodle')) {
                $stringid = 'required';
            }
            $mform->addRule($field, get_string($stringid), 'required', null, 'client');
        }

        profile_signup_fields_by_shortnames($mform, ['certname']);

        $mform->addElement('text', 'email', get_string('email'), 'maxlength="100" size="25"');
        $mform->setType('email', core_user::get_property_type('email'));
        $mform->addRule('email', get_string('missingemail'), 'required', null, 'client');
        $mform->setForceLtr('email');

        $mform->addElement('text', 'email2', get_string('emailagain'), 'maxlength="100" size="25"');
        $mform->setType('email2', core_user::get_property_type('email'));
        $mform->addRule('email2', get_string('missingemail'), 'required', null, 'client');
        $mform->setForceLtr('email2');

        if (! empty($CFG->passwordpolicy)) {
            $mform->addElement('static', 'passwordpolicyinfo', '', print_password_policy());
        }
        $mform->addElement('password', 'password', get_string('password'), [
            'maxlength' => 100,
            'size' => 12,
            'autocomplete' => 'new-password'
        ]);
        $mform->setType('password', core_user::get_property_type('password'));
        $mform->addRule('password', get_string('missingpassword'), 'required', null, 'client');
        $mform->addRule(
            'password',
            get_string('maximumchars', '', 100),
            'maxlength',
            100,
            'client'
        );

        if (signup_captcha_enabled()) {
            $mform->addElement('recaptcha', 'recaptcha_element', get_string('security_question', 'auth'));
            $mform->addHelpButton('recaptcha_element', 'recaptcha', 'auth');
            $mform->closeHeaderBefore('recaptcha_element');
        }

        // Hook for plugins to extend form definition.
        core_login_extend_signup_form($mform);

        $buttonarray   = [];
        $buttonarray[] = $mform->createElement('submit', 'nextbutton', get_string('next'));
        $buttonarray[] = $mform->createElement('cancel', 'cancelbutton', get_string('cancel'));
        $mform->addElement('html', '<div style="margin: 20px 0;"></div>');
        $mform->addGroup($buttonarray, 'buttonar', '', [' '], false);
    }
    protected function step2_form($mform) {
        global $CFG, $SITE;

        $mform->addElement('header', 'step2', get_string('personal_information', 'auth_flex'));

        profile_signup_fields_by_shortnames($mform, ['dob', 'gender']);

        $country             = get_string_manager()->get_list_of_countries();
        $default_country[''] = get_string('selectacountry');
        $country             = array_merge($default_country, $country);

        $mform->addElement('select', 'profile_field_nationality', get_string('nationality', 'auth_flex'), $country);
        $mform->addRule('profile_field_nationality', get_string('required'), 'required', null, 'client');

        $mform->addElement('select', 'country', get_string('country'), $country);
        $mform->addRule('country', get_string('required'), 'required', null, 'client');

        if (! empty($CFG->country)) {
            $mform->setDefault('country', $CFG->country);
        }

        $mform->addElement('text', 'city', get_string('city'), 'maxlength="120" size="20"');
        $mform->setType('city', \core_user::get_property_type('city'));
        if (! empty($CFG->defaultcity)) {
            $mform->setDefault('city', $CFG->defaultcity);
        }

        $buttonarray   = [];
        $buttonarray[] = $mform->createElement('html', '<a href="/login/signup.php?step=1" class="btn btn-secondary">' . get_string('previous') . '</a>');
        $buttonarray[] = $mform->createElement('submit', 'nextbutton', get_string('next'));
        $buttonarray[] = $mform->createElement('cancel', 'cancelbutton', get_string('cancel'));
        $mform->addElement('html', '<div style="margin: 20px 0;"></div>');
        $mform->addGroup($buttonarray, 'buttonar', '', [' '], false);
    }
    protected function step3_form($mform) {

        $mform->addElement('header', 'step3', get_string('academic_informaiton', 'auth_flex'));

        profile_signup_fields_by_shortnames($mform, ['education_level', 'current_job', 'marital_status']);

        $mform->addElement('text', 'phone1', get_string('phone1', 'auth_flex'), 'maxlength="100" size="25"');
        $mform->setType('phone1', \core_user::get_property_type('phone1'));
        $mform->addRule('phone1', get_string('missingphone1', 'auth_flex'), 'required', null, 'client');
        $mform->setForceLtr('phone1');
        $mform->addHelpButton('phone1', 'phone1', 'auth_flex');

        $manager = new \core_privacy\local\sitepolicy\manager();
        if ($manager->is_defined()) {
            $mform->addElement('checkbox', 'sitepolicyagree', '', '<a href="' . $manager->get_redirect_url() . '">' . get_string('sitepolicyagreement', 'auth_flowstep') . '</a>');
            $mform->addRule('sitepolicyagree', get_string('required'), 'required', null, 'client');
        }
        $manager->signup_form($mform);

        $buttonarray   = [];
        $buttonarray[] = $mform->createElement('html', '<a href="/login/signup.php?step=2" class="btn btn-secondary">' . get_string('previous') . '</a>');
        $buttonarray[] = $mform->createElement('submit', 'nextbutton', get_string('next'));
        $buttonarray[] = $mform->createElement('cancel', 'cancelbutton', get_string('cancel'));
        $mform->addElement('html', '<div style="margin: 20px 0;"></div>');
        $mform->addGroup($buttonarray, 'buttonar', '', [' '], false);
    }

}