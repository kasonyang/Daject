<?php
/**
 * 
 * @package Daject
 * @version 1.0.2
 * @author Kason Yang <kasonyang@163.com>
 */

class DajectConnectionException extends Exception {
    
}

class DajectQueryException extends Exception {
    
}

class DajectException extends Exception {
    
}

class DajectRecordConstructException extends Exception {
    
}

class DajectDataException extends Exception{
    
    private $error_message;
    
    function __construct($error_message=array()) {
        $this->error_message = $error_message;
        parent::__construct('Invalid Data');
    }
    
    function getFields(){
        return array_keys($this->error_message);
    }
    
    function getErrorMessage($field=null){
        return $field ? $this->error_message[$field] : $this->error_message;
    }
}

/**
 * 数据类型接口
 */
interface DajectValueInterface {
    /**
     * 返回数据
     * @return mixed
     */
    function getValue();
    function __toString();
    function getSQLExpression();
}

/**
 * 数据库驱动接口
 */
interface DajectDriverInterface{
    /**
     * 连接数据库
     * @param string $server
     * @param string $user
     * @param string $password
     * @return resource|false 成功时返回连接标识,失败时返回false
     */
    static function connect($server, $user, $password) ;
    /**
     * 构造函数
     * @param resource $connection
     */
    function __construct($connection) ;
    /**
     * 选择数据库
     * @param string $db_name
     */
    function selectDatabase($db_name);
    /**
     * 构造Select语句
     * @param array $params
     */
    function buildSelect(array $params);
    /**
     * 构造Delete语句
     * @param array $params
     */
    function buildDelete(array $params);
    /**
     * 构造Update语句
     * @param array $params
     */
    function buildUpdate(array $params);
    /**
     * 构造Insert语句
     * @param array $params
     */
    function buildInsert(array $params);
    /**
     * 计算记录总数
     * @param array $params
     */
    function count(array $params);
    /**
     * 执行查询
     * @param string $sql
     */
    function query($sql);
    /**
     * 错误描述
     * @return string
     */
    function error();
    /**
     * 错误代码
     * @return int
     */
    function errno();
    /**
     * @return int
     */
    function insertId();
    /**
     * @param string $field_name
     * @return Array
     */
    function fetchFields($table_name);
}

class Daject {

    static function formatData($data_array){
        foreach($data_array as $da_key => $da_value){
            if(!($da_value instanceof DajectValueBase)){
                $da_value = is_int($da_value) ? new DajectValueInteger($da_value)
                                              : new DajectValueString($da_value);
            }
            $data_array[$da_key] = $da_value->getSQLExpression();
        }
        return $data_array;
    }

    /**
     * 将关联数组转义为SQL的Where条件(不包含关键字WHERE)
     * @param array $arr
     * @return string
     */
    static function arrayToWhere($arr) {
        $arr = self::formatData($arr);
        foreach ($arr as $k => $v) {
            if (isset($where))
                $where.=" AND ";
            $where.='`' . $k . '`' . "=" . $v;
        }
        return $where;
    }

    /**
     * 解析字段的含义
     * @param string $type 类型描述符
     * @return array {type:string,length:int}
     */
    static function parseFieldType($type) {
        $type = trim($type);
        if (substr($type, -1) == ')') {
            $type = substr($type, 0, -1);
        }
        $type_arr = explode('(', $type);
        $ret['type'] = $type_arr[0];
        switch (strtoupper($ret['type'])) {
            case 'SET':
            case 'ENUM':
                $length = explode(',', $type_arr[1]);
                foreach ($length as $lkey => $lval) {
                    $length[$lkey] = substr($lval, 1, -1);
                }
                break;
            case 'TEXT':
                $length = 0;
                break;
            default :
                $length = $type_arr[1];
        }
        $ret['length'] = $length;
        return $ret;
    }
    
