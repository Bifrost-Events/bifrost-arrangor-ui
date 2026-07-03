<?php

declare(strict_types=1);

/**
 * PHP < 8.5: response headers from the last HTTP stream wrapper request.
 * Kept in a separate file so PHP 8.5+ never parses the deprecated $http_response_header variable.
 *
 * @return list<string>
 */
return isset($http_response_header) && is_array($http_response_header) ? $http_response_header : [];
