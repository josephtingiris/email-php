<?php
/**
 * This is an PHP Email class in the josephtingiris namespace.
 *
 * @author      Current authors: Joseph Tingiris <jtingiris>
 *                               (next author)
 *
 *              Original author: Joseph Tingiris <jtingiris>
 *
 * @license     https://opensource.org/licenses/GPL-3.0
 *
 * @version     0.0.1
 */
namespace josephtingiris;

/**
 * The josephtingiris\Email class contains methods for simplifying the sending of complex emails via php.
 */
class Email extends \josephtingiris\Debug
{
    /*
     * public properties.
     */

    /*
     * public functions.
     */

    public function __construct($debug_level=null)
    {

        if (!is_null($debug_level)) {
            parent::__construct($debug_level); // execute parent __construct; \josephtingiris\Debug()
        }

        $this->debug("Class = " . __CLASS__, 20);

        if (!isset($GLOBALS["Here"])) {
            $GLOBALS["Here"]=getcwd();
        }

        if (!isset($GLOBALS["Hostname"])) {
            $GLOBALS["Hostname"]=gethostname();
        }

    }

    public function __destruct()
    {
    }

    /**
     * a simple (to use), trustworthy send method
     *
     * @param {string|array|object} 'to' address(es)
     * @param {string|array|object} 'subject' subject; arrays or objects will be merged into a string
     * @param {string|array|object} 'message' message body (parts); if a body (part) contains html tags then content-type html is set
     * @param {string|array|object} 'additional_headers' & custom options, e.g. cc, bcc, reply-to, attachment(s), etc.
     * @param {string|array|object} 'additional_parameters' passed to php mail()
     * @param {boolean} alert on failure
     * @param {boolean} abort on failure
     * @return {boolean} true on success, false on failure
     */
    public function send($to=null, $subject=null, $message=null, $additional_headers=null, $additional_parameters=null, $alert=false, $abort=false)
    {

        $debug_level = 33;

        $this->debug(__FUNCTION__ . "(" . print_r(func_get_args(),true) . ")",$debug_level);

        // note; rfc2822 message header keys are case INSENSITIVE;
        // note; rfc2822 allows
        // only 1 Sender: address
        // multiple From: (mailbox-list)
        // unlimited Comments:

        // set the rfc2822_headers array that will be popoluated and reused
        $rfc2822_headers=array();

        $rfc2822_mailer_version="0.0.1";
        $rfc2822_mailer=preg_replace("/\\\/","-",get_class($this)) . " " . __FUNCTION__ . " [version $rfc2822_mailer_version]";

        // set the rfc2822_domain & rfc2822_user; these will be reused
        if (!empty($_SERVER['SERVER_NAME']) && !empty($_SERVER['HTTP_HOST'])) {
            $rfc2822_domain = $_SERVER['HTTP_HOST'];
            $rfc2822_user = "noreply";
        } else {
            // not apache, from is empty so set from to process user
            $rfc2822_domain = gethostname();

            $process_user = posix_getpwuid(posix_geteuid());
            $rfc2822_user=$process_user['name'];

            if (is_null($rfc2822_user) || $rfc2822_user == '') {
                $rfc2822_user="nobody";
            }
        }

        // create recipients array
        $recipients=array();
        if (!is_array($to) && !is_object($to)) {
            if (!is_null($to) && $to != '') {
                $to = trim((string)$to);
                $recipients = explode(",", $to);
            }
        } else {
            if (is_array($to)) {
                $recipients = $to;
            } else {
                if (is_object($to)) {
                    $recipients = (array)$to;
                }
            }
        }
        $recipients = array_map('trim', $recipients);
        $recipients = array_unique($recipients);
        $this->debugValue("recipients",$debug_level,$recipients);

        // check recipients array
        if (empty($recipients)) {
            $failure_reason = "failure, recipients are empty";
            return $this->failure($failure_reason, false, $abort);
        } else {
            // $to is used again, as an array, later ...
            $to = $recipients;
        }

        // create subject string
        if (is_array($subject) || is_object($subject)) {
            if (is_object($subject)) {
                $subject = (array)$subject;
            }
            $subject = implode($subject, " ");
        }
        $subject = trim($subject);

        // what if someone passes Subject: ? Which one wins ??
        if (!preg_grep("/^Subject:/i",$rfc2822_headers)) {
            $rfc2822_headers[] = "Subject: $subject";
        }

        // debug subject string
        $this->debugValue("subject",$debug_level,$subject);

        // create 'custom' options array
        $options=array();
        if (!is_array($additional_headers) && !is_object($additional_headers)) {
            if (!is_null($additional_headers) && $additional_headers != '') {
                $additional_headers = trim((string)$additional_headers);
                $options = explode(",", $additional_headers);
            }
        } else {
            if (is_array($additional_headers)) {
                $options = $additional_headers;
            } else {
                if (is_object($additional_headers)) {
                    $options = (array)$additional_headers;
                }
            }
        }
        $options = array_map('trim', $options);
        #$options = array_map('strtolower', $options); // DO NOT CONVERT CASE

        // debug options array
        $this->debugValue("options",$debug_level,$options);

        // convert the options to arrays & headers single option headers, rfc2822 'list' sematics (csv)

        // attachments are special; they're not technically headers
        $attachments=array();

        // these are rfc2822 header equivalents
        $custom_headers=array(
            "attachment",
            "bcc",
            "cc",
            "from",
            "replyto",
            "reply-to",
            "reply_to",
            "to",
        );
        $custom_headers=array_map('strtolower', $custom_headers); // rely on lowercase

        // iterate through 'options', find rfc2822 values that conflict with custom_headers & change them to custom header values
        foreach ($options as $option_key => $option_value) {

            // this is here in case a multi dimensional array is passed; ignore it
            if (!is_string($option_value)) {
                continue;
            } else {
                $option_value=trim($option_value);
            }

            $option_value=strtolower($option_value);

            $this->debugValue("option_key=$option_key, option_value",$debug_level,$option_value);

            $option_value_explode = explode("=", $option_value);

            $this->debugValue("option_value_explode",$debug_level,$option_value_explode);

            if (!isset($option_value_explode[0])) {
                continue;
            }

            if (!preg_grep("/$option_value_explode[0]/i",$custom_headers)) {

                // not a custom value; could be an rfc2822 header ... see if there's a dupe that can be consolidated

                $rfc2822_header_strpos = strpos($option_value, ":");

                if ($rfc2822_header_strpos === false) {
                    // there's no = or : in the option; for now don't do anything (this is probably an error, though)
                    continue;
                }

                $rfc2822_header_key = strtolower(trim(substr($option_value,0,$rfc2822_header_strpos)));
                foreach ($custom_headers as $custom_header) {
                    if ($custom_header == $rfc2822_header_key) {

                        // a conflicting rfc2822 header was passed; get the rfc2822 header value
                        $rfc2822_header_value = trim(substr($option_value,$rfc2822_header_strpos+1));

                        $this->debugValue("convert 'custom' option [$option_key] rfc2822 '$rfc2822_header_key'",$debug_level,$rfc2822_header_value);

                        // convert it to a custom header before processing custom_headers (no/null values are checked later)
                        // replace the option_key with the new converted value
                        $options[$option_key] = "$custom_header=$rfc2822_header_value";

                    }
                }
                unset($custom_header);

                unset($option_value_explode, $rfc2822_header_key, $rfc2822_header_value, $rfc2822_header_strpos);

            }
        }
        unset($option_key, $option_value);

        // iterate through 'options' & merge values into a local variable named after custom header values
        foreach ($options as $option) {

            // this is here in case a multi dimensional array is passed; ignore it
            if (!is_string($option)) {
                continue;
            } else {
                $option=trim($option);
            }

            $this->debugValue("option",$debug_level,$option);

            $option_explode = explode("=", $option);

            $this->debugValue("option_explode",$debug_level,$option_explode);

            if (!isset($option_explode[0])) {
                continue;
            }

            if (preg_grep("/$option_explode[0]/i",$custom_headers)) {

                $this->debugValue("option_explode[0]",$debug_level,$option_explode[0]);

                // todo; change this to foreach loop through custom_headers; handle attachments different

                // note; everything EXCEPT attachment is UNSET later; do NOT rely on the case values ! (use what's in rfc2822_headers)
                switch($option_explode[0]) {
                case "attachment":
                    if (isset($option_explode[1]) && is_readable($option_explode[1])) {
                        $attachments[] = $option_explode[1];
                    } else {
                        if (!isset($option_explode[1])) {
                            $failure_reason = "error, attachment filename not set";
                        } else {
                            if (!is_readable($option_explode[1])) {
                                $failure_reason = "error, removed attachment '$option_explode[1]' (not readable)";
                            } else {
                                $failure_reason = "error, removed attachment '$option_explode[1]' (unavailable)";
                            }
                        }

                        $this->failure($failure_reason, false, $abort);

                        // Comments: to rfc2822_headers
                        $rfc2822_headers[] = "Comments: $failure_reason";

                    }
                    break;
                case "cc":
                    if (isset($option_explode[1]) && !is_null($option_explode[1]) && $option_explode[1] != '') {
                        if (!isset($cc)) {
                            $cc = array();
                        }
                        $option_explode_explode = explode(",", $option_explode[1]);
                        $cc = array_merge($cc, $option_explode_explode);
                        $cc = array_unique(array_map('trim', $cc));
                        unset($option_explode_explode);
                    }
                    break;
                case "bcc":
                    if (isset($option_explode[1]) && !is_null($option_explode[1]) && $option_explode[1] != '') {
                        if (!isset($bcc)) {
                            $bcc = array();
                        }
                        $option_explode_explode = explode(",", $option_explode[1]);
                        $bcc = array_merge($bcc, $option_explode_explode);
                        $bcc = array_unique(array_map('trim', $bcc));
                        unset($option_explode_explode);
                    }
                    break;
                case "from":
                    if (isset($option_explode[1]) && !is_null($option_explode[1]) && $option_explode[1] != '') {
                        if (!isset($from)) {
                            $from = array();
                        }
                        $option_explode_explode = explode(",", $option_explode[1]);
                        $from = array_merge($from, $option_explode_explode);
                        $from = array_unique(array_map('trim', $from));
                        unset($option_explode_explode);
                    }
                    break;
                case "replyto":
                case "reply-to":
                case "reply_to":
                    if (isset($option_explode[1]) && !is_null($option_explode[1]) && $option_explode[1] != '') {
                        if (!isset($reply_to)) {
                            $reply_to = array();
                        }
                        $option_explode_explode = explode(",", $option_explode[1]);
                        $reply_to = array_merge($reply_to, $option_explode_explode);
                        $reply_to = array_unique(array_map('trim', $reply_to));
                        unset($option_explode_explode);
                    }
                    break;
                case "to":
                    if (isset($option_explode[1]) && !is_null($option_explode[1]) && $option_explode[1] != '') {
                        if (!isset($to)) {
                            $to = array();
                        }
                        $option_explode_explode = explode(",", $option_explode[1]);
                        $to = array_merge($to, $option_explode_explode);
                        $to = array_unique(array_map('trim', $to));
                        unset($option_explode_explode);
                    }
                    break;
                case "replyto":
                case "reply-to":
                case "reply_to":
                }

            } else {
                // it's not one of custom headers, so pass it through (trimmed)
                $rfc2822_headers[] .= trim($option);
            }
        }
        unset($option);

        // at this point, additional_headers & new options sematics have been merged into unique & expandable local variable arrays
        // also, custom headers that do not conflict with passed rfc2822_headers have been seperated and added to rfc2822_headers

        $this->debugValue("options",$debug_level,$options);

        // now, iterate through custom_headers
        foreach ($custom_headers as $custom_header) {

            // if a local variable (expansion) was set above
            if (!empty($$custom_header)) {
                $this->debugValue("$custom_header",$debug_level,$$custom_header);

                $header_values=null;
                $rfc2822_header = null;

                // then (implode) the values into comma separated values per rfc2822
                foreach ($$custom_header as $header_key => $header_value) {
                    $this->debug("customer_header=$custom_header, header_key=$header_key, header_value=$header_value",$debug_level);
                    $header_values .= "$header_value,";
                }
                unset($$custom_header, $header_key, $header_value); // NOTICE; $$custom_header is being UNSET

                $header_values=rtrim($header_values,",");

                // ucwords is not necessary, here ... rfc2822 explicitly (albeit indirectly) says that keys are case insensitive
                $rfc2822_header = str_replace("_","-",ucwords(strtolower($custom_header))) . ": $header_values\r\n";

                // and add them to the rfc2822_headers array
                $rfc2822_headers[] = $rfc2822_header;
            }

        }

        // at this point there is a de-duped list of rfc2822 headers, empty & duplicate values removed

        // check of rfc2822_headers keys & values
        // todo; better enforcement of rfc2822 for complex/messy calls

        // these are used to determine which values to reformat as rfc2822 address lists
        $rfc2822_address_lists = array(
            "bcc",
            "cc",
            "from",
            "reply-to",
            "to",
        );
        $rfc2822_address_lists=array_map('strtolower', $rfc2822_address_lists); // rely on lowercase

        // rfc2822 header keys can't have certain characters, e.g. :space:, ; ... there are more ... for now, just the logic
        // add rejects matches to this list
        $rfc2822_header_key_rejects = array(
            " ",
            "~",
            "!",
            "@",
            "#",
            "$",
            "%",
            "^",
            "&",
            "*",
            "(",
            ")",
            "+",
            ":",
            ";",
            ",",
            ".",
            "?",
            "'",
            "\"",
            "/",
            "\\",
        );

        // this loop validates the rfc2822_headers array
        foreach ($rfc2822_headers as $rfc2822_headers_key => $rfc2822_headers_value) {

            $rfc2822_header_strpos = strpos($rfc2822_headers_value, ":");

            // if there's no : in the rfc2822 option; remove it
            if ($rfc2822_header_strpos === false) {
                unset($rfc2822_headers[$rfc2822_headers_key]);

                $failure_reason = "error, removed invalid rfc2822 header [$rfc2822_headers_key] key '$rfc2822_headers_value' (no colon)";
                $this->failure($failure_reason, false, $abort);

                unset($rfc2822_headers_strpos);
                continue;
            }

            $rfc2822_header_key = strtolower(trim(substr($rfc2822_headers_value,0,$rfc2822_header_strpos)));
            $this->debugValue("rfc2822_header_key",$debug_level,$rfc2822_header_key);

            // rfc2822 header keys can't have certain characters, e.g. :space:, ; ...
            foreach ($rfc2822_header_key_rejects as $rfc2822_header_key_reject) {
                $rfc2822_header_key_reject_strpos = strpos($rfc2822_header_key, $rfc2822_header_key_reject);

                // if there's a reject string in the rfc2822 option; remove it
                if ($rfc2822_header_key_reject_strpos !== false) {
                    unset($rfc2822_headers[$rfc2822_headers_key]);

                    $failure_reason = "error, removed invalid rfc2822 header [$rfc2822_headers_key] key '$rfc2822_headers_value' (rejected '$rfc2822_header_key_reject')";
                    $this->failure($failure_reason, false, $abort);

                    unset($rfc2822_header_key_reject_strpos);
                    continue;
                }

            }
            unset($rfc2822_header_key_reject, $rfc2822_header_key_reject_strpos);

            $rfc2822_header_value = trim(substr($rfc2822_headers_value,$rfc2822_header_strpos+1));

            // format address lists
            foreach ($rfc2822_address_lists as $rfc2822_address_list) {

                if (strtolower($rfc2822_header_key) == $rfc2822_address_list) {
                    $rfc2822_address_value=null;
                    $this->debugValue("[$rfc2822_headers_key] $rfc2822_header_key is an address-list",$debug_level,$rfc2822_header_value);
                    $rfc2822_addresses=explode(",",$rfc2822_header_value);

                    foreach ($rfc2822_addresses as $rfc2822_address) {

                        $rfc2822_address = trim($rfc2822_address);

                        // first, check for a < delimiting <name> address
                        $rfc2822_address_strpos = strpos($rfc2822_address, "<");

                        $this->debugValue("[$rfc2822_headers_key] $rfc2822_address",$debug_level,$rfc2822_address);

                        if ($rfc2822_address_strpos === FALSE) {
                            // there's no name component
                            if (!empty($_SERVER['SERVER_NAME'])) {

                                // if it's apache then add one only if it's a valid email address

                                if (filter_var($rfc2822_address, FILTER_VALIDATE_EMAIL) === false) {

                                    // this happens if it's called via apache mod_php (adhere to rfc5351)

                                    // do not add the rfc5351 invalid address

                                    $failure_reason = "error, removed invalid $rfc2822_address_list address '$rfc2822_address'";
                                    $this->failure($failure_reason, false, $abort);

                                    // Comments: to rfc2822_headers
                                    $rfc2822_headers[] = "Comments: $failure_reason";

                                } else {

                                    // add the valid rfc5351 address
                                    $rfc2822_address_value .= "$rfc2822_address <$rfc2822_address>,";

                                }

                            } else {

                                // this happens if it's NOT called via apache mod_php (adhere to rfc2822)

                                // clone the address & add both components per rfc2822 address-list

                                $rfc2822_address_value .= "$rfc2822_address <$rfc2822_address>,";

                            }
                        } else {
                            if ($rfc2822_address_strpos == 0) {

                                // definitely invalid; flip it around

                                $rfc2822_address_address = trim(trim(substr($rfc2822_address, 0, strpos($rfc2822_address,">")),"<|>"));
                                $rfc2822_address_name = trim(trim(substr($rfc2822_address, strpos($rfc2822_address,">")),"<|>"));

                            } else {

                                // maybe invalid .. only flip it around if the address component is invalid

                                if ($rfc2822_address_strpos > 0) {

                                    $rfc2822_address_address = trim(trim(substr($rfc2822_address, strpos($rfc2822_address,"<")),"<|>"));
                                    $rfc2822_address_name = trim(trim(substr($rfc2822_address, 0, strpos($rfc2822_address,"<")),"<|>"));
                                    // if there's an @ in the name component but not the address component then flip it around
                                    if (strpos($rfc2822_address_address,"@") == false && strpos($rfc2822_address_name,"@") !== false) {
                                        $rfc2822_address_name = trim(trim(substr($rfc2822_address, strpos($rfc2822_address,"<")),"<|>"));
                                        $rfc2822_address_address = trim(trim(substr($rfc2822_address, 0, strpos($rfc2822_address,"<")),"<|>"));
                                    }

                                }
                            }

                            $this->debugValue("[$rfc2822_headers_key] rfc2822_address_address",$debug_level,$rfc2822_address_address);
                            $this->debugValue("[$rfc2822_headers_key] rfc2822_address_name",$debug_level,$rfc2822_address_name);

                            // note; rfc2822 & rfc5351 standards conflict & may cause confusion.
                            // note; rfc5351 2.3.5 specifies SMTP addresses must have a valid DNS domain & php filter_vars obliges.
                            // note; But, rfc2822 address-list does NOT require DNS domain components.
                            // note; php filter_vars is not rfc2822 compliant. This presents problems when/if sending mail direct from cli.
                            // note; What if I want to send an email with sendmail/postfix via a web page to someone@hostname?
                            // note; Or, to someone@hostname.localdomain ??  rfc2822 (and sendmail/postfix) allow this!

                            if (!empty($_SERVER['SERVER_NAME'])) {

                                // this happens if it's called via apache mod_php (adhere to rfc5351)

                                if (filter_var($rfc2822_address_address, FILTER_VALIDATE_EMAIL) === false) {

                                    // this happens if it's called via apache mod_php (adhere to rfc5351)

                                    // do not add the second component, it's not a valid email address (it should be)

                                    $failure_reason = "error, removed invalid $rfc2822_address_list address '$rfc2822_address' (reversed)";
                                    $this->failure($failure_reason, false, $abort);

                                    // Comments: to rfc2822_headers
                                    $rfc2822_headers[] = "Comments: $failure_reason";

                                } else {

                                    // the second component is a valid rfc5351 address; add it

                                    if (!is_null($rfc2822_address_address) && $rfc2822_address_address != '') {
                                        if (!is_null($rfc2822_address_name) && $rfc2822_address_name != '') {
                                            $rfc2822_address_value .= "$rfc2822_address_name <$rfc2822_address_address>,";
                                        } else {
                                            $rfc2822_address_value .= "$rfc2822_address_address <$rfc2822_address_address>,";
                                        }
                                    }

                                }
                            } else {

                                // this happens if it's NOT called via apache mod_php (adhere to rfc2822)

                                if (!is_null($rfc2822_address_address) && $rfc2822_address_address != '') {

                                    // if the address & name components are different, add them both
                                    if (!is_null($rfc2822_address_name) && $rfc2822_address_name != '') {
                                        $rfc2822_address_value .= "$rfc2822_address_name <$rfc2822_address_address>,";
                                    } else {
                                        // clone the address in both components
                                        $rfc2822_address_value .= "$rfc2822_address_address <$rfc2822_address_address>,";
                                    }

                                }

                            }
                        }

                        unset($rfc2822_address_address, $rfc2822_address_strpos, $rfc2822_address_name);
                    }
                    unset($rfc2822_address);

                    $rfc2822_address_value=trim($rfc2822_address_value,",");

                    if (!is_null($rfc2822_address_value) && $rfc2822_address_value != '') {

                        $this->debugValue("[$rfc2822_headers_key] rfc2822_address_value",$debug_level,$rfc2822_address_value);

                        // replace the existing address list with the formatted address list
                        $rfc2822_headers[$rfc2822_headers_key] = ucwords(strtolower($rfc2822_address_list)) . ": " . $rfc2822_address_value;
                    } else {
                        // nullify the invalid value
                        $rfc2822_header_value=null;
                    }

                    unset($rfc2822_address, $rfc2822_address_value);

                }

            }
            unset($rfc2822_address_list);

            // if there's an rfc2822 option, but an empty value; remove it
            if (is_null($rfc2822_header_value) || $rfc2822_header_value == '') {
                unset($rfc2822_headers[$rfc2822_headers_key]);

                $failure_reason = "error, removed invalid rfc2822 header [$rfc2822_headers_key] value for '$rfc2822_header_key' (no value)";
                $this->failure($failure_reason, false, $abort);

                unset($rfc2822_headers_strpos);
            }

            $this->debugValue("[$rfc2822_headers_key] $rfc2822_header_key",$debug_level,$rfc2822_header_value);
        }
        unset($rfc2822_headers_key, $rfc2822_headers_value);

        // check From: header, first pass
        $rfc2822_headers_from=preg_grep("/^From:/i",$rfc2822_headers);
        if ($rfc2822_headers_from) {
            $this->debugValue("rfc2822_headers_from",$debug_level,$rfc2822_headers_from);
        } else {
            // there's no From: address, so figure it out and add it to rfc2822_headers
            $rfc2822_from = $rfc2822_user . "@" . $rfc2822_domain;
            $rfc2822_from = "$rfc2822_from <$rfc2822_from>";
            $rfc2822_headers[] = "From: " . $rfc2822_from;
        }

        // (re)set these

        $rfc2822_from=null;
        $rfc2822_sender=null;

        // check From: header, second pass (set sender)
        $rfc2822_headers_from=preg_grep("/^From:/i",$rfc2822_headers);
        if ($rfc2822_headers_from) {
            foreach($rfc2822_headers_from as $rfc2822_header_from) {
                $rfc2822_from=trim(preg_replace("/^From:/i","", $rfc2822_header_from));

                $rfc2822_sender=trim(preg_replace("/^From:/i","", explode(",",$rfc2822_header_from)[0]));
                if (is_null($rfc2822_sender) || $rfc2822_sender == '') {
                    $rfc2822_sender=null; // this should get reset
                } else {
                    $rfc2822_headers[] = "Sender: " . $rfc2822_sender;
                }
                $this->debugValue("Sender: set",$debug_level,$rfc2822_sender);
            }
        } else {
            // no from, can't set sender ... return false
            $failure_reason = "failure, can't determine from address (no sender)";
            return $this->failure($failure_reason, false, $abort);
        }

        $this->debugValue("rfc2822_from",$debug_level,$rfc2822_from);

        // check Sender: header (final)
        $rfc2822_headers_sender=preg_grep("/^Sender:/i",$rfc2822_headers);
        if (!$rfc2822_headers_sender) {
            // no sender, something went wrong ... return false
            $failure_reason = "failure, can't determine sender address (no header)";
            return $this->failure($failure_reason, false, $abort);
        }

        $this->debugValue("rfc2822_sender",$debug_level,$rfc2822_sender);

        // add other generic, but useful (& missing) rfc2822_headers, here ...

        if (!preg_grep("/^Date:/i",$rfc2822_headers)) {
            $rfc2822_headers[] = 'Date: ' . date('r');
        }

        if (!preg_grep("/^Message-id:/i",$rfc2822_headers)) {
            $rfc2822_headers[] = 'Message-id: <' . $this->uuid() . '@' . $rfc2822_domain . '>';
        }

        if (!preg_grep("/^Return-path:/i",$rfc2822_headers)) {
            $rfc2822_headers[] = "Return-path: $rfc2822_sender";
        }

        if (!preg_grep("/^Reply-to:/i",$rfc2822_headers)) {
            $rfc2822_headers[] = "Reply-to: $rfc2822_sender";
        }

        if (!preg_grep("/^Thread-index:/i",$rfc2822_headers)) {
            $rfc2822_headers[] = "Thread-index: " . $this->uuid();
        }

        if (!preg_grep("/^X-priority:/i",$rfc2822_headers)) {
            $rfc2822_headers[] = "X-priority: 3";
        }

        if (!preg_grep("/^X-mailer:/i",$rfc2822_headers)) {
            $rfc2822_headers[] = "X-mailer: " . $rfc2822_mailer;
        }

        // trim, sort, & unique rfc2822_headers
        $rfc2822_headers = array_map('trim', $rfc2822_headers); // this needs happen per rfc2822
        sort($rfc2822_headers); // easier to read in debug output
        $rfc2822_headers = array_unique($rfc2822_headers);

        // at this point ...
        // rfc2822_headers *could* be passed to php mail() EXCEPT for the To: headers ...
        // there's a valid array of attachments (if given)

        // debug rfc2822_headers
        $this->debugValue("rfc282_headers",$debug_level,$rfc2822_headers);

        // debug attachments
        $this->debugValue("attachments",$debug_level,$attachments);

        // create parameters array
        $parameters=array();
        if (!is_array($additional_parameters) && !is_object($additional_parameters)) {
            if (!is_null($additional_parameters) && $additional_parameters != '') {
                $additional_parameters = trim((string)$additional_parameters);
                $parameters = explode(",", $additional_parameters);
            }
        } else {
            if (is_array($additional_parameters)) {
                $parameters = $additional_parameters;
            } else {
                if (is_object($additional_parameters)) {
                    $parameters = (array)$additional_parameters;
                }
            }
        }
        $parameters = array_map('trim', $parameters);

        // if no parameters are passed, then force Return-path with parameter (otherwise, honor passed parameters)
        if (empty($parameters) && !empty($rfc2822_sender)) {

            $rfc2822_sender_address = $rfc2822_sender;

            if (strpos($rfc2822_sender_address,"<") !== false && strpos($rfc2822_sender_address,">") !== false) {
                // sendmail/postfix -f argument wont accept rfc2822 compliant Sender:
                // ideally, Return-path: would only be back to sender's address
                $rfc2822_sender_address = substr($rfc2822_sender_address,strpos($rfc2822_sender_address,"<"));
                $rfc2822_sender_address = substr($rfc2822_sender_address,0,strpos($rfc2822_sender_address,">"));
            }

            if (strpos($rfc2822_sender_address," ") !== false) {
                // sendmail/postfix -f argument only accepts single words
                $rfc2822_sender_address = substr($rfc2822_sender_address,0,strpos($rfc2822_sender_address," "));
            }

            $rfc2822_sender_address = trim(trim($rfc2822_sender_address,"<|>"));

            if (!is_null($rfc2822_sender_address) && $rfc2822_sender_address != '') {
                $parameters[] = "-oi";
                $parameters[] = "-F '$rfc2822_sender'";
                $parameters[] = "-r '$rfc2822_sender_address'";
                $parameters[] = "-f '$rfc2822_sender_address'";
            }
        }

        // debug parameters
        $this->debugValue("parameters",$debug_level,$parameters);

        // check parameters array
        if (empty($parameters)) {
            // optional
        }

        // create body_parts array (this needs to be AFTER processing options)
        $body_parts=array();
        if (!is_array($message) && !is_object($message)) {
            if (!is_null($message) && $message != '') {
                $message = trim((string)$message);
                $body_parts[] = $message;
            }
        } else {
            if (is_array($message)) {
                $body_parts = $message;
            } else {
                if (is_object($message)) {
                    $body_parts = (array)$message;
                }
            }
        }
        $body_parts = array_map('trim', $body_parts);

        // add headers based on body type
        if (!preg_grep("/^Mime-version:/i",$rfc2822_headers)) {
            $rfc2822_headers[] = "Mime-version: 1.0";
        }
        if (!preg_grep("/^Content-type:/i",$rfc2822_headers)) {
            if (count($body_parts) > 1 || !empty($attachments)) {
                // create a unique rfc2822 boundary string
                $rfc2822_boundary = preg_replace("/\\\/","-",get_class($this)) . "-" . $this->uuid();

                $rfc2822_headers[] = "Content-type: multipart/alternative; boundary=$rfc2822_boundary";
            } else {
                $rfc2822_headers[] = "Content-type: text/plain; charset=utf-8";
            }
        }

        $this->debugValue("body_parts",$debug_level,$body_parts);

        // at this point ... rfc2822_headers && attachments arrays should be reliable & trustworthy

        // if this method hasn't already returned due to a parse failure, then use this as a return variable

        $email_sent = false;

        $php_mail=true; // enabled; use php mail(), no external depedencies; php 5.4+ compatible

        if ($php_mail) {

            // note;
            // php 5.4.x mail() will send empty To: & Subject: if they're in additional_headers, which violates rfc2822
            // php 5.4.x mail() will also send null To: & Subject: if called as mail(null, null, ...) (which also violates rfc2822)

            $php_mail_additional_headers = $rfc2822_headers;

            // pull To: & Subject: out of rfc2822_headers and put everything else into php_mail_additional_headers
            foreach ($php_mail_additional_headers as $php_mail_additional_headers_key => $php_mail_additional_headers_value) {
                if (preg_match("/^Subject:/i",$php_mail_additional_headers_value)) {
                    // put what's in rfc2822_headers Subject into $subject
                    $subject = trim(preg_replace("/^Subject:/i","",$php_mail_additional_headers_value));
                    unset($php_mail_additional_headers[$php_mail_additional_headers_key]);
                }
                if (preg_match("/^To:/i",$php_mail_additional_headers_value)) {
                    // put what's in rfc2822_headers To into $To
                    $to = trim(preg_replace("/^To:/i","",$php_mail_additional_headers_value));
                    unset($php_mail_additional_headers[$php_mail_additional_headers_key]);
                }
            }
            unset($php_mail_additional_headers_key, $php_mail_additional_headers_value);

            // last resort; set to to process user; hopefully no bounces
            if (empty($to)) {
                $to = $rfc2822_user;
            }

            $this->debugValue("to",$debug_level,$to);
            $this->debugValue("subject",$debug_level,$subject);

            // todo; reduce this debug level
            $this->debugValue("php_mail_additional_headers",$debug_level-25,$php_mail_additional_headers);

            if (!empty($attachments)) {
                // todo; reduce this debug level
                $this->debugValue("attachments",$debug_level-25,$attachments);
            }

            // put body together
            $body = null;
            foreach ($body_parts as $body_part) {

                // don't add multiparts unless this method set the boundary

                if (!empty($rfc2822_boundary)) {
                    if($body_part != strip_tags($body_part)) {
                        // contains html
                        $this->debugValue("body_part (html)",$debug_level,$body_part);
                        $body .= "--" . $rfc2822_boundary . "\r\n";
                        $body .= "Content-type: text/html; charset=utf-8" . "\r\n";
                    } else {
                        // no html
                        $this->debugValue("body_part (text)",$debug_level,$body_part);
                        $body .= "--" . $rfc2822_boundary . "\r\n";
                        $body .= "Content-type: text/plain; charset=utf-8" . "\r\n";
                    }
                    $body .= "Content-transfer-encoding: 7bit" . "\r\n";
                    $body .= "\r\n";
                }

                $body .= $body_part;
                $body .= "\r\n";

            }

            foreach ($attachments as $attachment) {
                // don't add attachments unless this method set the boundary
                if (!empty($rfc2822_boundary)) {
                    $attachment_size=filesize($attachment);
                    $body .= "--" . $rfc2822_boundary . "\r\n";
                    $body .= "Content-type: application/octet-stream; name=\"" . basename($attachment) . "\"" . "\r\n";
                    $body .= "Content-transfer-encoding: base64" . "\r\n";
                    $body .= "Content-disposition: attachment; filename=\"" . basename($attachment) . "\"; size=$attachment_size" . "\r\n";
                    $body .= "\r\n";
                    $body .= chunk_split(base64_encode(file_get_contents($attachment)));
                }
            }

            if (!empty($rfc2822_boundary)) {
                $body .= "--" . $rfc2822_boundary . "--";
            }

            // todo; reduce this debug level
            $this->debugValue("body",$debug_level-10,$body);

            $email_sent=mail($to, $subject, $body, implode("\r\n", $php_mail_additional_headers), implode(" ", $parameters));
        }

        if ($email_sent) {
            $this->debug("success, email to '$to', subject '$subject' sent!",1);
        } else {
            $failure_reason = "error, email to '$to', subject '$subject' not sent";
            return $this->failure($failure_reason, false, $abort);
        }

        return $email_sent;

    }

