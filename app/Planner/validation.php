<?php
declare(strict_types=1);
function validate_plan(array $plan): array {
    if (($plan['is_floor_plan'] ?? false) !== true) fail('The image could not be identified as a readable floor plan. Upload a clearer image.');
    $cameras=$plan['cameras'] ?? [];
    if(!is_array($cameras) || count($cameras)<1 || count($cameras)>8) fail('A plan must contain 1–8 camera positions. Larger systems require a custom survey.');
    $clean=[];
    foreach($cameras as $c){
        if(!is_array($c))fail('Invalid camera recommendation.');
        $type=strval_field($c,'type',20);if(!in_array($type,['indoor','outdoor']))fail('Invalid camera type.');
        $clean[]=['label'=>strval_field($c,'label',100),'type'=>$type,'x'=>number_field($c,'x',0,100),'y'=>number_field($c,'y',0,100),'direction'=>number_field($c,'direction',0,360),'reason'=>strval_field($c,'reason',1000)];
    }
    $warnings=$plan['warnings'] ?? [];
    if(!is_array($warnings) || count($warnings)>12)fail('Invalid plan warnings.');
    foreach($warnings as $w)if(!is_string($w)||mb_strlen($w)>1000)fail('Invalid plan warning.');
    return ['is_floor_plan'=>true,'summary'=>strval_field($plan,'summary',2000),'cameras'=>$clean,'warnings'=>$warnings];
}
