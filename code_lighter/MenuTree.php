<?php
namespace company\code_lighter;
/**
 * Author: code lighter
 * Date: 2017/5/28
 * Function: translate the plain menu data to a nested tree array in PHP
 * The input plain menu data format as follows:
 *    top_menu
 *    +sub_menu
 *    ++sub_menu
 *    top_menu
 * The program will translate above to php nested array
 * $output = [
 *  0=>["text"=>"top_menu",
 *      "id"=>1,
 *      "parent_id=>0,
 *      "children"=>[
 *          "text"=>"sub_menu",
 *          "id"=>2,
 *          "parent_id"=>1,
 *          "children"=>[
 *               "text"=>"sub_menu",
 *               "id"=>3,
 *               "parent_id"=>2
 *           ]
 *       ]
 * ],
 * 1=>["text="top_menu",
 *     "id"=>4,
 *     "parent_id"=>0
 *    ]
 * ];
 * Time: 11:14
 */
class Stack
{
    public $_stack_size;
    public $_ptr;
    public $_container;
    public function __construct()
    {
        $this->_container = [];
        $this->_stack_size = 20;
        $this->_ptr = -1;
    }
    public function isEmpty()
    {
        return $this->_ptr==-1;
    }
    public function isFull()
    {
        return $this->_ptr == $this->_stack_size-1;
    }
    public function push($data)
    {
        if($this->isFull())
            return false;
        $this->_container[] = $data;
        $this->_ptr++;
    }
    public function pop(){
        if($this->isEmpty())
            return false;
        $this->_ptr--;
        return  array_pop($this->_container);
    }
}
class MenuTree
{
    private $_filePath; //file path
    private $_loaded; //mark file loaded or not
    private $_errors;
    private $_data;
    private $_parsed_data;
    private $_cur_pos;
    private $_current_line_words;
    private $_stack;
    public function __construct($filePath)
    {
        $this->_filePath = $filePath;
        $this->_loaded= false;
        $this->_errors = [];
        $this->_data = [];
        $this->_parsed_data = [];
        $this->_cur_pos = 0;
        $this->_current_line_words = 0;
        $this->_stack= new Stack;
    }
    public function log($error_code,$error_msg){
        $_errors[] = ['error_code'=>$error_code,'error_msg'=>$error_msg];
    }
    public function hasError(){
        return count($this->_errors);
    }
    public function readFile()
    {
        if($this->_loaded) return;
        $file = fopen(dirname(__FILE__).'/'.$this->_filePath,'r');
        if(!$file){
            $this->log(601,"can't open file ".$this->_filePath);
        }
        while(!feof($file)){
            $this->_data[] = fgets($file);
        }
        fclose($file);
        $this->_loaded = true;
    }
    public function getData(){
        if(!$this->hasError()){
            if(!$this->_loaded){
                $this->readFile();
            }
            return $this->_data;
        }
        return false;
    }
    public function parse(){
        $this->readFile();
        if(!$this->hasError()){
            $data = [];
            foreach($this->_data as $k=>$line){
                $this->_current_line_words = mb_strlen($line,'utf-8');
                $level =0;
                while($this->getNextChar($line)=='+'){
                    $level++;
                }
                do{
                    $char = $this->getNextChar($line);
                }while($char != "\r" && $char != false);

                $_line = [
                    'id'=>$k+1,
                    'text'=>mb_substr($line,$level,$this->_cur_pos-1 -$level,'utf-8'),
                    'level'=>$level
                 ];
                $data[] = $_line;
                $this->_cur_pos = 0;
            }
            return $data;
        }
        return [];
    }

    public function markTree(&$data){
        $level = $parent_id = 0;
        foreach($data as $k=>$v){
            if($v['level']>$level){
                $this->_stack->push(["level"=>$v['level'],"id"=>$v['id']]);
                $data[$k]['parent_id'] =$data[$k-1]['id'];
                $level = $v['level'];
                $parent_id = $data[$k-1]['id'];
            }else if($v['level']<$level){
                $parent_id = $this->findParent($v['level']-1);
                $data[$k]['parent_id'] = $parent_id;
                $level = $v['level'];
            }else {
                $this->_stack->push(["level"=>$v['level'],"id"=>$v['id']]);
                $data[$k]['parent_id'] = $parent_id;
            }
        }
    }
    public function findParent($level){
        while(!$this->_stack->isEmpty()){
            $data = $this->_stack->pop();
            if($data['level'] == $level){
                return $data['id'];
            }
        }
        return 0;
    }
    public function buildTree(&$array,$callback=null,$parent_id=0,$child_node="children"){
        $tree = [];
        foreach($array as $k=>$v){
            if($v['parent_id'] == $parent_id){
                unset($array[$k]);
                $tmp =is_callable($callback)?call_user_func($callback,$v):$v;
                $children = $this->buildTree($array,$callback,$v['id'],$child_node);
                if($children){
                    $tmp[$child_node] = $children;
                }
                $tree[] = $tmp;
            }
        }
        return $tree;
    }

    public function getNextChar(&$line)
    {
        if($this->_cur_pos<$this->_current_line_words){
            $char = mb_substr($line,$this->_cur_pos,1,'utf-8');
            $this->_cur_pos ++;
            return $char;
        }
        $this->_cur_pos++;
        return false;
    }
    public function toJson()
    {
        $data = $this->parse();
        $this->markTree($data);
        $tree = $this->buildTree($data);
        return $tree;
    }
}