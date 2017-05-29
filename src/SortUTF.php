<?php

class SortUTF {

    static function sort($array, $useCh = NULL, $chars = NULL) {
        if (gettype($array) != 'array') {
            return false;
        }
        if ($chars == NULL) {
            $chars = '0,1,2,3,4,5,6,7,8,9,a,A,á,Á,b,B,c,C,č,Č,d,D,ď,Ď,e,E,é,É,ě,Ě,f,F,g,G,h,H,ch,Ch,CH,i,I,í,Í,j,J,k,K,l,L,m,M,n,N,ň,Ň,o,O,ó,Ó,p,P,q,Q,r,R,ř,Ř,s,S,š,Š,t,T,ť,Ť,u,U,ú,Ú,ů,Ů,v,V,w,W,x,X,y,Y,y,Ý,z,Z,ž,Ž';
        }
        $alphabet = explode(',', $chars);
        for ($i = 0; $i < count($array); $i++) {
            $array[$i] = SortUTF::split($array[$i], $useCh);
        }
        $array = SortUTF::bubbleSort($array, $alphabet);
        for ($i = 0; $i < count($array); $i++) {
            if ($useCh == true) {
                $checkNext = false;
                $tmp = '';
                for ($j = 0; $j < count($array[$i]); $j++) {
                    if ($checkNext == true) {
                        $checkNext = false;
                    } else {
                        $tmp = $tmp . $array[$i][$j];
                        if ($array[$i][$j] == 'ch' || $array[$i][$j] == 'Ch' || $array[$i][$j] == 'CH') {
                            $checkNext = true;
                        }
                    }
                }
                $array[$i] = $tmp;
            } else {
                $array[$i] = implode('', $array[$i]);
            }
        }
        return $array;
    }

    static function bubbleSort($array, $alphabet) {
        for ($i = 0; $i < count($array); $i++) {
            for ($j = 0; $j < count($array) - 1 - $i; $j++) {
                if (SortUTF::compareArray($array[$j], $array[$j + 1], $alphabet) == true) {
                    $tmp = $array[$j + 1];
                    $array[$j + 1] = $array[$j];
                    $array[$j] = $tmp;
                }
            }
        }
        return $array;
    }

    static function split($string, $useCh) {
        $length = mb_strlen($string);
        $array = array();
        $checkNext = false;
        for ($i = 0; $i < $length; $i++) {
            $array[$i] = mb_substr($string, $i, 1);
            if ($checkNext == true) {
                if ($array[$i] == 'h' || $array[$i] == 'H') {
                    $array[$i - 1] = $array[$i - 1] . $array[$i];
                }
                $checkNext = false;
            }
            if ($array[$i] == 'c' || $array[$i] == 'C') {
                if ($useCh != NULL) {
                    $checkNext = true;
                }
            }
        }
        return $array;
    }

    static function compareArray($a, $b, $alphabet) {
        $bigger = false;
        for ($i = 0;; $i++) {
            $keyA = SortUTF::giveKey($a[$i], $alphabet);
            $keyB = SortUTF::giveKey($b[$i], $alphabet);
            if ($keyA > $keyB) {
                $bigger = true;
                break;
            } elseif ($keyA < $keyB) {
                $bigger = false;
                break;
            }
            if (isset($a[$i + 1])) {
                if (!isset($b[$i + 1])) {
                    $bigger = true;
                    break;
                }
            } else {
                break;
            }
        }
        return $bigger;
    }

    static function giveKey($char, $alphabet) {
        $key = array_search($char, $alphabet);
        if ($key === NULL) {
            $key = $char;
        }
        return $key;
    }

}
