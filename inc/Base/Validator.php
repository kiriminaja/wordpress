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
        
        for ($i=0; $i<count($validateObjective); $i++){
            $validateString = @$validateObjective[$i] ?? '';
            
            /** Validation Subject*/
            
            if ($validateString === 'required'){
                if (!$value){
                    $status = false;
                    $msg = $label.' is required';
                }
            }
            if (str_contains($validateString,'max:')){
                $max_chars = explode(':',$validateString)[1] ?? 0;
                (new \Inc\Base\BaseInit())->logThis('$max_chars',[$max_chars]);
                if (strlen($value) > $max_chars){
                    $status = false;
                    $msg = $label.' max char is '.$max_chars;
                    break;
                }
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