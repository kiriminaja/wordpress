<?php
class settingIndex {
    function __construct(){
        $approvedSetupKey = (new Inc\Repositories\SettingRepository())->getSettingByKey('setup_key');

        $arrayParam = [];
        if ($_GET['tab']==='tab-integration'){
            $arrayParam=['oid_prefix'];
        } elseif ($_GET['tab']==='tab-shipping'){
            $arrayParam=['origin_name','origin_phone','origin_address','origin_sub_district_id','origin_sub_district_name'];
        } elseif ($_GET['tab']==='tab-advanced'){
            $arrayParam=['callback_url'];
        }
        
        $repo = (new \Inc\Repositories\SettingRepository())->getSettingByArray($arrayParam);
        global $inputValueArr;
        $inputValueArr = [];
        foreach ($repo as $obj){
            $inputValueArr[$obj->key]=$obj->value;
        }
        
        /** Return vars and view*/
        $GLOBALS["approvedSetupKey"] = $approvedSetupKey;
       
        if (@$approvedSetupKey->value){
            include 'setuped/index.php';
            return;
        }
        include 'unsetuped/index.php';
//        include dirname(__FILE__).'/../sample.php';
    }
}


new settingIndex();







?>