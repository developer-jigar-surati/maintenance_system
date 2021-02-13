<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Common extends Model
{
    public static function clean_input($data_arr)
    {
        Log::info("Common => clean_input Method Called ");
        $temp = [];
        if(!empty($data_arr)){
            foreach($data_arr as $key => $value){
                if(is_array($value)){
                    foreach($value as $k => $v){
                        $temp[$key][$k] = html_entity_decode(strip_tags(trim($v)),ENT_QUOTES);
                    }
                } else {
                    $temp[$key] = html_entity_decode(strip_tags(trim($value)),ENT_QUOTES);
                }
                
            }
        }
        return $temp;
    }
}
