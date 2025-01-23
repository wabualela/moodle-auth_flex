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
 * Main class for the Flex authentication plugin
 *
 * Documentation: {@link https://docs.moodle.org/dev/Authentication_plugins}
 *
 * @package    auth_flex
 * @copyright  2025 Wail Abualela wailabualela@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once "$CFG->libdir/authlib.php";
require_once "$CFG->dirroot/auth/flex/signup_form.php";
require_once "$CFG->dirroot/cohort/lib.php";

/**
 * Authentication plugin auth_flex
 *
 * @package    auth_flex
 * @copyright  2025 Wail Abualela wailabualela@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class auth_plugin_flex extends auth_plugin_base {

    /**
     * Constructor.
     */
    public function __construct() {
        $this->authtype = 'flex';
    }

    public function user_login($username, $password) {
        global $CFG, $DB;
        if ($user = $DB->get_record('user', array('username' => $username, 'mnethostid' => $CFG->mnet_localhost_id))) {
            return validate_internal_user_password($user, $password);
        }
        return false;
    }

    public function user_update_password($user, $newpassword) {
        $user = get_complete_user_data('id', $user->id);
        // This will also update the stored hash to the latest algorithm
        // if the existing hash is using an out-of-date algorithm (or the
        // legacy md5 algorithm).
        return update_internal_user_password($user, $newpassword);
    }

    public function user_signup($user, $notify = true) {
        global $CFG, $DB, $SESSION;

        $step = $user->step;

        switch ($step) {
            case 1:
                $user->username = $user->email;
                $SESSION->step1data = $user;
                redirect(new moodle_url('/login/signup.php', ['step' => 2]));
            case 2:
                $SESSION->step2data = $user;
                redirect(new moodle_url('/login/signup.php', ['step' => 3]));
            case 3:
                $newuserdata = (object) array_merge(
                    (array) $SESSION->step1data,
                    (array) $SESSION->step2data,
                    (array) $user
                );

                unset($SESSION->step1data);
                unset($SESSION->step2data);

                $confirmationurl = null;

                if (empty($newuserdata->calendartype)) {
                    $newuserdata->calendartype = $CFG->calendartype;
                }

                $plainpassword = $newuserdata->password;
                $newuserdata->password = hash_internal_user_password($newuserdata->password);
                $newuserdata->id = user_create_user($newuserdata, false, false);
                user_add_password_history($newuserdata->id, $plainpassword);

                // Save any custom profile field information.
                profile_save_data($newuserdata);

                // Save wantsurl against newuserdata's profile, so we can return them there upon confirmation.
                if (! empty($SESSION->wantsurl)) {
                    set_user_preference('auth_email_wantsurl', $SESSION->wantsurl, $newuserdata);
                }

                // Trigger event.
                \core\event\user_created::create_from_userid($newuserdata->id)->trigger();

                $cohortid = get_config('auth_flexauth', 'cohortid');

                if ($cohortid) {
                    // Add the user to the cohort.
                    cohort_add_member($cohortid, $user->id);
                }

                if (! send_confirmation_email($newuserdata, $confirmationurl)) {
                    throw new \moodle_exception('auth_emailnoemail', 'auth_email');
                }

                if ($notify) {
                    global $CFG, $PAGE, $OUTPUT;
                    $emailconfirm = get_string('emailconfirm');
                    $PAGE->navbar->add($emailconfirm);
                    $PAGE->set_title($emailconfirm);
                    $PAGE->set_heading($PAGE->course->fullname);
                    echo $OUTPUT->header();
                    notice(get_string('emailconfirmsent', '', $newuserdata->email), "$CFG->wwwroot/index.php");
                } else {
                    return true;
                }

        }
    }

    public function can_signup() {
        return true;
    }

    public function signup_validate_data($data, $files) {
        global $CFG, $DB, $SESSION;
        $errors     = [];
        $authplugin = get_auth_plugin($CFG->registerauth);

        if ($data['step'] == 1) {

            if (! validate_email($data['email'])) {
                $errors['email'] = get_string('invalidemail');

            } else if (empty($CFG->allowaccountssameemail)) {
                // Emails in Moodle as case-insensitive and accents-sensitive. Such a combination can lead to very slow queries
                // on some DBs such as MySQL. So we first get the list of candidate users in a subselect via more effective
                // accent-insensitive query that can make use of the index and only then we search within that limited subset.
                $sql = "SELECT 'x'
                  FROM {user}
                 WHERE " . $DB->sql_equal('email', ':email1', false, true) . "
                   AND id IN (SELECT id
                                FROM {user}
                               WHERE " . $DB->sql_equal('email', ':email2', false, false) . "
                                 AND mnethostid = :mnethostid)";

                $params = array(
                    'email1' => $data['email'],
                    'email2' => $data['email'],
                    'mnethostid' => $CFG->mnet_localhost_id,
                );

                // If there are other user(s) that already have the same email, show an error.
                if ($DB->record_exists_sql($sql, $params)) {
                    $forgotpasswordurl  = new moodle_url('/login/forgot_password.php');
                    $forgotpasswordlink = html_writer::link($forgotpasswordurl, get_string('emailexistshintlink'));
                    $errors['email']    = get_string('emailexists') . ' ' . get_string('emailexistssignuphint', 'moodle', $forgotpasswordlink);
                }
            }
            if (empty($data['email2'])) {
                $errors['email2'] = get_string('missingemail');

            } else if (core_text::strtolower($data['email2']) != core_text::strtolower($data['email'])) {
                $errors['email2'] = get_string('invalidemail');
            }
            if (! isset($errors['email'])) {
                if ($err = email_is_not_allowed($data['email'])) {
                    $errors['email'] = $err;
                }
            }

            // Construct fake user object to check password policy against required information.
            $tempuser = new stdClass();
            // To prevent errors with check_password_policy(),
            // the temporary user and the guest must not share the same ID.
            $tempuser->id        = (int) $CFG->siteguest + 1;
            $tempuser->firstname = $data['firstname'];
            $tempuser->lastname  = $data['lastname'];
            $tempuser->username  = $data['email'];
            $tempuser->email     = $data['email'];

            $errmsg = '';
            if ($data['step'] == 1 && ! check_password_policy($data['password'], $errmsg, $tempuser)) {
                $errors['password'] = $errmsg;
            }

            // Validate customisable profile fields. (profile_validation expects an object as the parameter with userid set).
            $dataobject     = (object) $data;
            $dataobject->id = 0;
            $errors += profile_validation($dataobject, $files);
        }

        return $errors;
    }

    public function can_confirm() {
        return true;
    }

    public function user_confirm($username, $confirmsecret) {
        global $DB, $SESSION;
        $user = get_complete_user_data('username', $username);

        if (! empty($user)) {
            if ($user->auth != $this->authtype) {
                return AUTH_CONFIRM_ERROR;

            } else if ($user->secret === $confirmsecret && $user->confirmed) {
                return AUTH_CONFIRM_ALREADY;

            } else if ($user->secret === $confirmsecret) {   // They have provided the secret key to get in
                $DB->set_field("user", "confirmed", 1, array("id" => $user->id));

                if ($wantsurl = get_user_preferences('auth_email_wantsurl', false, $user)) {
                    // Ensure user gets returned to page they were trying to access before signing up.
                    $SESSION->wantsurl = $wantsurl;
                    unset_user_preference('auth_email_wantsurl', $user);
                }

                return AUTH_CONFIRM_OK;
            }
        } else {
            return AUTH_CONFIRM_ERROR;
        }
    }

    public function signup_form() {
        return new login_signup_form(null, null, 'post', '', array('autocomplete' => 'on'));
    }

    public function can_change_password() {
        return true;
    }

    public function can_reset_password() {
        return true;
    }

}