    static function sqlprintf($sql,$var=null,$_=null){
        $keywords = array('s','i','e');
        $vars_count = func_num_args();
        if($vars_count == 1){
            return $sql;
        }
        for($i=1;$i<$vars_count;$i++){
            $vars[] = func_get_arg($i);
        }
        //分析sql取得关键字
        $offset = 0;
        while($sql){
            $symbol_offset = strpos($sql, '%',$offset);
            if($symbol_offset === false){
                $sql_arr[] = str_replace('%%','%',$sql);
                $key_arr[] = '';
                break;
            }
            $key = substr($sql, $symbol_offset+1, 1);
            if(in_array($key,$keywords)){
                $sql_arr[] = str_replace('%%', '%' , substr($sql, 0, $symbol_offset));
                $key_arr[] = $key;
                $sql = substr($sql, $symbol_offset + 2);
                $offset = 0;
            }else{
                $offset = $symbol_offset + 1;
            }
        }
        $key_count = 0;
        foreach($key_arr as $ka){
            if($ka != '') $key_count += 1;
        }
        if($key_count !== count($vars)){
            throw new Exception('错误的参数个数!');
        }
        //转义参数
        foreach($key_arr as $ka_key => $ka_value){
            switch ($ka_value) {
                case 's':
                    $var_arr[$ka_key] ="'" . addslashes($vars[$ka_key]) . "'";
                    break;
                case 'i':
                    $var_arr[$ka_key] = intval($vars[$ka_key]);
                    break;
                case 'e':
                    $var_arr[$ka_key] = $vars[$ka_key];
                    break;
                case '':
                    $var_arr[$ka_key] = '';
                    break;
                default :
                    return false;
            }
        }
        $ret = '';
        foreach($sql_arr as $sa_key => $sa_value){
            $ret .= $sa_value . $var_arr[$sa_key];
        }
        return $ret;
    }

}

class DajectConnection {

    private $connection;
    
    /**
     *
     * @var DajectDriverInterface
     */
    private $driver;

    /**
     * 返回数据库连接
     * @return resource
     * @throws DajectConnectionException
     */
    function getConnection() {
        return $this->connection;
    }
    
    /**
     * 返回正在使用的驱动
     * @return DajectDriverInterface
     */
    function getDriver(){
        return $this->driver;
    }

    /**
     * 选择数据库
     * @param string $db_name
     */
    function selectDatabase($db_name){
        return $this->driver->selectDatabase($db_name);
    }
    
    function __construct(
            $type
            ,$host='localhost'
            ,$user=''
            ,$password=''
            ,$name=''
            ,$charset=null) {
        $driver_name = $type . 'DajectDriver';
        include DajectConfig::getDriverDir() . '/' . $driver_name . '.php';
        $conn = call_user_func_array(array($driver_name, 'connect'), array($host, $user, $password));
        if ($conn === false)
            throw new DajectConnectionException('Fail to Connect the Database!');
        /* @var $driver DajectDriverInterface */
        $driver = new $driver_name($conn);
        if($name){
            if(!$driver->selectDatabase($name)){
                throw new DajectConnectionException('Fail to Select the Database');
            }    
        }
        if ($charset) {
            $driver->query('set names ' . $charset);
            //mysql_query('set names '.DajectConfig::getCharset());
        }
        $this->connection = $conn;
        $this->driver = $driver;
    }
}

abstract class DajectTableBase {

    /**
     *
     * @var DajectDriverInterface
     */
    private $last_driver;
    private $_array_pks;
    //private $_field,$_field_name, $_order, $_where, $_join, $_group, $_having;
    private $last_sql;
    private $full_table_name, $table_exp, $table_alias_exp;
    private $record_classes;

    //private $fields,$columns,$full_fields;

    private $sql_element;
    
    /**
     *数据表名,不带前缀
     * @var string
     */
    protected $table_name = null;
    /**
     *数据表对应的DajectRecord类名
     * @var string
     */
    protected $record = null;
    /**
     *主键名数组,通过数组里的全部键名和给定的值,能确定唯一的Record
     * @var array
     */
    protected $keys=array();
    
    /**
     * 
     * @param array $data
     * @return array
     * @throws DajectDataException
     */
    protected function insertValidator($data){
        return array();
    }
    
    /**
     * 
     * @param array $data
     * @return array
     * @throws DajectDataException
     */
    protected function updateValidator($data){
        return array();
    }
    
    private function validateResultHandler($validate_ret){
        if(is_array($validate_ret) and $validate_ret){
            throw new DajectDataException($validate_ret);
        }
    }

