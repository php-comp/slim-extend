<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 16/9/1
 * Time: 下午4:13
 */

namespace slimExt\base;

use inhere\librarys\exceptions\InvalidArgumentException;
use inhere\librarys\exceptions\InvalidConfigException;
use inhere\librarys\helpers\ArrHelper;
use Slim;
use slimExt\database\AbstractDriver;
use slimExt\DataConst;
use slimExt\helpers\ModelHelper;
use Windwalker\Query\Query;

/**
 * Class RecordModel
 * @package slimExt\base
 */
abstract class RecordModel extends Model
{
    /**
     * if true, will only save(insert/update) safe's data -- Through validation's data
     * @var bool
     */
    protected $onlySaveSafeData = true;

    /**
     * default only update the have been changed column.
     * @var bool
     */
    protected $onlyUpdateChanged = true;

    /**
     * @var array
     */
    private $_backup = [];

    const SCENE_DEFAULT = 'default';
    const SCENE_CREATE = 'create';
    const SCENE_UPDATE = 'update';
    const SCENE_DELETE = 'delete';
    const SCENE_SEARCH = 'search';

    /**
     * the table primary key name
     * @var string
     */
    protected static $priKey = 'id';

    /**
     * current table name alias
     * 'mt' -- main table
     * @var string
     */
    protected static $aliasName = 'mt';

    protected static $baseOptions = [
        /* data index column. */
        'indexKey' => null,
        /*
        data type, in :
            'model'      -- return object, instanceof `self`
            '\\stdClass' -- return object, instanceof `stdClass`
            'array'      -- return array, only  [ column's value ]
            'assoc'      -- return array, Contain  [ column's name => column's value]
        */
        'class'    => 'model',

        // 追加限制
        // 可用方法: limit($limit, $offset), group($columns), having($conditions, $glue = 'AND')
        // innerJoin($table, $condition = []), leftJoin($table, $condition = []), order($columns),
        // outerJoin($table, $condition = []), rightJoin($table, $condition = []), bind()
        // ... more {@see Query}
        //
        // e.g:
        //  'limit' => [10, 120],
        //  'order' => 'createTime ASC',
        //  'group' => 'id, type',
        'select'  => '*',

        // can be a closure
        // function ($query) { ... }
    ];

    public function __construct(array $items = [])
    {
        parent::__construct($items);

        if (!$this->columns()) {
            throw new InvalidConfigException('Must define method columns() and is can\'t empty.');
        }
    }

    /***********************************************************************************
     * some prepare work
     ***********************************************************************************/

    /**
     * define model field list
     * in sub class:
     * ```
     * public function columns()
     * {
     *    return [
     *          // column => type
     *          'id'          => 'int',
     *          'title'       => 'string',
     *          'createTime'  => 'int',
     *    ];
     * }
     * ```
     * @return array
     */
    abstract public function columns();

    /**
     * 定义保存数据时,当前场景允许写入的属性字段
     * @return array
     */
    public function sceneAttrs()
    {
        return [
            // 'create' => ['username', 'email', 'password','createTime'],
            // 'update' => ['username', 'email','createTime'],
        ];
    }

    /**
     * @return string
     */
    public static function tableName()
    {
        $className = lcfirst( basename( str_replace('\\', '/', get_called_class()) ) );

        // '@@' -- is table prefix placeholder
        // return '@@articles';
        // if no table prefix
        // return 'articles';

        return '@@' . $className;
    }

    /**
     * if {@see static::$aliasName} not empty, return `tableName AS aliasName`
     * @return string
     */
    public static function queryName()
    {
        return static::$aliasName ? static::tableName() . ' AS ' . static::$aliasName : static::tableName();
    }

    /**
     * @return AbstractDriver
     */
    public static function getDb()
    {
        return Slim::get('db');
    }

    /**
     * init query
     * @param mixed $where
     * @return Query
     */
    public static function query($where=null)
    {
        return ModelHelper::handleWhere($where, static::class)->from(static::queryName());
    }

    /***********************************************************************************
     * find operation
     ***********************************************************************************/

