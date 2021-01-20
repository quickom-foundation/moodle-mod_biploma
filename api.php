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
 * Certificate module capability definition
 *
 * @package    mod_biploma
 * @copyright  2020 Beowulf Blockchain.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace BPLM;

use moodle_exception;

require_once $CFG->libdir . "/externallib.php";
require_once $CFG->dirroot . '/mod/biploma/basic_env.php';

/**
 * API Wrappers
 */
class Api {

    private $api_key;

    private $api_endpoint = BPLM_API_URL_PROD;

    /**
     * Contruct API instance
     * @param String $api_key
     * @param boolean|null $test
     * @return null
     */
    public function __construct($api_key = null, $test = null) {
        global $CFG;

        if (empty($api_key)) {
            $api_key = get_config('core', 'biploma_api_key');
            if (empty($api_key)) {
                throw new moodle_exception('errorwebservice', 'mod_biploma', '', get_string('apikeymissing', 'biploma'));
            }
        }
        $this->setAPIKey($api_key);

        $this->api_endpoint = get_bplm_api_url();
    }

    /**
     * Set API Key
     * @param String $key
     * @return null
     */
    public function setAPIKey($key) {
        $this->api_key = $key;
    }

    /**
     * Get API Key
     * @return String
     */
    public function getAPIKey() {
        return $this->api_key;
    }

    /**
     * Strip out keys with a null value from an object http://stackoverflow.com/a/15953991
     * @param stdObject $object
     * @return stdObject
     */
    public function strip_empty_keys($object) {
        $json = json_encode($object);
        $json = preg_replace('/,\s*"[^"]+":null|"[^"]+":null,?/', '', $json);
        $object = json_decode($json);

        return $object;
    }