    /**
     * 过滤主键数组
     * @param array $data_array
     * @return array
     */
    private function getKeyValues($data_array){
        foreach($this->keys as $key){
            $ret[$key] = $data_array[$key];
        }
        return $ret;
    }
    /**
     * 
     * @return DajectDriverInterface
     */
    private function getWriteDriver(){
        return $this->last_driver = DajectConfig::getWriteConnection()->getDriver();
    }
    /**
     * 
     * @return DajectDriverInterface
     */
    private function getReadDriver(){
        return $this->last_driver = DajectConfig::getReadConnection()->getDriver();
    }
    /**
     * 构造函数
     * @param array $array_pks 关联数组
     * @throws DajectException
     */
    function __construct($array_pks = null) {
        $cn = get_class($this);
        if (substr($cn, -5) == 'Table') {
            $main_name = substr($cn, 0, -5);
        }
        if ($this->table_name === null) {
            if($main_name){
                $this->table_name = strtolower($main_name);
            }else{
                throw new DajectException('table name was undefinded！');
            }   
        }
        if ($this->record === null and !empty($main_name)) {
            $this->record = $main_name;
        }
        $this->full_table_name = DajectConfig::getTablePrefix() . $this->table_name;
        $this->table_exp = '`' . DajectConfig::getTablePrefix() . $this->table_name . '`';
        $this->table_alias_exp = $this->table_exp . ' `' . $this->table_name . '`';
        $this->record_classes[$this->table_name] = $this->record;

        if ($this->_array_pks = $array_pks) {
            $this->sql_element['where'] = Daject::arrayToWhere($array_pks);
        }
    }
    
    private function handleQueryResult($result) {
        if ($result === false) {
            throw new DajectQueryException($this->error());
        } else {
            if (is_array($result)) {
                $ret = array();
                if (!$this->sql_element['join']) {
                    foreach ($result as $dv) {
                        foreach ($dv as $fk => $fv) {
                            $ret[] = $fv;
                        }
                    }
                }
            } else {
                $ret = $result;
            }
        }
        return $ret;
    }

    //公有函数
    /**
     * 执行查询
     * @param string $sql 支持类型转义字符
     * @return array|boolean
     */
    function query($sql,$vars = null , $_ = null) {
        $params = func_get_args();
        $sql = call_user_func_array(array(Daject,'sqlprintf'), $params);
        $this->last_sql = $sql;
        if(strtoupper(substr($sql, 0,7)) == 'SELECT '){
            $driver = $this->getReadDriver();
        }else{
            $driver = $this->getWriteDriver();
        }
        return $this->handleQueryResult($driver->query($sql));
    }

    /**
     * 设置/读取field
     * @param string $field
     * @return \DajectTableBase|string
     */
    function field($field = null) {
        if ($field === null) {
            return $this->sql_element['field'];
        } else {
            $this->sql_element['field'] = $field;
            return $this;
        }
    }

    /**
     * 设置/读取order
     * @param string $order
     * @return \DajectTableBase|string
     */
    function order($order = null) {
        if ($order === null) {
            return $this->sql_element['order'];
        } else {
            $this->sql_element['order'] = $order;
            return $this;
        }
    }

    /**
     * 设置/读取where
     * @param string $where  支持类型转义字符
     * @param mixed $vars 变量
     * @return \DajectTableBase|string
     */
    function where($where = null,$vars = null,$_ = null) {
        if ($where === null) {
            return $this->sql_element['where'];
        } else {
            if(is_array($where)){
                 $w = Daject::arrayToWhere($where);
            }else{
                $params = func_get_args();
                $w = call_user_func_array(array($this,'sqlprintf'),$params);
            }
            if ($this->_array_pks) {
                $w = "($w) AND " . Daject::arrayToWhere($this->_array_pks);
            }
            $this->sql_element['where'] = $w;
            return $this;
        }
    }

    /**
     * 联接表
     * @param DajectTableBase $table
     * @param string $on
     * @param string $type
     * @return \DajectTableBase
     */
    function join($table, $on, $type = null/* $field_prefix = null, */) {
        $tb_exp = $table->tableExp();
        $tb_name = $table->tableName();
        $join_str = "JOIN $tb_exp ON $on";
        if ($type)
            $join_str = $type . ' ' . $join_str;
        $this->sql_element['join'][] = $join_str;
        $this->record_classes[$tb_name] = $table->recordClass();
        return $this;
    }

    /**
     * 设置/读取group
     * @param string $field
     * @return \DajectTableBase|string
     */
    function group($field = null) {
        if ($field === null) {
            return $this->sql_element['group'];
        } else {
            $this->sql_element['group'] = $field;
            return $this;
        }
    }

