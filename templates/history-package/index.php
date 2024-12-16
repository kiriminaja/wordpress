<?php
class History
{
    function getCountService($status="all"){
        return (new \Inc\Repositories\TransactionRepository())->getCountTabHistory($status);
    }
}
$history = new History();

/** Return vars and view*/
include 'view/index.php';
?>