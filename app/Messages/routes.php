<?php
declare(strict_types=1);
function handle_messages_action(string $action,array $data,array $u,bool $staff): never {
    switch ($action) {
    case 'message_unread':
        require_user(['admin','staff','customer']);
        $count=$staff
            ? query("SELECT COUNT(*) FROM messages m JOIN users u ON u.id=m.customer_id WHERE m.sender_id=m.customer_id AND m.read_at IS NULL AND u.active=1 AND u.role='customer'")->fetchColumn()
            : query('SELECT COUNT(*) FROM messages WHERE customer_id=? AND sender_id<>? AND read_at IS NULL',[$u['id'],$u['id']])->fetchColumn();
        json_out(['unread'=>(int)$count]);
    case 'threads':
        require_user(['admin','staff']);
        json_out(['items'=>query("SELECT u.id,u.name,u.email,(SELECT body FROM messages WHERE customer_id=u.id ORDER BY id DESC LIMIT 1) last_message,(SELECT COUNT(*) FROM messages WHERE customer_id=u.id AND sender_id=u.id AND read_at IS NULL) unread FROM users u WHERE u.role='customer' AND u.active=1 ORDER BY (SELECT MAX(id) FROM messages WHERE customer_id=u.id) DESC,u.name")->fetchAll()]);
    case 'messages':
    case 'message_send':
        require_user(['admin','staff','customer']);
        $cid=$staff?integer_field($data,'customer_id',1):$u['id'];
        if(!query("SELECT id FROM users WHERE id=? AND role='customer'",[$cid])->fetch()) fail('Conversation not found.',404);
        if($action==='message_send'){
            db()->beginTransaction();query('INSERT INTO messages(customer_id,sender_id,body) VALUES(?,?,?)',[$cid,$u['id'],strval_field($data,'body',4000)]);$key='message:'.db()->lastInsertId();if($staff)notify_user((int)$cid,'message','New message from NCPCS','Open Messages to read the reply.','messages',$key);else notify_office('message','New customer message','Open Messages to read it.','messages/'.$cid,$key);db()->commit();json_out(['ok'=>true]);
        }
        if($staff)query('UPDATE messages SET read_at=NOW() WHERE customer_id=? AND sender_id=? AND read_at IS NULL',[$cid,$cid]);
        else query('UPDATE messages SET read_at=NOW() WHERE customer_id=? AND sender_id<>? AND read_at IS NULL',[$cid,$cid]);
        json_out(['items'=>array_reverse(query('SELECT m.*,u.name sender_name,u.role sender_role FROM messages m JOIN users u ON u.id=m.sender_id WHERE customer_id=? ORDER BY m.id DESC LIMIT 200',[$cid])->fetchAll())]);
    default: fail('Action not found.',404);
    }
}