    /**
     * find record by primary key
     * @param mixed $priValue
     * @param string|array $options
     * @return static
     */
    public static function findByPk($priValue, $options= [])
    {
        if ( is_array($priValue) ) {// many
            $where = static::$priKey . ' IN (' . implode(',', $priValue) . ')';

        } else { // only one
            $where = [static::$priKey => $priValue];
        }

        return static::findOne($where, $options);
    }

    /**
     * find a record by where condition
     * @param mixed $where
     * @param string|array $options
     * @return static|array
     */
    public static function findOne($where, $options= [])
    {
        // as select
        if ( is_string($options) ) {
            $options = [
                'select' => $options
            ];
        }

        $options = array_merge(static::$baseOptions, $options);
        $class = $options['class'] === 'model' ? static::class : $options['class'];

        unset($options['indexKey'], $options['class']);
        $query = ModelHelper::applyAppendOptions($options, static::query($where));

        $model = static::setQuery($query)->loadOne($class);

        // use data model
        if ( $class === static::class ) {
            /** @var static $model */
            $model->setOldData($model->all());
        }

        return $model;
    }

    /**
     * @param mixed $where {@see ModelHelper::handleWhere() }
     * @param string|array $options
     * @return array
     */
    public static function findAll($where, $options = [])
    {
        // as select
        if ( is_string($options) ) {
            $options = [
                'select' => $options
            ];
        }

        $options = array_merge(static::$baseOptions, $options);
        $indexKey = ArrHelper::remove('indexKey',$options, null);
        $class = $options['class'] === 'model' ? static::class : $options['class'];

        unset($options['indexKey'], $options['class']);

        $query = ModelHelper::applyAppendOptions($options, static::query($where));

        return static::setQuery($query)->loadAll($indexKey, $class);
    }

    /**
     * simple count
     * @param $where
     * @return int
     */
    public static function counts($where)
    {
        $query = static::query($where);

        return static::setQuery($query)->count();
    }

    /**
     * @param $where
     * @return int
     */
    public static function exists($where)
    {
        $query = static::query($where);

        return static::setQuery($query)->exists();
    }

    /***********************************************************************************
     * create operation
     ***********************************************************************************/

    /**
     * @param array $updateColumns
     * @param bool|false $updateNulls
     * @return bool
     */
    public function save($updateColumns = [], $updateNulls = false)
    {
        $this->beforeSave();
        $result = $this->isNew() ? $this->insert() : $this->update($updateColumns, $updateNulls);

        if ($result) {
            $this->afterSave();
        }

        return $result ? true : false;
    }

    /**
     * @return static
     */
    public function insert()
    {
        $this->beforeInsert();
        $this->beforeSave();

        if ( $this->enableValidate && $this->validate()->fail() ) {
            return false;
        }

        $data = $this->getColumnsData();
        $priValue = static::getDb()->insert(static::tableName(), $data);

        // when insert successful.
        if ($priValue) {
            $this->set(static::$priKey, $priValue);

            $this->afterInsert();
            $this->afterSave();
        }

        return $this;
    }

    /**
     * more @see AbstractDriver::insertBatch()
     * @param array $columns
     * @param array $values
     * @return bool|int
     */
    public static function insertBatch(array $columns, array $values)
    {
        if ( static::getDb()->supportBatchSave() ) {
            return static::getDb()->insertBatch( static::tableName(), $columns, $values);
        }

        throw new \RuntimeException('The driver ['.static::getDb()->getDriver().'] don\'t support one-time insert multi records.');
    }

    /**
     * insert multiple
     * @param array $dataSet
     * @return array
     */
    public static function insertMulti(array $dataSet)
    {
        $pris = [];

        foreach ($dataSet as $k => $data) {
            $pris[$k] = static::load($data)->insert()->priValue();
        }

        return $pris;
    }

    /***********************************************************************************
     * update operation
     ***********************************************************************************/

