### 树状菜单的前世今生
很多时候，前端WEB界面需要动态加载多级分类菜单，一般后台通过查询数据库生成分类菜单的嵌套数组，然后将嵌套数组输出JSON数据给前端显示。类似下面这样:
```php
$data = [0=>["text"=>"生活电器",
            "id"=>1,
            "parent_id"=>0,
            "children"=>[0=>["text"=>"空调",
                             "id"=>2,
                             "parent_id"=>1],
                         1=>["text"=>"冰箱",
                             "id"=>3,
                             "parent_id"=>1]
                        ]
             ],
           1=>["text"=>"男装",
               "id"=>4,
               "parent_id"=>0
];
```
 > 我们发现上面这种嵌套数据的录入会非常麻烦，因为parent_id指向父类的id，如果在平时测试的时候，需要录入这种层级菜单数据，将会非常头疼。那么有没有一种快速录入这种分类菜单的方法呢？当然有，比如我灵机一动，就想到下面这种方法.
	
	
打开txt文件，设置编码格式为utf-8,录入下面这种格式的数据，一行代表一个菜单，一个+号表示一级子菜单，二个+号表示二级子菜单，依次类推，顶级菜单不需要＋号，录入的时候记得前后不要留空格，这样看上去更直观。仔细观察这种数据结构，我们会发现它暗含了菜单的层级关系，而且录入十分方便。
###  生活电器
#### +空调
#####  ++智能空调
#####  ++变频空调
#### +冰箱
#####  ++三门冰箱
#####  ++对开门冰箱
###  男装
#### +清凉夏装
####  +温情冬装

那么问题来了，程序如何读取上面这种数据，并识别每级菜单的层级关系呢？如果我们读取一行就解析一行，会发现要回溯查看，处理起来会非常麻烦。比如当处理到 **冰箱** 这一行的时候，程序需要回溯查看父级菜单 **生活电器** 并将 **冰箱** 的 `parent_id` 指向  **生活电器** 的`id`，而处理到 **男装** 这一行的时候 `parent_id = 0`，程序的状态变化并不好控制。
在处理类似这种比较棘手的编程问题的时候， 我们要转变思维。借鉴数学领域里经常用到的化归思想。不断将问题进行转化，转化到到我们熟悉的问题上来。
1. 在PHP项目中，生成分类层级菜单，我们一般采用的方法是从数据库中读取出分类菜单数据[一个二维数组]，然后根据每行数据的`id, parent_id` 递归调用菜单生成函数，将二维数组转化为嵌套数组，如下所示

```php
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
```

上面这个函数的 `callback` 参数是一个回调函数，是用来过滤每行数据的，回调函数里，简单的赋值语句，可以只取出想要的字段，比如 `menu_name, is_visible, id, parent_id`  。当然 `id,parent_id` 是必须取出来的，因为他们标识了菜单的层级关系。注意程序里面的 ` unset($array[$k]); `  这一句代码主要是为了提高递归程序的性能，每次递归的时候，数组会变小。这样一来，已经遍历过的数据，就不会重复遍历了。

好了有了上面的函数做铺垫，我们发现只要设置每行数据的id,parent_id的关系，就能调用上面这个菜单函数生成想要的多维嵌套数组。
怎么确定这种关系是关键，也是难点。我们分步来做
**步骤 1.**   遍历每行菜单数据，根据每行数据前面的+号，标识每行的层级level
**步骤 2.** 在 **步骤 1** 基础上我们再次遍历每行菜单数据， 根据每行数据的 `level` 大小，我们来设置每个菜单的 `parent_id` 从而确定菜单之间的关系，遍历的过程我们发现需要查看之前菜单的 `id`, 而且我们不能确定当前行的菜单的父类到底是遍历过的菜单行的哪一个。这个时候我们需要采用堆栈数据结构，将每次遍历菜单的`id` 压栈, 当层级深的菜单遍历到层级浅的菜单，我们再将数据出栈。比如遍历到 **冰箱** 这一行的时候，我们需要弹出到 **生活电器**这一层。具体代码如下.
```php
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
```

总结, 在处理复杂问题的时候，我们发现分步解决是一个很好的手段，也是常见的工程思想，比如火箭建造，程序编译，TCP/IP通讯 莫不如是。甚至CPU芯片这么高级的东西，都借鉴了流水线生产的思想，设计了多级指令缓存，提高程序执行效率。

完整程序源码:
```php
<?php
namespace company\code_lighter;
/**
 * Author: code lighter
 * Date: 2017/5/28
 * Time: 21:14
 * Function: translate the plain menu data to a nested tree array in PHP
 * The input plain menu data format as follows:
 *    top_menu
 *    +sub_menu
 *    ++sub_menu
 *    top_menu
 * The program will translate above data to php nested array
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
    public function toJson()
    {
        $data = $this->parse();
        $this->markTree($data);
        $tree = $this->buildTree($data);
        return $tree;
    }
}
```

### 测试例程

```php
namespace company\controllers;
use Yii;
use yii\web\Controller;
use yii\web\Response;
use company\code_lighter\MenuTree;

class TestController extends Controller
{
    public $enableCsrfValidation = false;
    public function actionMenuTree()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        // following lines are the test code
        $menuTree = new MenuTree("menu.txt");
        return $menuTree->toJson();
    }
}
```