<?php
// +----------------------------------------------------------------------
// | OneThink [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013 http://www.onethink.cn All rights reserved.
// +----------------------------------------------------------------------
// | Author: huajie <banhuajie@163.com>
// +----------------------------------------------------------------------

namespace app\admin\model;
// use think\Model;

/**
 * 文档基础模型
 */
class Model extends \think\Model{
    //自动写入时间戳字段
    protected $autoWriteTimestamp = true;

    // 自动完成规则
    protected $auto = ['name', 'field_sort', 'attribute_list'];
    protected $insert = ['status'=>1];
    protected $update = [];
    protected function setNameAttr($value){
        return strtolower($value);
    }
    // 处理字段排序数据
    protected function setField_sortAttr($value){
        return empty($value) ? '' : json_encode($value);
    }
    protected function setAttribute_listAttr($value){
        return empty($value) ? '' : implode(',', $value);
    }

    // 自动验证规则

    /* 自动验证规则 */
    protected $validate = [
        'rule' => [
            'name'=>'require|unique:model|regex:/^[a-zA-Z]\w{0,39}$/',
            'title'=>'require|length:1,30',
            // 'list_grid'=>''
        ],
        'msg' => [
            'name.require'=>'标识不能为空',
            'name.unique'=>'标识已经存在',
            'name.regex'=>'文档标识不合法',
            'title.require'=>'标题不能为空',
            'title.length'=>'标题长度不能超过30个字符',
            'list_grid'=>'列表定义不能为空',
        ]
    ];
    /**
     * 检查列表定义
     * @param type $data
     */
    // array('', 'checkListGrid', , self::MUST_VALIDATE, 'callback', self::MODEL_UPDATE),
    // protected function checkListGrid($data) {
    //     return input("post.extend") != 0 || !empty($data);
    // }



    /**
     * 新增或更新一个文档
     * @return boolean fasle 失败 ， int  成功 返回完整的数据
     * @author huajie <banhuajie@163.com>
     */
    public function change(){
        /* 有id的判断为更新，否则为新增 */
        if( input('id') ){
            $this->isUpdate(true); // 更新
            $info = '更新模型';
        }else{
            $this->isUpdate(false); // 新增
            $info = '新增模型';
        }

        /* 添加或新增基础内容 */
        if( !$this->save($_POST) ){
            if( empty($this->error) ){
                $this->error = $info . '出错！';
            }
            return false;
        }
        // 清除模型缓存数据
        cache('DOCUMENT_MODEL_LIST', null);

        //记录行为
        action_log('update_model','model',$this->id,UID);

        //内容添加或更新完成
        $this ->getSuccess = $info . '成功！';
        return true;
    }


    /**
     * 获取指定数据库的所有表名
     */
    public function getTables(){
        return $this->db->getTables();
    }

    /**
     * 根据数据表生成模型及其属性数据
     * @author huajie <banhuajie@163.com>
     */
    public function generate($table,$name='',$title=''){
        //新增模型数据
        if(empty($name)){
            $name = $title = substr($table, strlen(config('DB_PREFIX')));
        }
        $data = array('name'=>$name, 'title'=>$title);
        $data = $this->create($data);
        if($data){
            $res = $this->add($data);
            if(!$res){
                return false;
            }
        }else{
            $this->error = $this->getError();
            return false;
        }

        //新增属性
        $fields = db()->query('SHOW FULL COLUMNS FROM '.$table);
        foreach ($fields as $key=>$value){
            $value  =   array_change_key_case($value);
            //不新增id字段
            if(strcmp($value['field'], 'id') == 0){
                continue;
            }

            //生成属性数据
            $data = array();
            $data['name'] = $value['field'];
            $data['title'] = $value['comment'];
            $data['type'] = 'string';	//TODO:根据字段定义生成合适的数据类型
            //获取字段定义
            $is_null = strcmp($value['null'], 'NO') == 0 ? ' NOT NULL ' : ' NULL ';
            $data['field'] = $value['type'].$is_null;
            $data['value'] = $value['default'] == null ? '' : $value['default'];
            $data['model_id'] = $res;
            $_POST = $data;		//便于自动验证
            model('Attribute')->update($data, false);
        }
        return $res;
    }

    /**
     * 删除一个模型
     * @param integer $id 模型id
     * @author huajie <banhuajie@163.com>
     */
    public function del($id){
        //获取表名
        $model = $this->field('name,extend')->find($id);
        if($model['extend'] == 0){
            $table_name = config('DB_PREFIX').strtolower($model['name']);
        }elseif($model['extend'] == 1){
            $table_name = config('DB_PREFIX').'document_'.strtolower($model['name']);
        }else{
            $this->error = '只支持删除文档模型和独立模型';
            return false;
        }

        //删除属性数据
        db('Attribute')->where(array('model_id'=>$id))->delete();
        //删除模型数据
        $this->delete($id);
        //删除该表
        $sql = <<<sql
                DROP TABLE {$table_name};
sql;
        $res = db()->execute($sql);
        return $res !== false;
    }
}