    /**
     * update by primary key
     * @param array $updateColumns only update some columns
     * @param bool|false $updateNulls
     * @return bool
     */
    public function update($updateColumns = [], $updateNulls = false)
    {
        $priKey = static::$priKey;
        $this->beforeUpdate();
        $this->beforeSave();

        // the primary column is must be exists.
        if ($updateColumns && !in_array($priKey, $updateColumns)) {
            $updateColumns[] = $priKey;
        }

        // validate data
        if ($this->enableValidate && $this->validate($updateColumns)->fail() ) {
            return false;
        }

        // collect there are data will update.
        $data = $this->getColumnsData();

        if ( $this->onlyUpdateChanged ) {
            // Exclude the column if it value not change
            foreach ($data as $column => $value) {
                if (!$this->valueIsChanged($column) && $column !== $priKey) {
                    unset($data[$column]);
                }
            }
        }

        // check primary key
        if ( !isset($data[$priKey]) ) {
            throw new InvalidArgumentException('Must be require primary column of the method update()');
        }

        $result = static::getDb()->update(static::tableName(), $data, $priKey, $updateNulls);

        if ($result) {
            $this->afterUpdate();
            $this->afterSave();
        }

        return $result;
    }

    /**
     * @param $dataSet
     * @param array $updateColumns
     * @param bool|false $updateNulls
     * @return mixed
     */
    public static function updateMulti($dataSet, $updateColumns = [], $updateNulls = false)
    {
        $res = [];

        foreach ($dataSet as $k => $data) {
            $res[$k] = static::load($data)->update($updateColumns, $updateNulls);
        }

        return $res;
    }

    /**
     * @param $data
     * @param array $conditions
     * @return bool
     */
    public static function updateBatch($data, $conditions = [])
    {
        return static::getDb()->updateBatch( static::tableName(), $data, $conditions);
    }

    /***********************************************************************************
     * delete operation
     ***********************************************************************************/

    /**
     * delete by model
     * @return int
     */
    public function delete()
    {
        $this->beforeDelete();

        if ( !($priValue = $this->priValue()) ) {
            return 0;
        }

        $query = ModelHelper::handleWhere([ static::$priKey => $priValue ], static::class)
            ->delete(static::tableName());

        if ($affected = static::setQuery($query)->execute()->countAffected() ) {
            $this->afterDelete();
        }

        return $affected;
    }

    /**
     * @param int|array $priValue
     * @return int
     */
    public static function deleteByPk($priValue)
    {
        // only one
        $where = [static::$priKey => $priValue];

        // many
        if ( is_array($priValue) ) {
            $where = static::$priKey . ' in (' . implode(',', $priValue) . ')';
        }

        $query = ModelHelper::handleWhere($where, static::class)->delete(static::tableName());

        return static::setQuery($query)->execute()->countAffected();
    }

    /**
     * @param mixed $where
     * @return int
     */
    public static function deleteBy($where)
    {
        $query = ModelHelper::handleWhere($where, static::class)->delete(static::tableName());

        return static::setQuery($query)->execute()->countAffected();
    }

    /***********************************************************************************
     * transaction operation
     ***********************************************************************************/

    /**
     * @param bool $throwException throw a exception on failure.
     * @return bool
     */
    public static function beginTrans($throwException = true)
    {
        return static::getDb()->beginTrans($throwException);
    }

    /**
     * @param bool $throwException throw a exception on failure.
     * @return bool
     */
    public static function commit($throwException = true)
    {
        return static::getDb()->commit($throwException);
    }

    /**
     * @param bool $throwException throw a exception on failure.
     * @return bool
     */
    public static function rollBack($throwException = true)
    {
        return static::getDb()->rollBack($throwException);
    }

    /**
     * @return bool
     */
    public static function inTrans()
    {
        return static::getDb()->inTrans();
    }

    /***********************************************************************************
     * extra operation
     ***********************************************************************************/

    protected function beforeInsert()
    {
        return true;
    }
    protected function afterInsert(){}
    protected function beforeUpdate()
    {
        return true;
    }
    protected function afterUpdate(){}
    protected function beforeSave()
    {
        return true;
    }
    protected function afterSave(){}
    protected function beforeDelete()
    {
        return true;
    }
    protected function afterDelete(){}

    /**
     * @param $column
     * @param int $step
     * @return bool
     */
    public function increment($column, $step=1)
    {
        $priKey = static::$priKey;

        if ( !is_int($this->$column) ) {
            throw new \InvalidArgumentException('The method only can be used in the column of type integer');
        }

        $this->$column += (int)$step;

        $data = [
            $priKey => $this->get($priKey),
            $column => $this->$column,
        ];

        return static::getDb()->update(static::tableName(), $data, $priKey);
    }
    public function incre($column, $step=1)
    {
        return $this->increment($column, $step);
    }

