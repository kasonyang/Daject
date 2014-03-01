<?php
/**
 * 
 * @package Daject
 * @version 1.0.1
 * @author Kason Yang <kasonyang@163.com>
 */
class mysqlDajectDriver implements DajectDriverInterface {
    private $connection;
    static function connect($server, $user, $password) {
        return @mysql_pconnect($server, $user, $password);
    }
    function __construct($connection) {
        $this->connection = $connection;
    }
    function selectDatabase($db_name) {
        return mysql_select_db($db_name, $this->connection);
    }
    function buildSelect(array $params){
        $field=isset($params['field']) ? $params['field'] : '*';
        $sql = "SELECT $field FROM {$params['table']}";
        if (isset($params['join'])){
            foreach ($params['join'] as $value) {
                $sql.=' ' . $value;
            }
        }
        if (isset($params['where']))
            $sql.=" WHERE " . $params['where'];
        if (isset($params['order']))
            $sql.=" ORDER BY " . $params['order'];
        if (isset($params['group']))
            $sql.=' GROUP BY ' . $params['group'];
        if (isset($params['having']))
            $sql.=' HAVING ' . $params['having'];
        if (isset($params['limit']))
            $sql.=" LIMIT " . $params['limit'];
        return $sql;
    }
    function buildDelete(array $params){
        $sql = "DELETE FROM {$params['table']}";
        if (isset($params['where']))
            $sql.=" WHERE " . $params['where'];
        if (isset($params['order']))
            $sql.=" ORDER BY " . $params['order'];
        if (isset($params['limit']))
            $sql.=" LIMIT " . $params['limit'];
        return $sql;
    }
    function buildUpdate(array $params){
        foreach ($params['data'] as $key => $value) {
            if (isset($str_value))
                $str_value.=",";
            $str_value.="`$key`=$value";
        }
        $sql = "UPDATE {$params['table']} SET $str_value";
        if (isset($params['where']))
            $sql.=" WHERE " . $params['where'];
        if(isset($params['order']))
            $sql.=' ORDER BY '.$params['order'];
        if (isset($params['limit']))
            $sql.=" LIMIT " . $params['limit'];
        return $sql;
    }
    function buildInsert(array $params){
        foreach ($params['data'] as $key => $value) {
            if (isset($str_field)) {
                $str_field.=",";
                $str_value.=",";
            }
            $str_field.="`$key`";
            $str_value .= $value;
        }
        $sql = "INSERT INTO {$params['table']} ($str_field) VALUES ($str_value)";
        return $sql;
    }
    function count(array $params){
        $sql = 'SELECT COUNT(*) AS total FROM '.  $params['table'];
        if (isset($params['join'])){
            foreach ($params['join'] as $value) {
                $sql.=' ' . $value;
            }
        }
        if (isset($params['where']))
            $sql.=" WHERE " . $params['where'];
        if (isset($params['order']))
            $sql.=" ORDER BY " . $params['order'];
        if (isset($params['having']))
            $sql.=' HAVING ' . $params['having'];
        $ret = $this->query($sql);
        if($ret === false){
            return FALSE;
        }else{
            return intval($ret[0][0]['total']);
        }
    }
    function query($sql){
        $ret = mysql_query($sql, $this->connection);
        if(is_resource($ret)){
            $return_rows = array();
            while ($arow = mysql_fetch_array($ret, MYSQL_NUM)){
                $rows[]=$arow;
            }
            $field_num=  mysql_num_fields($ret);
            for($i=0;$i<$field_num;$i++){
                $field_info[] = mysql_fetch_field($ret,$i);
            }
            $rows_count=count($rows);
            for($row_i=0;$row_i<$rows_count;$row_i++) {
                for($i=0;$i<$field_num;$i++){
                    $field=$field_info[$i];
                    $field_value = $rows[$row_i][$i];
                    if($field->table){
                        $return_rows[$row_i][$field->table][$field->name]=$field_value;
                    }else{
                        $return_rows[$row_i][0][$field->name]=$field_value;
                    }
                }
            }
            return $return_rows;
        }
        return $ret;
    }
    function error(){
        return mysql_error($this->connection);
    }
    function errno(){
        return mysql_errno($this->connection);
    }
    function insertId(){
        return mysql_insert_id($this->connection);
    }
    /**
     * @param string $field_name
     * @return Array
     */
    function fetchFields($table_name){
        $ret = array();
        $field_arr = array();
        $arr = $this->query('SHOW FULL FIELDS FROM ' . $table_name);
        foreach ($arr as $key => $value) {
            $field_arr[] = $value['COLUMNS'];
        }
        foreach($field_arr as $field){
            $field_type = Daject::parseFieldType($field['Type']);
            $ret[$field['Field']] = array(
                'name'  =>  $field['Field'],
                'type'  =>  $field_type['type'],
                'length'    =>  $field_type['length'],
                'key'   =>  $field['Key'],
                'default'   =>  $field['Default'],
                'extra'     =>  $field['Extra'],
                'comment'   =>  $field['Comment']
            );
        }
        return $ret;
    }
}