<?php
/**
 * Dotenv — tiny .env file loader. No Composer, no dependencies.
 *
 * Supports:
 *   KEY=value
 *   KEY="quoted value with # kept"
 *   KEY='single quoted'
 *   export KEY=value
 *   # comments, blank lines
 *   Inline comments only outside quotes
 *
 * Values already present in the real environment are never overwritten,
 * matching the behaviour of most dotenv libraries.
 */
class Dotenv
{
    /**
     * Load variables from a .env file into getenv()/$_ENV.
     *
     * @param string $path Path to the .env file.
     * @return int Number of variables loaded.
     */
    public static function load($path)
    {
        if (!is_file($path) || !is_readable($path)) {
            return 0;
        }

        $loaded = 0;
        foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (stripos($line, 'export ') === 0) {
                $line = trim(substr($line, 7));
            }

            $eq = strpos($line, '=');
            if ($eq === false) {
                continue;
            }

            $key = trim(substr($line, 0, $eq));
            $value = trim(substr($line, $eq + 1));

            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
                continue;
            }

            // Quoted values keep everything inside the quotes (incl. #).
            $first = isset($value[0]) ? $value[0] : '';
            if (($first === '"' || $first === "'") && strlen($value) > 1 && substr($value, -1) === $first) {
                $value = substr($value, 1, -1);
                if ($first === '"') {
                    // Double-quoted: unescape common sequences.
                    $value = stripcslashes($value);
                }
            } else {
                // Unquoted: strip trailing inline comment.
                $hash = strpos($value, '#');
                if ($hash !== false) {
                    $value = trim(substr($value, 0, $hash));
                }
            }

            // Never clobber the real environment.
            if (getenv($key) === false && !array_key_exists($key, $_ENV)) {
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
                $loaded++;
            }
        }

        return $loaded;
    }

    /**
     * Fetch a variable with an optional default.
     */
    public static function get($key, $default = null)
    {
        $val = getenv($key);
        return $val === false ? $default : $val;
    }
}