    /**
     * @param $column
     * @param int $step
     * @return bool
     */
    public function decrement($column, $step=-1)
    {
        $priKey = static::$priKey;

        if ( !is_int($this->$column) ) {
            throw new \InvalidArgumentException('The method only can be used in the column of type integer');
        }

        $this->$column += (int)$step;

        $data = [
            $priKey => $this->get($priKey),
            $column => $this->$column,
        ];

        return static::getDb()->update(static::tableName(), $data, $priKey);
    }
    public function decre($column, $step=-1)
    {
        return $this->decrement($column, $step);
    }

    /***********************************************************************************
     * helper method
     ***********************************************************************************/

    /**
     * @param null|bool $value
     * @return bool
     */
    public function enableValidate($value=null)
    {
        if ( is_bool($value) ) {
            $this->enableValidate = $value;
        }

        return $this->enableValidate;
    }

    /**
     * @param bool $forceNew
     * @return Query
     */
    final public static function getQuery($forceNew=false)
    {
        return static::getDb()->newQuery($forceNew);
    }

    /**
     * @return bool
     */
    public function isNew()
    {
        return !($this->has(static::$priKey) && $this->get(static::$priKey, false));
    }

    /**
     * findXxx 无法满足需求时，自定义 $query
     *
     * ```
     * $query = XModel::getQuery();
     * ...
     * ...
     * self::setQuery($query)->loadAll(null, XModel::class);
     * ```
     * @param string|Query $query
     * @return AbstractDriver
     */
    final public static function setQuery($query)
    {
        return static::getDb()->setQuery($query);
    }

    /**
     * format column's data type
     * @param string $column
     * @param mixed $value
     * @return $this|void
     */
    public function set($column, $value)
    {
        // belong to the model.
        if ( isset($this->columns()[$column]) ) {
            $type = $this->columns()[$column];

            if ($type === DataConst::TYPE_INT ) {
                $value = (int)$value;
            }
        }

        return parent::set($column, $value);
    }

    /**
     * @return array
     */
    public function getColumnsData()
    {
        $source = $this->onlySaveSafeData ? $this->getSafeData() : $this;
        $data = [];

        foreach ($source as $col => $val) {
            if ( isset($this->columns()[$col]) ) {
                $data[$col] = $val;
            }
        }

        return $data;
    }

    /**
     * @return mixed
     */
    public function priValue()
    {
        return $this->get(static::$priKey);
    }

    /**
     * Check whether the column's value is changed, the update.
     * @param string $column
     * @return bool
     */
    protected function valueIsChanged($column)
    {
        return $this->isNew() || $this->get($column) !== $this->getOld($column);
    }

    /**
     * @return array
     */
    public function getOldData()
    {
        return $this->_backup;
    }

    /**
     * @param $data
     * @return array
     */
    public function setOldData($data)
    {
        $this->_backup = $data;
    }

    /**
     * @param $column
     * @return mixed
     */
    public function getOld($column)
    {
        return isset($this->_backup[$column]) ? $this->_backup[$column] : null;
    }

    /**
     * @return array
     */
//    public static function callableList()
//    {
//        return static::$callableList;
//    }

    /**
     * @param $method
     * @param array $args
     * @return mixed
     */
    // public function __call($method, array $args)
    // {
    //     $db = static::getDb();
    //     array_unshift($args, static::tableName());
    //     if ( in_array($method, static::$callableList) AND method_exists($db, $method) ) {
    //         return call_user_func_array( [$db, $method], $args);
    //     }
    //     throw new \RuntimeException("Called method [$method] don't exists!");
    // }

    /**
     * @param $method
     * @param array $args
     * @return mixed
     */
    // public static function __callStatic($method, array $args)
    // {
    //     $db = static::getDb();
    //     array_unshift($args, static::tableName());
    //     if ( in_array($method, static::$callableList) AND method_exists($db, $method) ) {
    //         return call_user_func_array( [$db, $method], $args);
    //     }
    //     throw new \RuntimeException("Called static method [$method] don't exists!");
    // }
}