<?php
if (!function_exists('dbDelta')) { function dbDelta($queries) { global $__dbdelta_captured; if (is_string($queries)) { $q=$queries; } elseif (is_array($queries)) { $q=implode("\n", $queries); } else { $q=''; } $GLOBALS['__dbdelta_captured'][] = $q; return true; } }
