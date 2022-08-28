<?php
require_once __DIR__ . '/config.php';

use webrium\foxql\DB;
use PHPUnit\Framework\TestCase;

class whereColumnTest extends TestCase{
    
    public function test(){
        
        
        $res = DB::table('users')->whereColumn('phone','fax')->get();


        $this->assertSame(1,count($res));
        
        if(count($res)){
            $this->assertSame($res[0]->phone, $res[0]->fax);
        }
    }
}