    /**
     * 设置/读取having
     * @param string $condition
     * @return \DajectTableBase|string
     */
    function having($condition = null) {
        if ($condition === null) {
            return $this->sql_element['having'];
        } else {
            $this->sql_element['having'] = $condition;
            return $this;
        }
    }

    /**
     * 返回表的全名
     * @return string
     */
    function fullTableName() {
        return $this->full_table_name;
    }

    /**
     * 返回主要表名
     * @return string
     */
    function tableName() {
        return $this->table_name;
    }

    /**
     * 返回表名表达式:`全名` as `主要表名`
     * @return string
     */
    function tableExp() {
        return $this->table_exp;
    }

    /**
     * 记录类名
     * @return string
     */
    function recordClass() {
        return $this->record_classes[$this->table_name];
    }

    /**
     * 构建Select语句
     * @param int $limit
     * @param int $offset
     * @return string
     */
    function buildSelect($limit = null, $offset = null) {
        $params = $this->sql_element;
        $params['table'] = $this->table_alias_exp;
        if ($limit !== null) {
            $limit_str = $limit;
            if ($offset !== null) {
                $limit_str = $offset . ',' . $limit_str;
            }
            $params['limit'] = $limit_str;
        }
        return $this->getReadDriver()->buildSelect($params);
    }

    /**
     * 执行Select查询
     * @param int $limit
     * @param int $offset
     * @return array
     */
    function select($limit = null, $offset = null) {
        $sql = $this->buildSelect($limit, $offset);
        return $this->query($sql);
    }
    
    /**
     * 
     * @return array|false
     */
    function selectOne(){
        $list = $this->select(1);
        return $list[0] ? $list[0] : FALSE;
    }

    /**
     * 执行Select查询,并纵向排列数据
     * @param int $limit
     * @param int $offset
     * @return array 以字段名为键的数组
     */
    function selectAsColumn($limit = null, $offset = null) {
        $ret = array();
        $datas = $this->select($limit, $offset);
        foreach ($datas as $dval) {
            foreach ($dval as $fkey => $fval) {
                $ret[$fkey][] = $fval;
            }
        }
        return $ret;
    }

    /**
     * 返回记录的总数
     * @return int
     */
    function count() {
        $params = $this->sql_element;
        $params['table'] = $this->table_alias_exp;
        return $this->handleQueryResult($this->getReadDriver()->count($params));
    }

    /**
     * 执行Select查询,返回DajectRecordBase类型的数据
     * @return array
     */
    function selectObject($limit = null, $offset = null) {
        if(!$this->keys){
            return array();
        }
        $ret = array();
        if ($datas = $this->select($limit, $offset)) {
            foreach ($datas as $d) {
                if ($this->sql_element['join']) {
                    $obj = null;
                    foreach ($d as $key => $value) {
                        if (!is_int($key)) {
                            $obj[$key] = new $this->record_classes[$key]($this->getKeyValues($value), $value);
                        } else {
                            $obj[] = $value;
                        }
                    }
                    $ret[] = $obj;
                } else {
                    $class = $this->record_classes[$this->table_name];
                    $ret[] = new $class($this->getKeyValues($d), $d);
                }
            }
        }
        return $ret;
    }
    
    /**
     * 
     * @return DajectRecordBase|false
     */
    function selectOneObject(){
        $list = $this->selectObject(1);
        return $list[0] ? $list[0] : FALSE;
    }

    /**
     * 构造Insert语句
     * @param array $data_array 以字段名为键的关联数组
     * @return string
     */
    function buildInsert($data_array) {
        if ($this->_array_pks)
            $data_array = array_merge($data_array, $this->_array_pks);
        $data_array = Daject::formatData($data_array);
        $params = $this->sql_element;
        $params['table'] = $this->table_exp;
        $params['data'] = $data_array;
        return $this->getWriteDriver()->buildInsert($params);
    }

    /**
     * 插入数据
     * @param array $data_array 以字段名为键的关联数组
     * @return boolean
     * @throws DajectDataException
     */
    function insert($data_array) {
        $this->validateResultHandler($this->insertValidator($data_array));
        $sql = $this->buildInsert($data_array);
        return $this->query($sql);
    }

    /**
     * 构造Update语句
     * @param array $data_array 以字段名为键的关联数组
     * @param int $limit
     * @return string
     */
    function buildUpdate($data_array, $limit = null) {
        $params = $this->sql_element;
        $params['table'] = $this->table_alias_exp;
        $params['data'] = Daject::formatData($data_array);
        $params['limit'] = $limit;
        return $this->getWriteDriver()->buildUpdate($params);
    }