    /*
     * private properties.
     */

    /*
     * private functions.
     */

    /**
     * aborts (exits, dies) with a given message & return code
     */
    private function aborting($aborting_message=null, $return_code=1, $alert=false)
    {

        $aborting = "aborting";
        if ($alert) {
            $aborting .= " & alerting";
        }
        $aborting .= ", $aborting_message ... ($return_code)";
        $aborting=$this->timeStamp($aborting);

        echo $this->br();
        echo "$aborting" . $this->br();;
        echo $this->br();

        if ($alert) {
            error_log($subject="!! ABORT !!", $body=$aborting . "\r\n\r\n");
        }

        $this->stop($return_code);

    }

    /**
     * output a debug message & return false or exit non-zero
     */
    private function failure($failure_reason=null, $abort=false, $alert=false)
    {
        $debug_level = 3;

        if ($failure_reason == null) {
            $failure_reason = "failure";
        }

        $failure_reason = trim($failure_reason);
        $failure_reason = trim($failure_reason,",");

        if ($abort) {
            $this->aborting($failure_reason,1,$alert);
        } else {
            if ($alert) {
                error_log($failure_reason);
            } else {
                $this->debug($failure_reason, $debug_level);
            }
            return false;
        }
    }

    /**
     * return a RFC 4122 compliant universally unique identifier
     */
    private function uuid()
    {

        $random_string=openssl_random_pseudo_bytes(16);
        $time_low=bin2hex(substr($random_string, 0, 4));
        $time_mid=bin2hex(substr($random_string, 4, 2));
        $time_hi_and_version=bin2hex(substr($random_string, 6, 2));
        $clock_seq_hi_and_reserved=bin2hex(substr($random_string, 8, 2));
        $node=bin2hex(substr($random_string, 10, 6));
        $time_hi_and_version=hexdec($time_hi_and_version);
        $time_hi_and_version=$time_hi_and_version >> 4;
        $time_hi_and_version=$time_hi_and_version | 0x4000;
        $clock_seq_hi_and_reserved=hexdec($clock_seq_hi_and_reserved);
        $clock_seq_hi_and_reserved=$clock_seq_hi_and_reserved >> 2;
        $clock_seq_hi_and_reserved=$clock_seq_hi_and_reserved | 0x8000;

        return sprintf("%08s-%04s-%04x-%04x-%012s", $time_low, $time_mid, $time_hi_and_version, $clock_seq_hi_and_reserved, $node);

    }

    /*
     * protected properties.
     */

    /*
     * protected functions.
     */

}

