<?php

/**
 * Disable REST API users endpoint
 */
add_filter('rest_endpoints', function ($endpoints) {
    unset($endpoints['/wp/v2/users']);
    return $endpoints;
});
