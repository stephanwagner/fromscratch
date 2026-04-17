<?php

/**
 * Harden REST user endpoints against public enumeration.
 * Keep endpoints registered, but deny unauthenticated requests.
 */
add_filter('rest_request_before_callbacks', function ($response, $handler, $request) {

    if (!($request instanceof WP_REST_Request)) {
        return $response;
    }

    $route = (string) $request->get_route();

    // Match all relevant user endpoints
    if (!preg_match('#^/wp/v2/users(?:/(\d+|me))?$#', $route)) {
        return $response;
    }

    // 1. Normal REST auth (works when nonce is present)
    if (is_user_logged_in()) {
        return $response;
    }

    // 2. Fallback: validate logged-in cookie (for admin / Gutenberg edge cases)
    if (defined('LOGGED_IN_COOKIE') && !empty($_COOKIE[LOGGED_IN_COOKIE])) {
        $cookie = wp_unslash($_COOKIE[LOGGED_IN_COOKIE]);

        if (wp_validate_auth_cookie($cookie, 'logged_in')) {
            return $response;
        }
    }

    // 3. Block everything else
    return new WP_Error(
        'rest_forbidden_users',
        __('You are not allowed to access user data.', 'fromscratch'),
        ['status' => 403]
    );
}, 10, 3);
