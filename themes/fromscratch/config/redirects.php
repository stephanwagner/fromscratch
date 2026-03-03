<?php

/**
 * Redirects config.
 *
 * method: 'wordpress' = run redirects in PHP (template_redirect).
 *         'htaccess'  = write rules to .htaccess (Apache; file must be writable).
 */
return [
	'method' => 'htaccess',
];
