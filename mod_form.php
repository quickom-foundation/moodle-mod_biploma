<?php

// This file is part of the Biploma Certificate module for Moodle - http://moodle.org/
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
 * Instance add/edit form
 *
 * @package    mod_biploma
 * @copyright  2020 Beowulf Blockchain.
 * @copyright  based on work by Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.'); ///  It must be included from a Moodle page
}

require_once $CFG->dirroot . '/course/moodleform_mod.php';
require_once $CFG->dirroot . '/mod/biploma/lib.php';
require_once $CFG->dirroot . '/mod/biploma/locallib.php';
require_once $CFG->dirroot . '/mod/biploma/basic_env.php';

class mod_biploma_mod_form extends moodleform_mod {

    function definition() {
        global $DB, $OUTPUT, $CFG, $COURSE;

        $course = $COURSE;
        $updatingcert = false;
        $alreadyexists = false;

        $description = strip_tags($course->summary);
        if (empty($description)) {
            $description = "Recipient has compeleted the achievement.";
        }

        // Make sure the API key is set
        if (!isset($CFG->biploma_api_key)) {
            print_error('Please set your API Key first in the plugin settings.');
        }
        // Update form init
        if (optional_param('update', '', PARAM_INT)) {
            $updatingcert = true;
            $cm_id = optional_param('update', '', PARAM_INT);
            $cm = get_coursemodule_from_id('biploma', $cm_id, 0, false, MUST_EXIST);
            $id = $cm->course;
            $course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
            $biploma_certificate = $DB->get_record('biploma', ['id' => $cm->instance], '*', MUST_EXIST);
        }
        // New form init
        elseif (optional_param('course', '', PARAM_INT)) {
            $id = optional_param('course', '', PARAM_INT);
            $course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
            // see if other biploma certificates already exist for this course
            $alreadyexists = $DB->record_exists('biploma', ['course' => $id]);
        }

        // Load user data
        $context = context_course::instance($course->id);
        $users = get_enrolled_users($context, "mod/biploma:view", null, 'u.*');

        // Load final quiz choices
        $quiz_choices = [0 => 'None'];
        if ($quizes = $DB->get_records_select('quiz', 'course = :course_id', ['course_id' => $id])) {
            foreach ($quizes as $quiz) {
                $quiz_choices[$quiz->id] = $quiz->name;
            }
        }

        $base_link = get_bplm_url_https();

        // Form start
        $mform = &$this->_form;
        $mform->addElement('hidden', 'course', $id);
        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('static', 'overview', get_string('overview', 'biploma'), get_string('activitydescription', 'biploma', $base_link));
        if ($alreadyexists) {
            $mform->addElement('static', 'additionalactivitiesone', '', get_string('additionalactivitiesone', 'biploma'));
        }
        $mform->addElement('text', 'name', get_string('activityname', 'biploma'), ['style' => 'width: 399px']);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->setType('name', PARAM_TEXT);
        $mform->setDefault('name', $course->fullname);

        if ($alreadyexists) {
            $mform->addElement('static', 'additionalactivitiestwo', '', get_string('additionalactivitiestwo', 'biploma'));
        }

        // If we're updating and have a group then let the issuer choose to edit this
        if ($updatingcert && $biploma_certificate->groupid) {
            // Grab the list of groups available
            $groups = biploma_get_groups();
            $templates = biploma_get_templates_2();

            $mform->addElement('static', 'usestemplatesdescription', '', get_string('usestemplatesdescription', 'biploma'));
            $mform->addElement('select', 'groupid', get_string('groupname', 'biploma'), $groups);
            $mform->addElement('select', 'templateid', get_string('templatename', 'biploma'), $templates);
            $mform->addRule('groupid', null, 'required', null, 'client');
            $mform->setDefault('groupid', $biploma_certificate->groupid);
            $mform->addRule('templateid', null, 'required', null, 'client');
            $mform->setDefault('templateid', $biploma_certificate->templateid);
        }

        // Get certificates if updating
        if ($updatingcert) {
            // Grab existing certificates and cross-reference emails
            if ($biploma_certificate->groupid) {
                $certificates = biploma_get_credentials($biploma_certificate->groupid);
            }
        }

        $users_earned_certificate = [];
        // Generate list of users who have earned a certificate, if updating
        if ($updatingcert) {
            foreach ($users as $user) {
                // If this user has completed the criteria to earn a certificate, add to $users_earned_certificate
                if (biploma_check_if_cert_earned($biploma_certificate, $course, $user)) {
                    $users_earned_certificate[$user->id] = $user;
                }
            }
        }

        // Unissued certificates header
        if (count($users_earned_certificate) > 0) {
            $unissued_header = false;
            foreach ($users_earned_certificate as $user) {
                $existing_certificate = false;

                foreach ($certificates as $certificate) {
                    // Search through the certificates to see if this user has one existing
                    if ($certificate->email == $user->email) {
                        // This user has an existing certificate, no need to continue searching
                        $existing_certificate = true;
                        break;
                    }
                }

                if (!$existing_certificate) {
                    if (!$unissued_header) {
                        // The header has not been added to the form yet and is needed
                        $mform->addElement('header', 'chooseunissuedusers', get_string('unissuedheader', 'biploma'));
                        $mform->addElement('static', 'unissueddescription', '', get_string('unissueddescription', 'biploma'));
                        $this->add_checkbox_controller(2, 'Select All/None');
                        $unissued_header = true;
                    }
                    // No existing certificate, add this user to the unissued users list
                    $mform->addElement('advcheckbox', 'unissuedusers[' . $user->id . ']', $user->firstname . ' ' . $user->lastname . '  /  ' . $user->email, null, ['group' => 2]);
                }

            }
        }

        // Manually issue certificates header
        $mform->addElement('header', 'chooseusers', get_string('manualheader', 'biploma'));
        $this->add_checkbox_controller(1, 'Select All/None');

        if ($updatingcert) {

            // Grab existing credentials and cross-reference emails
            if ($biploma_certificate->groupid) {
                $certificates = biploma_get_credentials($biploma_certificate->groupid);
            }

            foreach ($users as $user) {
                $cert_id = null;
                $tran_id = null;
                // check cert emails for this user
                foreach ($certificates as $certificate) {
                    if ($certificate->email == $user->email) {
                        $cert_id = $certificate->record_id;
                        $tran_id = $certificate->transaction_id;
                        $cert_link_text = "Link";
                        if (isset($certificate->url)) {
                            $cert_link = $certificate->url;
                        } else {
                            if (!empty($tran_id)) {
                                $cert_link = 'https://' . get_bplm_url() . '/tx/' . $tran_id;
                                $cert_link_text = "Published";
                            } else {
                                $cert_link = 'https://' . get_bplm_url();
                                $cert_link_text = "Unpublished";
                            }
                        }
                    }
                }
                // show the certificate if they have a certificate
                if ($cert_id) {
                    $mform->addElement('static', 'certlink' . $user->id, $user->firstname . ' ' . $user->lastname . '  /  ' . $user->email, "Certificate $cert_id - <a href='$cert_link' target='_blank'>$cert_link_text</a>");
                } // show a checkbox if they don't
                else {
                    $mform->addElement('advcheckbox', 'users[' . $user->id . ']', $user->firstname . ' ' . $user->lastname . '  /  ' . $user->email, null, ['group' => 1]);
                }
            }
        }
        // For new modules, just list all the users
        else {
            foreach ($users as $user) {
                $mform->addElement('advcheckbox', 'users[' . $user->id . ']', $user->firstname . ' ' . $user->lastname . '  /  ' . $user->email, null, ['group' => 1]);
            }
        }

        $mform->addElement('header', 'gradeissue', get_string('gradeissueheader', 'biploma'));
        $mform->addElement('select', 'finalquiz', get_string('chooseexam', 'biploma'), $quiz_choices);
        $mform->addElement('text', 'passinggrade', get_string('passinggrade', 'biploma'));
        $mform->setType('passinggrade', PARAM_INT);
        $mform->setDefault('passinggrade', 70);

        $mform->addElement('header', 'completionissue', get_string('completionissueheader', 'biploma'));
        if ($updatingcert) {

            $mform->addElement('checkbox', 'completionactivities', get_string('completionissuecheckbox', 'biploma'));
            if (isset($biploma_certificate->completionactivities)) {
                $mform->setDefault('completionactivities', 1);
            }
        } else {
            $mform->addElement('checkbox', 'completionactivities', get_string('completionissuecheckbox', 'biploma'));
            // $mform->addElement('advcheckbox', 'activities['.$quiz->id.']', 'Quiz', $quiz->name, array('group' => 2));
            // $mform->addElement('advcheckbox', 'users['.$user->id.']', $user->firstname . ' ' . $user->lastname, null, array('group' => 1));

            // if($quizes) {
            //     foreach ($quizes as $quiz) {
            //         $mform->addElement('advcheckbox', 'activities['.$quiz->id.']', 'Quiz', $quiz->name, array('group' => 2));
            //     }
            // }
        }

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }
}