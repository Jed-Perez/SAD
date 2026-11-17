<?php
declare(strict_types=1);
function handle_users_action(string $action,array $data,array $u): never {
    switch ($action) {
    case 'users':
        require_user(['admin','staff']);
        $sql='SELECT id,name,email,role,phone,address,active,created_at FROM users';
        if($u['role']==='staff') $sql.=" WHERE role IN ('customer','technician')";
        json_out(['items'=>query($sql.' ORDER BY name')->fetchAll()]);
    case 'user_save':
        require_user(['admin','staff']);
        $id=integer_field($data,'id');$role=strval_field($data,'role',20);
        if(!in_array($role,['admin','staff','technician','customer'])) fail('Invalid role.');
        if($u['role']==='staff' && ($role!=='customer' || ($id && query('SELECT role FROM users WHERE id=?',[$id])->fetchColumn()!=='customer'))) fail('Staff can manage customers only.',403);
        $email=strtolower(strval_field($data,'email',190));if(!filter_var($email,FILTER_VALIDATE_EMAIL)) fail('Invalid email.');
        $active=integer_field($data,'active',0,1);
        if($id===$u['id'] && ($role!==$u['role'] || !$active)) fail('You cannot deactivate or change your own role.');
        $p=[strval_field($data,'name',120),$email,$role,strval_field($data,'phone',40,false),strval_field($data,'address',500,false),$active];
        db()->beginTransaction();
        if($id){
            $existing=query('SELECT * FROM users WHERE id=? FOR UPDATE',[$id])->fetch();if(!$existing) fail('User not found.',404);
            if($existing['role']!==$role && (query('SELECT COUNT(*) FROM services WHERE customer_id=? OR technician_id=?',[$id,$id])->fetchColumn() || query('SELECT COUNT(*) FROM blueprints WHERE user_id=?',[$id])->fetchColumn() || query('SELECT COUNT(*) FROM messages WHERE customer_id=?',[$id])->fetchColumn())) fail('Keep the role of an account with service, blueprint, or conversation history. Create a separate account for the new role.');
            query('UPDATE users SET name=?,email=?,role=?,phone=?,address=?,active=? WHERE id=?',[...$p,$id]);
        } else {
            $pw=strval_field($data,'password',200);if(strlen($pw)<10) fail('Use a password with at least 10 characters.');
            query('INSERT INTO users(name,email,role,phone,address,active,password_hash) VALUES(?,?,?,?,?,?,?)',[...$p,password_hash($pw,PASSWORD_DEFAULT)]);
        }
        audit('Saved user',$email);db()->commit();json_out(['ok'=>true]);
    default: fail('Action not found.',404);
    }
}
