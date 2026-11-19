<?php
declare(strict_types=1);
function report_date_range(array $data,string $type): array {
    $from=strval_field($data,'from',10,false);$to=strval_field($data,'to',10,false);
    foreach([$from,$to] as $value){
        if($value==='')continue;$date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);
        if(!$date || $date->format('Y-m-d')!==$value)fail('Enter a valid report date in YYYY-MM-DD format.');
    }
    if($from!==''&&$to!==''&&$from>$to)fail('The start date must be on or before the end date.');
    if(($from!==''||$to!=='')&&!in_array($type,['services','schedule','movements'],true))fail('Date filters apply to service, schedule and stock-movement reports.');
    return [$from,$to];
}
function filter_report_query(string $sql,string $type,string $from,string $to): array {
    $column=match($type){'services'=>'s.created_at','schedule'=>'s.scheduled_at','movements'=>'m.created_at',default=>null};
    $conditions=[];$params=[];
    if($column && $from!==''){$conditions[]="$column >= ?";$params[]=$from.' 00:00:00';}
    if($column && $to!==''){$conditions[]="$column < DATE_ADD(?, INTERVAL 1 DAY)";$params[]=$to.' 00:00:00';}
    if($conditions){
        $order=strpos($sql,' ORDER BY');$base=$order===false?$sql:substr($sql,0,$order);$tail=$order===false?'':substr($sql,$order);
        $sql=$base.(str_contains($base,' WHERE ')?' AND ':' WHERE ').implode(' AND ',$conditions).$tail;
    }
    return [$sql,$params];
}
