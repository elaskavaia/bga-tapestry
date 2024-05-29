<?php


class material_test {
    function __construct() {
        include '../material.inc.php';
        include '../stats.inc.php';

        //var_dump($this->card_types); // whatever your var
        //$cc = get_defined_constants(true)['user'];
        //foreach ($cc as $key => $value) {          
        //    print ("const $key = $value;\n");
        //}

        //5. Ships - Gain 1 VP for each territory you control [Bonus: pay 1 resource to gain 1 farm]
        /*
        foreach ($this->tech_track_data as $track => $track_arr){
            foreach ($track_arr as $spot => $data){
                print("$spot. $data[name] - $data[description]");
                if (isset($data['description_bonus'])) {
                    print(" [BONUS: $data[description]]");
                }
                if (isset($data['landmark'])) {
                    $ben=$data['landmark'][0];
                    foreach ($this->landmark_data as $ld) {
                        if ($ben == $ld['benefit']) {
                            print(" [LANDMARK: $ld[name]]");
                        }
                    }
                }
                print("\n");
            }
        }*/
        //         $i=61;
        $lookup = [];
        addConstants($lookup);
        // $common=['name','r','t'];
        // print("id|con|name|r|t|php\n");
        // ksort($this->benefit_types, SORT_NUMERIC);
        // foreach ($this->benefit_types as $ben => $ben_data){
        //     $fields = [$ben];
        //     $fields[]= array_get($lookup['constants_reverse']['BE'],$ben);
        //     if (array_get($ben_data,'t'))
        //         $ben_data['r']='t';
        //     $r = array_get($ben_data,'r');
        //     foreach ($common as $field) {
        //         $fields[]= array_get($ben_data,$field);
        //         unset($ben_data[$field]);
        //     }
        //     $flags = array_get($ben_data,'flags');
        //     if ($flags) {
        //         $nval = $flags;
        //         if ($r == 't')
        //             switch ($flags) {
        //                 case 1 :
        //                     $nval = "(FLAG_GAIN_BENFIT)";
        //                     break;
        //                 case 3 :
        //                     $nval = "(FLAG_GAIN_BENFIT|FLAG_PAY_BONUS)";
        //                     break;
        //                 case FLAG_MAXOUT_BONUS:
        //                     $nval = "(FLAG_MAXOUT_BONUS)";
        //                     break;
        //                 case FLAG_FREE_BONUS:
        //                     $nval = "(FLAG_FREE_BONUS)";
        //                     break;
        //                 case FLAG_JUMP:
        //                     $nval = "(FLAG_JUMP)";
        //                     break;
        //                 case FLAG_GAIN_BENFIT | FLAG_PAY_BONUS|FLAG_MAXOUT_BONUS :
        //                     $nval = "(FLAG_GAIN_BENFIT|FLAG_PAY_BONUS|FLAG_MAXOUT_BONUS)";
        //                     break;
        //             }
        //         $ben_data['flags']=str_replace("|", "\|", $nval);
        //     }
        //     $other = [];
        //     foreach ($ben_data as $key => $value) {
        //         if ((int)$value == $value || (is_string($value) && startsWith($value, "(")))
        //             $other[]="'$key'=>$value";
        //         else
        //             $other[]="'$key'=>'$value'";
        //     }
        //     if (count($other)>0)
        //     $fields[]=implode(",", $other);
        //     else
        //         $fields[]='';
        //     print(implode("|", $fields));
        //     print("\n");

        // }


        print("id,name, description\n");
        $this->doAdjustMaterial(2, 4);
        ksort($this->civilizations, SORT_NUMERIC);
        foreach ($this->civilizations as $civ => $civ_data) {
            $description = $civ_data['description'];
            if (is_array($description)) {
                $description = implode("\n", $description);
            }
            $name = $civ_data['name'];

            print("$civ,$name,\"$description\"\n");
        }
    }
}
// stub
function clienttranslate($x) {
    return $x;
}
function totranslate($x) {
    return $x;
}
function startsWith($haystack, $needle) {
    // search backwards starting from haystack length characters from the end
    return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== false;
}

function addConstants(&$result) {
    $cc = get_defined_constants(true)['user'];
    foreach ($cc as $key => $value) {
        $im = explode('_', $key);
        switch ($im[0]) {
            case 'CARD':
            case 'BE':
            case 'TERRAIN':
            case 'CIV':
            case 'TRACK':
            case 'RES':
            case 'INCOME':
            case 'TAP':
            case 'FLAG':
                $result['constants'][$key] = $value;
                $result['constants_reverse'][$im[0]][$value] = $key;
                break;
            default:
                break;
        }
    }
}

if (!function_exists('array_get')) {

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
        if (array_key_exists($key, $array))
            return $array[$key];
        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }
            $array = $array[$segment];
        }
        return $array;
    }
}

new material_test();
