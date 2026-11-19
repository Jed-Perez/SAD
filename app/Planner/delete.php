<?php
declare(strict_types=1);

function delete_blueprint(int $id,array $u): void {
    accessible_blueprint($id,$u);
    $original=null;$staged=null;
    db()->beginTransaction();
    try{
        $b=query('SELECT * FROM blueprints WHERE id=? FOR UPDATE',[$id])->fetch();
        if(!$b)fail('Blueprint not found.',404);
        if($b['status']==='Analyzing')fail('Wait for the blueprint analysis to finish before deleting it.',409);
        if(query('SELECT id FROM quotations WHERE blueprint_id=? LIMIT 1',[$id])->fetch())fail('This plan is linked to a quotation and must be kept with its records.',409);
        // A missing upload must not prevent removal of its old plan record.
        if(preg_match('/^[a-f0-9]{48}\.(jpg|png|webp)$/D',$b['filename'])){
            $original=dirname(__DIR__,2).'/storage/blueprints/'.$b['filename'];
            if(is_file($original)){
                $staged=$original.'.deleting-'.bin2hex(random_bytes(8));
                if(!rename($original,$staged))fail('The image could not be removed. Ask the administrator to check storage permissions.',503);
            }
        }
        query('DELETE r FROM blueprint_reviews r JOIN blueprint_versions v ON v.id=r.version_id WHERE v.blueprint_id=?',[$id]);
        query('DELETE FROM blueprint_versions WHERE blueprint_id=?',[$id]);
        query('DELETE FROM blueprints WHERE id=?',[$id]);
        // Retain notification history, but point old links back to the plan list.
        query("UPDATE notifications SET target='planner' WHERE target=?",['planner/'.$id]);
        audit('Deleted blueprint','Blueprint '.$id);
        db()->commit();
    }catch(Throwable $e){
        if(db()->inTransaction())db()->rollBack();
        if($staged && is_file($staged) && !rename($staged,$original))error_log('Blueprint deletion rollback could not restore its staged image.');
        throw $e;
    }
    if($staged && is_file($staged) && !unlink($staged))error_log('Deleted blueprint has a private staged image awaiting cleanup.');
}