    /**
     * 更新数据
     * @param int $data_array 以字段名为键的关联数组
     * @param int $limit
     * @return boolean
     * @throws DajectDataException
     */
    function update($data_array, $limit = null) {
        $this->validateResultHandler($this->updateValidator($data_array));
        $sql = $this->buildUpdate($data_array, $limit);
        return $this->query($sql);
    }

    /**
     * 构造Delete语句
     * @param int $limit
     * @return string
     */
    function buildDelete($limit = null) {
        $params = $this->sql_element;
        $params['table'] = $this->table_exp;
        if ($limit)
            $params['limit'] = $limit;
        return $this->getWriteDriver()->buildDelete($params);
    }

    /**
     * 删除数据
     * @param int $limit
     * @return boolean
     */
    function delete($limit = null) {
        $sql = $this->buildDelete($limit);
        return $this->query($sql);
    }

    /**
     * 上一次插入操作的自增字段id(如果有自增字段的话)
     * @return int
     */
    function insertId() {
        return $this->getWriteDriver()->insertId();
    }

    /**
     * 错误描述
     * @return string
     */
    function error() {
        return $this->last_driver->error();
    }

    /**
     * 错误代码
     * @return int
     */
    function errno() {
        return $this->last_driver->errno();
    }

    /**
     * 最近一次SQL查询语句,包含出错的语句
     * @return string
     */
    function getLastSQL() {
        return $this->last_sql;
    }

    /**
     * 读取字段信息
     * @return array
     */
    function fetchFields() {
        return $this->getReadDriver()->fetchFields($this->table_exp);
    }

}

abstract class DajectRecordBase {

    private $data, $new_data, $exist = null;
    private $tb = null, $key_value_array;
    /**
     *数据表名,不带前缀
     * @var string 
     */
    protected $table_name = null;
    protected $table_class = null;

    /**
     * 
     * @return DajectTableBase
     */
    private function getTable() {
        if (!$this->tb) {
            $this->tb = new $this->table_class($this->key_value_array);
            //$this->tb = new DajectTableObject($this->table_name, $this->key_value_array);
        }
        return $this->tb;
    }

    /**
     * 构造函数
     * @param array $pks_array 主键名-值关联数组
     * @param array $data_array 非主键列名-值数组，若此参数缺省，构造函数会自动
     * 读取数据表，以取得非主键列的值
     * @throws DajectRecordConstructException
     */
    function __construct($pks_array, $data_array = null) {
        $cls_name = get_class($this);
        if (!$this->table_name) {
            $this->table_name = strtolower($cls_name);
        }
        if(!$this->table_class){
            $this->table_class = $cls_name . 'Table';
        }
        if(!is_array($pks_array)) throw new DajectRecordConstructException('Invalid construct parameters！');
        $this->key_value_array = $pks_array;
        if ($data_array === null) {
            $d = $this->getTable()->select(1);
            $data_array = $d[0];
        }
        $this->data = $data_array;
    }

    function __destruct() {
        if ($this->new_data)
            $this->save();
    }

    /**
     * 重新读取数据数据库，以获得最新的数据，调用此函数后，以前的赋值将被覆盖
     */
    function refresh() {
        $this->new_data = NULL;
        $this->data = NULL;
        $ret = $this->getTable()->select(1);
        if ($ret[0] != NULL) {
            $this->exist = true;
            $this->data = $ret[0];
        }
    }

    /**
     * 保存数据到数据库，若有赋值，系统会自动调用此函数
     * @param bool $AutoCreate
     * @return boolean
     */
    function save($AutoCreate = true) {
        if ($this->new_data or !$this->exist()) {
            if ($this->exist()) {
                return $this->getTable()->update($this->new_data, 1);
            } elseif ($AutoCreate) {
                return $this->getTable()->insert($this->new_data);
            } else {
                return false;
            }
        } else {
            return true;
        }
    }

    /**
     * 删除记录
     * @return boolean
     */
    function delete() {
        return $this->getTable()->delete(1);
    }

    /**
     * 检查相应的记录是否存在
     * @return bool
     */
    function exist() {
        if ($this->exist === null) {
            $this->exist = $this->getTable()->count() > 0;
        }
        return $this->exist;
    }