    /**
     * Makes a REST call.
     *
     * @param string $method The HTTP method to use.
     * @param string $url The URL to append to the API URL
     * @param array $data The data to attach to the call.
     * @param array $headers The data to attach to the header of the call.
     * @return array response
     * @throws moodle_exception Moodle exception is thrown for curl errors.
     */
    public static function call_api($method, $url, $data, $headers = []) {

        $curl = curl_init();

        switch ($method) {
            case "POST":
                curl_setopt($curl, CURLOPT_POST, 1);
                if ($data) {
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                }
                break;
            case "PUT":
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
                if ($data) {
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                }
                break;
            case "DELETE":
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
                if ($data) {
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                }
                break;

            default:
                if ($data) {
                    $url = sprintf("%s?%s", $url, http_build_query($data));
                }
        }

        curl_setopt($curl, CURLOPT_URL, $url);
        $defaultheaders = [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data),
            'Accept: application/json',
        ];
        if (empty($headers)) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $defaultheaders);
        } else {
            curl_setopt($curl, CURLOPT_HTTPHEADER, array_merge($defaultheaders, $headers));
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

        $result = curl_exec($curl);

        curl_close($curl);

        if ($result === false) {
            $err = 'Curl error: ' . curl_error($curl);
            throw new moodle_exception('errorwebservice', 'mod_biploma', '', get_string('connectionfailed', 'biploma'), $err);
        }

        $resp = json_decode($result, false); // object

        if (!empty($resp->error)) {
            $err = "Curl error: ($resp->error) $resp->error_description";
            if ($resp->error == '401001') {
                $err .= " / Invalid API key";
            }
            throw new moodle_exception('errorwebservice', 'mod_biploma', '', get_string('connectionfailed', 'biploma'), $err);
        }

        return $resp;
    }

    /**
     * Get Credentials to check for existing credential
     * @param String|null $group_id
     * @param String|null $template_id
     * @param String|null $email
     * @return stdObject
     */
    public function get_credentials_with_email($group_id = null, $template_id = null, $email = null) {
        $data = [];
        if (!empty($group_id)) {
            $data['group_id'] = $group_id;
        }
        if (!empty($template_id)) {
            $data['template_id'] = $template_id;
        }
        if (!empty($email)) {
            $data['email'] = $email;
        }

        $data_json = json_encode($data);

        $headers = ['Authorization: ' . $this->getAPIKey()];
        $method = 'POST';
        $url = 'https://' . $this->api_endpoint . '/cert/v1/org/cert/record/api-key/email/list';

        $response = $this->call_api($method, $url, $data_json, $headers);

        return $response;
    }

    /**
     * Get a Credential
     * @param String $id
     * @return stdObject
     */
    public function get_credential($id) {
        $data = [];
        $data_json = json_encode($data);

        $headers = ['Authorization: ' . $this->getAPIKey()];
        $method = 'GET';
        $url = 'https://' . $this->api_endpoint . '/cert/v1/org/cert/record/api-key/get?recordId=' . $id;

        $response = $this->call_api($method, $url, $data_json, $headers);

        return $response;
    }

    /**
     * Get Credentials
     * @param String|null $group_id
     * @param String|null $template_id
     * @param String|null $email
     * @param String|null $page_size
     * @param String $page
     * @return stdObject
     */
    public function get_credentials($group_id = null, $template_id = null, $email = null, $page_size = null, $page = 1) {
        $data = [];
        if (!empty($group_id)) {
            $data['group_id'] = $group_id;
        }
        if (!empty($template_id)) {
            $data['template_id'] = $template_id;
        }
        if (!empty($email)) {
            $data['email'] = $email;
        }

        $data_json = json_encode($data);

        $headers = ['Authorization: ' . $this->getAPIKey()];
        $method = 'POST';
        $url = 'https://' . $this->api_endpoint . '/cert/v1/org/cert/record/api-key/list';

        $response = $this->call_api($method, $url, $data_json, $headers);

        return $response;
    }

    /**
     * Creates a Credential given an existing Group
     * @param String $recipient_name
     * @param String $recipient_email
     * @param String $group_id
     * @param String $template_id
     * @param Date|null $issued_on
     * @param Date|null $expired_on
     * @param stdObject|null $custom_attributes
     * @return stdObject
     */
    public function create_credential(
        $recipient_name,
        $recipient_email,
        $group_id,
        $template_id,
        $issued_on = null,
        $expired_on = null,
        $custom_attributes = null) {

        if ($issued_on == null) {
            $issued_on = date("M d, Y");
        }
        $tempserialno = $group_id . '_' . time();
        $data = [
            "bc_data" => [
                "rcvName" => $recipient_name,
                "issuedDate" => $issued_on,
                "regNo" => $tempserialno,
                "serialNo" => $tempserialno,
            ],
            "email" => $recipient_email,
            "group_id" => $group_id,
            "template_id" => $template_id,
        ];

        $data_json = json_encode($data);

        $headers = ['Authorization: ' . $this->getAPIKey()];
        $method = 'POST';
        $url = 'https://' . $this->api_endpoint . '/cert/v1/org/cert/record/api-key/create';

        $response = $this->call_api($method, $url, $data_json, $headers);

        return $response;
    }

    /**
     * Updates a Credential
     * @param type $id
     * @param String|null $recipient_name
     * @param String|null $recipient_email
     * @param String|null $course_id
     * @param Date|null $issued_on
     * @param Date|null $expired_on
     * @param stdObject|null $custom_attributes
     * @return stdObject
     */
    public function update_credential(
        $id,
        $recipient_name = null,
        $recipient_email = null,
        $course_id = null,
        $issued_on = null,
        $expired_on = null,
        $custom_attributes = null) {

        if ($issued_on == null) {
            $issued_on = date("M d, Y");
        }
        $data = [
            "bc_data" => [
                "rcvName" => $recipient_name,
                "issuedDate" => $issued_on,
            ],
            "record_id" => $id,
        ];
        $data = $this->strip_empty_keys($data);

        $data_json = json_encode($data);

        $headers = ['Authorization: ' . $this->getAPIKey()];
        $method = 'POST';
        $url = 'https://' . $this->api_endpoint . '/cert/v1/org/cert/record/api-key/update';

        $response = $this->call_api($method, $url, $data_json, $headers);

        return $response;
    }

    /**
     * Delete a Credential
     * @param String $id
     * @return stdObject
     */
    public function delete_credential($id) {

        $data = [];
        $data_json = json_encode($data);

        $headers = ['Authorization: ' . $this->getAPIKey()];
        $method = 'DELETE';
        $url = 'https://' . $this->api_endpoint . '/cert/v1/org/cert/record/api-key/delete?recordId=' . $id;

        $response = $this->call_api($method, $url, $data_json, $headers);

        return $response;
    }

    /**
     * Create a new Group
     * @param String $name
     * @param String $course_name
     * @param String $course_description
     * @param String|null $course_link
     * @return stdObject
     */
    public function create_group($name, $course_name = null, $course_description = null, $course_link = null) {
        $data = [
            "group_name" => $name,
            "description" => $course_description,
        ];
        $data_json = json_encode($data);

        $headers = ['Authorization: ' . $this->getAPIKey()];
        $method = 'POST';
        $url = 'https://' . $this->api_endpoint . '/cert/v1/org/cert/group/api-key/create';

        $response = $this->call_api($method, $url, $data_json, $headers);

        return $response;
    }

    /**
     * Get a Group
     * @param String $id
     * @return stdObject
     */
    public function get_group($id) {
        $data = [];
        $data_json = json_encode($data);

        $headers = ['Authorization: ' . $this->getAPIKey()];
        $method = 'GET';
        $url = 'https://' . $this->api_endpoint . '/cert/v1/org/cert/group/api-key/get?groupId=' . $id;

        $response = $this->call_api($method, $url, $data_json, $headers);

        return $response;
    }

    /**
     * Get all Groups
     * @param String $page_size
     * @param String $page
     * @return stdObject
     */
    public function get_groups($page_size = null, $page = 1) {
        $data = [
            "from" => 0,
            "to" => 0,
        ];
        $data_json = json_encode($data);

        $headers = ['Authorization: ' . $this->getAPIKey()];
        $method = 'POST';
        $url = 'https://' . $this->api_endpoint . '/cert/v1/org/cert/group/api-key/list';

        $response = $this->call_api($method, $url, $data_json, $headers);

        return $response;
    }

    /**
     * Update a Group
     * @param String $id
     * @param String|null $name
     * @param String|null $course_name
     * @param String|null $course_description
     * @param String|null $course_link
     * @return stdObject
     */
    public function update_group($id, $name = null, $course_name = null, $course_description = null, $course_link = null, $design_id = null) {
        $data = [
            "group_id" => $id,
            "group_name" => $name,
            "description" => $course_description,
        ];
        $data = $this->strip_empty_keys($data);
        $data_json = json_encode($data);

        $headers = ['Authorization: ' . $this->getAPIKey()];
        $method = 'POST';
        $url = 'https://' . $this->api_endpoint . '/cert/v1/org/cert/group/api-key/update';

        $response = $this->call_api($method, $url, $data_json, $headers);

        return $response;
    }

    /**
     * Delete a Group
     * @param String $id
     * @return stdObject
     */
    public function delete_group($id) {
        return false;

        // $data = [];
        // $data = $this->strip_empty_keys($data);
        // $data_json = json_encode($data);

        // $headers = ['Authorization: ' . $this->getAPIKey()];
        // $method = 'POST';
        // $url = 'https://' . $this->api_endpoint . '/cert/v1/org/cert/group/api-key/delete?groupId=' . $id;

        // $response = $this->call_api($method, $url, $data_json, $headers);
        // return $response;
    }

    /**
     * Get all Templates
     * @param String $page_size
     * @param String $page
     * @return stdObject
     */
    public function get_templates($page_size = null, $page = 1) {
        $data = [
            "from" => 0,
            "to" => 0,
        ];
        $data_json = json_encode($data);

        $headers = ['Authorization: ' . $this->getAPIKey()];
        $method = 'POST';
        $url = 'https://' . $this->api_endpoint . '/cert/v1/org/cert/template/api-key/list';

        $response = $this->call_api($method, $url, $data_json, $headers);

        return $response;
    }

}
