<?php

namespace App\View\Helper;

use Cake\View\Helper;

class AmountHelper extends Helper {
    public function format($value) {
        $value = number_format($value, 8, '.', ',');
        $dotIdx = strpos($value, '.');
        if ($dotIdx !== false) {
            $left = substr($value, 0, $dotIdx);
            $right = substr($value, $dotIdx + 1);

            $value = $left;
            if ((int) $right > 0) {
                $value .= '.' . rtrim($right, '0');
            }
        }

        return $value;
    }

    public function formatCurrency($value) {
        $dotIdx = strpos($value, '.');
        if ($dotIdx !== false) {
            $left = substr($value, 0, $dotIdx);
            $right = substr($value, $dotIdx + 1);

            $value = number_format($left, 0, '', ',');
            if ((int) $right > 0) {
                if (strlen($right) === 1) {
                    $value .= '.' . $right . '0';
                } else {
                    $value .= '.' . substr($right, 0, 2);
                }
            }
        }

        return $value;
    }
}

?>