<?php

require_once(dirname(__FILE__) . "/../vendor/autoload.php");

$Email = new \josephtingiris\Email(35); // 35 is the debug level

/**
 * a simple (to use), trustworthy email method
 *
 * @param {string|array|object} 'to' email address(es)
 * @param {string|array|object} 'subject' subject; arrays or objects will be merged into a string
 * @param {string|array|object} 'message' message body (parts); if a body (part) contains html tags then content-type html is set
 * @param {string|array|object} 'additional_headers' & custom options, e.g. cc, bcc, reply-to, attachment(s), etc.
 * @param {string|array|object} 'additional_parameters' passed to php mail()
 * @param {boolean} alert on failure
 * @param {boolean} abort on failure
 * @return {boolean} true on success, false on failure
 */
# public function email($to=null, $subject=null, $message=null, $additional_headers=null, $additional_parameters=null, $alert=false, $abort=false)

$Email->send("joseph.tingiris@gmail.com","test subject", "test message; plain text"); // most basic use case
?>
