<?php
declare(strict_types=1);
function accessible_quote(int $id,array $u,bool $lock=false): array {
    $q=query('SELECT * FROM quotations WHERE id=?'.($lock?' FOR UPDATE':''),[$id])->fetch();
    if(!$q || ($u['role']==='customer' && $q['customer_id']!=$u['id']) || $u['role']==='technician')fail('Quotation not found.',404);return $q;
}
function quote_event(array $q,int $actor,string $status,string $note): void {
    query('INSERT INTO quotation_events(quotation_id,version,actor_id,status,note) VALUES(?,?,?,?,?)',[$q['id'],$q['current_version'],$actor,$status,$note]);
    $key='quote:'.db()->lastInsertId();$title='QT-'.str_pad((string)$q['id'],5,'0',STR_PAD_LEFT).' · '.$status;
    notify_user((int)$q['customer_id'],'quotation',$title,$note,'quotations/'.$q['id'],$key);notify_office('quotation',$title,$note,'quotations/'.$q['id'],$key);
}
function handle_quotations(string $action,array $data,array $u): never {
    require_user(['admin','staff','customer']);$staff=in_array($u['role'],['admin','staff'],true);
    if($action==='quotations'){
        $where=$staff?'':' WHERE q.customer_id=? AND q.status<>\'Draft\'';
        json_out(['items'=>query("SELECT q.*,u.name customer_name,s.title,v.total,v.valid_until,CASE WHEN q.status IN ('Sent','Viewed','Revision Requested') AND v.valid_until<CURDATE() THEN 'Expired' ELSE q.status END display_status FROM quotations q JOIN users u ON u.id=q.customer_id JOIN services s ON s.id=q.service_id JOIN quotation_versions v ON v.quotation_id=q.id AND v.version=q.current_version".$where.' ORDER BY q.id DESC',$staff?[]:[$u['id']])->fetchAll()]);
    }
    if($action==='quotation'){
        $q=accessible_quote(integer_field($data,'id',1),$u);if(!$staff && $q['status']==='Draft')fail('Quotation not found.',404);
        $version=integer_field($data,'version')?:$q['current_version'];$v=query('SELECT * FROM quotation_versions WHERE quotation_id=? AND version=?',[$q['id'],$version])->fetch();if(!$v)fail('Quotation version not found.',404);
        $customer=query('SELECT name,email,phone,address FROM users WHERE id=?',[$q['customer_id']])->fetch();
        $current=query('SELECT valid_until FROM quotation_versions WHERE quotation_id=? AND version=?',[$q['id'],$q['current_version']])->fetchColumn();if($current<date('Y-m-d') && in_array($q['status'],['Sent','Viewed','Revision Requested'],true))$q['status']='Expired';
        json_out(['item'=>$q,'version'=>$v,'customer'=>$customer,'service'=>accessible_service((int)$q['service_id'],$u),'items'=>query('SELECT * FROM quotation_items WHERE version_id=?',[$v['id']])->fetchAll(),'versions'=>query('SELECT version,created_at FROM quotation_versions WHERE quotation_id=? ORDER BY version DESC',[$q['id']])->fetchAll(),'events'=>query('SELECT e.*,u.name FROM quotation_events e JOIN users u ON u.id=e.actor_id WHERE quotation_id=? ORDER BY e.id DESC',[$q['id']])->fetchAll()]);
    }
    if($action==='quotation_save'){
        require_user(['admin','staff']);$id=integer_field($data,'id');$sid=integer_field($data,'service_id',1);$items=$data['items']??null;if(!is_array($items)||!count($items)||count($items)>50)fail('Add between one and fifty equipment items.');
        $valid=strict_date(strval_field($data,'valid_until',10));if($valid<date('Y-m-d'))fail('Quotation validity cannot be in the past.');
        $terms=strval_field($data,'terms',5000);$labor=(int)round(number_field($data,'labor')*100);$additional=(int)round(number_field($data,'additional')*100);$discount=(int)round(number_field($data,'discount')*100);
        db()->beginTransaction();$s=accessible_service($sid,$u,true);if(in_array($s['status'],['Cancelled','Completed'],true))fail('Cannot quote a closed service.');
        if($id){$q=accessible_quote($id,$u,true);check_revision($data,(int)$q['current_version'],'version');if($q['service_id']!=$sid)fail('A revision must keep the same service.');if($q['status']==='Approved')fail('An approved quotation is immutable. Create a separate proposal.',409);}
        else{
            $key=request_key($data);$duplicate=query('SELECT id,service_id,current_version FROM quotations WHERE customer_id=? AND request_key=? FOR UPDATE',[$s['customer_id'],$key])->fetch();if($duplicate){if($duplicate['service_id']!=$sid)fail('This submission key belongs to another quotation.',409);db()->commit();json_out(['id'=>(int)$duplicate['id'],'version'=>(int)$duplicate['current_version'],'duplicate'=>true]);}
            $bid=integer_field($data,'blueprint_id')?:null;if($bid){$b=accessible_blueprint($bid,$u);if($b['user_id']!=$s['customer_id'])fail('The blueprint must belong to this customer.');}
            query('INSERT INTO quotations(customer_id,service_id,blueprint_id,request_key) VALUES(?,?,?,?)',[$s['customer_id'],$sid,$bid,$key]);$id=(int)db()->lastInsertId();$q=accessible_quote($id,$u);
        }
        $lines=[];$subtotal=$labor+$additional;$seen=[];
        foreach($items as $item){if(!is_array($item))fail('Invalid equipment line.');$pid=integer_field($item,'product_id',1);$qty=integer_field($item,'quantity',1,100000);if(isset($seen[$pid]))fail('Combine duplicate equipment lines.');$seen[$pid]=true;
            $p=query('SELECT id,name,price FROM products WHERE id=? AND active=1',[$pid])->fetch();if(!$p)fail('Choose active catalog equipment.');$price=(int)round((float)$p['price']*100);$subtotal+=$price*$qty;$lines[]=[$pid,$p['name'],$qty,$price/100];
        }
        $settings=settings();$tax=(float)($settings['quote_tax_percent']??0);$maxDiscount=(float)($settings['quote_max_discount_percent']??0);
        if($discount>$subtotal || $discount>(int)round($subtotal*$maxDiscount/100))fail('Discount exceeds the configured allowance.');
        $total=$subtotal-$discount+(int)round(($subtotal-$discount)*$tax/100);if($total>999999999999)fail('Quotation total exceeds the supported amount.');
        $version=(int)$q['current_version']+1;
        query('INSERT INTO quotation_versions(quotation_id,version,author_id,valid_until,terms,labor,additional,discount,tax_percent,total) VALUES(?,?,?,?,?,?,?,?,?,?)',[$id,$version,$u['id'],$valid,$terms,$labor/100,$additional/100,$discount/100,$tax,$total/100]);$vid=(int)db()->lastInsertId();
        foreach($lines as $line)query('INSERT INTO quotation_items(version_id,product_id,description,quantity,unit_price) VALUES(?,?,?,?,?)',[$vid,...$line]);
        query("UPDATE quotations SET current_version=?,status='Draft' WHERE id=?",[$version,$id]);$q['current_version']=$version;
        query('INSERT INTO quotation_events(quotation_id,version,actor_id,status,note) VALUES(?,?,?,?,?)',[$id,$version,$u['id'],'Draft','Created quotation version.']);audit('Saved quotation',"$id v$version");db()->commit();json_out(['id'=>$id,'version'=>$version]);
    }
    $id=integer_field($data,'id',1);$status=strval_field($data,'status',30);db()->beginTransaction();
    // Consistent service -> quotation lock order with saving and stock consumption.
    $initial=accessible_quote($id,$u);accessible_service((int)$initial['service_id'],$u,true);$q=accessible_quote($id,$u,true);check_revision($data,(int)$q['current_version'],'version');
    $v=query('SELECT * FROM quotation_versions WHERE quotation_id=? AND version=?',[$id,$q['current_version']])->fetch();
    if($v['valid_until']<date('Y-m-d'))fail('This quotation has expired. Ask staff for a new version.',409);
    if($staff){if($status!=='Sent'||$q['status']!=='Draft')fail('Only draft quotations can be sent.',409);}
    else{
        if(!in_array($status,['Viewed','Approved','Rejected','Revision Requested'],true) || !in_array($q['status'],['Sent','Viewed'],true))fail('This quotation can no longer be changed.',409);
        if($status==='Viewed' && $q['status']==='Viewed'){db()->commit();json_out(['ok'=>true]);}
    }
    $note=strval_field($data,'note',2000,$status==='Revision Requested');
    query('UPDATE quotations SET status=? WHERE id=?',[$status,$id]);quote_event($q,(int)$u['id'],$status,$note?:'Quotation version '.$q['current_version'].' '.$status.'.');audit('Quotation decision',"$id v{$q['current_version']}: $status");db()->commit();json_out(['ok'=>true]);
}