    /**
     * 返回记录的全部列的名-值数组
     * @return array
     */
    function fetch() {
        if ($this->data or $this->new_data) {
            $data = $this->data ? $this->data : array();
            $new_data = $this->new_data ? $this->new_data : array();
            return array_merge($data, $new_data);
        } else {
            return false;
        }
    }

    /**
     * 以关联数组的形式赋值
     * @param array $data_array 需要赋值的键-值数组
     * @return \DajectRecordBase
     */
    function assign($data_array) {
        foreach ($data_array as $k => $v) {
            $this->__set($k, $v);
        }
        return $this;
    }

    function __set($name, $value) {
        if (!$this->exist() or array_key_exists($name, $this->data)) {
            $this->new_data[$name] = $value;
        } else {
            //trigger_error("属性[".$name."]不存在");
        }
    }

    function __get($name) {
        if (isset($this->new_data) and array_key_exists($name, $this->new_data)) {
            return $this->new_data[$name];
        } elseif (isset($this->data) and array_key_exists($name, $this->data)) {
            return $this->data[$name];
        } else {
            //trigger_error("属性[".$name."]不存在");
        }
    }

}

class DajectTableObject extends DajectTableBase {

    function __construct($table_name, $array_pks = null) {
        $this->table_name = $table_name;
        parent::__construct($array_pks);
    }

}

class DajectRecordObject extends DajectRecordBase {

    function __construct($table_name, $pks_array, $data_array = null) {
        //$this->keys = array_keys($pks_array);
        $this->table_name = $table_name;
        parent::__construct($pks_array, $data_array);
    }

}

abstract class DajectValueBase implements DajectValueInterface{
    protected $value;
    function __construct($value) {
        $this->value = $value;
    }
    function getValue() {
        return $this->value;
    }
    function __toString() {
        return (string)  $this->value;
    }
    function getSQLExpression() {
        return $this->value;
    }
}

/**
 * Daject字符串类型
 */
class DajectValueString extends DajectValueBase{
    function getSQLExpression() {
        return  "'" . addslashes($this->getValue()) . "'";
    }
}

/**
 * Daject整数类型
 */
class DajectValueInteger extends DajectValueBase{
    function __construct($value) {
        parent::__construct(intval($value));
    }
}

/**
 * Daject函数
 */
class DajectValueExpression extends DajectValueBase{
    
}

class DajectConfig{
    static $config,$write_connection,$read_connection;
    /**
     * 
     * @param string $name
     * @return \DajectConnection
     */
    static private function createConnection($name){
        $config = self::$config['dbs'][$name];
        return new DajectConnection($config['type'], $config['host'], $config['user'], $config['password'], $config['name'],$config['charset']);
    }
    static function addDatabase(
            $dbname
            ,$type
            ,$host='localhost'
            ,$user=''
            ,$password=''
            ,$name=''
           // ,$tb_prefix=''
            ,$charset=null){
                self::$config['dbs'][$dbname] = array(
                    'type'  =>  $type,
                    'host'  =>  $host,
                    'user'  =>  $user,
                    'password'  =>  $password,
                    'name'  =>  $name,
                   // 'tb_prefix' =>  $tb_prefix,
                    'charset'   =>  $charset
                );
    }
    /**
     * 
     * @param string $write_db
     * @param string $read_db
     */
    static function setDatabase($write_db,$read_db=null){
        self::$config['wdb'] = $write_db;
        self::$config['rdb'] = $read_db;
    }
    /**
     * 
     * @return DajectConnection
     */
    static function getReadConnection(){
        if(!self::$config['rdb']){
            return self::getWriteConnection();
        }else{
            if(!self::$read_connection){
                self::$read_connection = self::createConnection(self::$config['rdb']);
            }
            return self::$read_connection;
        }
    }
    /**
     * 
     * @return DajectConnection
     */
    static function getWriteConnection(){
        if(!self::$write_connection){
            self::$write_connection = self::createConnection(self::$config['wdb']);
        }
        return self::$write_connection;
    }
    static function setDriverDir($dir){
        self::$config['driver_dir'] = $dir;
    }
    
    static function getDriverDir(){
        return self::$config['driver_dir'];
    }
    static function setTablePrefix($value) {
        self::$config['tb_prefix'] = $value;
    }

    static function getTablePrefix() {
        return self::$config['tb_prefix'];
    }
}

DajectConfig::setDriverDir(dirname(__FILE__) . '/drivers');