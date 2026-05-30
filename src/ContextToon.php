<?php namespace ProcessWire;

/**
 * Context Module - TOON serializer
 *
 * Converts PHP arrays into Token-Oriented Object Notation for AI context files.
 */
class ContextToon {

    public function convert($data) {
        if(!is_array($data)) {
            return $this->formatSimpleValue($data) . "\n";
        }

        return $this->toToonRecursive($data, 0);
    }

    protected function toToonRecursive($data, $level = 0) {
        $indent = str_repeat('  ', $level);
        $output = '';

        if(!is_array($data)) {
            return $indent . $this->formatSimpleValue($data) . "\n";
        }

        if(isset($data[0]) && is_array($data)) {
            if($this->isTableFormat($data)) {
                return $this->formatTableData($data, $level);
            }

            foreach($data as $index => $item) {
                if(is_array($item)) {
                    $output .= $indent . "- # item $index\n";
                    foreach($item as $key => $value) {
                        if(is_array($value) && !empty($value)) {
                            if($this->isTableFormat($value)) {
                                $output .= $this->formatAsTable($key, $value, $level + 1);
                            } else {
                                $output .= $indent . "  " . $this->escapeKey($key) . ":\n";
                                $output .= $this->toToonRecursive($value, $level + 2);
                            }
                        } else {
                            $output .= $indent . "  " . $this->escapeKey($key) . ": " . $this->formatSimpleValue($value) . "\n";
                        }
                    }
                } else {
                    $output .= $indent . "- " . $this->formatSimpleValue($item) . "\n";
                }
            }

            return $output;
        }

        foreach($data as $key => $value) {
            if(is_array($value) && !empty($value)) {
                if($this->isTableFormat($value)) {
                    $output .= $this->formatAsTable($key, $value, $level);
                } else {
                    $output .= $indent . $this->escapeKey($key) . ":\n";
                    $output .= $this->toToonRecursive($value, $level + 1);
                }
            } else {
                $output .= $indent . $this->escapeKey($key) . ": " . $this->formatSimpleValue($value) . "\n";
            }
        }

        return $output;
    }

    protected function isTableFormat($array) {
        if(!isset($array[0])) return false;
        if(!is_array($array[0])) return false;

        $firstKeys = array_keys($array[0]);
        if(empty($firstKeys)) return false;

        foreach($array as $item) {
            if(!is_array($item)) return false;
            if(array_keys($item) != $firstKeys) return false;
        }

        return true;
    }

    protected function formatTableData($array, $level = 0) {
        $indent = str_repeat('  ', $level);
        $count = count($array);
        $fields = array_keys($array[0]);

        $output = $indent . "[{$count}]{" . implode(',', array_map([$this, 'escapeKey'], $fields)) . "}:\n";

        foreach($array as $row) {
            $values = [];
            foreach($fields as $field) {
                $val = $row[$field];
                $values[] = is_array($val)
                    ? $this->formatJsonValue($val)
                    : $this->formatSimpleValue($val);
            }
            $output .= $indent . implode(',', $values) . "\n";
        }

        return $output;
    }

    protected function formatAsTable($key, $array, $level = 0) {
        $indent = str_repeat('  ', $level);
        $count = count($array);
        $fields = array_keys($array[0]);

        $output = $indent . $this->escapeKey($key) . "[{$count}]{" . implode(',', array_map([$this, 'escapeKey'], $fields)) . "}:\n";

        foreach($array as $row) {
            $values = [];
            foreach($fields as $field) {
                $val = $row[$field];
                $values[] = is_array($val)
                    ? $this->formatJsonValue($val)
                    : $this->formatSimpleValue($val);
            }
            $output .= $indent . implode(',', $values) . "\n";
        }

        return $output;
    }

    protected function escapeKey($key) {
        $key = (string)$key;
        if(preg_match('/^[A-Za-z_][\w.]*$/', $key)) {
            return $key;
        }
        return '"' . addslashes($key) . '"';
    }

    protected function formatJsonValue($value) {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE);
        return $json === false ? $this->formatSimpleValue('[unserializable]') : $json;
    }

    protected function formatSimpleValue($value) {
        if(is_null($value)) return 'null';
        if(is_bool($value)) return $value ? 'true' : 'false';
        if(is_numeric($value)) return $value;

        $value = (string)$value;
        $needsQuotes = (
            strpos($value, ',') !== false ||
            strpos($value, ':') !== false ||
            strpos($value, "\n") !== false ||
            strpos($value, "\r") !== false ||
            strpos($value, "\t") !== false ||
            trim($value) !== $value ||
            $value === '' ||
            in_array(strtolower($value), ['true', 'false', 'null'])
        );

        if($needsQuotes) {
            $value = str_replace(['\\', '"', "\n", "\r", "\t"], ['\\\\', '\\"', '\\n', '\\r', '\\t'], $value);
            return '"' . $value . '"';
        }

        return $value;
    }
}
