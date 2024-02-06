<?php

namespace Inc\Base;

class Validator{
    
    public function validateSingle($value, $label, array $validateObjective){
        $status = true;
        $msg = '';
        if (in_array('required',$validateObjective)){
            if (!$value){
                $status = false;
                $msg = $label.' is required';
            }
        }
        return [
          'status'=>$status,
          'msg'=>$msg  
        ];
    }
    
    public function validateMultiple(array $validationParams){
        for ($i=0;$i<count($validationParams);$i++){
            $validationParam = $validationParams[$i];
            $validate = $this->validateSingle($validationParam[0],$validationParam[1],$validationParam[2]);
            if (!$validate['status']){
                return $validate;
                break;
            }
        }
        return [
            'status'=>true,
            'msg'=>''
        ];
    }
    
}