<?php

/**
 * Created by PhpStorm.
 * User: Inhere
 * Date: 2016/2/19 0019
 * Time: 23:35
 */

namespace slimExt\base;

use Slim;
use inhere\validate\ValidationTrait;
use Windwalker\Query\Query;

/**
 * Class BaseModel
 * @package slimExt
 *
 */
abstract class Model extends Collection
{
    use ValidationTrait;

    /**
     * @var bool
     */
    protected $enableValidate = true;

    /**
     * if true, will only save(insert/update) safe's data -- Through validation's data
     * @var bool
     */
    protected $onlySaveSafeData = true;


    /**
     * Validation class name
     */
    //protected $validateHandler = '\inhere\validate\Validation';


    /**
     * @param $data
     * @return static
     */
    public static function load($data)
    {
        return new static($data);
    }


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
}
