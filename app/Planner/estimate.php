<?php
declare(strict_types=1);
function build_estimate(array $plan, array $rates, array $catalog): array {
    $bySku=[];foreach($catalog as $p)$bySku[$p['sku']]=$p;
    $count=count($plan['cameras']);$out=count(array_filter($plan['cameras'],fn($c)=>$c['type']==='outdoor'));$in=$count-$out;
    $qty=['CAM-OUT-4MP'=>$out,'CAM-IN-4MP'=>$in,'NVR-8-POE'=>1,'HDD-1TB-SUR'=>1,'CAT6-M'=>(int)ceil($count*(float)$rates['cable_meters_per_camera']),'INSTALL-KIT'=>$count];
    $lines=[];$equipment=0;$warnings=[];
    foreach($qty as $sku=>$n){
        if(!$n)continue;
        if(!isset($bySku[$sku]))fail("The quotation catalog is missing $sku. Ask an administrator to restore it.",409);
        $p=$bySku[$sku];$unit=(int)round((float)$p['price']*100);$sum=$unit*$n;$equipment+=$sum;
        $lines[]=['sku'=>$sku,'name'=>$p['name'],'quantity'=>$n,'unit_price'=>$unit/100,'total'=>$sum/100,'available'=>(int)$p['stock']];
        if($p['stock']<$n)$warnings[]=$p['name'].' requires restocking before installation.';
    }
    $laborCamera=(int)round((float)$rates['labor_per_camera']*100)*$count;
    $setup=(int)round((float)$rates['setup_labor']*100);
    $subtotal=$equipment+$laborCamera+$setup;
    $reserve=(int)round($subtotal*(float)$rates['contingency_percent']/100);
    return ['currency'=>'PHP','lines'=>$lines,'equipment'=>$equipment/100,'installation_labor'=>$laborCamera/100,'setup_labor'=>$setup/100,'subtotal'=>$subtotal/100,'contingency_percent'=>(float)$rates['contingency_percent'],'contingency'=>$reserve/100,'total'=>($subtotal+$reserve)/100,'warnings'=>$warnings,'price_note'=>$rates['price_note'],'created_at'=>date(DATE_ATOM),'assumptions'=>['All cameras use the same PoE/IP system, with one 8-channel PoE NVR.','Cable allowance: '.$rates['cable_meters_per_camera'].' meters per camera. Actual routes must be measured.','1TB storage is an allowance; recording duration depends on bitrate, recording mode, and camera count.','No taxes, civil works, internet fees, scaffolding, or electrical upgrades are separately calculated. Confirm them in the final quote.','Approximate positions and directions require an on-site check for blind spots, mounting height, lighting, and privacy.']];
}
