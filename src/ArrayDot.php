<?php
/**
 * ArrayDot — dot-notation access for nested PHP arrays.
 *
 * Dependency-free. Keys are split on dots; numeric segments address list
 * indexes. Missing paths return a default instead of throwing notices.
 *
 *   $cfg = ['db' => ['host' => 'localhost', 'ports' => [3306, 3307]]];
 *   ArrayDot::get($cfg, 'db.host');          // 'localhost'
 *   ArrayDot::get($cfg, 'db.ports.1');       // 3307
 *   ArrayDot::get($cfg, 'db.user', 'root');  // 'root' (default)
 *   ArrayDot::set($cfg, 'cache.ttl', 300);   // creates nested arrays
 *   ArrayDot::pluck($rows, 'user.email');    // column of nested values
 */
final class ArrayDot
{
    /**
     * Get a nested value. Returns $default when any segment is missing.
     *
     * @param array  $array
     * @param string $key     Dot path, e.g. 'db.connections.mysql.host'
     * @param mixed  $default
     * @return mixed
     */
    public static function get(array $array, $key, $default = null)
    {
        if ($key === '' || $key === null) {
            return $array;
        }
        $segments = explode('.', (string) $key);
        $current  = $array;
        foreach ($segments as $segment) {
            if (is_array($current) && self::exists($current, $segment)) {
                $current = $current[$segment];
            } else {
                return $default;
            }
        }
        return $current;
    }

    /**
     * Set a nested value, creating intermediate arrays as needed.
     *
     * @param array  $array Passed by reference.
     * @param string $key   Dot path.
     * @param mixed  $value
     */
    public static function set(array &$array, $key, $value)
    {
        $segments = explode('.', (string) $key);
        $ref      = &$array;
        $last     = array_pop($segments);
        foreach ($segments as $segment) {
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = array();
            }
            $ref = &$ref[$segment];
        }
        $ref[$last] = $value;
        unset($ref); // break the reference
    }

    /**
     * Check whether a dot path exists (even if its value is null).
     */
    public static function has(array $array, $key)
    {
        $segments = explode('.', (string) $key);
        $current  = $array;
        foreach ($segments as $segment) {
            if (is_array($current) && self::exists($current, $segment)) {
                $current = $current[$segment];
            } else {
                return false;
            }
        }
        return true;
    }

    /**
     * Remove a nested key. No-op when the path does not exist.
     */
    public static function forget(array &$array, $key)
    {
        $segments = explode('.', (string) $key);
        $last     = array_pop($segments);
        $ref      = &$array;
        foreach ($segments as $segment) {
            if (!is_array($ref) || !self::exists($ref, $segment)) {
                unset($ref);
                return;
            }
            $ref = &$ref[$segment];
        }
        if (is_array($ref)) {
            unset($ref[$last]);
        }
        unset($ref);
    }

    /**
     * Pull one nested column out of a list of arrays.
     * Missing values become $default so the result keeps its indexes.
     *
     * @param array  $rows
     * @param string $key
     * @param mixed  $default
     * @return array
     */
    public static function pluck(array $rows, $key, $default = null)
    {
        $out = array();
        foreach ($rows as $i => $row) {
            $out[$i] = is_array($row) ? self::get($row, $key, $default) : $default;
        }
        return $out;
    }

    /**
     * Flatten nested arrays to dot keys: ['a' => ['b' => 1]] → ['a.b' => 1].
     * Numeric keys are kept (lists flatten to 'tags.0', 'tags.1', ...).
     *
     * @param array  $array
     * @param string $prefix Internal recursion prefix.
     * @return array
     */
    public static function flatten(array $array, $prefix = '')
    {
        $out = array();
        foreach ($array as $k => $v) {
            $path = $prefix === '' ? (string) $k : $prefix . '.' . $k;
            if (is_array($v) && $v !== array()) {
                $out += self::flatten($v, $path);
            } else {
                $out[$path] = $v;
            }
        }
        return $out;
    }

    /**
     * Return only the given top-level keys.
     */
    public static function only(array $array, array $keys)
    {
        return array_intersect_key($array, array_flip($keys));
    }

    /**
     * Return the array without the given top-level keys.
     */
    public static function except(array $array, array $keys)
    {
        return array_diff_key($array, array_flip($keys));
    }

    /**
     * Array key existence that also sees numeric-string keys and null values
     * (unlike isset()).
     */
    private static function exists(array $array, $segment)
    {
        return array_key_exists($segment, $array)
            || (is_numeric($segment) && array_key_exists((int) $segment, $array));
    }
}
