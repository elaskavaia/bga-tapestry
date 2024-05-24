<?php

function shuffle_assoc($my_array) {
    $keys = array_keys($my_array);
    shuffle($keys);
    foreach ( $keys as $key ) {
        $new [$key] = $my_array [$key];
    }
    $my_array = $new;
    return $my_array;
}

function reason_tapestry($number, $arg="") {
    return reason('tapestry', $number, $arg);
}

function reason_civ($number, $arg="") {
    return reason('civ', $number, $arg);
}

function reason($kind, $number, $arg="") {
    return ":$kind:$number".($arg?":$arg":"");
}

function getPart($haystack, $i, $bNoexeption = false, $sep = '_') {
    $parts = explode($sep, $haystack);
    $len = count($parts);
    if ($bNoexeption && $i >= $len)
        return "";
    return $parts [$i];
}

function getReasonPart($haystack, $i){
    return getPart($haystack, $i, true, ':');
}

function varsub($line, $keymap) {
    if (strpos($line, "{") !== false) {
        foreach ( $keymap as $key => $value ) {
            $exkey = '${' . "$key}";
            if (strpos($line, $exkey) !== false) {
                $line = str_replace($exkey, $value, $line);
            }
        }
    }
    return $line;
}

function startsWith($haystack, $needle) {
    // search backwards starting from haystack length characters from the end
    return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== false;
}

function endsWith($haystack, $needle) {
    $length = strlen($needle);
    return $length === 0 || (substr($haystack, -$length) === $needle);
}

function toJson($data, $options = JSON_PRETTY_PRINT) {
    $json_string = json_encode($data, $options);
    return $json_string;
}
if ( !function_exists('array_get')) {

    /**
     * Get an item from an array using "dot" notation.
     *
     * @param array $array
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function array_get($array, $key, $default = null) {
        if (is_null($key))
            return $array;
        if ( !is_array($array))
            return $default;
        if (array_key_exists($key, $array))
            return $array [$key];
        foreach ( explode('.', $key) as $segment ) {
            if ( !is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }
            $array = $array [$segment];
        }
        return $array;
    }
}
if ( !function_exists('array_key_first')) {

    function array_key_first(array $arr) {
        foreach ( $arr as $key => $_unused ) {
            return $key;
        }
        return null;
    }
}

function array_get_def($array, $key, $field, $default = null) {
    return array_get($array, "${key}.${field}", $default);
}

function array_inc(&$array, $key, $inc = 1) {
    if (is_null($key))
        throw new feException("null key");
    if ( !is_array($array)) {
        throw new feException("not array");
    }
    if (array_key_exists($key, $array)) {
        $array [$key] += $inc;
    } else {
        $array [$key] = $inc;
    }
    return $array [$key];
}

function array_prefix_all($array, $prefix) {
    foreach ( $array as $key => $val ) {
        if ($val===null) continue;
        $array[$key] = $prefix . $val;
    }
    return $array;
}

function in_range($value, $min, $max) {
    return ($min <= $value) && ($value <= $max);
}

function is_flag_set($value, $flag) {
    return ($value & $flag) == $flag;
}

function array_remove_value(&$array, $value) {
    if (($key = array_search($value, $array)) !== false) {
        unset($array [$key]);
        return $key;
    }
    return null;
}